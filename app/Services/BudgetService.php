<?php

namespace App\Services;

use App\Models\BudgetItemMaster;
use App\Models\BudgetMonthlyDetail;
use App\Models\BudgetPlanItem;
use App\Models\BudgetPlanMonthlyDetail;
use App\Models\Car;
use App\Models\ComputerEquipment;
use App\Models\CostCenterMaster;
use App\Models\Furniture;
use App\Models\GeneralLedgerMaster;
use App\Models\GlActualTemplate;
use App\Models\GlEntry;
use App\Models\GlEntryMonthlyDetail;
use App\Models\MedicalEquipment;
use App\Models\NonVetManpower;
use App\Models\PositionAdjustment;
use App\Models\Renovate;
use App\Models\Revenue;
use App\Models\RevenueMonthlyDetail;
use App\Models\Software;
use App\Models\ToolsEquipment;
use App\Models\VetManpower;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BudgetService
{
    public const VET_CUSTOM_POSITION_GL_CODE = '52000008';
    public const NON_VET_SALARY_GL_CODE = '60100001';
    public const NON_VET_ALLOWANCE_GL_CODE = '60100002';

    public const MEDICAL_EQUIPMENT_GL_CODE_HIGH = '61300010';
    public const MEDICAL_EQUIPMENT_GL_CODE_LOW = '60300002';
    public const MEDICAL_EQUIPMENT_GL_PRICE_THRESHOLD = 5000;

    public const COMPUTER_EQUIPMENT_GL_CODE_HIGH = '61300004';
    public const COMPUTER_EQUIPMENT_GL_CODE_LOW = '60300002';
    public const COMPUTER_EQUIPMENT_GL_PRICE_THRESHOLD = 5000;

    public const FURNITURE_GL_CODE_HIGH = '61300012';
    public const FURNITURE_GL_CODE_LOW = '60600003';
    public const FURNITURE_GL_PRICE_THRESHOLD = 5000;

    public const TOOLS_EQUIPMENT_GL_CODE_HIGH = '61300011';
    public const TOOLS_EQUIPMENT_GL_CODE_LOW = '60300002';
    public const TOOLS_EQUIPMENT_GL_PRICE_THRESHOLD = 5000;

    public const CAR_GL_CODE = '61300013';
    public const RENOVATE_GL_CODE = '61300008';
    public const SOFTWARE_GL_CODE = '61300005';
    public const FIX_ASSET_MIN_PURCHASE_PRICE = 5000;

    public const EXPENSE_PAGE_EXCLUDED_GL_CODES = [
        '61300006', '61300009', '61300002', '61300004', '61300005', '61300008',
        '61300011', '61300012', '61300013', '61300007', '61300010',
        '61100003', '61100007',
        '41100001', '41200001', '41100002', '41200004', '41200002', '41200003',
        '42000001', '42000019', '42000005',
        '61300014', '61300016', '61300015',
    ];

    public static array $prefixMap = [
        'VET' => 'VET',
        'NON VET' => 'NONVET',
        'Position Adjustment' => 'ADJ',
        'Medical Equipment' => 'MED',
        'Computer Equipment' => 'COM',
        'Furniture' => 'FUR',
        'Tools & Equipment' => 'TEQ',
        'Tools Equipment' => 'TEQ',
        'CAR' => 'CAR',
        'Car' => 'CAR',
        'Renovate' => 'REN',
        'Software' => 'SW',
        'GL Entry' => 'GLE',
        'Revenue' => 'REV',
        'Budget Plan' => 'PLAN',
        'GL Actual Template' => 'GLACT',
    ];

    public static array $modelMap = [
        'VET' => VetManpower::class,
        'NON VET' => NonVetManpower::class,
        'Position Adjustment' => PositionAdjustment::class,
        'Medical Equipment' => MedicalEquipment::class,
        'Computer Equipment' => ComputerEquipment::class,
        'Furniture' => Furniture::class,
        'Tools & Equipment' => ToolsEquipment::class,
        'Tools Equipment' => ToolsEquipment::class,
        'CAR' => Car::class,
        'Car' => Car::class,
        'Renovate' => Renovate::class,
        'Software' => Software::class,
        'GL Entry' => GlEntry::class,
        'Revenue' => Revenue::class,
        'Budget Plan' => BudgetPlanItem::class,
        'GL Actual Template' => GlActualTemplate::class,
    ];

    public static function resolveModelClass(string $docType): ?string
    {
        $normalized = strtoupper(trim($docType));
        foreach (self::$modelMap as $k => $cls) {
            if (strtoupper($k) === $normalized) {
                return $cls;
            }
        }
        return null;
    }

    public static function generateDocumentNo(string $docType): string
    {
        $prefixCode = self::$prefixMap[$docType] ?? 'DOC';
        $todayStr = date('Ym');
        $prefix = "{$prefixCode}-{$todayStr}-";

        $modelClass = self::resolveModelClass($docType);
        if (! $modelClass) {
            return "{$prefix}001";
        }

        $lastDoc = $modelClass::where('document_no', 'like', "{$prefix}%")
            ->orderByDesc('document_no')
            ->first();

        if ($lastDoc && $lastDoc->document_no) {
            $parts = explode('-', $lastDoc->document_no);
            $lastNumStr = end($parts);
            $newNumber = is_numeric($lastNumStr) ? ((int) $lastNumStr + 1) : 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s%03d', $prefix, $newNumber);
    }

    public static function resolveCanonicalCostCenterName(?string $rawName): ?string
    {
        if (! $rawName) {
            return null;
        }
        $val = trim($rawName);
        if ($val === '') {
            return null;
        }

        try {
            $cc = CostCenterMaster::whereRaw('LOWER(cost_center_name) = ?', [strtolower($val)])->first();
            if ($cc) {
                return $cc->cost_center_name;
            }

            $cc = CostCenterMaster::whereRaw('LOWER(cost_center_code) = ?', [strtolower($val)])->first();
            if ($cc) {
                return $cc->cost_center_name;
            }

            if (str_contains($val, ':')) {
                $parts = explode(':', $val, 2);
                $codePart = trim($parts[0]);
                $cc = CostCenterMaster::whereRaw('LOWER(cost_center_code) = ?', [strtolower($codePart)])->first();
                if ($cc) {
                    return $cc->cost_center_name;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Error resolving canonical cost center name: " . $e->getMessage());
        }

        return $val;
    }

    public static function findExistingBudgetDocument($modelClass, $createEid, $budgetYear, $baseBranchId, $costCenterName = null): ?array
    {
        if (! $modelClass || ! $createEid || ! $budgetYear || ! $baseBranchId) {
            return null;
        }

        $yearVal = (int) $budgetYear;
        $query = $modelClass::query()
            ->whereRaw('LOWER(TRIM(create_eid)) = ?', [strtolower(trim($createEid))])
            ->where('budget_year', $yearVal)
            ->whereRaw('LOWER(TRIM(base_branch_id)) = ?', [strtolower(trim($baseBranchId))])
            ->where('status', '!=', 'CANCELLED');

        if ($costCenterName && (new $modelClass)->getConnection()->getSchemaBuilder()->hasColumn((new $modelClass)->getTable(), 'cost_center_name')) {
            $rawCc = trim((string) $costCenterName);
            $canonicalCc = self::resolveCanonicalCostCenterName($rawCc);

            $query->where(function ($q) use ($rawCc, $canonicalCc) {
                $q->whereRaw('LOWER(TRIM(cost_center_name)) = ?', [strtolower($rawCc)]);
                if ($canonicalCc && $canonicalCc !== $rawCc) {
                    $q->orWhereRaw('LOWER(TRIM(cost_center_name)) = ?', [strtolower($canonicalCc)])
                      ->orWhereRaw('LOWER(cost_center_name) LIKE ?', [strtolower($rawCc) . '%']);
                    if (str_contains($canonicalCc, '(')) {
                        $shortPrefix = trim(explode('(', $canonicalCc)[0]);
                        if ($shortPrefix !== '') {
                            $q->orWhereRaw('LOWER(TRIM(cost_center_name)) = ?', [strtolower($shortPrefix)]);
                        }
                    }
                }
            });
        }

        $doc = $query->orderByDesc('create_date')->first();
        if ($doc) {
            return [
                'document_no' => $doc->document_no,
                'budget_year' => $doc->budget_year,
                'base_branch_id' => $doc->base_branch_id,
                'cost_center_name' => $doc->cost_center_name ?? $costCenterName,
                'status' => $doc->status,
            ];
        }

        return null;
    }

    public static function checkDuplicateBudgetDocument($modelClass, string $docType, string $createEid, $budgetYear, string $baseBranchId, ?string $costCenterName = null): ?JsonResponse
    {
        $canonicalCc = $costCenterName ? self::resolveCanonicalCostCenterName($costCenterName) : null;
        $existing = self::findExistingBudgetDocument(
            $modelClass,
            $createEid,
            $budgetYear,
            $baseBranchId,
            $canonicalCc ?: $costCenterName
        );

        if ($existing) {
            $docNo = $existing['document_no'] ?? '';
            $cc = $existing['cost_center_name'] ?? $canonicalCc ?? $costCenterName ?? '';
            $ccMsg = $cc ? " Cost Center: {$cc}" : '';

            return response()->json([
                'status' => 'duplicate',
                'doc_type' => $docType,
                'document_no' => $docNo,
                'budget_year' => $existing['budget_year'],
                'branch_id' => $existing['base_branch_id'],
                'cost_center_name' => $cc,
                'create_user' => $createEid,
                'message' => "พบเอกสาร {$docType} เลขที่ {$docNo} ปีงบประมาณ {$existing['budget_year']} สำหรับสาขา {$existing['base_branch_id']}{$ccMsg} ในระบบแล้ว ท่านสามารถเปิดดูรายละเอียดเอกสารดังกล่าวได้ทันที",
            ]);
        }

        return null;
    }

    public static function resolvePriceThresholdGlCode($purchasePrice, string $glCodeHigh, string $glCodeLow, $threshold): string
    {
        $price = (float) $purchasePrice;
        $thresh = (float) $threshold;
        return $price >= $thresh ? $glCodeHigh : $glCodeLow;
    }

    public static function resolveEquipmentGlCode(string $docType, $purchasePrice, ?string $fallbackGlCode = null): ?string
    {
        $dt = strtoupper(trim($docType));
        if ($dt === 'MEDICAL EQUIPMENT' || $dt === 'MED') {
            return self::resolvePriceThresholdGlCode($purchasePrice, self::MEDICAL_EQUIPMENT_GL_CODE_HIGH, self::MEDICAL_EQUIPMENT_GL_CODE_LOW, self::MEDICAL_EQUIPMENT_GL_PRICE_THRESHOLD);
        } elseif ($dt === 'COMPUTER EQUIPMENT' || $dt === 'COM') {
            return self::resolvePriceThresholdGlCode($purchasePrice, self::COMPUTER_EQUIPMENT_GL_CODE_HIGH, self::COMPUTER_EQUIPMENT_GL_CODE_LOW, self::COMPUTER_EQUIPMENT_GL_PRICE_THRESHOLD);
        } elseif ($dt === 'FURNITURE' || $dt === 'FUR') {
            return self::resolvePriceThresholdGlCode($purchasePrice, self::FURNITURE_GL_CODE_HIGH, self::FURNITURE_GL_CODE_LOW, self::FURNITURE_GL_PRICE_THRESHOLD);
        } elseif ($dt === 'TOOLS & EQUIPMENT' || $dt === 'TOOLS EQUIPMENT' || $dt === 'TEQ') {
            return self::resolvePriceThresholdGlCode($purchasePrice, self::TOOLS_EQUIPMENT_GL_CODE_HIGH, self::TOOLS_EQUIPMENT_GL_CODE_LOW, self::TOOLS_EQUIPMENT_GL_PRICE_THRESHOLD);
        } elseif ($dt === 'CAR') {
            return self::CAR_GL_CODE;
        } elseif ($dt === 'RENOVATE') {
            return self::RENOVATE_GL_CODE;
        } elseif ($dt === 'SOFTWARE') {
            return self::SOFTWARE_GL_CODE;
        }
        return $fallbackGlCode;
    }

    public static function resolveAdjustmentGlCodes(string $adjustmentType): array
    {
        $type = strtoupper(trim($adjustmentType));
        if ($type === 'VET') {
            return [self::VET_CUSTOM_POSITION_GL_CODE, null];
        }
        if ($type === 'NON VET') {
            return [self::NON_VET_SALARY_GL_CODE, self::NON_VET_ALLOWANCE_GL_CODE];
        }
        return [null, null];
    }

    public static function validateManpowerHeadcountIntegers(array $data, string $positionKey = 'position_name'): ?string
    {
        $monthNamesTh = [
            'jan' => 'มกราคม (Jan)', 'feb' => 'กุมภาพันธ์ (Feb)', 'mar' => 'มีนาคม (Mar)',
            'apr' => 'เมษายน (Apr)', 'may' => 'พฤษภาคม (May)', 'jun' => 'มิถุนายน (Jun)',
            'jul' => 'กรกฎาคม (Jul)', 'aug' => 'สิงหาคม (Aug)', 'sep' => 'กันยายน (Sep)',
            'oct' => 'ตุลาคม (Oct)', 'nov' => 'พฤศจิกายน (Nov)', 'dec' => 'ธันวาคม (Dec)',
        ];

        foreach ($data as $item) {
            $monthlyData = $item['monthly_data'] ?? [];
            $posName = $item[$positionKey] ?? $item['new_position_name'] ?? 'ไม่ระบุตำแหน่ง';

            foreach ($monthlyData as $mKey => $mVal) {
                $headcount = is_array($mVal) ? ($mVal['headcount'] ?? 0) : $mVal;

                if (is_string($headcount)) {
                    $hcStr = trim(str_replace(',', '', $headcount));
                    if (preg_match('/^0\d+/', $hcStr)) {
                        $mLabel = $monthNamesTh[strtolower($mKey)] ?? strtoupper($mKey);
                        return "จำนวนในเดือน {$mLabel} ไม่อนุญาตให้ระบุรูปแบบที่มีเลข 0 นำหน้า เช่น \"{$hcStr}\" (รายการ: {$posName})";
                    }
                }

                if (! is_numeric($headcount)) {
                    $mLabel = $monthNamesTh[strtolower($mKey)] ?? strtoupper($mKey);
                    return "จำนวนในเดือน {$mLabel} ไม่ถูกต้อง (รายการ: {$posName})";
                }

                $hcFloat = (float) $headcount;
                if (fmod($hcFloat, 1.0) !== 0.0 || $hcFloat < 0) {
                    $mLabel = $monthNamesTh[strtolower($mKey)] ?? strtoupper($mKey);
                    return "จำนวนในเดือน {$mLabel} ต้องเป็นตัวเลขจำนวนเต็มเท่านั้น ไม่อนุญาตให้ระบุทศนิยม (รายการ: {$posName})";
                }
            }
        }

        return null;
    }

    public static function validateNonzeroMonthlyRows(array $data, string $positionKey = 'position_name'): ?string
    {
        $emptyItems = [];
        $idx = 1;

        foreach ($data as $item) {
            $monthlyData = $item['monthly_data'] ?? [];
            $posName = $item[$positionKey] ?? $item['new_position_name'] ?? "แถวที่ {$idx}";
            $hasPositive = false;

            foreach ($monthlyData as $mKey => $mVal) {
                $headcount = is_array($mVal) ? ($mVal['headcount'] ?? 0) : $mVal;
                if (is_numeric($headcount) && (float) $headcount > 0) {
                    $hasPositive = true;
                    break;
                }
            }

            if (! $hasPositive) {
                $emptyItems[] = "แถวที่ {$idx}: {$posName}";
            }
            $idx++;
        }

        if (! empty($emptyItems)) {
            $itemsStr = "\n- " . implode("\n- ", $emptyItems);
            return "ระบบไม่อนุญาตให้บันทึกรายการที่ไม่ได้ระบุจำนวนของทุกเดือน (ค่าว่างหรือ 0 ทั้งหมด)\nกรุณาระบุจำนวนอย่างน้อย 1 เดือนสำหรับรายการดังต่อไปนี้:{$itemsStr}";
        }

        return null;
    }

    public static function validateFixAssetUniqueRows(array $data, string $itemLabel = 'เครื่องมือ'): ?string
    {
        $seen = [];
        $itemsMeta = [];
        $idx = 1;

        foreach ($data as $item) {
            $itemName = trim((string) ($item['item_name'] ?? ''));
            $desc = trim((string) ($item['description'] ?? ''));
            if ($itemName === '') {
                $idx++;
                continue;
            }
            $key = strtolower($itemName) . '|' . strtolower($desc);
            if (! isset($seen[$key])) {
                $seen[$key] = [$idx];
                $itemsMeta[$key] = [$itemName, $desc];
            } else {
                $seen[$key][] = $idx;
            }
            $idx++;
        }

        $duplicates = [];
        foreach ($seen as $k => $rows) {
            if (count($rows) > 1) {
                $duplicates[] = [$itemsMeta[$k][0], $itemsMeta[$k][1], $rows];
            }
        }

        if (! empty($duplicates)) {
            $lines = [];
            foreach ($duplicates as [$name, $desc, $rows]) {
                $rowsStr = implode(', ', array_map(fn($r) => "แถวที่ {$r}", $rows));
                $descStr = $desc !== '' ? "\"{$desc}\"" : '(ไม่มีรายละเอียด / ค่าว่าง)';
                $lines[] = "- {$itemLabel}: \"{$name}\" | รายละเอียด: {$descStr} — ซ้ำกันใน {$rowsStr}";
            }
            $errList = implode("\n", $lines);
            return "ระบบตรวจพบว่ามีการระบุข้อมูล {$itemLabel} และ รายละเอียด ซ้ำกันในเอกสาร:\n{$errList}\nกรุณาระบุรายละเอียดให้แตกต่างกัน หรือรวมจำนวนเข้าด้วยกันในแถวเดียว";
        }

        return null;
    }

    public static function saveMonthlyData($parentObj, string $docType, array $monthlyDataDict): void
    {
        $fkFieldMap = [
            'VET' => 'vet_budget_id',
            'NON VET' => 'non_vet_budget_id',
            'Position Adjustment' => 'position_adj_id',
            'Medical Equipment' => 'med_equip_id',
            'Computer Equipment' => 'comp_equip_id',
            'Furniture' => 'furniture_equip_id',
            'Tools & Equipment' => 'tools_equip_id',
            'Tools Equipment' => 'tools_equip_id',
            'CAR' => 'car_equip_id',
            'Car' => 'car_equip_id',
            'Renovate' => 'renovate_equip_id',
            'Software' => 'software_equip_id',
        ];

        $fkField = $fkFieldMap[$docType] ?? null;
        if (! $fkField) {
            return;
        }

        // Delete existing monthly details for this parent
        BudgetMonthlyDetail::where($fkField, $parentObj->id)->delete();

        // Check if asset depreciation applies
        $isAsset = in_array($docType, [
            'Medical Equipment', 'Computer Equipment', 'Furniture', 'Tools & Equipment',
            'Tools Equipment', 'CAR', 'Car', 'Renovate', 'Software'
        ], true);

        $depreciationRate = 0.0;
        if ($isAsset && ! empty($parentObj->general_ledger_code)) {
            $gl = GeneralLedgerMaster::where('gl_code', $parentObj->general_ledger_code)->first();
            if ($gl && (float) $gl->depreciation > 0) {
                $depreciationRate = (float) $gl->depreciation;
            }
        }

        $monthMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        $monthlyAdditions = array_fill(1, 12, 0.0);
        $runningCumDep = 0.0;

        for ($m = 1; $m <= 12; $m++) {
            $mKey = array_search($m, $monthMap, true);
            $mVal = $monthlyDataDict[$mKey] ?? [];

            $headcount = (float) ($mVal['headcount'] ?? 0);
            $cost = (float) ($mVal['cost'] ?? 0);
            $salary = (float) ($mVal['salary'] ?? 0);
            $positionAllowance = (float) ($mVal['position_allowance'] ?? 0);
            $newSalary = (float) ($mVal['new_salary'] ?? 0);
            $newAllowance = (float) ($mVal['new_allowance'] ?? 0);

            if ($isAsset && $depreciationRate > 0 && $cost > 0) {
                // monthly addition = cost / (depreciation_rate * 12.0)
                $monthlyAdditions[$m] += $cost / ($depreciationRate * 12.0);
            }

            if ($isAsset && $depreciationRate > 0) {
                // Accumulate additions from month 1 through m
                $currentMonthDep = 0.0;
                for ($k = 1; $k <= $m; $k++) {
                    $currentMonthDep += $monthlyAdditions[$k];
                }
                $runningCumDep = round($currentMonthDep, 2);
            } else {
                $runningCumDep = 0.0;
            }

            BudgetMonthlyDetail::create([
                $fkField => $parentObj->id,
                'month' => $m,
                'headcount' => $headcount,
                'cost' => $cost,
                'salary' => $salary,
                'position_allowance' => $positionAllowance,
                'new_salary' => $newSalary,
                'new_allowance' => $newAllowance,
                'cumulative_depreciation' => $runningCumDep,
            ]);
        }
    }

    public static function saveGlEntryMonthlyData($parentObj, array $monthlyDataDict): void
    {
        $monthMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];
        $existingDetails = GlEntryMonthlyDetail::where('gl_entry_id', $parentObj->id)->get()->keyBy('month');

        foreach ($monthlyDataDict as $monthStr => $data) {
            $monthInt = $monthMap[strtolower($monthStr)] ?? null;
            if (!$monthInt) {
                continue;
            }

            $detailNote = $data['detail_note'] ?? null;
            $amount = (float) ($data['amount'] ?? 0);
            $cumAmount = (float) ($data['cumulative_amount'] ?? 0);

            if ($existingDetails->has($monthInt)) {
                $d = $existingDetails->get($monthInt);
                $d->update([
                    'detail_note' => $detailNote,
                    'amount' => $amount,
                    'cumulative_amount' => $cumAmount,
                ]);
            } else {
                GlEntryMonthlyDetail::create([
                    'gl_entry_id' => $parentObj->id,
                    'month' => $monthInt,
                    'detail_note' => $detailNote,
                    'amount' => $amount,
                    'cumulative_amount' => $cumAmount,
                ]);
            }
        }
    }

    public static function saveRevenueMonthlyData($parentObj, array $monthlyDataDict): void
    {
        $monthMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];
        $existingDetails = RevenueMonthlyDetail::where('revenue_entry_id', $parentObj->id)->get()->keyBy('month');

        foreach ($monthlyDataDict as $monthStr => $data) {
            $monthInt = $monthMap[strtolower($monthStr)] ?? null;
            if (!$monthInt) {
                continue;
            }

            $detailNote = $data['detail_note'] ?? null;
            $amount = (float) ($data['amount'] ?? 0);
            $cumAmount = (float) ($data['cumulative_amount'] ?? 0);

            if ($existingDetails->has($monthInt)) {
                $d = $existingDetails->get($monthInt);
                $d->update([
                    'detail_note' => $detailNote,
                    'amount' => $amount,
                    'cumulative_amount' => $cumAmount,
                ]);
            } else {
                RevenueMonthlyDetail::create([
                    'revenue_entry_id' => $parentObj->id,
                    'month' => $monthInt,
                    'detail_note' => $detailNote,
                    'amount' => $amount,
                    'cumulative_amount' => $cumAmount,
                ]);
            }
        }
    }

    public static function saveBudgetPlanMonthlyData($parentObj, array $monthlyDataDict): void
    {
        $monthMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];
        $existingDetails = BudgetPlanMonthlyDetail::where('plan_item_id', $parentObj->id)->get()->keyBy('month');

        foreach ($monthlyDataDict as $monthStr => $data) {
            $monthInt = $monthMap[strtolower($monthStr)] ?? null;
            if (!$monthInt) {
                continue;
            }

            $amount = (float) ($data['amount'] ?? 0);

            if ($existingDetails->has($monthInt)) {
                $d = $existingDetails->get($monthInt);
                $d->update([
                    'amount' => $amount,
                ]);
            } else {
                BudgetPlanMonthlyDetail::create([
                    'plan_item_id' => $parentObj->id,
                    'month' => $monthInt,
                    'amount' => $amount,
                ]);
            }
        }
    }

    public static function getMasterFksBulk(array $itemNames, ?int $categoryId = null, ?int $subCategoryId = null): array
    {
        $query = BudgetItemMaster::whereIn('item_name', array_unique($itemNames));
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }
        if ($subCategoryId !== null) {
            $query->where('sub_category_id', $subCategoryId);
        }
        $masters = $query->get();

        $res = [];
        foreach ($masters as $m) {
            $res[$m->item_name] = [$m, $m->general_ledger_code];
        }
        return $res;
    }

    public static function getGlMasterFieldsBulk(array $glCodes): array
    {
        $masters = GeneralLedgerMaster::whereIn('gl_code', array_unique($glCodes))->get();
        $res = [];
        foreach ($masters as $m) {
            $res[$m->gl_code] = [(float) $m->proportion, (float) $m->depreciation];
        }
        return $res;
    }

    public static function getCostCentersJson(): array
    {
        return CostCenterMaster::orderBy('cost_center_code')->get()->map(function ($cc) {
            return [
                'cost_center_code' => $cc->cost_center_code,
                'cost_center_name' => $cc->cost_center_name,
                'description' => $cc->description,
            ];
        })->toArray();
    }

    public static function getGeneralLedgersJson(?array $excludeCodes = null, ?string $nameStartswith = null, ?string $excludeNameStartswith = null): array
    {
        $query = GeneralLedgerMaster::query();
        if (! empty($excludeCodes)) {
            $query->whereNotIn('gl_code', $excludeCodes);
        }
        if ($nameStartswith !== null && $nameStartswith !== '') {
            $query->where('gl_name', 'like', "{$nameStartswith}%");
        }
        if ($excludeNameStartswith !== null && $excludeNameStartswith !== '') {
            $query->where('gl_name', 'not like', "{$excludeNameStartswith}%");
        }
        return $query->orderBy('gl_code')->get()->map(function ($gl) {
            return [
                'gl_code' => $gl->gl_code,
                'gl_name' => $gl->gl_name,
                'proportion' => (float) $gl->proportion,
                'depreciation' => (float) $gl->depreciation,
            ];
        })->toArray();
    }

    public static function resolveBranchForCreate(Request $request, array $data): string
    {
        $branchInfo = $request->session()->get('branch_info') ?? BudgetRoleService::getBranchInfoFromImedx($request->user()->username);
        $branches = $branchInfo['branches'] ?? [];

        if (count($branches) > 1) {
            $selected = trim((string) ($data['base_branch_id'] ?? ''));
            if ($selected !== '' && in_array(strtoupper($selected), array_map('strtoupper', $branches), true)) {
                return strtoupper($selected);
            }
        }

        return ! empty($branches) ? $branches[0] : 'HO';
    }

    public static function resolveBranchForUpdate(Request $request, array $data, string $currentBranchId): string
    {
        $branchInfo = $request->session()->get('branch_info') ?? BudgetRoleService::getBranchInfoFromImedx($request->user()->username);
        $branches = $branchInfo['branches'] ?? [];

        if (count($branches) > 1) {
            $selected = trim((string) ($data['base_branch_id'] ?? ''));
            if ($selected !== '' && in_array(strtoupper($selected), array_map('strtoupper', $branches), true)) {
                return strtoupper($selected);
            }
        }

        return $currentBranchId;
    }
}
