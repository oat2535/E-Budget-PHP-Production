<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FreezeAndSystemTest extends TestCase
{
    protected function getAdminUser(): User
    {
        $user = new User(['username' => 'admin']);
        $user->is_superuser = true;
        return $user;
    }

    protected function getRegularUser(): User
    {
        $user = new User(['username' => 'test_officer']);
        $user->is_superuser = false;
        return $user;
    }

    public function test_toggle_freeze_by_regular_user_returns_403(): void
    {
        $response = $this->actingAs($this->getRegularUser())->postJson('/system/toggle_freeze', []);
        $response->assertStatus(403);
    }

    public function test_set_active_year_by_regular_user_returns_403(): void
    {
        $response = $this->actingAs($this->getRegularUser())->postJson('/system/set_active_year', ['year' => 2026]);
        $response->assertStatus(403);
    }

    public function test_toggle_freeze_by_admin_succeeds(): void
    {
        Cache::forget('ebudget_system_settings');
        $initialFrozen = (int) SystemSetting::current(true)->is_frozen;

        $response = $this->actingAs($this->getAdminUser())->postJson('/system/toggle_freeze', [
            'message' => 'ปิดงบประมาณเพื่อทดสอบ',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $newFrozen = (int) SystemSetting::current(true)->is_frozen;
        $this->assertNotEquals($initialFrozen, $newFrozen);

        // Toggle back to restore initial state
        $restoreResponse = $this->actingAs($this->getAdminUser())->postJson('/system/toggle_freeze', []);
        $restoreResponse->assertStatus(200);
        $restored = (int) SystemSetting::current(true)->is_frozen;
        $this->assertEquals($initialFrozen, $restored);
    }

    public function test_set_active_year_by_admin_succeeds(): void
    {
        $initialYear = (int) SystemSetting::current(true)->active_budget_year;

        $response = $this->actingAs($this->getAdminUser())->postJson('/system/set_active_year', [
            'year' => 2027,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'active_budget_year' => 2027]);

        // Restore initial year
        $this->actingAs($this->getAdminUser())->postJson('/system/set_active_year', [
            'year' => $initialYear,
        ]);
        $this->assertEquals($initialYear, (int) SystemSetting::current(true)->active_budget_year);
    }

    public function test_ensure_budget_not_frozen_blocks_regular_user_when_frozen(): void
    {
        // Ensure system is frozen
        $settings = SystemSetting::current(true);
        $originalFrozen = $settings->is_frozen;
        $settings->is_frozen = 1;
        $settings->save();
        Cache::forget('ebudget_system_settings');

        try {
            $payload = [
                [
                    'cost_center_name' => 'CC1',
                    'position_name' => 'Veterinarian',
                    'salary' => 30000,
                    'monthly_data' => [],
                ],
            ];

            $response = $this->actingAs($this->getRegularUser())->postJson('/add', $payload);
            $response->assertStatus(403);
            $response->assertJson(['status' => 'error']);
        } finally {
            // Restore frozen state
            $settings->is_frozen = $originalFrozen;
            $settings->save();
            Cache::forget('ebudget_system_settings');
        }
    }

    public function test_ensure_budget_not_frozen_allows_privileged_user_when_frozen(): void
    {
        $settings = SystemSetting::current(true);
        $originalFrozen = $settings->is_frozen;
        $settings->is_frozen = 1;
        $settings->save();
        Cache::forget('ebudget_system_settings');

        try {
            // Privileged user hits /add with invalid payload -> passes middleware and reaches controller validation (returns 200 with error 'กรุณาเลือก Cost Center')
            $response = $this->actingAs($this->getAdminUser())->postJson('/add', []);
            $response->assertStatus(200);
            $response->assertJson(['status' => 'error', 'message' => 'กรุณาเลือก Cost Center']);
        } finally {
            $settings->is_frozen = $originalFrozen;
            $settings->save();
            Cache::forget('ebudget_system_settings');
        }
    }
}
