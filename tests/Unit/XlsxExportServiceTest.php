<?php

namespace Tests\Unit;

use App\Services\XlsxExportService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class XlsxExportServiceTest extends TestCase
{
    public function test_build_dashboard_xlsx_generates_valid_zip(): void
    {
        $payload = [
            'budget_year' => 2026,
            'branch' => [
                'code' => 'PM',
                'name' => 'สาขาประดิษฐ์มนูธรรม',
            ],
            'gl_actual_document' => [
                'actual_year' => 2025,
                'actual_month_count' => 9,
                'growth_pct_1' => 10,
                'growth_pct_2' => 5,
                'growth_pct_3' => 2,
            ],
            'rows' => [
                [
                    'row_order' => 1,
                    'general_ledger_code' => 'H1',
                    'gl_name' => 'รายได้',
                    'row_type' => 'header',
                    'is_header' => true,
                    'is_subtotal' => false,
                    'actual_value' => 0, 'per_month_value' => 0, 'annualized_value' => 0,
                    'sales_ratio' => 0, 'growth_rate' => 0,
                    'january' => 0, 'february' => 0, 'march' => 0, 'april' => 0, 'may' => 0, 'june' => 0,
                    'july' => 0, 'august' => 0, 'september' => 0, 'october' => 0, 'november' => 0, 'december' => 0,
                    'total' => 0,
                    'pct_jan' => 0, 'pct_feb' => 0, 'pct_mar' => 0, 'pct_apr' => 0, 'pct_may' => 0, 'pct_jun' => 0,
                    'pct_jul' => 0, 'pct_aug' => 0, 'pct_sep' => 0, 'pct_oct' => 0, 'pct_nov' => 0, 'pct_dec' => 0,
                ],
                [
                    'row_order' => 2,
                    'general_ledger_code' => '41100001',
                    'gl_name' => 'รายได้ค่ารักษา OPD/IPD',
                    'row_type' => 'gl',
                    'is_header' => false,
                    'is_subtotal' => false,
                    'actual_value' => 250000.00, 'per_month_value' => 27777.78, 'annualized_value' => 333333.36,
                    'sales_ratio' => 100.00, 'growth_rate' => -1.00,
                    'january' => 100000.50, 'february' => 120000.00, 'march' => 110000.00, 'april' => 0, 'may' => 0, 'june' => 0,
                    'july' => 0, 'august' => 0, 'september' => 0, 'october' => 0, 'november' => 0, 'december' => 0,
                    'total' => 330000.50,
                    'pct_jan' => 0, 'pct_feb' => 0, 'pct_mar' => 0, 'pct_apr' => 0, 'pct_may' => 0, 'pct_jun' => 0,
                    'pct_jul' => 0, 'pct_aug' => 0, 'pct_sep' => 0, 'pct_oct' => 0, 'pct_nov' => 0, 'pct_dec' => 0,
                ],
            ],
        ];

        $xlsxBytes = XlsxExportService::buildDashboardXlsx($payload);
        $this->assertNotEmpty($xlsxBytes);

        // Standard ZIP header: PK\x03\x04
        $this->assertStringStartsWith("PK\x03\x04", $xlsxBytes);

        // Open in ZipArchive and verify OOXML parts
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        file_put_contents($tmpFile, $xlsxBytes);

        $zip = new ZipArchive();
        $res = $zip->open($tmpFile);
        $this->assertTrue($res === true);

        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $this->assertNotFalse($zip->locateName('xl/styles.xml'));

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('41100001', $sheetXml);
        $this->assertStringContainsString('330000.50', $sheetXml);

        $zip->close();
        @unlink($tmpFile);
    }
}
