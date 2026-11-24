<?php

namespace App\Http\Controllers;

use App\Models\BudgetItemMaster;
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
use App\Services\BudgetRoleService;
use App\Services\BudgetService;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BudgetCreateController extends Controller
{
    public const FIX_ASSET_MIN_PURCHASE_PRICE = 5000;

    public const DOC_TYPE_ITEM_COLUMN_NAMES = [
        'Medical Equipment' => 'เครื่องมือแพทย์',
        'Computer Equipment' => 'อุปกรณ์คอมพิวเตอร์',
        'Furniture' => 'เฟอร์นิเจอร์',
        'Tools & Equipment' => 'เครื่องมือและอุปกรณ์',
        'CAR' => 'ยานพาหนะ',
        'Renovate' => 'งานปรับปรุง',
        'Software' => 'ซอฟต์แวร์',
    ];

    public function vetManpower(Request $request): View|JsonResponse|RedirectResponse
    {
        if (BudgetRoleService::isExecutiveViewOnlyUser($request) || BudgetRoleService::isViewOnlyUser($request)) {
            if ($request->isMethod('POST')) {
                return response()->json(['status' => 'error', 'message' => 'ตำแหน่งของท่านมีสิทธิ์ดูข้อมูลเท่านั้น ไม่สามารถสร้างเอกสารได้'], 403);
            }
            return redirect()->route('budget_list');
        }

        if ($request->isMethod('POST')) {
            try {
                $data = $request->json()->all();
                if (empty($data) || empty($data[0]['cost_center_name'])) {
                    return response()->json(['status' => 'error', 'message' => 'กรุณาเลือก Cost Center']);
                }

                $err = BudgetService::validateManpowerHeadcountIntegers($data, 'position_name');
                if ($err) {
                    return response()->json(['status' => 'error', 'message' => $err]);
                }
                $err = BudgetService::validateNonzeroMonthlyRows($data, 'position_name');
                if ($err) {
                    return response()->json(['status' => 'error', 'message' => $err]);
                }

                $positions = array_column($data, 'position_name');
                $masterFks = BudgetService::getMasterFksBulk($positions, 1, 1);
                foreach ($data as $item) {
                    $pos = trim($item['position_name'] ?? '');
                    if (! $pos || ! isset($masterFks[$pos])) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "ไม่อนุญาตให้พิมพ์ระบุข้อความเอง กรุณาเลือกตำแหน่งจากรายการ Dropdown เท่านั้น (ตำแหน่ง: \"{$pos}\" ไม่มีอยู่ในระบบ)",
                        ]);
                    }
                }

                $canonicalCc = BudgetService::resolveCanonicalCostCenterName($data[0]['cost_center_name']);
                foreach ($data as &$it) {
                    $it['cost_center_name'] = $canonicalCc;
                }
                unset($it);

                $user = $request->user();
                [$branchId, $deptId] = BudgetRoleService::resolveBranchForCreate($request, $data, 'VET');
                $budgetYear = SystemSetting::current()->active_budget_year;

                $duplicateResp = BudgetService::checkDuplicateBudgetDocument(
                    VetManpower::class,
                    'VET',
                    $user->username,
                    $budgetYear,
                    $branchId,
                    $canonicalCc
                );
                if ($duplicateResp) {
                    return $duplicateResp;
                }

                $docNo = BudgetService::generateDocumentNo('VET');

                DB::connection('pgsql')->transaction(function () use ($data, $user, $branchId, $docNo, $masterFks, $budgetYear) {
                    foreach ($data as $item) {
                        [$masterObj, $glCode] = $masterFks[$item['position_name']] ?? [null, null];
                        $obj = VetManpower::create([
                            'position_name' => $item['position_name'],
                            'description' => $item['description'] ?? null,
                            'salary' => $item['salary'],
                            'create_eid' => $user->username,
                            'base_branch_id' => $branchId,
                            'department_id' => null,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'document_no' => $docNo,
                            'item_master_id' => $masterObj ? $masterObj->id : null,
                            'general_ledger_code' => $glCode ?: BudgetService::VET_CUSTOM_POSITION_GL_CODE,
                            'budget_year' => $budgetYear,
                            'status' => VetManpower::STATUS_PENDING,
                        ]);
                        BudgetService::saveMonthlyData($obj, 'VET', $item['monthly_data'] ?? []);
                    }
                });

                return response()->json(['status' => 'success']);
            } catch (\Throwable $e) {
                Log::error("vetManpower error: " . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $items = BudgetItemMaster::where('category_id', 1)
            ->where('sub_category_id', 1)
            ->orderBy('id')
            ->get(['id', 'item_name', 'salary']);

        $itemsList = $items->map(function ($item) {
            return [
                'id' => $item->item_name,
                'master_id' => $item->id,
                'name' => $item->item_name,
                'salary' => (float) $item->salary,
            ];
        })->toArray();

        return view('budget.budget_add', array_merge([
            'items_json' => $itemsList,
            'cost_centers_json' => BudgetService::getCostCentersJson(),
        ], BudgetRoleService::getBranchSelectionContext($request)));
    }

    public function nonVetManpower(Request $request): View|JsonResponse|RedirectResponse
    {
        if (BudgetRoleService::isExecutiveViewOnlyUser($request) || BudgetRoleService::isViewOnlyUser($request)) {
            if ($request->isMethod('POST')) {
                return response()->json(['status' => 'error', 'message' => 'ตำแหน่งของท่านมีสิทธิ์ดูข้อมูลเท่านั้น ไม่สามารถสร้างเอกสารได้'], 403);
            }
            return redirect()->route('budget_list');
        }

        if ($request->isMethod('POST')) {
            try {
                $data = $request->json()->all();
                if (empty($data) || empty($data[0]['cost_center_name'])) {
                    return response()->json(['status' => 'error', 'message' => 'กรุณาเลือก Cost Center']);
                }

                $err = BudgetService::validateManpowerHeadcountIntegers($data, 'position_name');
                if ($err) {
                    return response()->json(['status' => 'error', 'message' => $err]);
                }
                $err = BudgetService::validateNonzeroMonthlyRows($data, 'position_name');
                if ($err) {
                    return response()->json(['status' => 'error', 'message' => $err]);
                }

                $positions = array_column($data, 'position_name');
                $masterFks = BudgetService::getMasterFksBulk($positions, 1, 2);
                foreach ($data as $item) {
                    $pos = trim($item['position_name'] ?? '');
                    if (! $pos || ! isset($masterFks[$pos])) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "ไม่อนุญาตให้พิมพ์ระบุข้อความเอง กรุณาเลือกตำแหน่งจากรายการ Dropdown เท่านั้น (ตำแหน่ง: \"{$pos}\" ไม่มีอยู่ในระบบ)",
                        ]);
                    }
                }

                $canonicalCc = BudgetService::resolveCanonicalCostCenterName($data[0]['cost_center_name']);
                foreach ($data as &$it) {
                    $it['cost_center_name'] = $canonicalCc;
                }
                unset($it);

                $user = $request->user();
                [$branchId, $deptId] = BudgetRoleService::resolveBranchForCreate($request, $data, 'NON VET');
                $budgetYear = SystemSetting::current()->active_budget_year;

                $duplicateResp = BudgetService::checkDuplicateBudgetDocument(
                    NonVetManpower::class,
                    'NON VET',
                    $user->username,
                    $budgetYear,
                    $branchId,
                    $canonicalCc
                );
                if ($duplicateResp) {
                    return $duplicateResp;
                }

                $docNo = BudgetService::generateDocumentNo('NON VET');

                DB::connection('pgsql')->transaction(function () use ($data, $user, $branchId, $deptId, $docNo, $masterFks, $budgetYear) {
                    foreach ($data as $item) {
                        [$masterObj, $glCode] = $masterFks[$item['position_name']] ?? [null, null];
                        $obj = NonVetManpower::create([
                            'position_name' => $item['position_name'],
                            'description' => $item['description'] ?? null,
                            'salary' => $item['salary'],
                            'position_allowance' => $item['position_allowance'] ?? 0,
                            'create_eid' => $user->username,
                            'base_branch_id' => $branchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'document_no' => $docNo,
                            'item_master_id' => $masterObj ? $masterObj->id : null,
                            'salary_general_ledger_code' => BudgetService::NON_VET_SALARY_GL_CODE,
                            'position_allowance_general_ledger_code' => BudgetService::NON_VET_ALLOWANCE_GL_CODE,
                            'budget_year' => $budgetYear,
                            'status' => NonVetManpower::STATUS_PENDING,
                        ]);
                        BudgetService::saveMonthlyData($obj, 'NON VET', $item['monthly_data'] ?? []);
                    }
                });

                return response()->json(['status' => 'success']);
            } catch (\Throwable $e) {
                Log::error("nonVetManpower error: " . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $items = BudgetItemMaster::where('category_id', 1)
            ->where('sub_category_id', 2)
            ->orderBy('id')
            ->get(['id', 'item_name', 'salary', 'position_allowance']);

        $itemsList = $items->map(function ($item) {
            return [
                'id' => $item->item_name,
                'master_id' => $item->id,
                'name' => $item->item_name,
                'salary' => (float) $item->salary,
                'position_allowance' => (float) ($item->position_allowance ?? 0),
            ];
        })->toArray();

        return view('budget.budget_add_non_vet', array_merge([
            'items_json' => $itemsList,
            'cost_centers_json' => BudgetService::getCostCentersJson(),
        ], BudgetRoleService::getBranchSelectionContext($request)));
    }

    public function positionAdjustment(Request $request): View|JsonResponse|RedirectResponse
    {
        if (BudgetRoleService::isExecutiveViewOnlyUser($request) || BudgetRoleService::isViewOnlyUser($request)) {
            if ($request->isMethod('POST')) {
                return response()->json(['status' => 'error', 'message' => 'ตำแหน่งของท่านมีสิทธิ์ดูข้อมูลเท่านั้น ไม่สามารถสร้างเอกสารได้'], 403);
            }
            return redirect()->route('budget_list');
        }

        if ($request->isMethod('POST')) {
            try {
                $data = $request->json()->all();
                if (empty($data) || empty($data[0]['cost_center_name'])) {
                    return response()->json(['status' => 'error', 'message' => 'กรุณาเลือก Cost Center']);
                }

                $err = BudgetService::validateManpowerHeadcountIntegers($data, 'new_position_name');
                if ($err) {
                    return response()->json(['status' => 'error', 'message' => $err]);
                }
                $err = BudgetService::validateNonzeroMonthlyRows($data, 'new_position_name');
                if ($err) {
                    return response()->json(['status' => 'error', 'message' => $err]);
                }

                $allPositions = array_filter(array_merge(array_column($data, 'old_position_name'), array_column($data, 'new_position_name')));
                $vetFks = BudgetService::getMasterFksBulk($allPositions, 1, 1);
                $nonVetFks = BudgetService::getMasterFksBulk($allPositions, 1, 2);

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

                $canonicalCc = BudgetService::resolveCanonicalCostCenterName($data[0]['cost_center_name']);
                foreach ($data as &$it) {
                    $it['cost_center_name'] = $canonicalCc;
                }
                unset($it);

                $user = $request->user();
                [$branchId, $deptId] = BudgetRoleService::resolveBranchForCreate($request, $data, 'Position Adjustment');
                $budgetYear = SystemSetting::current()->active_budget_year;

                $duplicateResp = BudgetService::checkDuplicateBudgetDocument(
                    PositionAdjustment::class,
                    'Position Adjustment',
                    $user->username,
                    $budgetYear,
                    $branchId,
                    $canonicalCc
                );
                if ($duplicateResp) {
                    return $duplicateResp;
                }

                $docNo = BudgetService::generateDocumentNo('Position Adjustment');

                DB::connection('pgsql')->transaction(function () use ($data, $user, $branchId, $deptId, $docNo, $vetFks, $nonVetFks, $budgetYear) {
                    foreach ($data as $item) {
                        $adjType = trim($item['adjustment_type'] ?? '');
                        $allowedFks = ($adjType === 'VET') ? $vetFks : $nonVetFks;
                        [$masterObj, $glCode] = $allowedFks[$item['new_position_name']] ?? [null, null];
                        [$salaryGl, $allowanceGl] = BudgetService::resolveAdjustmentGlCodes($adjType);

                        $obj = PositionAdjustment::create([
                            'adjustment_type' => $adjType ?: null,
                            'old_position_name' => $item['old_position_name'],
                            'old_description' => $item['old_description'] ?? null,
                            'new_position_name' => $item['new_position_name'],
                            'new_description' => $item['new_description'] ?? null,
                            'old_salary' => $item['old_salary'],
                            'old_allowance' => ($adjType === 'VET') ? 0 : ($item['old_allowance'] ?? 0),
                            'new_salary' => $item['new_salary'],
                            'new_allowance' => ($adjType === 'VET') ? 0 : ($item['new_allowance'] ?? 0),
                            'create_eid' => $user->username,
                            'base_branch_id' => $branchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'document_no' => $docNo,
                            'item_master_id' => $masterObj ? $masterObj->id : null,
                            'salary_general_ledger_code' => $salaryGl,
                            'allowance_general_ledger_code' => $allowanceGl,
                            'budget_year' => $budgetYear,
                            'status' => PositionAdjustment::STATUS_PENDING,
                        ]);
                        BudgetService::saveMonthlyData($obj, 'Position Adjustment', $item['monthly_data'] ?? []);
                    }
                });

                return response()->json(['status' => 'success']);
            } catch (\Throwable $e) {
                Log::error("positionAdjustment error: " . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $itemsList = [];
        $vetItems = BudgetItemMaster::where('category_id', 1)->where('sub_category_id', 1)->orderBy('id')->get(['id', 'item_name', 'salary']);
        foreach ($vetItems as $item) {
            $itemsList[] = [
                'id' => $item->item_name,
                'master_id' => $item->id,
                'name' => $item->item_name,
                'group' => 'VET Manpower',
                'salary' => (float) $item->salary,
                'position_allowance' => 0,
            ];
        }

        $nonVetItems = BudgetItemMaster::where('category_id', 1)->where('sub_category_id', 2)->orderBy('id')->get(['id', 'item_name', 'salary', 'position_allowance']);
        foreach ($nonVetItems as $item) {
            $itemsList[] = [
                'id' => $item->item_name,
                'master_id' => $item->id,
                'name' => $item->item_name,
                'group' => 'NON VET Manpower',
                'salary' => (float) $item->salary,
                'position_allowance' => (float) ($item->position_allowance ?? 0),
            ];
        }

        return view('budget.budget_add_adjustment', array_merge([
            'items_json' => $itemsList,
            'cost_centers_json' => BudgetService::getCostCentersJson(),
        ], BudgetRoleService::getBranchSelectionContext($request)));
    }

    private function handleEquipmentView(Request $request, string $modelClass, string $docType, string $viewName, int $subCategoryId): View|JsonResponse|RedirectResponse
    {
        if (BudgetRoleService::isViewOnlyUser($request)) {
            if ($request->isMethod('POST')) {
                return response()->json(['status' => 'error', 'message' => 'ตำแหน่งของท่านมีสิทธิ์ดูข้อมูลเท่านั้น ไม่สามารถสร้างเอกสารได้'], 403);
            }
            return redirect()->route('budget_list');
        }

        if ($request->isMethod('POST')) {
            try {
                $data = $request->json()->all();
                if (empty($data) || empty($data[0]['cost_center_name'])) {
                    return response()->json(['status' => 'error', 'message' => 'กรุณาเลือก Cost Center']);
                }

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

                foreach ($data as $item) {
                    $price = (float) ($item['purchase_price'] ?? 0);
                    if ($price < self::FIX_ASSET_MIN_PURCHASE_PRICE) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "ราคาซื้อในหมวดสินทรัพย์ถาวร (Fix Asset) ต้องมีมูลค่าตั้งแต่ 5,000 บาทขึ้นไป (รายการ: " . ($item['item_name'] ?? '') . ")",
                        ]);
                    }
                }

                $masterFks = BudgetService::getMasterFksBulk(
                    array_column($data, 'item_name'),
                    2,
                    $subCategoryId
                );

                foreach ($data as $item) {
                    $itName = trim($item['item_name'] ?? '');
                    if (! $itName || ! isset($masterFks[$itName])) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "ไม่อนุญาตให้พิมพ์ระบุข้อความเอง กรุณาเลือกรายการจาก Dropdown เท่านั้น (รายการ: \"{$itName}\" ไม่มีอยู่ในระบบ)",
                        ]);
                    }
                }

                $canonicalCc = BudgetService::resolveCanonicalCostCenterName($data[0]['cost_center_name']);
                foreach ($data as &$it) {
                    $it['cost_center_name'] = $canonicalCc;
                }
                unset($it);

                $user = $request->user();
                [$branchId, $deptId] = BudgetRoleService::resolveBranchForCreate($request, $data, $docType);
                $budgetYear = SystemSetting::current()->active_budget_year;

                $duplicateResp = BudgetService::checkDuplicateBudgetDocument(
                    $modelClass,
                    $docType,
                    $user->username,
                    $budgetYear,
                    $branchId,
                    $canonicalCc
                );
                if ($duplicateResp) {
                    return $duplicateResp;
                }

                $docNo = BudgetService::generateDocumentNo($docType);

                DB::connection('pgsql')->transaction(function () use ($data, $modelClass, $docType, $user, $branchId, $deptId, $docNo, $masterFks, $budgetYear) {
                    foreach ($data as $item) {
                        [$masterObj, $glCode] = $masterFks[$item['item_name']] ?? [null, null];
                        $glCode = BudgetService::resolveEquipmentGlCode($docType, $item['purchase_price'], $glCode);
                        $obj = $modelClass::create([
                            'item_name' => $item['item_name'],
                            'description' => $item['description'] ?? null,
                            'purchase_price' => $item['purchase_price'],
                            'create_eid' => $user->username,
                            'base_branch_id' => $branchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'document_no' => $docNo,
                            'item_master_id' => $masterObj ? $masterObj->id : null,
                            'general_ledger_code' => $glCode,
                            'budget_year' => $budgetYear,
                            'status' => $modelClass::STATUS_PENDING,
                        ]);
                        BudgetService::saveMonthlyData($obj, $docType, $item['monthly_data'] ?? []);
                    }
                });

                return response()->json(['status' => 'success']);
            } catch (\Throwable $e) {
                Log::error("Equipment save error ({$docType}): " . $e->getMessage());
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $items = BudgetItemMaster::where('category_id', 2)
            ->where('sub_category_id', $subCategoryId)
            ->orderBy('id')
            ->get(['id', 'item_name', 'purchase_price']);

        $itemsList = $items->map(function ($item) {
            return [
                'master_id' => $item->id,
                'name' => $item->item_name,
                'purchase_price' => (float) ($item->purchase_price ?? 0),
            ];
        })->toArray();

        $sampleGlCode = BudgetService::resolveEquipmentGlCode($docType, 100000);
        $glMaster = GeneralLedgerMaster::where('gl_code', $sampleGlCode)->first();
        $glDepreciation = ($glMaster && (float) $glMaster->depreciation > 0) ? (float) $glMaster->depreciation : 5.0;

        return view($viewName, array_merge([
            'items_json' => $itemsList,
            'cost_centers_json' => BudgetService::getCostCentersJson(),
            'doc_type' => $docType,
            'gl_depreciation' => $glDepreciation,
        ], BudgetRoleService::getBranchSelectionContext($request)));
    }

    public function medicalEquipment(Request $request): View|JsonResponse|RedirectResponse
    {
        return $this->handleEquipmentView($request, MedicalEquipment::class, 'Medical Equipment', 'budget.budget_add_medical_equipment', 4);
    }

    public function computerEquipment(Request $request): View|JsonResponse|RedirectResponse
    {
        return $this->handleEquipmentView($request, ComputerEquipment::class, 'Computer Equipment', 'budget.budget_add_computer_equipment', 5);
    }

    public function furniture(Request $request): View|JsonResponse|RedirectResponse
    {
        return $this->handleEquipmentView($request, Furniture::class, 'Furniture', 'budget.budget_add_furniture', 6);
    }

    public function toolsEquipment(Request $request): View|JsonResponse|RedirectResponse
    {
        return $this->handleEquipmentView($request, ToolsEquipment::class, 'Tools & Equipment', 'budget.budget_add_tools_equipment', 7);
    }

    public function car(Request $request): View|JsonResponse|RedirectResponse
    {
        return $this->handleEquipmentView($request, Car::class, 'CAR', 'budget.budget_add_car', 8);
    }

    public function renovate(Request $request): View|JsonResponse|RedirectResponse
    {
        return $this->handleEquipmentView($request, Renovate::class, 'Renovate', 'budget.budget_add_renovate', 9);
    }

    public function software(Request $request): View|JsonResponse|RedirectResponse
    {
        return $this->handleEquipmentView($request, Software::class, 'Software', 'budget.budget_add_software', 10);
    }

    public function glEntry(Request $request): View|JsonResponse|RedirectResponse
    {
        if (BudgetRoleService::isExecutiveViewOnlyUser($request) || BudgetRoleService::isViewOnlyUser($request)) {
            if ($request->isMethod('POST')) {
                return response()->json(['status' => 'error', 'message' => 'ตำแหน่งของท่านมีสิทธิ์ดูข้อมูลเท่านั้น ไม่สามารถสร้างเอกสารได้'], 403);
            }
            return redirect()->route('budget_list');
        }

        if ($request->isMethod('POST')) {
            try {
                $data = $request->json()->all();
                if (empty($data) || empty($data[0]['cost_center_name'])) {
                    return response()->json(['status' => 'error', 'message' => 'กรุณาเลือก Cost Center']);
                }

                $canonicalCc = BudgetService::resolveCanonicalCostCenterName($data[0]['cost_center_name']);
                foreach ($data as &$it) {
                    $it['cost_center_name'] = $canonicalCc;
                }
                unset($it);

                $user = $request->user();
                [$branchId, $deptId] = BudgetRoleService::resolveBranchForCreate($request, $data, 'GL Entry');
                $budgetYear = SystemSetting::current()->active_budget_year;

                $duplicateResp = BudgetService::checkDuplicateBudgetDocument(
                    GlEntry::class,
                    'GL Entry',
                    $user->username,
                    $budgetYear,
                    $branchId,
                    $canonicalCc
                );
                if ($duplicateResp) {
                    return $duplicateResp;
                }

                $docNo = BudgetService::generateDocumentNo('GL Entry');
                $glMasterFields = BudgetService::getGlMasterFieldsBulk(array_column($data, 'general_ledger_code'));

                DB::connection('pgsql')->transaction(function () use ($data, $user, $branchId, $deptId, $docNo, $glMasterFields, $budgetYear) {
                    foreach ($data as $item) {
                        [$proportion, $depreciation] = $glMasterFields[$item['general_ledger_code']] ?? [0, 0];
                        $obj = GlEntry::create([
                            'general_ledger_code' => $item['general_ledger_code'],
                            'proportion' => $proportion,
                            'depreciation' => $depreciation,
                            'detail_note' => $item['detail_note'] ?? null,
                            'create_eid' => $user->username,
                            'base_branch_id' => $branchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'document_no' => $docNo,
                            'budget_year' => $budgetYear,
                            'status' => GlEntry::STATUS_PENDING,
                        ]);
                        BudgetService::saveGlEntryMonthlyData($obj, $item['monthly_data'] ?? []);
                    }
                });

                return response()->json(['status' => 'success']);
            } catch (\Throwable $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        return view('budget.budget_add_gl_entry', array_merge([
            'general_ledgers_json' => BudgetService::getGeneralLedgersJson(null, null, 'ค่าเสื่อม'),
            'cost_centers_json' => BudgetService::getCostCentersJson(),
        ], BudgetRoleService::getBranchSelectionContext($request)));
    }

    public function revenue(Request $request): View|JsonResponse|RedirectResponse
    {
        if (BudgetRoleService::isExecutiveViewOnlyUser($request) || BudgetRoleService::isViewOnlyUser($request)) {
            if ($request->isMethod('POST')) {
                return response()->json(['status' => 'error', 'message' => 'ตำแหน่งของท่านมีสิทธิ์ดูข้อมูลเท่านั้น ไม่สามารถสร้างเอกสารได้'], 403);
            }
            return redirect()->route('budget_list');
        }

        if ($request->isMethod('POST')) {
            try {
                $data = $request->json()->all();
                if (empty($data) || empty($data[0]['cost_center_name'])) {
                    return response()->json(['status' => 'error', 'message' => 'กรุณาเลือก Cost Center']);
                }

                $canonicalCc = BudgetService::resolveCanonicalCostCenterName($data[0]['cost_center_name']);
                foreach ($data as &$it) {
                    $it['cost_center_name'] = $canonicalCc;
                }
                unset($it);

                $user = $request->user();
                [$branchId, $deptId] = BudgetRoleService::resolveBranchForCreate($request, $data, 'Revenue');
                $budgetYear = SystemSetting::current()->active_budget_year;

                $duplicateResp = BudgetService::checkDuplicateBudgetDocument(
                    Revenue::class,
                    'Revenue',
                    $user->username,
                    $budgetYear,
                    $branchId,
                    $canonicalCc
                );
                if ($duplicateResp) {
                    return $duplicateResp;
                }

                $docNo = BudgetService::generateDocumentNo('Revenue');
                $glMasterFields = BudgetService::getGlMasterFieldsBulk(array_column($data, 'general_ledger_code'));

                DB::connection('pgsql')->transaction(function () use ($data, $user, $branchId, $deptId, $docNo, $glMasterFields, $budgetYear) {
                    foreach ($data as $item) {
                        [$proportion, $depreciation] = $glMasterFields[$item['general_ledger_code']] ?? [0, 0];
                        $obj = Revenue::create([
                            'general_ledger_code' => $item['general_ledger_code'],
                            'proportion' => $proportion,
                            'depreciation' => $depreciation,
                            'detail_note' => $item['detail_note'] ?? null,
                            'create_eid' => $user->username,
                            'base_branch_id' => $branchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'document_no' => $docNo,
                            'budget_year' => $budgetYear,
                            'status' => Revenue::STATUS_PENDING,
                        ]);
                        BudgetService::saveRevenueMonthlyData($obj, $item['monthly_data'] ?? []);
                    }
                });

                return response()->json(['status' => 'success']);
            } catch (\Throwable $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        return view('budget.budget_add_revenue', array_merge([
            'general_ledgers_json' => BudgetService::getGeneralLedgersJson(null, 'รายได้'),
            'cost_centers_json' => BudgetService::getCostCentersJson(),
        ], BudgetRoleService::getBranchSelectionContext($request)));
    }

    public function budgetPlan(Request $request): View|JsonResponse|RedirectResponse
    {
        if (BudgetRoleService::isExecutiveViewOnlyUser($request) || BudgetRoleService::isViewOnlyUser($request)) {
            if ($request->isMethod('POST')) {
                return response()->json(['status' => 'error', 'message' => 'ตำแหน่งของท่านมีสิทธิ์ดูข้อมูลเท่านั้น ไม่สามารถสร้างเอกสารได้'], 403);
            }
            return redirect()->route('budget_list');
        }

        if ($request->isMethod('POST')) {
            try {
                $data = $request->json()->all();
                if (empty($data) || empty($data[0]['cost_center_name'])) {
                    return response()->json(['status' => 'error', 'message' => 'กรุณาเลือก Cost Center']);
                }

                $canonicalCc = BudgetService::resolveCanonicalCostCenterName($data[0]['cost_center_name']);
                foreach ($data as &$it) {
                    $it['cost_center_name'] = $canonicalCc;
                }
                unset($it);

                $user = $request->user();
                [$branchId, $deptId] = BudgetRoleService::resolveBranchForCreate($request, $data, 'Budget Plan');
                $budgetYear = SystemSetting::current()->active_budget_year;

                $duplicateResp = BudgetService::checkDuplicateBudgetDocument(
                    BudgetPlanItem::class,
                    'Budget Plan',
                    $user->username,
                    $budgetYear,
                    $branchId,
                    $canonicalCc
                );
                if ($duplicateResp) {
                    return $duplicateResp;
                }

                $docNo = BudgetService::generateDocumentNo('Budget Plan');

                DB::connection('pgsql')->transaction(function () use ($data, $user, $branchId, $deptId, $docNo, $budgetYear) {
                    foreach ($data as $item) {
                        $obj = BudgetPlanItem::create([
                            'budget_category' => $item['budget_category'] ?? null,
                            'item_code' => $item['item_code'] ?? null,
                            'general_ledger_code' => $item['general_ledger_code'] ?? null,
                            'description' => $item['description'] ?? null,
                            'create_eid' => $user->username,
                            'base_branch_id' => $branchId,
                            'department_id' => $deptId,
                            'cost_center_name' => $item['cost_center_name'] ?? null,
                            'document_no' => $docNo,
                            'budget_year' => $budgetYear,
                            'status' => BudgetPlanItem::STATUS_PENDING,
                        ]);
                        BudgetService::saveBudgetPlanMonthlyData($obj, $item['monthly_data'] ?? []);
                    }
                });

                return response()->json(['status' => 'success']);
            } catch (\Throwable $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        return view('budget.budget_add_budget_plan', array_merge([
            'general_ledgers_json' => BudgetService::getGeneralLedgersJson(),
            'cost_centers_json' => BudgetService::getCostCentersJson(),
        ], BudgetRoleService::getBranchSelectionContext($request)));
    }

    public function glActualTemplate(Request $request): View|JsonResponse|RedirectResponse
    {
        if (BudgetRoleService::isExecutiveViewOnlyUser($request) || ! BudgetRoleService::canAccessGlActualTemplate($request)) {
            if ($request->isMethod('POST')) {
                return response()->json(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ในการเข้าถึงฟังก์ชันนี้'], 403);
            }
            return redirect()->route('budget_list');
        }

        if ($request->isMethod('POST')) {
            try {
                $data = $request->json()->all();
                if (empty($data)) {
                    return response()->json(['status' => 'error', 'message' => 'ไม่มีข้อมูลสำหรับบันทึก']);
                }

                $branchCode = $data[0]['branch_code'] ?? null;
                $validBranches = array_column(BudgetRoleService::GL_ACTUAL_TEMPLATE_BRANCHES, 0);
                if (! in_array($branchCode, $validBranches, true)) {
                    return response()->json(['status' => 'error', 'message' => 'กรุณาเลือกสาขาให้ถูกต้อง']);
                }

                $user = $request->user();
                $budgetYear = SystemSetting::current()->active_budget_year;

                $duplicateResp = BudgetService::checkDuplicateBudgetDocument(
                    GlActualTemplate::class,
                    'GL Actual Template',
                    $user->username,
                    $budgetYear,
                    $branchCode,
                    null
                );
                if ($duplicateResp) {
                    return $duplicateResp;
                }

                $docNo = BudgetService::generateDocumentNo('GL Actual Template');

                DB::connection('pgsql')->transaction(function () use ($data, $user, $branchCode, $docNo, $budgetYear) {
                    foreach ($data as $item) {
                        GlActualTemplate::create([
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
                            'create_eid' => $user->username,
                            'base_branch_id' => $branchCode,
                            'budget_year' => $budgetYear,
                            'actual_month_count' => $item['actual_month_count'] ?? null,
                            'growth_pct_1' => $item['growth_pct_1'] ?? 0,
                            'growth_pct_2' => $item['growth_pct_2'] ?? 0,
                            'growth_pct_3' => $item['growth_pct_3'] ?? 0,
                            'document_no' => $docNo,
                            'status' => GlActualTemplate::STATUS_PENDING,
                        ]);
                    }
                });

                return response()->json(['status' => 'success']);
            } catch (\Throwable $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
        }

        $templateRows = GlActualTemplateRow::orderBy('row_order')->get(['id', 'row_order', 'code', 'label', 'row_type']);
        $templateRowsJson = $templateRows->map(function ($r) {
            return [
                'template_row_id' => $r->id,
                'row_order' => $r->row_order,
                'code' => $r->code,
                'label' => $r->label,
                'row_type' => $r->row_type,
            ];
        })->toArray();

        return view('budget.budget_add_gl_actual_template', [
            'template_rows_json' => $templateRowsJson,
            'branches_json' => DashboardService::getAllBranches(),
            'actual_year' => Carbon::now()->year,
        ]);
    }
}
