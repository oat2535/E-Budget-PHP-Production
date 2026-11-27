<?php

namespace App\Services;

use App\Models\BudgetPlanItem;
use App\Models\Car;
use App\Models\ComputerEquipment;
use App\Models\Furniture;
use App\Models\GeneralLedgerMaster;
use App\Models\GlActualTemplate;
use App\Models\GlActualTemplateRow;
use App\Models\GlEntry;
use App\Models\MedicalEquipment;
use App\Models\NonVetManpower;
use App\Models\PositionAdjustment;
use App\Models\Renovate;
use App\Models\Revenue;
use App\Models\Software;
use App\Models\SystemSetting;
use App\Models\ToolsEquipment;
use App\Models\VetManpower;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public const MONTH_KEYS = [
        'january', 'february', 'march', 'april', 'may', 'june',
        'july', 'august', 'september', 'october', 'november', 'december',
    ];

    public const PCT_KEYS = [
        'pct_jan', 'pct_feb', 'pct_mar', 'pct_apr', 'pct_may', 'pct_jun',
        'pct_jul', 'pct_aug', 'pct_sep', 'pct_oct', 'pct_nov', 'pct_dec',
    ];

    public const ACTUAL_FIELD_KEYS = [
        'actual_value', 'per_month_value', 'annualized_value', 'sales_ratio', 'growth_rate',
    ];

    public const SECTION_LEAF_BLOCKS = [
        ['FH101', 3, 12],
        ['FH201', 16, 18],
        ['FH202', 21, 32],
        ['FH203', 35, 35],
        ['FH301', 40, 57],
        ['FH302', 60, 62],
        ['FH303', 65, 68],
        ['FH304', 71, 73],
        ['FH305', 76, 78],
        ['FH306', 81, 83],
        ['FH307', 86, 92],
        ['FH308', 95, 105],
        ['FH309', 108, 110],
        ['FH310', 113, 115],
        ['FH311', 118, 126],
        ['FH312', 129, 135],
        ['FH313', 138, 140],
        ['FH410', 145, 158],
        ['FH401', 161, 162],
        ['FH5', 166, 167],
        ['FH7', 172, 177],
    ];

    public const STANDALONE_GL_CODES = ['42000005', '61700001'];
    public const PNL_SUMMARY_CODES = ['FH101', 'FH2', 'FH3', 'FH1-H3', 'SP-B', 'H6', 'H8', 'FH8'];

    public const FIX_ASSET_SOURCES = [
        ['MED', MedicalEquipment::class, 'med_equip_id'],
        ['COMP', ComputerEquipment::class, 'comp_equip_id'],
        ['FURN', Furniture::class, 'furniture_equip_id'],
        ['TOOL', ToolsEquipment::class, 'tools_equip_id'],
        ['CAR', Car::class, 'car_equip_id'],
        ['RENO', Renovate::class, 'renovate_equip_id'],
        ['SOFT', Software::class, 'software_equip_id'],
    ];

    public static function round2($val): float
    {
        if ($val === null || $val === '') {
            return 0.0;
        }
        return round((float) $val, 2);
    }

    public static function getAllBranches(): array
    {
        $branches = [];
        foreach (BudgetRoleService::GL_ACTUAL_TEMPLATE_BRANCHES as [$code, $name]) {
            $branches[] = ['code' => $code, 'name' => $name];
        }
        return $branches;
    }

    public static function canViewAllBranchesDashboard(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        $info = $request->session()->get('branch_info') ?? BudgetRoleService::getBranchInfoFromImedx($user->username);
        return BudgetRoleService::isPrivilegedUserAccount($user, $request)
            || (! empty($info['is_executive_view_only']));
    }

    public static function getTlphBranches(): array
    {
        $branches = [];
        try {
            $rows = DB::connection('pgsql')->select("
                SELECT sub_branch_id, sub_branch_name
                FROM base_sub_branch
                WHERE branch_id_id = 'TLPH'
                ORDER BY sub_branch_id
            ");
            if (! empty($rows)) {
                foreach ($rows as $r) {
                    $code = trim((string) ($r->sub_branch_id ?? ''));
                    $name = trim((string) ($r->sub_branch_name ?? ''));
                    if ($code !== '' && $name !== '') {
                        $branches[] = ['code' => $code, 'name' => $name];
                    }
                }
                return $branches;
            }
        } catch (\Throwable $e) {
            // fallback
        }

        foreach (BudgetRoleService::GL_ACTUAL_TEMPLATE_BRANCHES as [$code, $name]) {
            if (! str_starts_with($code, 'MY-')) {
                $branches[] = ['code' => $code, 'name' => $name];
            }
        }
        return $branches;
    }

    public static function getAllowedBranchesForDashboard(Request $request): array
    {
        if (! BudgetRoleService::canAccessDashboard($request)) {
            return [];
        }

        $user = $request->user();
        $info = $request->session()->get('branch_info') ?? BudgetRoleService::getBranchInfoFromImedx($user->username);

        $tlphBranches = self::getTlphBranches();
        $tlphMap = [];
        $tlphOrder = [];
        foreach ($tlphBranches as $idx => $b) {
            $tlphMap[$b['code']] = $b;
            $tlphOrder[$b['code']] = $idx;
        }

        $canViewAll = BudgetRoleService::isPrivilegedUserAccount($user, $request)
            || (! empty($info['is_executive_view_only']));

        if ($canViewAll) {
            return array_merge([
                ['code' => 'ALL', 'name' => 'ทุกสาขา (TLPH ทั้งหมด)', 'is_all' => true],
            ], $tlphBranches);
        }

        $rawCodes = $info['branches'] ?? [];
        $seen = [];
        $cleanCodes = [];
        foreach ($rawCodes as $c) {
            $code = strtoupper(trim((string) $c));
            if ($code !== '' && isset($tlphMap[$code]) && ! isset($seen[$code])) {
                $seen[$code] = true;
                $cleanCodes[] = $code;
            }
        }

        if (empty($cleanCodes)) {
            return [];
        }

        $result = [];
        foreach ($cleanCodes as $code) {
            $result[] = [
                'code' => $code,
                'name' => $tlphMap[$code]['name'] ?? $code,
            ];
        }

        usort($result, function ($a, $b) use ($tlphOrder) {
            $ordA = $tlphOrder[$a['code']] ?? 999;
            $ordB = $tlphOrder[$b['code']] ?? 999;
            return $ordA <=> $ordB;
        });

        return $result;
    }

    public static function validateDashboardBranch(Request $request, ?string $rawBranch): array
    {
        $allowed = self::getAllowedBranchesForDashboard($request);
        if (empty($allowed)) {
            return [null, 'บัญชีของท่านไม่มีสิทธิ์เข้าใช้งาน Dashboard หรือยังไม่ได้ตั้งค่าสาขาในกลุ่ม TLPH', 403];
        }

        $user = $request->user();
        $canViewAll = BudgetRoleService::isPrivilegedUserAccount($user, $request)
            || (! empty($request->session()->get('branch_info')['is_executive_view_only']));

        $branchCode = strtoupper(trim((string) $rawBranch));
        if ($branchCode === '') {
            $msg = $canViewAll ? 'กรุณาเลือกสาขาที่ต้องการดูข้อมูล' : 'กรุณาเลือกสาขาที่ต้องการดูข้อมูล (ดูได้ครั้งละ 1 สาขาเท่านั้น)';
            return [null, $msg, 400];
        }

        if (str_contains($branchCode, ',') || str_contains($branchCode, ';') || str_contains($branchCode, ' ')) {
            return [null, 'กรุณาเลือกดูรายสาขา หรือเลือก "ทุกสาขา" จากตัวเลือกที่กำหนดเท่านั้น', 400];
        }

        $allowedMap = [];
        foreach ($allowed as $b) {
            $allowedMap[$b['code']] = $b;
        }

        if ($branchCode === 'ALL') {
            if (! $canViewAll || ! isset($allowedMap['ALL'])) {
                return [null, 'สิทธิ์ของท่านอนุญาตให้เลือกดูได้ทีละ 1 สาขาเท่านั้น (ไม่รองรับการดูทุกสาขารวม)', 400];
            }
            $tlphCodes = [];
            foreach ($allowed as $b) {
                if ($b['code'] !== 'ALL') {
                    $tlphCodes[] = $b['code'];
                }
            }
            if (empty($tlphCodes)) {
                return [null, 'ไม่พบรายชื่อสาขาในกลุ่ม TLPH สำหรับแสดงผลรวม', 400];
            }
            $resolved = $allowedMap['ALL'];
            $resolved['branch_codes'] = $tlphCodes;
            return [$resolved, null, 200];
        }

        if (! isset($allowedMap[$branchCode])) {
            return [null, "ท่านไม่มีสิทธิ์เข้าดูข้อมูล Dashboard ของสาขา {$branchCode}", 403];
        }

        $resolved = $allowedMap[$branchCode];
        $resolved['is_all'] = false;
        $resolved['branch_codes'] = [$branchCode];
        return [$resolved, null, 200];
    }

    public static function getDashboardBudgetYears(): array
    {
        $settings = SystemSetting::loadSetting();
        $years = [(int) $settings->active_budget_year];

        $tables = [
            'ebudget_vet_manpower', 'ebudget_non_vet_manpower', 'ebudget_position_adjustment',
            'ebudget_medical_equipment', 'ebudget_computer_equipment', 'ebudget_furniture',
            'ebudget_tools_equipment', 'ebudget_car', 'ebudget_renovate', 'ebudget_software',
            'ebudget_gl_entry', 'ebudget_revenue', 'ebudget_budget_plan_item', 'ebudget_gl_actual_template',
        ];

        $unionSql = implode(' UNION ', array_map(fn($t) => "SELECT DISTINCT budget_year FROM {$t} WHERE budget_year IS NOT NULL", $tables));

        try {
            $rows = DB::connection('pgsql')->select($unionSql);
            foreach ($rows as $r) {
                if ($r->budget_year !== null) {
                    $years[] = (int) $r->budget_year;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $years = array_unique($years);
        rsort($years);
        return $years;
    }

    public static function buildMonthlyBudgetSqlAndParams(array $branchCodes, int $budgetYear, ?string $costCenter = null): array
    {
        $codes = array_values(array_unique(array_filter(array_map('trim', $branchCodes))));
        $inCodes = implode("','", array_map(fn($c) => strtoupper($c), $codes));
        $branchClause = "UPPER(TRIM(v.base_branch_id)) IN ('{$inCodes}')";

        $ccFilterClause = '';
        if ($costCenter !== null && $costCenter !== '' && strtoupper(trim($costCenter)) !== 'ALL') {
            $rawCc = trim($costCenter);
            $canonicalCc = BudgetService::resolveCanonicalCostCenterName($rawCc);
            $shortPrefix = str_contains($rawCc, '(') ? trim(explode('(', $rawCc)[0]) : null;
            $filterItems = array_unique(array_filter([$rawCc, $canonicalCc, $shortPrefix]));
            $safeItems = implode("','", array_map('addslashes', $filterItems));
            $ccFilterClause = "AND v.cost_center_name IN ('{$safeItems}')";
        }

        $subqueries = [];
        $params = [];

        $addLeg = function ($glExpr, $amtExpr, $fromClause, $glNotNullExpr) use (&$subqueries, &$params, $branchClause, $budgetYear, $ccFilterClause) {
            $subqueries[] = "
                SELECT TRIM({$glExpr}) AS gl_code,
                       md.month AS month_num,
                       {$amtExpr} AS amt
                {$fromClause}
                WHERE {$branchClause}
                  AND v.budget_year = ?
                  AND COALESCE(v.status, '') <> 'CANCELLED'
                  {$ccFilterClause}
                  AND {$glNotNullExpr} IS NOT NULL
                  AND TRIM({$glNotNullExpr}) <> ''
            ";
            $params[] = $budgetYear;
        };

        // 1. Vet Manpower
        $addLeg('v.general_ledger_code', 'COALESCE(md.cost, 0)', 'FROM ebudget_vet_manpower v INNER JOIN ebudget_monthly_detail md ON md.vet_budget_id = v.id', 'v.general_ledger_code');

        // 2a/2b. Non-Vet Manpower
        $addLeg('v.salary_general_ledger_code', 'COALESCE(md.salary, 0)', 'FROM ebudget_non_vet_manpower v INNER JOIN ebudget_monthly_detail md ON md.non_vet_budget_id = v.id', 'v.salary_general_ledger_code');
        $addLeg('v.position_allowance_general_ledger_code', 'COALESCE(md.position_allowance, 0)', 'FROM ebudget_non_vet_manpower v INNER JOIN ebudget_monthly_detail md ON md.non_vet_budget_id = v.id', 'v.position_allowance_general_ledger_code');

        // 3a/3b. Position Adjustment
        $addLeg(
            'v.salary_general_ledger_code',
            'CASE WHEN (COALESCE(v.new_salary, 0) - COALESCE(v.old_salary, 0)) + (COALESCE(v.new_allowance, 0) - COALESCE(v.old_allowance, 0)) <> 0 THEN COALESCE(md.cost, 0) * (COALESCE(v.new_salary, 0) - COALESCE(v.old_salary, 0)) / ((COALESCE(v.new_salary, 0) - COALESCE(v.old_salary, 0)) + (COALESCE(v.new_allowance, 0) - COALESCE(v.old_allowance, 0))) ELSE 0 END',
            'FROM ebudget_position_adjustment v INNER JOIN ebudget_monthly_detail md ON md.position_adj_id = v.id',
            'v.salary_general_ledger_code'
        );
        $addLeg(
            'v.allowance_general_ledger_code',
            'CASE WHEN (COALESCE(v.new_salary, 0) - COALESCE(v.old_salary, 0)) + (COALESCE(v.new_allowance, 0) - COALESCE(v.old_allowance, 0)) <> 0 THEN COALESCE(md.cost, 0) * (COALESCE(v.new_allowance, 0) - COALESCE(v.old_allowance, 0)) / ((COALESCE(v.new_salary, 0) - COALESCE(v.old_salary, 0)) + (COALESCE(v.new_allowance, 0) - COALESCE(v.old_allowance, 0))) ELSE 0 END',
            'FROM ebudget_position_adjustment v INNER JOIN ebudget_monthly_detail md ON md.position_adj_id = v.id',
            'v.allowance_general_ledger_code'
        );

        // 4-10. Seven Fix Asset tables
        foreach (self::FIX_ASSET_SOURCES as [$tag, $modelClass, $fkCol]) {
            $tbl = (new $modelClass)->getTable();
            $addLeg(
                'v.general_ledger_code',
                'CASE WHEN COALESCE(gl.depreciation, 0) > 0 THEN COALESCE(md.cumulative_depreciation, 0) ELSE COALESCE(md.cost, 0) END',
                "FROM {$tbl} v INNER JOIN ebudget_monthly_detail md ON md.{$fkCol} = v.id LEFT JOIN master_data_ebudget_general_ledger_master gl ON gl.gl_code = v.general_ledger_code",
                'v.general_ledger_code'
            );
        }

        // 11. GL Entry
        $addLeg('v.general_ledger_code', 'CASE WHEN COALESCE(v.depreciation, 0) > 0 THEN COALESCE(md.cumulative_amount, 0) ELSE COALESCE(md.amount, 0) END', 'FROM ebudget_gl_entry v INNER JOIN ebudget_gl_entry_monthly_detail md ON md.gl_entry_id = v.id', 'v.general_ledger_code');

        // 12. Revenue
        $addLeg('v.general_ledger_code', 'CASE WHEN COALESCE(v.depreciation, 0) > 0 THEN COALESCE(md.cumulative_amount, 0) ELSE COALESCE(md.amount, 0) END', 'FROM ebudget_revenue v INNER JOIN ebudget_revenue_monthly_detail md ON md.revenue_entry_id = v.id', 'v.general_ledger_code');

        // 13. Budget Plan Item
        $addLeg('v.general_ledger_code', 'COALESCE(md.amount, 0)', 'FROM ebudget_budget_plan_item v INNER JOIN ebudget_budget_plan_monthly_detail md ON md.plan_item_id = v.id', 'v.general_ledger_code');

        $unionBody = implode("\nUNION ALL\n", $subqueries);
        $monthPivots = [];
        for ($m = 1; $m <= 12; $m++) {
            $col = self::MONTH_KEYS[$m - 1];
            $monthPivots[] = "ROUND(SUM(CASE WHEN u.month_num = {$m} THEN u.amt ELSE 0 END), 2) AS {$col}";
        }
        $monthPivotsStr = implode(",\n", $monthPivots);

        $sql = "
            SELECT u.gl_code,
                   {$monthPivotsStr}
            FROM ({$unionBody}) u
            GROUP BY u.gl_code
        ";

        return [$sql, $params];
    }

    public static function fetchBranchBudgetByGl(array $branchCodes, int $budgetYear, ?string $costCenter = null): array
    {
        [$sql, $params] = self::buildMonthlyBudgetSqlAndParams($branchCodes, $budgetYear, $costCenter);
        $rows = DB::connection('pgsql')->select($sql, $params);

        $byGl = [];
        foreach ($rows as $r) {
            $gl = trim((string) ($r->gl_code ?? ''));
            if ($gl === '') {
                continue;
            }
            $months = [];
            foreach (self::MONTH_KEYS as $k) {
                $months[] = (float) ($r->$k ?? 0);
            }
            $byGl[$gl] = $months;
        }

        return $byGl;
    }

    public static function fetchGlActualTemplateForBranch(array $branchCodes, int $budgetYear): array
    {
        $codes = array_values(array_unique(array_filter(array_map('trim', $branchCodes))));
        if (empty($codes)) {
            return ['document' => null, 'by_code' => [], 'by_row_order' => []];
        }

        $inCodes = implode("','", array_map(fn($c) => strtoupper($c), $codes));

        $headers = DB::connection('pgsql')->select("
            SELECT DISTINCT ON (base_branch_id)
                   base_branch_id, document_no, status, actual_month_count,
                   growth_pct_1, growth_pct_2, growth_pct_3
            FROM ebudget_gl_actual_template
            WHERE UPPER(TRIM(base_branch_id)) IN ('{$inCodes}')
              AND budget_year = ?
              AND COALESCE(status, '') <> 'CANCELLED'
            ORDER BY base_branch_id ASC,
                     CASE WHEN status = 'APPROVED' THEN 0 ELSE 1 END,
                     modify_date DESC NULLS LAST,
                     create_date DESC NULLS LAST,
                     id DESC
        ", [$budgetYear]);

        if (empty($headers)) {
            return ['document' => null, 'by_code' => [], 'by_row_order' => []];
        }

        $docPairs = [];
        foreach ($headers as $h) {
            $docPairs[] = "('" . addslashes($h->base_branch_id) . "', '" . addslashes($h->document_no) . "')";
        }
        $pairsStr = implode(',', $docPairs);

        $rows = DB::connection('pgsql')->select("
            SELECT base_branch_id, row_order, code, row_type,
                   COALESCE(actual_value, 0) AS actual_value,
                   COALESCE(per_month_value, 0) AS per_month_value,
                   COALESCE(annualized_value, 0) AS annualized_value,
                   COALESCE(sales_ratio, 0) AS sales_ratio,
                   COALESCE(growth_rate, 0) AS growth_rate
            FROM ebudget_gl_actual_template
            WHERE budget_year = ?
              AND COALESCE(status, '') <> 'CANCELLED'
              AND (base_branch_id, document_no) IN ({$pairsStr})
            ORDER BY row_order ASC, base_branch_id ASC
        ", [$budgetYear]);

        if (count($headers) === 1) {
            $h = $headers[0];
            $byCode = [];
            $byRowOrder = [];
            foreach ($rows as $r) {
                $entry = [
                    'actual_value' => self::round2($r->actual_value),
                    'per_month_value' => self::round2($r->per_month_value),
                    'annualized_value' => self::round2($r->annualized_value),
                    'sales_ratio' => self::round2($r->sales_ratio),
                    'growth_rate' => self::round2($r->growth_rate),
                ];
                if ($r->code) {
                    $byCode[trim((string) $r->code)] = $entry;
                }
                if ($r->row_order !== null) {
                    $byRowOrder[(int) $r->row_order] = $entry;
                }
            }
            $docMeta = [
                'document_no' => $h->document_no,
                'status' => $h->status,
                'actual_month_count' => (int) ($h->actual_month_count ?? 0),
                'growth_pct_1' => self::round2($h->growth_pct_1),
                'growth_pct_2' => self::round2($h->growth_pct_2),
                'growth_pct_3' => self::round2($h->growth_pct_3),
            ];
            return ['document' => $docMeta, 'by_code' => $byCode, 'by_row_order' => $byRowOrder];
        }

        // Consolidated multi-branch
        $aggByOrd = [];
        $branchFh101 = [];
        foreach ($rows as $r) {
            $ordNum = (int) $r->row_order;
            $codeClean = trim((string) ($r->code ?? ''));
            $act = self::round2($r->actual_value);
            $perM = self::round2($r->per_month_value);
            $ann = self::round2($r->annualized_value);
            $gr = self::round2($r->growth_rate);

            if ($codeClean === 'FH101') {
                $branchFh101[$r->base_branch_id] = abs($act);
            }

            if (! isset($aggByOrd[$ordNum])) {
                $aggByOrd[$ordNum] = [
                    'code' => $codeClean,
                    'row_type' => $r->row_type,
                    'actual_value' => 0.0,
                    'per_month_value' => 0.0,
                    'annualized_value' => 0.0,
                    'gr_weighted_num' => 0.0,
                    'gr_weight_den' => 0.0,
                    'gr_vals' => [],
                ];
            }

            $aggByOrd[$ordNum]['actual_value'] += $act;
            $aggByOrd[$ordNum]['per_month_value'] += $perM;
            $aggByOrd[$ordNum]['annualized_value'] += $ann;

            $w = abs($act);
            if ($w > 0.0) {
                $aggByOrd[$ordNum]['gr_weighted_num'] += $gr * $w;
                $aggByOrd[$ordNum]['gr_weight_den'] += $w;
            }
            if ($gr != 0.0) {
                $aggByOrd[$ordNum]['gr_vals'][] = $gr;
            }
        }

        $fh101Total = 0.0;
        foreach ($aggByOrd as $slot) {
            if ($slot['code'] === 'FH101') {
                $fh101Total = self::round2($slot['actual_value']);
                break;
            }
        }

        $byCode = [];
        $byRowOrder = [];
        foreach ($aggByOrd as $ordNum => $slot) {
            $actSum = self::round2($slot['actual_value']);
            $perMSum = self::round2($slot['per_month_value']);
            $annSum = self::round2($slot['annualized_value']);

            $isCalcHeader = in_array($slot['code'], ['H6', 'H7', 'H8'], true);
            if ($fh101Total != 0.0 && ($slot['row_type'] !== 'header' || $isCalcHeader)) {
                $sr = self::round2(($actSum * 100.0) / $fh101Total);
            } else {
                $sr = 0.0;
            }

            if ($slot['gr_weight_den'] > 0.0) {
                $gr = self::round2($slot['gr_weighted_num'] / $slot['gr_weight_den']);
            } elseif (! empty($slot['gr_vals'])) {
                $gr = self::round2(array_sum($slot['gr_vals']) / count($slot['gr_vals']));
            } else {
                $gr = 0.0;
            }

            $entry = [
                'actual_value' => $actSum,
                'per_month_value' => $perMSum,
                'annualized_value' => $annSum,
                'sales_ratio' => $sr,
                'growth_rate' => $gr,
            ];
            if ($slot['code']) {
                $byCode[$slot['code']] = $entry;
            }
            $byRowOrder[$ordNum] = $entry;
        }

        $docMeta = [
            'document_no' => 'รวม ' . count($headers) . ' สาขา TLPH',
            'status' => 'CONSOLIDATED',
            'actual_month_count' => max(array_map(fn($h) => (int) ($h->actual_month_count ?? 0), $headers)),
            'growth_pct_1' => self::round2(array_sum(array_map(fn($h) => (float) $h->growth_pct_1, $headers)) / count($headers)),
            'growth_pct_2' => self::round2(array_sum(array_map(fn($h) => (float) $h->growth_pct_2, $headers)) / count($headers)),
            'growth_pct_3' => self::round2(array_sum(array_map(fn($h) => (float) $h->growth_pct_3, $headers)) / count($headers)),
        ];

        return ['document' => $docMeta, 'by_code' => $byCode, 'by_row_order' => $byRowOrder];
    }

    public static function buildDashboardData(array $branchInfo, int $budgetYear, ?string $costCenter = null): array
    {
        $targetCodes = $branchInfo['branch_codes'] ?? [$branchInfo['code']];
        $outline = GlActualTemplateRow::orderBy('row_order')->get()->toArray();
        $actualBundle = self::fetchGlActualTemplateForBranch($targetCodes, $budgetYear);
        $budgetByGl = self::fetchBranchBudgetByGl($targetCodes, $budgetYear, $costCenter);

        $footerOrderByCode = [];
        foreach ($outline as $r) {
            if ($r['code'] && $r['row_type'] === 'subtotal') {
                $footerOrderByCode[$r['code']] = (int) $r['row_order'];
            }
        }

        $leafToFooter = [];
        foreach (self::SECTION_LEAF_BLOCKS as [$footerCode, $startOrd, $endOrd]) {
            $footerOrd = $footerOrderByCode[$footerCode] ?? null;
            if ($footerOrd === null) {
                continue;
            }
            for ($ordNum = $startOrd; $ordNum <= $endOrd; $ordNum++) {
                $leafToFooter[$ordNum] = $footerOrd;
            }
        }

        $rowMonths = [];
        $rowActuals = [];
        $byCodeMeta = [];

        foreach ($outline as $item) {
            $ordNum = (int) $item['row_order'];
            $code = trim((string) ($item['code'] ?? ''));
            $rowType = $item['row_type'];
            if ($code !== '') {
                $byCodeMeta[$code] = $ordNum;
            }

            $actSrc = $actualBundle['by_code'][$code] ?? ($actualBundle['by_row_order'][$ordNum] ?? null);
            if ($actSrc) {
                $rowActuals[$ordNum] = $actSrc;
            } else {
                $rowActuals[$ordNum] = array_fill_keys(self::ACTUAL_FIELD_KEYS, 0.0);
            }

            if ($rowType === 'gl' && $code !== '') {
                $rowMonths[$ordNum] = $budgetByGl[$code] ?? array_fill(0, 12, 0.0);
            } else {
                $rowMonths[$ordNum] = array_fill(0, 12, 0.0);
            }
        }

        // Section leaf aggregation
        $sectionSums = [];
        foreach ($outline as $item) {
            $ordNum = (int) $item['row_order'];
            if ($item['row_type'] !== 'gl') {
                continue;
            }
            $footerOrd = $leafToFooter[$ordNum] ?? null;
            if ($footerOrd !== null) {
                if (! isset($sectionSums[$footerOrd])) {
                    $sectionSums[$footerOrd] = array_fill(0, 12, 0.0);
                }
                for ($m = 0; $m < 12; $m++) {
                    $sectionSums[$footerOrd][$m] += $rowMonths[$ordNum][$m];
                }
            }
        }

        foreach ($sectionSums as $footerOrd => $monthsList) {
            $rowMonths[$footerOrd] = array_map([self::class, 'round2'], $monthsList);
        }

        $monthsOf = function ($codeKey) use (&$byCodeMeta, &$rowMonths) {
            $ord = $byCodeMeta[$codeKey] ?? null;
            return $ord !== null ? $rowMonths[$ord] : array_fill(0, 12, 0.0);
        };

        $setMonths = function ($codeKey, $values) use (&$byCodeMeta, &$rowMonths) {
            $ord = $byCodeMeta[$codeKey] ?? null;
            if ($ord !== null) {
                $rowMonths[$ord] = array_map([self::class, 'round2'], $values);
            }
        };

        $addM = fn($a, $b) => array_map(fn($x, $y) => self::round2($x + $y), $a, $b);
        $subM = fn($a, $b) => array_map(fn($x, $y) => self::round2($x - $y), $a, $b);

        // FH2 = FH201 + FH202 + FH203
        $fh2 = $addM($addM($monthsOf('FH201'), $monthsOf('FH202')), $monthsOf('FH203'));
        $setMonths('FH2', $fh2);

        // FH3 = SUM(FH301..FH313)
        $fh3 = array_fill(0, 12, 0.0);
        for ($i = 301; $i <= 313; $i++) {
            $fh3 = $addM($fh3, $monthsOf("FH{$i}"));
        }
        $setMonths('FH3', $fh3);

        // FH1-H3 (EBITDA) = FH101 - FH2 - FH3
        $fh101 = $monthsOf('FH101');
        $ebitda = $subM($subM($fh101, $fh2), $fh3);
        $setMonths('FH1-H3', $ebitda);

        // SP-B = FH1-H3 - FH410 - FH401
        $spB = $subM($subM($ebitda, $monthsOf('FH410')), $monthsOf('FH401'));
        $setMonths('SP-B', $spB);

        // H6 = SP-B - FH5
        $h6 = $subM($spB, $monthsOf('FH5'));
        $setMonths('H6', $h6);

        // H8 = H6 + 42000005 - FH7
        $h8 = $subM($addM($h6, $monthsOf('42000005')), $monthsOf('FH7'));
        $setMonths('H8', $h8);

        // FH8 = H8 - 61700001
        $fh8 = $subM($h8, $monthsOf('61700001'));
        $setMonths('FH8', $fh8);

        $outputRows = [];
        foreach ($outline as $item) {
            $ordNum = (int) $item['row_order'];
            $code = trim((string) ($item['code'] ?? ''));
            $rowType = $item['row_type'];
            $months = $rowMonths[$ordNum];
            $budgetTotal = self::round2(array_sum($months));
            $actuals = $rowActuals[$ordNum];

            $pcts = [];
            if ($rowType === 'subtotal') {
                foreach ($months as $mVal) {
                    $pcts[] = $mVal != 0.0 ? 100.0 : 0.0;
                }
            } elseif ($rowType === 'gl') {
                $footerOrd = $leafToFooter[$ordNum] ?? null;
                if ($footerOrd !== null) {
                    $denomMonths = $rowMonths[$footerOrd];
                    for ($m = 0; $m < 12; $m++) {
                        $denom = $denomMonths[$m];
                        $pcts[] = $denom != 0.0 ? self::round2(($months[$m] * 100.0) / $denom) : 0.0;
                    }
                } elseif (in_array($code, self::STANDALONE_GL_CODES, true)) {
                    foreach ($months as $mVal) {
                        $pcts[] = $mVal != 0.0 ? 100.0 : 0.0;
                    }
                } else {
                    $pcts = array_fill(0, 12, 0.0);
                }
            } else {
                $pcts = array_fill(0, 12, 0.0);
            }

            $annVal = $actuals['annualized_value'];
            $diffVsActual = self::round2($budgetTotal - $annVal);

            $hasNonzero = ($actuals['actual_value'] != 0.0)
                || ($actuals['per_month_value'] != 0.0)
                || ($actuals['annualized_value'] != 0.0)
                || ($budgetTotal != 0.0);
            if (! $hasNonzero) {
                foreach ($months as $mVal) {
                    if ($mVal != 0.0) {
                        $hasNonzero = true;
                        break;
                    }
                }
            }

            $rowDict = [
                'row_order' => $ordNum,
                'general_ledger_code' => $code,
                'gl_name' => $item['label'] ?? '',
                'row_type' => $rowType,
                'is_pnl_summary' => in_array($code, self::PNL_SUMMARY_CODES, true),
                'has_nonzero' => $hasNonzero,
                'actual_value' => $actuals['actual_value'],
                'per_month_value' => $actuals['per_month_value'],
                'annualized_value' => $actuals['annualized_value'],
                'sales_ratio' => $actuals['sales_ratio'],
                'growth_rate' => $actuals['growth_rate'],
                'total' => $budgetTotal,
                'diff_vs_actual' => $diffVsActual,
            ];

            for ($m = 0; $m < 12; $m++) {
                $rowDict[self::MONTH_KEYS[$m]] = $months[$m];
                $rowDict[self::PCT_KEYS[$m]] = $pcts[$m];
            }

            $outputRows[] = $rowDict;
        }

        return [
            'branch' => $branchInfo,
            'budget_year' => $budgetYear,
            'cost_center' => $costCenter,
            'gl_actual_document' => $actualBundle['document'],
            'rows' => $outputRows,
        ];
    }
}
