<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\BudgetRoleService;
use App\Services\DashboardService;
use App\Services\XlsxExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|Response
    {
        if (! BudgetRoleService::canAccessDashboard($request)) {
            abort(403, 'ไม่มีสิทธิ์ในการเข้าถึงแดชบอร์ด');
        }

        $allowedBranches = DashboardService::getAllowedBranchesForDashboard($request);
        $canViewAll = DashboardService::canViewAllBranchesDashboard($request);
        $settings = SystemSetting::current();
        $activeYear = (int) $settings->active_budget_year;
        $budgetYears = DashboardService::getDashboardBudgetYears();

        $requestedYear = $request->query('year');
        $selectedYear = $activeYear;
        if ($requestedYear && is_numeric($requestedYear)) {
            $parsed = (int) $requestedYear;
            if (in_array($parsed, $budgetYears, true)) {
                $selectedYear = $parsed;
            }
        }

        $allowedCodes = array_column($allowedBranches, 'code');
        $requestedBranch = strtoupper(trim((string) $request->query('branch', '')));
        $selectedBranch = ($requestedBranch && in_array($requestedBranch, $allowedCodes, true)) ? $requestedBranch : '';

        $allowedCostCenters = BudgetRoleService::getAllowedCostCentersForDashboard($request);
        $selectedCostCenter = (string) $request->query('cost_center', '');
        $costCenterNames = array_column($allowedCostCenters, 'name');
        if ($selectedCostCenter !== '' && ! in_array($selectedCostCenter, $costCenterNames, true) && strtoupper($selectedCostCenter) !== 'ALL') {
            $selectedCostCenter = '';
        }
        if ($selectedCostCenter === '' && count($allowedCostCenters) === 1) {
            $selectedCostCenter = $allowedCostCenters[0]['name'];
        }

        return response()
            ->view('budget.dashboard', [
                'allowed_branches' => $allowedBranches,
                'selected_branch' => $selectedBranch,
                'is_single_branch_locked' => count($allowedBranches) === 1,
                'can_view_all_branches' => $canViewAll,
                'budget_years' => $budgetYears,
                'selected_year' => $selectedYear,
                'allowed_cost_centers' => $allowedCostCenters,
                'selected_cost_center' => $selectedCostCenter,
            ])
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    private function resolveDashboardPayloadOrError(Request $request): array
    {
        if (! BudgetRoleService::canAccessDashboard($request)) {
            return [null, null, response()->json(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ในการเข้าถึงแดชบอร์ด'], 403)];
        }

        [$branchDict, $errMsg, $errStatus] = DashboardService::validateDashboardBranch($request, $request->query('branch'));
        if ($errMsg) {
            return [null, null, response()->json(['status' => 'error', 'message' => $errMsg], $errStatus)];
        }

        $rawYear = $request->query('year');
        if ($rawYear === null || trim((string) $rawYear) === '') {
            $budgetYear = (int) SystemSetting::current()->active_budget_year;
        } else {
            if (! is_numeric($rawYear)) {
                return [null, null, response()->json(['status' => 'error', 'message' => 'ปีงบประมาณไม่ถูกต้อง'], 400)];
            }
            $budgetYear = (int) $rawYear;
        }

        $allowedCostCenters = BudgetRoleService::getAllowedCostCentersForDashboard($request);
        $rawCostCenter = trim((string) $request->query('cost_center', ''));
        $costCenter = null;
        if ($rawCostCenter !== '' && strtoupper($rawCostCenter) !== 'ALL') {
            $found = false;
            foreach ($allowedCostCenters as $cc) {
                if (strcasecmp($cc['name'], $rawCostCenter) === 0 || strcasecmp($cc['code'], $rawCostCenter) === 0) {
                    $found = true;
                    $costCenter = $cc['name'];
                    break;
                }
            }
            if (! $found) {
                return [null, null, response()->json(['status' => 'error', 'message' => 'ท่านไม่มีสิทธิ์เข้าดูข้อมูล Cost Center นี้'], 403)];
            }
        }

        $payload = DashboardService::buildDashboardData($branchDict, $budgetYear, $costCenter);
        return [$branchDict, $budgetYear, $payload];
    }

    public function data(Request $request): JsonResponse
    {
        [$branchDict, $budgetYear, $result] = $this->resolveDashboardPayloadOrError($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(array_merge(['status' => 'ok'], $result));
    }

    public function export(Request $request): Response|JsonResponse
    {
        [$branchDict, $budgetYear, $result] = $this->resolveDashboardPayloadOrError($request);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $xlsxBytes = XlsxExportService::buildDashboardXlsx($result);

        $safeBranch = preg_replace('/[^A-Za-z0-9_-]/', '', $branchDict['code']) ?: 'branch';
        $safeCostCenter = ! empty($result['cost_center']) ? '_' . preg_replace('/[^A-Za-z0-9_-]/', '', $result['cost_center']) : '';
        $filename = "Dashboard_{$safeBranch}{$safeCostCenter}_{$budgetYear}.xlsx";

        return response($xlsxBytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store',
        ]);
    }
}
