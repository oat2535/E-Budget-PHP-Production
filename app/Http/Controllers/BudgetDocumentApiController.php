<?php

namespace App\Http\Controllers;

use App\Models\BudgetItemMaster;
use App\Models\BudgetPlanItem;
use App\Models\Car;
use App\Models\ComputerEquipment;
use App\Models\Furniture;
use App\Models\GeneralLedgerMaster;
use App\Models\GlActualTemplate;
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
use App\Services\BudgetRoleService;
use App\Services\BudgetService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BudgetDocumentApiController extends Controller
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const FIX_ASSET_MIN_PURCHASE_PRICE = 5000;

    public const DOC_TYPE_MODEL_MAP = [
        'VET' => [VetManpower::class, 'VET'],
        'NON VET' => [NonVetManpower::class, 'NON VET'],
        'POSITION ADJUSTMENT' => [PositionAdjustment::class, 'Position Adjustment'],
        'MEDICAL EQUIPMENT' => [MedicalEquipment::class, 'Medical Equipment'],
        'COMPUTER EQUIPMENT' => [ComputerEquipment::class, 'Computer Equipment'],
        'FURNITURE' => [Furniture::class, 'Furniture'],
        'TOOLS EQUIPMENT' => [ToolsEquipment::class, 'Tools Equipment'],
        'TOOLS & EQUIPMENT' => [ToolsEquipment::class, 'Tools & Equipment'],
        'CAR' => [Car::class, 'Car'],
        'RENOVATE' => [Renovate::class, 'Renovate'],
        'SOFTWARE' => [Software::class, 'Software'],
        'GL ENTRY' => [GlEntry::class, 'GL Entry'],
        'REVENUE' => [Revenue::class, 'Revenue'],
        'BUDGET PLAN' => [BudgetPlanItem::class, 'Budget Plan'],
        'GL ACTUAL TEMPLATE' => [GlActualTemplate::class, 'GL Actual Template'],
    ];

    public const DOC_TYPE_ITEM_COLUMN_NAMES = [
        'Medical Equipment' => 'เครื่องมือแพทย์',
        'Computer Equipment' => 'อุปกรณ์คอมพิวเตอร์',
        'Furniture' => 'เฟอร์นิเจอร์',
        'Tools & Equipment' => 'เครื่องมือและอุปกรณ์',
        'CAR' => 'ยานพาหนะ',
        'Renovate' => 'งานปรับปรุง',
        'Software' => 'ซอฟต์แวร์',
    ];

    public function getDocuments(Request $request, string $categoryCode): JsonResponse
    {
        $categoryCode = strtoupper(trim($categoryCode));
        $results = [];

        if ($categoryCode === 'C01') {
            $types = [
                ['model' => VetManpower::class, 'type' => 'VET', 'sum_col' => 'cost'],
                ['model' => NonVetManpower::class, 'type' => 'NON VET', 'sum_col' => 'cost'],
                ['model' => PositionAdjustment::class, 'type' => 'Position Adjustment', 'sum_col' => 'cost'],
            ];

            foreach ($types as $t) {
                $query = $t['model']::query();
                BudgetRoleService::applyDocumentVisibilityFilter($query, $request);

                $docs = $query->with('monthlyDetails')
                    ->get()
                    ->groupBy('document_no');

                foreach ($docs as $docNo => $rows) {
                    $first = $rows->first();
                    $createDate = $rows->max('create_date');
                    $dt = $createDate ? Carbon::parse($createDate) : null;
                    $totalBudget = 0.0;
                    foreach ($rows as $r) {
                        foreach ($r->monthlyDetails as $md) {
                            $totalBudget += (float) ($md->cost ?? 0);
                        }
                    }

                    $results[] = [
                        'document_no' => $docNo ?: '-',
                        'create_date_raw' => $dt ? $dt->toISOString() : '',
                        'create_date' => $dt ? $dt->format('d/m/Y H:i') : '-',
                        'create_eid' => $rows->max('create_eid') ?: '-',
                        'total_positions' => $rows->count(),
                        'budget_year' => $rows->max('budget_year') ?: '-',
                        'status' => $rows->max('status') ?: self::STATUS_PENDING,
                        'base_branch_id' => $rows->max('base_branch_id') ?: '-',
                        'cost_center_name' => $rows->max('cost_center_name') ?: '-',
                        'total_budget' => round($totalBudget, 2),
                        'type' => $t['type'],
                    ];
                }
            }
        } elseif ($categoryCode === 'C02') {
            $types = [
                ['model' => MedicalEquipment::class, 'type' => 'Medical Equipment'],
                ['model' => ComputerEquipment::class, 'type' => 'Computer Equipment'],
                ['model' => Furniture::class, 'type' => 'Furniture'],
                ['model' => ToolsEquipment::class, 'type' => 'Tools & Equipment'],
                ['model' => Car::class, 'type' => 'CAR'],
                ['model' => Renovate::class, 'type' => 'Renovate'],
                ['model' => Software::class, 'type' => 'Software'],
            ];

            foreach ($types as $t) {
                $query = $t['model']::query();
                BudgetRoleService::applyDocumentVisibilityFilter($query, $request);

                $docs = $query->with('monthlyDetails')
                    ->get()
                    ->groupBy('document_no');

                foreach ($docs as $docNo => $rows) {
                    $createDate = $rows->max('create_date');
                    $dt = $createDate ? Carbon::parse($createDate) : null;
                    $totalBudget = 0.0;
                    foreach ($rows as $r) {
                        foreach ($r->monthlyDetails as $md) {
                            $totalBudget += (float) ($md->cost ?? 0);
                        }
                    }

                    $results[] = [
                        'document_no' => $docNo ?: '-',
                        'create_date_raw' => $dt ? $dt->toISOString() : '',
                        'create_date' => $dt ? $dt->format('d/m/Y H:i') : '-',
                        'create_eid' => $rows->max('create_eid') ?: '-',
                        'total_positions' => $rows->count(),
                        'budget_year' => $rows->max('budget_year') ?: '-',
                        'status' => $rows->max('status') ?: self::STATUS_PENDING,
                        'base_branch_id' => $rows->max('base_branch_id') ?: '-',
                        'cost_center_name' => $rows->max('cost_center_name') ?: '-',
                        'total_budget' => round($totalBudget, 2),
                        'type' => $t['type'],
                    ];
                }
            }
        } elseif ($categoryCode === 'C03') {
            // GL Entry
            $glQuery = GlEntry::query();
            BudgetRoleService::applyDocumentVisibilityFilter($glQuery, $request);
            $glDocs = $glQuery->with('monthlyDetails')->get()->groupBy('document_no');
            foreach ($glDocs as $docNo => $rows) {
                $createDate = $rows->max('create_date');
                $dt = $createDate ? Carbon::parse($createDate) : null;
                $totalBudget = 0.0;
                foreach ($rows as $r) {
                    foreach ($r->monthlyDetails as $md) {
                        $totalBudget += (float) ($md->amount ?? 0);
                    }
                }
                $results[] = [
                    'document_no' => $docNo ?: '-',
                    'create_date_raw' => $dt ? $dt->toISOString() : '',
                    'create_date' => $dt ? $dt->format('d/m/Y H:i') : '-',
                    'create_eid' => $rows->max('create_eid') ?: '-',
                    'total_positions' => $rows->count(),
                    'budget_year' => $rows->max('budget_year') ?: '-',
                    'status' => $rows->max('status') ?: self::STATUS_PENDING,
                    'base_branch_id' => $rows->max('base_branch_id') ?: '-',
                    'cost_center_name' => $rows->max('cost_center_name') ?: '-',
                    'total_budget' => round($totalBudget, 2),
                    'type' => 'GL Entry',
                ];
            }

            // Revenue
            $revQuery = Revenue::query();
            BudgetRoleService::applyDocumentVisibilityFilter($revQuery, $request);
            $revDocs = $revQuery->with('monthlyDetails')->get()->groupBy('document_no');
            foreach ($revDocs as $docNo => $rows) {
                $createDate = $rows->max('create_date');
                $dt = $createDate ? Carbon::parse($createDate) : null;
                $totalBudget = 0.0;
                foreach ($rows as $r) {
                    foreach ($r->monthlyDetails as $md) {
                        $totalBudget += (float) ($md->amount ?? 0);
                    }
                }
                $results[] = [
                    'document_no' => $docNo ?: '-',
                    'create_date_raw' => $dt ? $dt->toISOString() : '',
                    'create_date' => $dt ? $dt->format('d/m/Y H:i') : '-',
                    'create_eid' => $rows->max('create_eid') ?: '-',
                    'total_positions' => $rows->count(),
                    'budget_year' => $rows->max('budget_year') ?: '-',
                    'status' => $rows->max('status') ?: self::STATUS_PENDING,
                    'base_branch_id' => $rows->max('base_branch_id') ?: '-',
                    'cost_center_name' => $rows->max('cost_center_name') ?: '-',
                    'total_budget' => round($totalBudget, 2),
                    'type' => 'Revenue',
                ];
            }

            // Budget Plan
            $planQuery = BudgetPlanItem::query();
            BudgetRoleService::applyDocumentVisibilityFilter($planQuery, $request);
            $planDocs = $planQuery->with('monthlyDetails')->get()->groupBy('document_no');
            foreach ($planDocs as $docNo => $rows) {
                $createDate = $rows->max('create_date');
                $dt = $createDate ? Carbon::parse($createDate) : null;
                $totalBudget = 0.0;
                foreach ($rows as $r) {
                    foreach ($r->monthlyDetails as $md) {
                        $totalBudget += (float) ($md->amount ?? 0);
                    }
                }
                $results[] = [
                    'document_no' => $docNo ?: '-',
                    'create_date_raw' => $dt ? $dt->toISOString() : '',
                    'create_date' => $dt ? $dt->format('d/m/Y H:i') : '-',
                    'create_eid' => $rows->max('create_eid') ?: '-',
                    'total_positions' => $rows->count(),
                    'budget_year' => $rows->max('budget_year') ?: '-',
                    'status' => $rows->max('status') ?: self::STATUS_PENDING,
                    'base_branch_id' => $rows->max('base_branch_id') ?: '-',
                    'cost_center_name' => $rows->max('cost_center_name') ?: '-',
                    'total_budget' => round($totalBudget, 2),
                    'type' => 'Budget Plan',
                ];
            }

            // GL Actual Template
            $glatQuery = GlActualTemplate::query();
            BudgetRoleService::applyDocumentVisibilityFilter($glatQuery, $request);
            $glatDocs = $glatQuery->get()->groupBy('document_no');
            foreach ($glatDocs as $docNo => $rows) {
                $createDate = $rows->max('create_date');
                $dt = $createDate ? Carbon::parse($createDate) : null;
                $totalBudget = (float) $rows->sum('annualized_value');

                $results[] = [
                    'document_no' => $docNo ?: '-',
                    'create_date_raw' => $dt ? $dt->toISOString() : '',
                    'create_date' => $dt ? $dt->format('d/m/Y H:i') : '-',
                    'create_eid' => $rows->max('create_eid') ?: '-',
                    'total_positions' => $rows->count(),
                    'budget_year' => $rows->max('budget_year') ?: '-',
                    'status' => $rows->max('status') ?: self::STATUS_PENDING,
                    'base_branch_id' => $rows->max('base_branch_id') ?: '-',
                    'cost_center_name' => '-',
                    'total_budget' => round($totalBudget, 2),
                    'type' => 'GL Actual Template',
                ];
            }
        } else {
            return response()->json(['status' => 'error', 'message' => 'ไม่มีข้อมูลสำหรับหมวดหมู่นี้']);
        }

        usort($results, function ($a, $b) {
            return strcmp($b['create_date_raw'], $a['create_date_raw']);
        });

        return response()->json(['status' => 'success', 'data' => $results]);
    }

    public function getDocumentDetail(Request $request, string $docType, string $docNo): JsonResponse
    {
        $modelClass = BudgetService::resolveModelClass($docType);
        if (! $modelClass) {
            return response()->json(['status' => 'error', 'message' => 'ประเภทเอกสารไม่ถูกต้อง']);
        }

        $query = $modelClass::where('document_no', $docNo);
        BudgetRoleService::applyDocumentVisibilityFilter($query, $request);

        $isGlat = ($docType === 'GL Actual Template');
        if (! $isGlat) {
            $query->with('monthlyDetails');
        }

        $items = $query->orderBy('id')->get();
        if ($items->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'ไม่พบเอกสารนี้']);
        }

        $firstItem = $items->first();
        $dt = $firstItem->create_date ? Carbon::parse($firstItem->create_date) : null;

        $docInfo = [
            'document_no' => $firstItem->document_no,
            'create_date' => $dt ? $dt->format('d/m/Y H:i') : '-',
            'create_eid' => $firstItem->create_eid,
            'type' => $docType,
            'base_branch_id' => $firstItem->base_branch_id ?: '-',
            'cost_center_name' => $isGlat ? '' : ($firstItem->cost_center_name ?: ''),
            'budget_year' => $firstItem->budget_year ?: '-',
            'status' => $firstItem->status ?: self::STATUS_PENDING,
            'cancel_reason' => $firstItem->cancel_reason ?: '',
        ];

        if ($isGlat) {
            $docInfo['actual_year'] = $dt ? $dt->year : '-';
            $docInfo['actual_month_count'] = $firstItem->actual_month_count ?: '-';
            $docInfo['growth_pct_1'] = (float) $firstItem->growth_pct_1;
            $docInfo['growth_pct_2'] = (float) $firstItem->growth_pct_2;
            $docInfo['growth_pct_3'] = (float) $firstItem->growth_pct_3;
        }

        $glNameByCode = [];
        if (in_array($docType, ['GL Entry', 'Revenue', 'Budget Plan'], true)) {
            $glNameByCode = GeneralLedgerMaster::pluck('gl_name', 'gl_code')->toArray();
        }

        $manpowerList = [];
        foreach ($items as $item) {
            if ($docType === 'Position Adjustment') {
                $data = [
                    'id' => $item->id,
                    'adjustment_type' => $item->adjustment_type,
                    'old_position_name' => $item->old_position_name,
                    'old_description' => $item->old_description ?: '',
                    'new_position_name' => $item->new_position_name,
                    'new_description' => $item->new_description ?: '',
                    'old_salary' => (float) $item->old_salary,
                    'old_allowance' => (float) $item->old_allowance,
                    'new_salary' => (float) $item->new_salary,
                    'new_allowance' => (float) $item->new_allowance,
                    'monthly_data' => $item->monthly_data_dict,
                ];
            } elseif (in_array($docType, ['Medical Equipment', 'Computer Equipment', 'Furniture', 'Tools & Equipment', 'Tools Equipment', 'CAR', 'Car', 'Renovate', 'Software'], true)) {
                $monthlyDict = $item->monthly_data_dict;
                $decCumDep = (float) ($monthlyDict['dec']['cumulative_depreciation'] ?? 0.0);
                $data = [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'description' => $item->description ?: '',
                    'purchase_price' => (float) $item->purchase_price,
                    'cumulative_depreciation' => $decCumDep,
                    'monthly_data' => $monthlyDict,
                ];
            } elseif ($docType === 'GL Entry' || $docType === 'Revenue') {
                $data = [
                    'id' => $item->id,
                    'general_ledger_code' => $item->general_ledger_code,
                    'general_ledger_name' => $glNameByCode[$item->general_ledger_code] ?? $item->general_ledger_code,
                    'proportion' => (float) $item->proportion,
                    'depreciation' => (float) $item->depreciation,
                    'detail_note' => $item->detail_note ?: '',
                    'monthly_data' => $item->monthly_data_dict,
                ];
            } elseif ($docType === 'Budget Plan') {
                $data = [
                    'id' => $item->id,
                    'budget_category' => $item->budget_category,
                    'budget_category_display' => $item->budget_category_display,
                    'item_code' => $item->item_code,
                    'general_ledger_code' => $item->general_ledger_code,
                    'general_ledger_name' => $glNameByCode[$item->general_ledger_code] ?? $item->general_ledger_code,
                    'description' => $item->description,
                    'monthly_data' => $item->monthly_data_dict,
                ];
            } elseif ($docType === 'GL Actual Template') {
                $data = [
                    'id' => $item->id,
                    'template_row_id' => $item->template_row_id,
                    'row_order' => $item->row_order,
                    'code' => $item->code,
                    'label' => $item->label,
                    'row_type' => $item->row_type,
                    'actual_value' => (float) $item->actual_value,
                    'per_month_value' => (float) $item->per_month_value,
                    'annualized_value' => (float) $item->annualized_value,
                    'sales_ratio' => (float) $item->sales_ratio,
                    'growth_rate' => (float) $item->growth_rate,
                ];
            } else {
                $data = [
                    'id' => $item->id,
                    'position_name' => $item->position_name,
                    'salary' => (float) $item->salary,
                    'monthly_data' => $item->monthly_data_dict,
                ];
                if ($docType === 'NON VET') {
                    $data['position_allowance'] = (float) ($item->position_allowance ?? 0);
                }
                if ($docType === 'VET' || $docType === 'NON VET') {
                    $data['description'] = $item->description ?: '';
                }
            }
            $manpowerList[] = $data;
        }

        return response()->json([
            'status' => 'success',
            'doc_info' => $docInfo,
            'manpower_list' => $manpowerList,
        ]);
    }

    public function updateDocument(Request $request, string $docType, string $docNo): JsonResponse
    {
        if (BudgetRoleService::isViewOnlyUser($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'ตำแหน่งของท่านมีสิทธิ์ดูข้อมูลเท่านั้น ไม่สามารถแก้ไขข้อมูลได้',
            ], 403);
        }

        $modelClass = BudgetService::resolveModelClass($docType);
        if (! $modelClass) {
            return response()->json(['status' => 'error', 'message' => 'ประเภทเอกสารไม่ถูกต้อง']);
        }

        $data = $request->json()->all();
        if (empty($data)) {
            return response()->json(['status' => 'error', 'message' => 'ไม่มีข้อมูลสำหรับบันทึก']);
        }

        if ($docType !== 'GL Actual Template' && empty($data[0]['cost_center_name'])) {
            return response()->json(['status' => 'error', 'message' => 'กรุณาเลือก Cost Center']);
        }

        $existingItems = $modelClass::where('document_no', $docNo)->get();
        if ($existingItems->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'ไม่พบเอกสาร']);
        }

        $firstItem = $existingItems->first();
        if ($firstItem->status === self::STATUS_APPROVED) {
            return response()->json(['status' => 'error', 'message' => 'เอกสารนี้ได้รับการอนุมัติแล้ว ไม่สามารถแก้ไขได้']);
        }
        if ($firstItem->status === self::STATUS_CANCELLED) {
            return response()->json(['status' => 'error', 'message' => 'เอกสารนี้ถูกยกเลิกแล้ว ไม่สามารถแก้ไขได้ กรุณาสร้างเอกสารใหม่แทน']);
        }

        $user = $request->user();
        $isPrivileged = BudgetRoleService::isPrivilegedUserAccount($user, $request);
        if (! $isPrivileged && (empty($firstItem->create_eid) || strtolower($firstItem->create_eid) !== strtolower($user->username))) {
            return response()->json(['status' => 'error', 'message' => 'คุณแก้ไขได้เฉพาะเอกสารที่ตัวเองสร้างเท่านั้น']);
        }

        if ($docType === 'GL Actual Template') {
            $chosenBranch = $data[0]['branch_code'] ?? null;
            $validBranches = array_column(BudgetRoleService::GL_ACTUAL_TEMPLATE_BRANCHES, 0);
            if (! in_array($chosenBranch, $validBranches, true)) {
                return response()->json(['status' => 'error', 'message' => 'กรุณาเลือกสาขาให้ถูกต้อง']);
            }
            $baseBranchId = $chosenBranch;
        } else {
            $baseBranchId = BudgetRoleService::resolveBranchForUpdate($request, $data, $firstItem->base_branch_id);
        }

        $budgetYear = $firstItem->budget_year;
        $createDate = $firstItem->create_date;
        $createEid = $firstItem->create_eid;
        $deptId = $firstItem->department_id;

        // Canonical cost center name resolution
        if (! empty($data[0]['cost_center_name'])) {
            $canonicalCc = BudgetService::resolveCanonicalCostCenterName($data[0]['cost_center_name']);
            foreach ($data as &$it) {
                $it['cost_center_name'] = $canonicalCc;
            }
            unset($it);
        }

        // Master lookup field
        $equipmentSubcatMap = [
            'Medical Equipment' => 4,
            'Computer Equipment' => 5,
            'Furniture' => 6,
            'Tools & Equipment' => 7,
            'Tools Equipment' => 7,
            'CAR' => 8,
            'Car' => 8,
            'Renovate' => 9,
            'Software' => 10,
        ];
        $editSubcatId = $equipmentSubcatMap[$docType] ?? null;
        $editCatId = $editSubcatId ? 2 : (in_array($docType, ['VET', 'NON VET'], true) ? 1 : null);

        $lookupField = match (true) {
            $docType === 'Position Adjustment' => 'new_position_name',
            isset($equipmentSubcatMap[$docType]) => 'item_name',
            in_array($docType, ['GL Entry', 'Revenue', 'Budget Plan', 'GL Actual Template'], true) => null,
            default => 'position_name',
        };

        $masterFks = [];
        if ($lookupField) {
            $names = array_column($data, $lookupField);
            $masterFks = BudgetService::getMasterFksBulk($names, $editCatId, $editSubcatId);
        }

        $glMasterFields = [];
        if (in_array($docType, ['GL Entry', 'Revenue'], true)) {
            $glCodes = array_filter(array_column($data, 'general_ledger_code'));
            $glMasterFields = BudgetService::getGlMasterFieldsBulk($glCodes);
        }

        $vetFks = [];
        $nonVetFks = [];

        // Validate Dropdown items exist in master data
        if ($docType === 'VET' || $docType === 'NON VET') {
            foreach ($data as $item) {
                $pos = trim($item['position_name'] ?? '');
                if (! $pos || ! isset($masterFks[$pos])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "ไม่อนุญาตให้พิมพ์ระบุข้อความเอง กรุณาเลือกตำแหน่งจากรายการ Dropdown เท่านั้น (ตำแหน่ง: \"{$pos}\" ไม่มีอยู่ในระบบ)",
                    ]);
                }
            }
        } elseif ($docType === 'Position Adjustment') {
            $allAdjPos = array_filter(array_merge(array_column($data, 'old_position_name'), array_column($data, 'new_position_name')));
            $vetFks = BudgetService::getMasterFksBulk($allAdjPos, 1, 1);
            $nonVetFks = BudgetService::getMasterFksBulk($allAdjPos, 1, 2);

            foreach ($data as $item) {
                $adjType = trim($item['adjustment_type'] ?? '');
                if (! in_array($adjType, ['VET', 'NON VET'], true)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'ไม่อนุญาตให้พิมพ์ระบุข้อความเอง กรุณาเลือกประเภท (VET หรือ NON VET) จากรายการ Dropdown เท่านั้น',
                    ]);
                }
                $allowedFks = ($adjType === 'VET') ? $vetFks : $nonVetFks;
                $oldP = trim($item['old_position_name'] ?? '');
                $newP = trim($item['new_position_name'] ?? '');
                if (! $oldP || ! isset($allowedFks[$oldP])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "ไม่อนุญาตให้พิมพ์ระบุข้อความเอง กรุณาเลือกตำแหน่งเดิมจากรายการ Dropdown เท่านั้น (ตำแหน่ง: \"{$oldP}\" ไม่มีอยู่ในรายการของประเภท {$adjType})",
                    ]);
                }
                if (! $newP || ! isset($allowedFks[$newP])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "ไม่อนุญาตให้พิมพ์ระบุข้อความเอง กรุณาเลือกตำแหน่งใหม่จากรายการ Dropdown เท่านั้น (ตำแหน่ง: \"{$newP}\" ไม่มีอยู่ในรายการของประเภท {$adjType})",
                    ]);
                }
            }
        } elseif (isset($equipmentSubcatMap[$docType])) {
            foreach ($data as $item) {
                $itName = trim($item['item_name'] ?? '');
                if (! $itName || ! isset($masterFks[$itName])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "ไม่อนุญาตให้พิมพ์ระบุข้อความเอง กรุณาเลือกรายการจาก Dropdown เท่านั้น (รายการ: \"{$itName}\" ไม่มีอยู่ในระบบ)",
                    ]);
                }
            }
        }

        // Validations
        if (in_array($docType, ['VET', 'NON VET', 'Position Adjustment'], true)) {
            $posKey = ($docType === 'Position Adjustment') ? 'new_position_name' : 'position_name';
            $err = BudgetService::validateManpowerHeadcountIntegers($data, $posKey);
            if ($err) {
                return response()->json(['status' => 'error', 'message' => $err]);
            }
            $err = BudgetService::validateNonzeroMonthlyRows($data, $posKey);
            if ($err) {
                return response()->json(['status' => 'error', 'message' => $err]);
            }
        } elseif (isset($equipmentSubcatMap[$docType])) {
            $err = BudgetService::validateManpowerHeadcountIntegers($data, 'item_name');
            if ($err) {
                return response()->json(['status' => 'error', 'message' => $err]);
            }
            $itemLabel = self::DOC_TYPE_ITEM_COLUMN_NAMES[$docType] ?? 'เครื่องมือ';
            $err = BudgetService::validateFixAssetUniqueRows($data, $itemLabel);
            if ($err) {
                return response()->json(['status' => 'error', 'message' => $err]);
            }
            $err = BudgetService::validateNonzeroMonthlyRows($data, 'item_name');
            if ($err) {
                return response()->json(['status' => 'error', 'message' => $err]);
            }
            foreach ($data as $row) {
                $price = (float) ($row['purchase_price'] ?? 0);
                if ($price < self::FIX_ASSET_MIN_PURCHASE_PRICE) {
                    $itName = $row['item_name'] ?? '';
                    return response()->json([
                        'status' => 'error',
                        'message' => "ราคาซื้อในหมวดสินทรัพย์ถาวร (Fix Asset) ต้องมีมูลค่าตั้งแต่ 5,000 บาทขึ้นไป (รายการ: {$itName})",
                    ]);
                }
            }
        }

        try {
            DB::connection('pgsql')->transaction(function () use (
                $docType, $docNo, $data, $modelClass, $existingItems, $baseBranchId,
                $deptId, $budgetYear, $createDate, $createEid, $user, $masterFks,
                $glMasterFields, $equipmentSubcatMap, $vetFks, $nonVetFks
            ) {
                $existingMap = $existingItems->keyBy('id');
                $docStatus = $existingItems->first()->status ?? self::STATUS_PENDING;
                $submittedIds = [];
                foreach ($data as $item) {
                    $itemId = $item['id'] ?? null;
                    if ($itemId && is_numeric($itemId) && $existingMap->has((int) $itemId)) {
                        $submittedIds[] = (int) $itemId;
                    }
                }

                // Delete rows removed by user
                $toDelete = $modelClass::where('document_no', $docNo)
                    ->whereNotIn('id', $submittedIds)
                    ->get();
                foreach ($toDelete as $delObj) {
                    if (method_exists($delObj, 'monthlyDetails')) {
                        $delObj->monthlyDetails()->delete();
                    }
                    $delObj->delete();
                }

                // Upsert rows
                foreach ($data as $item) {
                    $itemId = $item['id'] ?? null;
                    $itemIdInt = ($itemId && is_numeric($itemId) && $existingMap->has((int) $itemId)) ? (int) $itemId : null;

                    if ($docType === 'Position Adjustment') {
                        $adjType = $item['adjustment_type'] ?? '';
                        $allowedFks = ($adjType === 'VET') ? $vetFks : $nonVetFks;
                        [$masterObj, $_] = $allowedFks[$item['new_position_name']] ?? [null, null];
                        [$salaryGl, $allowanceGl] = BudgetService::resolveAdjustmentGlCodes($adjType);
                        $fields = [
                            'adjustment_type' => $adjType ?: null,
                            'old_position_name' => $item['old_position_name'],
                            'old_description' => $item['old_description'] ?? null,
                            'new_position_name' => $item['new_position_name'],
                            'new_description' => $item['new_description'] ?? null,
                            'old_salary' => $item['old_salary'],
                            'old_allowance' => ($adjType === 'VET') ? 0 : ($item['old_allowance'] ?? 0),
                            'new_salary' => $item['new_salary'],
                            'new_allowance' => ($adjType === 'VET') ? 0 : ($item['new_allowance'] ?? 0),
                            'base_branch_id' => $baseBranchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'modify_eid' => $user->username,
                            'item_master_id' => $masterObj ? $masterObj->id : null,
                            'salary_general_ledger_code' => $salaryGl,
                            'allowance_general_ledger_code' => $allowanceGl,
                            'budget_year' => $budgetYear,
                        ];

                        if ($itemIdInt) {
                            $obj = $existingMap->get($itemIdInt);
                            $obj->update($fields);
                        } else {
                            $fields = array_merge($fields, [
                                'document_no' => $docNo,
                                'create_date' => $createDate,
                                'create_eid' => $createEid,
                                'status' => $docStatus,
                            ]);
                            $obj = $modelClass::create($fields);
                        }
                        BudgetService::saveMonthlyData($obj, $docType, $item['monthly_data'] ?? []);
                    } elseif ($docType === 'VET') {
                        [$masterObj, $glCode] = $masterFks[$item['position_name']] ?? [null, null];
                        $fields = [
                            'position_name' => $item['position_name'],
                            'description' => $item['description'] ?? null,
                            'salary' => $item['salary'],
                            'base_branch_id' => $baseBranchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'modify_eid' => $user->username,
                            'item_master_id' => $masterObj ? $masterObj->id : null,
                            'general_ledger_code' => $glCode ?: BudgetService::VET_CUSTOM_POSITION_GL_CODE,
                            'budget_year' => $budgetYear,
                        ];

                        if ($itemIdInt) {
                            $obj = $existingMap->get($itemIdInt);
                            $obj->update($fields);
                        } else {
                            $fields = array_merge($fields, [
                                'document_no' => $docNo,
                                'create_date' => $createDate,
                                'create_eid' => $createEid,
                                'status' => $docStatus,
                            ]);
                            $obj = $modelClass::create($fields);
                        }
                        BudgetService::saveMonthlyData($obj, $docType, $item['monthly_data'] ?? []);
                    } elseif (isset($equipmentSubcatMap[$docType])) {
                        [$masterObj, $glCode] = $masterFks[$item['item_name']] ?? [null, null];
                        $glCode = BudgetService::resolveEquipmentGlCode($docType, $item['purchase_price'], $glCode);
                        $fields = [
                            'item_name' => $item['item_name'],
                            'description' => $item['description'] ?? null,
                            'purchase_price' => $item['purchase_price'],
                            'base_branch_id' => $baseBranchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'modify_eid' => $user->username,
                            'item_master_id' => $masterObj ? $masterObj->id : null,
                            'general_ledger_code' => $glCode,
                            'budget_year' => $budgetYear,
                        ];

                        if ($itemIdInt) {
                            $obj = $existingMap->get($itemIdInt);
                            $obj->update($fields);
                        } else {
                            $fields = array_merge($fields, [
                                'document_no' => $docNo,
                                'create_date' => $createDate,
                                'create_eid' => $createEid,
                                'status' => $docStatus,
                            ]);
                            $obj = $modelClass::create($fields);
                        }
                        BudgetService::saveMonthlyData($obj, $docType, $item['monthly_data'] ?? []);
                    } elseif ($docType === 'GL Entry' || $docType === 'Revenue') {
                        [$proportion, $depreciation] = $glMasterFields[$item['general_ledger_code']] ?? [0, 0];
                        $fields = [
                            'general_ledger_code' => $item['general_ledger_code'],
                            'proportion' => $proportion,
                            'depreciation' => $depreciation,
                            'detail_note' => $item['detail_note'] ?? null,
                            'base_branch_id' => $baseBranchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'modify_eid' => $user->username,
                            'budget_year' => $budgetYear,
                        ];

                        if ($itemIdInt) {
                            $obj = $existingMap->get($itemIdInt);
                            $obj->update($fields);
                        } else {
                            $fields = array_merge($fields, [
                                'document_no' => $docNo,
                                'create_date' => $createDate,
                                'create_eid' => $createEid,
                                'status' => $docStatus,
                            ]);
                            $obj = $modelClass::create($fields);
                        }

                        if ($docType === 'GL Entry') {
                            BudgetService::saveGlEntryMonthlyData($obj, $item['monthly_data'] ?? []);
                        } else {
                            BudgetService::saveRevenueMonthlyData($obj, $item['monthly_data'] ?? []);
                        }
                    } elseif ($docType === 'Budget Plan') {
                        $fields = [
                            'budget_category' => $item['budget_category'] ?? null,
                            'item_code' => $item['item_code'] ?? null,
                            'general_ledger_code' => $item['general_ledger_code'] ?? null,
                            'description' => $item['description'] ?? null,
                            'base_branch_id' => $baseBranchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'modify_eid' => $user->username,
                            'budget_year' => $budgetYear,
                        ];

                        if ($itemIdInt) {
                            $obj = $existingMap->get($itemIdInt);
                            $obj->update($fields);
                        } else {
                            $fields = array_merge($fields, [
                                'document_no' => $docNo,
                                'create_date' => $createDate,
                                'create_eid' => $createEid,
                                'status' => $docStatus,
                            ]);
                            $obj = $modelClass::create($fields);
                        }
                        BudgetService::saveBudgetPlanMonthlyData($obj, $item['monthly_data'] ?? []);
                    } elseif ($docType === 'GL Actual Template') {
                        $fields = [
                            'template_row_id' => $item['template_row_id'] ?? null,
                            'row_order' => $item['row_order'],
                            'code' => $item['code'],
                            'label' => $item['label'],
                            'row_type' => $item['row_type'],
                            'actual_value' => $item['actual_value'] ?? 0,
                            'per_month_value' => $item['per_month_value'] ?? 0,
                            'annualized_value' => $item['annualized_value'] ?? 0,
                            'sales_ratio' => $item['sales_ratio'] ?? 0,
                            'growth_rate' => $item['growth_rate'] ?? 0,
                            'base_branch_id' => $baseBranchId,
                            'department_id' => $deptId,
                            'budget_year' => $budgetYear,
                            'actual_month_count' => $item['actual_month_count'] ?? null,
                            'growth_pct_1' => $item['growth_pct_1'] ?? 0,
                            'growth_pct_2' => $item['growth_pct_2'] ?? 0,
                            'growth_pct_3' => $item['growth_pct_3'] ?? 0,
                            'modify_eid' => $user->username,
                        ];

                        if ($itemIdInt) {
                            $obj = $existingMap->get($itemIdInt);
                            $obj->update($fields);
                        } else {
                            $fields = array_merge($fields, [
                                'document_no' => $docNo,
                                'create_date' => $createDate,
                                'create_eid' => $createEid,
                                'status' => $docStatus,
                            ]);
                            $modelClass::create($fields);
                        }
                    } else {
                        // NON VET
                        [$masterObj, $_] = $masterFks[$item['position_name']] ?? [null, null];
                        $fields = [
                            'position_name' => $item['position_name'],
                            'description' => $item['description'] ?? null,
                            'salary' => $item['salary'],
                            'position_allowance' => $item['position_allowance'] ?? 0,
                            'base_branch_id' => $baseBranchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'modify_eid' => $user->username,
                            'item_master_id' => $masterObj ? $masterObj->id : null,
                            'salary_general_ledger_code' => BudgetService::NON_VET_SALARY_GL_CODE,
                            'position_allowance_general_ledger_code' => BudgetService::NON_VET_ALLOWANCE_GL_CODE,
                            'budget_year' => $budgetYear,
                        ];

                        if ($itemIdInt) {
                            $obj = $existingMap->get($itemIdInt);
                            $obj->update($fields);
                        } else {
                            $fields = array_merge($fields, [
                                'document_no' => $docNo,
                                'create_date' => $createDate,
                                'create_eid' => $createEid,
                                'status' => $docStatus,
                            ]);
                            $obj = $modelClass::create($fields);
                        }
                        BudgetService::saveMonthlyData($obj, $docType, $item['monthly_data'] ?? []);
                    }
                }
            });

            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            Log::error("updateDocument error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function approveDocument(Request $request, string $docType, string $docNo): JsonResponse
    {
        $user = $request->user();
        if (! BudgetRoleService::isPrivilegedUserAccount($user, $request)) {
            return response()->json(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ในการอนุมัติเอกสาร']);
        }

        $modelClass = BudgetService::resolveModelClass($docType);
        if (! $modelClass) {
            return response()->json(['status' => 'error', 'message' => 'ประเภทเอกสารไม่ถูกต้อง']);
        }

        $items = $modelClass::where('document_no', $docNo)->get();
        if ($items->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'ไม่พบเอกสาร']);
        }

        $firstItem = $items->first();
        if ($firstItem->status !== self::STATUS_PENDING) {
            return response()->json(['status' => 'error', 'message' => 'สามารถอนุมัติได้เฉพาะเอกสารที่รออนุมัติเท่านั้น']);
        }

        try {
            DB::connection('pgsql')->transaction(function () use ($modelClass, $docNo, $user) {
                $modelClass::where('document_no', $docNo)->update([
                    'status' => self::STATUS_APPROVED,
                    'modify_eid' => $user->username,
                    'modify_date' => Carbon::now()->startOfSecond(),
                ]);
            });
            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancelDocument(Request $request, string $docType, string $docNo): JsonResponse
    {
        if (BudgetRoleService::isViewOnlyUser($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'ตำแหน่งของท่านมีสิทธิ์ดูข้อมูลเท่านั้น ไม่สามารถยกเลิกเอกสารได้',
            ], 403);
        }

        $reason = trim((string) ($request->json('cancel_reason') ?? $request->input('cancel_reason') ?? ''));
        if ($reason === '') {
            return response()->json(['status' => 'error', 'message' => 'กรุณาระบุเหตุผลการยกเลิก']);
        }

        $modelClass = BudgetService::resolveModelClass($docType);
        if (! $modelClass) {
            return response()->json(['status' => 'error', 'message' => 'ประเภทเอกสารไม่ถูกต้อง']);
        }

        $items = $modelClass::where('document_no', $docNo)->get();
        if ($items->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'ไม่พบเอกสาร']);
        }

        $firstItem = $items->first();
        $user = $request->user();
        $isPrivileged = BudgetRoleService::isPrivilegedUserAccount($user, $request);

        if (! $isPrivileged && (empty($firstItem->create_eid) || strtolower($firstItem->create_eid) !== strtolower($user->username))) {
            return response()->json(['status' => 'error', 'message' => 'คุณยกเลิกได้เฉพาะเอกสารที่ตัวเองสร้างเท่านั้น']);
        }

        if ($firstItem->status !== self::STATUS_PENDING) {
            return response()->json(['status' => 'error', 'message' => 'สามารถยกเลิกได้เฉพาะเอกสารที่รออนุมัติเท่านั้น']);
        }

        try {
            DB::connection('pgsql')->transaction(function () use ($modelClass, $docNo, $reason, $user) {
                $modelClass::where('document_no', $docNo)->update([
                    'status' => self::STATUS_CANCELLED,
                    'cancel_reason' => $reason,
                    'modify_eid' => $user->username,
                    'modify_date' => Carbon::now()->startOfSecond(),
                ]);
            });
            return response()->json(['status' => 'success']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        $rawDocType = strtoupper(trim((string) $request->query('doc_type', '')));
        $branchCode = trim((string) $request->query('branch_code', ''));
        $costCenter = trim((string) $request->query('cost_center', '')) ?: null;

        $mapped = self::DOC_TYPE_MODEL_MAP[$rawDocType] ?? null;
        if (! $mapped) {
            return response()->json(['status' => 'ok', 'is_duplicate' => false]);
        }

        [$modelClass, $displayDocType] = $mapped;
        $username = $request->user()->username;
        $settings = SystemSetting::current();
        $budgetYear = $settings->active_budget_year;

        $info = BudgetRoleService::getBranchInfo($request);
        $branches = $info['branches'] ?? [];

        if (! empty($info['needs_selection'])) {
            $branchId = in_array($branchCode, $branches, true) ? $branchCode : ($branchCode ?: null);
        } else {
            $branchId = ! empty($branches) ? $branches[0] : ($branchCode ?: null);
        }

        if (! $branchId) {
            return response()->json(['status' => 'ok', 'is_duplicate' => false]);
        }

        $duplicateResp = BudgetService::checkDuplicateBudgetDocument(
            $modelClass,
            $displayDocType,
            $username,
            $budgetYear,
            $branchId,
            $costCenter
        );

        if ($duplicateResp) {
            return $duplicateResp;
        }

        return response()->json(['status' => 'ok', 'is_duplicate' => false]);
    }

    public function getGeneralLedgers(Request $request): JsonResponse
    {
        $docType = trim((string) $request->query('doc_type', ''));
        $nameStartswith = $request->query('name_startswith');

        $allGls = BudgetService::getGeneralLedgersJson();
        $excludedGl = ['60300002', '61300004', '61300010'];
        $expenseGls = BudgetService::getGeneralLedgersJson($excludedGl, null, 'ค่าเสื่อม');
        $revenueGls = BudgetService::getGeneralLedgersJson(null, 'รายได้');

        if ($docType === 'GL Entry' || $docType === 'EXPENSE') {
            $targetGls = $expenseGls;
        } elseif ($docType === 'Revenue') {
            $targetGls = $revenueGls;
        } elseif ($nameStartswith !== null && $nameStartswith !== '') {
            $targetGls = BudgetService::getGeneralLedgersJson($excludedGl, $nameStartswith);
        } else {
            $targetGls = $expenseGls;
        }

        return response()->json([
            'status' => 'success',
            'data' => $targetGls,
            'general_ledgers' => $targetGls,
            'all_general_ledgers' => $allGls,
            'by_doc_type' => [
                'GL Entry' => $expenseGls,
                'EXPENSE' => $expenseGls,
                'Revenue' => $revenueGls,
                'Budget Plan' => $allGls,
            ],
        ]);
    }

    public function keepalive(Request $request): JsonResponse
    {
        $request->session()->put('last_activity', time());
        return response()->json([
            'status' => 'success',
            'authenticated' => true,
            'idle_timeout_seconds' => config('session.lifetime', 120) * 60,
        ]);
    }
}
