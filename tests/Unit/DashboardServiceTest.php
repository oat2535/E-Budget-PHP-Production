<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\BudgetRoleService;
use App\Services\DashboardService;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    public function test_get_all_branches(): void
    {
        $all = DashboardService::getAllBranches();
        $this->assertCount(count(BudgetRoleService::GL_ACTUAL_TEMPLATE_BRANCHES), $all);
        $codes = array_column($all, 'code');
        $this->assertContains('HO', $codes);
        $this->assertContains('PM', $codes);
        $this->assertContains('R5', $codes);
        $this->assertContains('AP', $codes);
        $this->assertContains('BN', $codes);
    }

    public function test_get_tlph_branches(): void
    {
        $tlph = DashboardService::getTlphBranches();
        $this->assertNotEmpty($tlph);
        $codes = array_column($tlph, 'code');
        $this->assertContains('PM', $codes);
        $this->assertContains('R5', $codes);
    }

    public function test_get_dashboard_budget_years(): void
    {
        $years = DashboardService::getDashboardBudgetYears();
        $this->assertNotEmpty($years);
        $activeYear = (int) SystemSetting::current()->active_budget_year;
        $this->assertContains($activeYear, $years);
    }

    public function test_pnl_summary_constants(): void
    {
        $this->assertContains('FH101', DashboardService::PNL_SUMMARY_CODES);
        $this->assertContains('FH2', DashboardService::PNL_SUMMARY_CODES);
        $this->assertContains('FH3', DashboardService::PNL_SUMMARY_CODES);
        $this->assertContains('FH1-H3', DashboardService::PNL_SUMMARY_CODES);
        $this->assertContains('SP-B', DashboardService::PNL_SUMMARY_CODES);
        $this->assertContains('H6', DashboardService::PNL_SUMMARY_CODES);
        $this->assertContains('H8', DashboardService::PNL_SUMMARY_CODES);
        $this->assertContains('FH8', DashboardService::PNL_SUMMARY_CODES);
    }
}
