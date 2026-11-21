<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
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

    public function test_dashboard_data_api_with_cost_center(): void
    {
        $branch = $this->getTestBranchCode();
        $response = $this->actingAs($this->getAdminUser())->getJson("/api/dashboard/data?branch={$branch}&year=2026&cost_center=Center");
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'cost_center' => 'Center',
        ]);
    }

    public function test_dashboard_cost_center_resolution_rules(): void
    {
        $adminUser = $this->getAdminUser();
        $adminCcs = \App\Services\BudgetRoleService::resolveAllowedCostCenters($adminUser, 'ADMIN', 'BACK-IT');
        $this->assertCount(27, $adminCcs);

        $execCcs = \App\Services\BudgetRoleService::resolveAllowedCostCenters(new User(['username' => 'exec']), 'COO', 'BACK-ALL');
        $this->assertCount(27, $execCcs);

        $branchCcs = \App\Services\BudgetRoleService::resolveAllowedCostCenters(new User(['username' => 'branch_user']), 'MANAGER', 'R8,R5');
        $this->assertCount(3, $branchCcs);
        $branchNames = array_column($branchCcs, 'name');
        $this->assertContains('Center', $branchNames);
        $this->assertContains('PH (Pet Hospital+Pet Taxi)', $branchNames);
        $this->assertContains('PC (Pet Wellness+Pet Shop+GM+Pet Pool)', $branchNames);

        $deptCcs = \App\Services\BudgetRoleService::resolveAllowedCostCenters(new User(['username' => 'it_user']), 'MANAGER', 'BACK-IT');
        $this->assertCount(1, $deptCcs);
        $this->assertEquals('แผนก IT', $deptCcs[0]['name']);

        $multiCcs = \App\Services\BudgetRoleService::resolveAllowedCostCenters(new User(['username' => 'bu_user']), 'MANAGER', 'BACK-PC,R9,R2,R8');
        $multiNames = array_column($multiCcs, 'name');
        $this->assertContains('Center', $multiNames);
        $this->assertContains('PH (Pet Hospital+Pet Taxi)', $multiNames);
        $this->assertContains('PC (Pet Wellness+Pet Shop+GM+Pet Pool)', $multiNames);
    }

    public function test_dashboard_unauthorized_cost_center_returns_403(): void
    {
        $user = new User(['username' => 'dept_user']);
        $user->is_superuser = false;
        $branch = $this->getTestBranchCode();

        // Simulate session branch_info for a BACK-IT user
        $response = $this->actingAs($user)
            ->withSession([
                'branch_info' => [
                    'branches' => [$branch],
                    'bank_account_note' => 'MANAGER',
                    'bank_sub_name' => 'BACK-IT',
                    'is_manager' => true,
                    'is_admin' => false,
                ],
            ])
            ->getJson("/api/dashboard/data?branch={$branch}&year=2026&cost_center=" . urlencode('แผนกบัญชี'));

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'message' => 'ท่านไม่มีสิทธิ์เข้าดูข้อมูล Cost Center นี้',
        ]);
    }
}
