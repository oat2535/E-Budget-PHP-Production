<?php

namespace Tests\Unit;

use App\Services\BudgetService;
use PHPUnit\Framework\TestCase;

class EquipmentGlCodeResolutionTest extends TestCase
{
    public function test_resolve_equipment_gl_code_returns_correct_constants(): void
    {
        $this->assertEquals('61300013', BudgetService::CAR_GL_CODE);
        $this->assertEquals('61300008', BudgetService::RENOVATE_GL_CODE);
        $this->assertEquals('61300005', BudgetService::SOFTWARE_GL_CODE);

        $this->assertEquals('61300013', BudgetService::resolveEquipmentGlCode('CAR', 500000));
        $this->assertEquals('61300008', BudgetService::resolveEquipmentGlCode('Renovate', 150000));
        $this->assertEquals('61300005', BudgetService::resolveEquipmentGlCode('Software', 25000));
    }

    public function test_resolve_price_threshold_gl_code_allows_5000_as_fix_asset(): void
    {
        // 5,000 THB exactly (Fix Asset capitalization threshold)
        $this->assertEquals(BudgetService::MEDICAL_EQUIPMENT_GL_CODE_HIGH, BudgetService::resolveEquipmentGlCode('Medical Equipment', 5000));
        $this->assertEquals(BudgetService::COMPUTER_EQUIPMENT_GL_CODE_HIGH, BudgetService::resolveEquipmentGlCode('Computer Equipment', 5000));
        $this->assertEquals(BudgetService::FURNITURE_GL_CODE_HIGH, BudgetService::resolveEquipmentGlCode('Furniture', 5000));
        $this->assertEquals(BudgetService::TOOLS_EQUIPMENT_GL_CODE_HIGH, BudgetService::resolveEquipmentGlCode('Tools & Equipment', 5000));

        // Greater than 5,000 THB
        $this->assertEquals(BudgetService::MEDICAL_EQUIPMENT_GL_CODE_HIGH, BudgetService::resolveEquipmentGlCode('Medical Equipment', 5001));
        $this->assertEquals(BudgetService::MEDICAL_EQUIPMENT_GL_CODE_HIGH, BudgetService::resolveEquipmentGlCode('Medical Equipment', 25000));

        // Less than 5,000 THB
        $this->assertEquals(BudgetService::MEDICAL_EQUIPMENT_GL_CODE_LOW, BudgetService::resolveEquipmentGlCode('Medical Equipment', 4999));
        $this->assertEquals(BudgetService::COMPUTER_EQUIPMENT_GL_CODE_LOW, BudgetService::resolveEquipmentGlCode('Computer Equipment', 4999));
        $this->assertEquals(BudgetService::FURNITURE_GL_CODE_LOW, BudgetService::resolveEquipmentGlCode('Furniture', 4999));
        $this->assertEquals(BudgetService::TOOLS_EQUIPMENT_GL_CODE_LOW, BudgetService::resolveEquipmentGlCode('Tools & Equipment', 4999));
    }

    public function test_adjustment_gl_codes_resolution(): void
    {
        [$salaryGl, $allowanceGl] = BudgetService::resolveAdjustmentGlCodes('VET');
        $this->assertEquals('52000008', $salaryGl);
        $this->assertNull($allowanceGl);

        [$salaryGl, $allowanceGl] = BudgetService::resolveAdjustmentGlCodes('NON VET');
        $this->assertEquals('60100001', $salaryGl);
        $this->assertEquals('60100002', $allowanceGl);
    }
}
