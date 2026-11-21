<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function getAdminUser(): User
    {
        $user = new User(['username' => 'admin']);
        $user->is_superuser = true;
        return $user;
    }

    protected function getTestBranchCode(): string
    {
        $branches = \App\Services\DashboardService::getTlphBranches();
        return ! empty($branches) ? $branches[0]['code'] : 'HO';
    }

    public function test_dashboard_unauthenticated_redirects_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_dashboard_view_renders_for_admin(): void
    {
        $response = $this->actingAs($this->getAdminUser())->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('E-Budget Branch Dashboard');
    }

    public function test_dashboard_data_api_missing_branch_returns_400(): void
    {
        $response = $this->actingAs($this->getAdminUser())->getJson('/api/dashboard/data');
        $response->assertStatus(400);
        $response->assertJson(['status' => 'error']);
    }

    public function test_dashboard_data_api_invalid_year_returns_400(): void
    {
        $branch = $this->getTestBranchCode();
        $response = $this->actingAs($this->getAdminUser())->getJson("/api/dashboard/data?branch={$branch}&year=invalid");
        $response->assertStatus(400);
        $response->assertJson(['status' => 'error', 'message' => 'ปีงบประมาณไม่ถูกต้อง']);
    }

    public function test_dashboard_data_api_valid_single_branch(): void
    {
        $branch = $this->getTestBranchCode();
        $response = $this->actingAs($this->getAdminUser())->getJson("/api/dashboard/data?branch={$branch}&year=2026");
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'branch' => [
                'code' => $branch,
            ],
            'budget_year' => 2026,
        ]);
        $this->assertIsArray($response->json('rows'));
    }

    public function test_dashboard_data_api_all_tlph(): void
    {
        $response = $this->actingAs($this->getAdminUser())->getJson('/api/dashboard/data?branch=ALL&year=2026');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'branch' => [
                'code' => 'ALL',
            ],
        ]);
        $this->assertIsArray($response->json('rows'));
    }

    public function test_dashboard_export_api_returns_xlsx(): void
    {
        $branch = $this->getTestBranchCode();
        $response = $this->actingAs($this->getAdminUser())->get("/api/dashboard/export?branch={$branch}&year=2026");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('Content-Disposition');
        // Excel files generated are zip archives starting with 'PK\x03\x04'
        $content = $response->getContent();
        $this->assertStringStartsWith("PK\x03\x04", $content);
    }
}
