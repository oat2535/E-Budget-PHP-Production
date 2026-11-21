<?php

namespace Tests\Unit;

use App\Services\BudgetService;
use PHPUnit\Framework\TestCase;

class ManpowerValidationTest extends TestCase
{
    public function test_validate_manpower_headcount_integers_valid(): void
    {
        $validData = [
            [
                'position_name' => 'Veterinarian',
                'monthly_data' => [
                    'jan' => ['headcount' => 1, 'cost' => 30000],
                    'feb' => ['headcount' => 2.0, 'cost' => 60000],
                    'mar' => ['headcount' => '3', 'cost' => 90000],
                    'apr' => ['headcount' => 0, 'cost' => 0],
                ],
            ],
        ];
        $err = BudgetService::validateManpowerHeadcountIntegers($validData, 'position_name');
        $this->assertNull($err);
    }

    public function test_validate_manpower_headcount_integers_invalid_decimal(): void
    {
        $invalidData = [
            [
                'position_name' => 'Veterinarian A',
                'monthly_data' => [
                    'jan' => ['headcount' => 1.5, 'cost' => 45000],
                ],
            ],
        ];
        $err = BudgetService::validateManpowerHeadcountIntegers($invalidData, 'position_name');
        $this->assertNotNull($err);
        $this->assertStringContainsString('มกราคม (Jan)', $err);
        $this->assertStringContainsString('จำนวนเต็มเท่านั้น', $err);
        $this->assertStringContainsString('Veterinarian A', $err);
    }

    public function test_validate_manpower_headcount_integers_negative(): void
    {
        $negData = [
            [
                'position_name' => 'Vet Tech',
                'monthly_data' => [
                    'feb' => ['headcount' => -1, 'cost' => 0],
                ],
            ],
        ];
        $err = BudgetService::validateManpowerHeadcountIntegers($negData, 'position_name');
        $this->assertNotNull($err);
        $this->assertStringContainsString('กุมภาพันธ์ (Feb)', $err);
        $this->assertStringContainsString('จำนวนเต็มเท่านั้น', $err);
    }

    public function test_validate_manpower_headcount_integers_invalid_leading_zeros(): void
    {
        foreach (['03', '04', '05', '007'] as $badVal) {
            $badData = [
                [
                    'position_name' => 'Vet Tech',
                    'monthly_data' => [
                        'mar' => ['headcount' => $badVal, 'cost' => 15000],
                    ],
                ],
            ];
            $err = BudgetService::validateManpowerHeadcountIntegers($badData, 'position_name');
            $this->assertNotNull($err);
            $this->assertStringContainsString('มีนาคม (Mar)', $err);
            $this->assertStringContainsString('ไม่อนุญาตให้ระบุรูปแบบที่มีเลข 0 นำหน้า', $err);
        }

        // Standalone '0' or 0 must still be completely valid
        $zeroData = [
            [
                'position_name' => 'Vet Tech',
                'monthly_data' => [
                    'mar' => ['headcount' => '0', 'cost' => 0],
                    'apr' => ['headcount' => 0, 'cost' => 0],
                ],
            ],
        ];
        $errZero = BudgetService::validateManpowerHeadcountIntegers($zeroData, 'position_name');
        $this->assertNull($errZero);
    }

    public function test_validate_nonzero_monthly_rows(): void
    {
        // Invalid data: all months are 0
        $allZeroData = [
            [
                'position_name' => 'Position 1',
                'monthly_data' => [
                    'jan' => ['headcount' => 0, 'cost' => 0],
                    'feb' => ['headcount' => 0, 'cost' => 0],
                ],
            ],
        ];
        $err = BudgetService::validateNonzeroMonthlyRows($allZeroData, 'position_name');
        $this->assertNotNull($err);
        $this->assertStringContainsString('Position 1', $err);

        // Valid data: at least one month > 0
        $validData = [
            [
                'position_name' => 'Position 1',
                'monthly_data' => [
                    'jan' => ['headcount' => 0, 'cost' => 0],
                    'feb' => ['headcount' => 1, 'cost' => 10000],
                ],
            ],
        ];
        $err = BudgetService::validateNonzeroMonthlyRows($validData, 'position_name');
        $this->assertNull($err);
    }

    public function test_validate_fix_asset_unique_rows(): void
    {
        // Duplicate: same item_name and same description
        $dupData = [
            ['item_name' => 'X-Ray Machine', 'description' => 'Room 1'],
            ['item_name' => 'Ultrasound', 'description' => 'Room 2'],
            ['item_name' => 'X-Ray Machine', 'description' => 'Room 1'],
        ];
        $err = BudgetService::validateFixAssetUniqueRows($dupData, 'เครื่องมือแพทย์');
        $this->assertNotNull($err);
        $this->assertStringContainsString('X-Ray Machine', $err);
        $this->assertStringContainsString('แถวที่ 1', $err);
        $this->assertStringContainsString('แถวที่ 3', $err);

        // Not duplicate: same item_name but different descriptions
        $distinctData = [
            ['item_name' => 'X-Ray Machine', 'description' => 'Room 1'],
            ['item_name' => 'X-Ray Machine', 'description' => 'Room 2'],
        ];
        $err = BudgetService::validateFixAssetUniqueRows($distinctData, 'เครื่องมือแพทย์');
        $this->assertNull($err);
    }
}
