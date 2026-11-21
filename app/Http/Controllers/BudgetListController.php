<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategoryMaster;
use App\Models\BudgetItemMaster;
use App\Models\CostCenterMaster;
use App\Models\GlActualTemplateRow;
use App\Models\SystemSetting;
use App\Services\BudgetRoleService;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetListController extends Controller
{
    public function index(Request $request)
    {
        $categories = BudgetCategoryMaster::where('category_code', 'like', 'C%')
            ->orderBy('category_code')
            ->get();

        $getItems = function (int $catId, int $subCatId, bool $withPrice = false, bool $withAllowance = false) {
            $query = BudgetItemMaster::where('category_id', $catId)
                ->where('sub_category_id', $subCatId)
                ->orderBy('id');

            return $query->get()->map(function ($item) use ($withPrice, $withAllowance) {
                $row = [
                    'id' => $item->item_name,
                    'master_id' => $item->id,
                    'name' => $item->item_name,
                    'salary' => (float) $item->salary,
                ];
                if ($withPrice) {
                    $row['purchase_price'] = (float) $item->purchase_price;
                }
                if ($withAllowance) {
                    $row['position_allowance'] = (float) $item->position_allowance;
                }
                return $row;
            })->toArray();
        };

        $vetItems = $getItems(1, 1);
        $nonVetItems = $getItems(1, 2, false, true);
        $medItems = $getItems(2, 4, true);
        $compItems = $getItems(2, 5, true);
        $furnitureItems = $getItems(2, 6, true);
        $toolsItems = $getItems(2, 7, true);
        $carItems = $getItems(2, 8, true);
        $renovateItems = $getItems(2, 9, true);
        $softwareItems = $getItems(2, 10, true);

        $costCenters = BudgetService::getCostCentersJson();
        $glTemplateRows = GlActualTemplateRow::orderBy('row_order')->get()->toArray();
        $allBranches = \App\Services\DashboardService::getAllBranches();

        $user = Auth::user();
        $isViewOnly = BudgetRoleService::isViewOnlyUser($request);
        $isPrivileged = BudgetRoleService::isPrivilegedUserAccount($user, $request);

        return view('budget.budget_list', array_merge([
            'categories' => $categories,
            'items_vet_json' => $vetItems,
            'items_non_vet_json' => $nonVetItems,
            'items_med_json' => $medItems,
            'items_comp_json' => $compItems,
            'items_furniture_json' => $furnitureItems,
            'items_tools_json' => $toolsItems,
            'items_car_json' => $carItems,
            'items_renovate_json' => $renovateItems,
            'items_software_json' => $softwareItems,
            'cost_centers_json' => $costCenters,
            'glat_template_rows_json' => $glTemplateRows,
            'glat_branches_json' => $allBranches,
            'is_view_only' => $isViewOnly,
            'is_privileged' => $isPrivileged,
        ], BudgetRoleService::getBranchSelectionContext($request)));
    }
}
