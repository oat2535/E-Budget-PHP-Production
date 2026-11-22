<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DiagnosisTest extends TestCase
{
    public function test_unauthenticated_dianosis_redirects_to_login_with_formal_popup(): void
    {
        $response = $this->get('/dianosis');

        $response->assertRedirect('/login?next=%2Fdianosis');
        $response->assertSessionHas('warning_title', 'แจ้งเตือนความปลอดภัย');
        $response->assertSessionHas('warning', 'หน้านี้จำกัดสิทธิ์เฉพาะผู้ใช้งานที่ผ่านการเข้าสู่ระบบแล้วเท่านั้น กรุณาลงชื่อเข้าใช้งานก่อนเข้าถึงหน้าตรวจสอบและวินิจฉัยระบบ (System Diagnostics)');

        $followResponse = $this->followRedirects($response);
        $followResponse->assertStatus(200);
        $followResponse->assertSee('แจ้งเตือนความปลอดภัย');
        $followResponse->assertSee('หน้านี้จำกัดสิทธิ์เฉพาะผู้ใช้งานที่ผ่านการเข้าสู่ระบบแล้วเท่านั้น');
        $followResponse->assertSee('value="/dianosis"', false);
    }

    public function test_unauthenticated_diagnosis_redirects_to_login_with_formal_popup(): void
    {
        $response = $this->get('/diagnosis');

        $response->assertRedirect('/login?next=%2Fdiagnosis');
        $response->assertSessionHas('warning_title', 'แจ้งเตือนความปลอดภัย');
    }

    public function test_unauthenticated_dianosis_json_returns_401(): void
    {
        $response = $this->getJson('/dianosis?format=json');

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 'unauthenticated',
        ]);
        $response->assertSee('Authentication Required');
    }

    public function test_authenticated_user_can_access_dianosis(): void
    {
        $user = new User(['username' => 'test_officer']);
        $user->is_superuser = false;

        $response = $this->actingAs($user)->get('/dianosis');

        $response->assertStatus(200);
        $response->assertSee('PHP Version');
        $response->assertSee('Laravel Framework');
        $response->assertSee(PHP_VERSION);
        $response->assertSee(app()->version());
    }

    public function test_authenticated_user_can_access_diagnosis(): void
    {
        $user = new User(['username' => 'test_officer']);
        $user->is_superuser = false;

        $response = $this->actingAs($user)->get('/diagnosis');

        $response->assertStatus(200);
        $response->assertSee('System Diagnostics');
    }

    public function test_authenticated_user_can_get_diagnosis_json(): void
    {
        $user = new User(['username' => 'test_officer']);
        $user->is_superuser = false;

        $response = $this->actingAs($user)->getJson('/dianosis?format=json');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('databases', $data);
        $this->assertArrayHasKey('extensions', $data);
        $this->assertArrayHasKey('performance', $data);

        // Ensure no sensitive passwords leaked
        $jsonString = json_encode($data);
        $this->assertStringNotContainsString('Tlp@2023', $jsonString);
        $this->assertStringNotContainsString('Tlph@2026', $jsonString);
    }
}
