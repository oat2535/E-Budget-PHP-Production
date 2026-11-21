<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\BudgetRoleService;
use PHPUnit\Framework\TestCase;

class BudgetRoleServiceTest extends TestCase
{
    public function test_is_admin_role(): void
    {
        $this->assertTrue(BudgetRoleService::isAdminRole('ADMIN'));
        $this->assertTrue(BudgetRoleService::isAdminRole('IT-ADMIN'));
        $this->assertTrue(BudgetRoleService::isAdminRole('ACC-ADMIN'));
        $this->assertTrue(BudgetRoleService::isAdminRole('admin'));
        $this->assertTrue(BudgetRoleService::isAdminRole('SUPER_ADMIN'));

        $this->assertFalse(BudgetRoleService::isAdminRole('MANAGER'));
        $this->assertFalse(BudgetRoleService::isAdminRole('FRONT'));
        $this->assertFalse(BudgetRoleService::isAdminRole('IT'));
        $this->assertFalse(BudgetRoleService::isAdminRole(null));
        $this->assertFalse(BudgetRoleService::isAdminRole(''));
    }

    public function test_is_executive_role(): void
    {
        $this->assertTrue(BudgetRoleService::isExecutiveRole('CHRO'));
        $this->assertTrue(BudgetRoleService::isExecutiveRole('CFO'));
        $this->assertTrue(BudgetRoleService::isExecutiveRole('COO'));
        $this->assertTrue(BudgetRoleService::isExecutiveRole('chro'));
        $this->assertTrue(BudgetRoleService::isExecutiveRole(' cfo '));

        $this->assertFalse(BudgetRoleService::isExecutiveRole('MANAGER'));
        $this->assertFalse(BudgetRoleService::isExecutiveRole('ADMIN'));
        $this->assertFalse(BudgetRoleService::isExecutiveRole('executive, cfo'));
        $this->assertFalse(BudgetRoleService::isExecutiveRole(null));
    }

    public function test_is_district_role(): void
    {
        $this->assertTrue(BudgetRoleService::isDistrictRole('DISTRICT MANAGER'));
        $this->assertTrue(BudgetRoleService::isDistrictRole('DIRECTOR'));
        $this->assertTrue(BudgetRoleService::isDistrictRole('district manager north'));

        $this->assertFalse(BudgetRoleService::isDistrictRole('BRANCH MANAGER'));
        $this->assertFalse(BudgetRoleService::isDistrictRole('CHRO'));
        $this->assertFalse(BudgetRoleService::isDistrictRole(null));
    }

    public function test_is_view_only_role(): void
    {
        // Executive or District without ADMIN is view-only
        $this->assertTrue(BudgetRoleService::isViewOnlyRole('CHRO'));
        $this->assertTrue(BudgetRoleService::isViewOnlyRole('DISTRICT MANAGER'));

        // If also ADMIN, not view-only
        $this->assertFalse(BudgetRoleService::isViewOnlyRole('CHRO, ADMIN'));
        $this->assertFalse(BudgetRoleService::isViewOnlyRole('MANAGER'));
        $this->assertFalse(BudgetRoleService::isViewOnlyRole(null));
    }

    public function test_is_manager_role(): void
    {
        $this->assertTrue(BudgetRoleService::isManagerRole('BRANCH MANAGER'));
        $this->assertTrue(BudgetRoleService::isManagerRole('ASSISTANT MANAGER'));
        $this->assertTrue(BudgetRoleService::isManagerRole('manager'));

        $this->assertFalse(BudgetRoleService::isManagerRole('OFFICER'));
        $this->assertFalse(BudgetRoleService::isManagerRole(null));
    }

    public function test_is_privileged_user_account(): void
    {
        $adminUser = new User();
        $adminUser->username = 'admin';
        $adminUser->is_superuser = true;
        $this->assertTrue(BudgetRoleService::isPrivilegedUserAccount($adminUser));

        $regularUser = new User();
        $regularUser->username = 'regular_user';
        $regularUser->is_superuser = false;
        // In unit test without DB connection, defaults to non-privileged unless mocked
    }
}
