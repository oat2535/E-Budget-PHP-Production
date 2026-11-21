<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\BudgetRoleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystemController extends Controller
{
    public function toggleFreeze(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! BudgetRoleService::isPrivilegedUserAccount($user, $request)) {
            return response()->json(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ในการดำเนินการนี้'], 403);
        }

        try {
            $data = $request->json()->all();
            $now = Carbon::now()->startOfSecond();
            $settingsObj = null;

            DB::connection('pgsql')->transaction(function () use ($data, $user, $now, &$settingsObj) {
                $current = SystemSetting::current(true);
                if ($current->is_frozen) {
                    $current->is_frozen = 0;
                    $current->unfrozen_eid = $user->username;
                    $current->unfrozen_date = $now;
                    $current->save();
                    $settingsObj = $current;
                } else {
                    $customMessage = trim((string) ($data['message'] ?? ''));
                    $settingsObj = SystemSetting::create([
                        'is_frozen' => 1,
                        'frozen_message' => $customMessage ?: $current->frozen_message,
                        'active_budget_year' => $current->active_budget_year,
                        'frozen_eid' => $user->username,
                        'frozen_date' => $now,
                    ]);
                }
            });

            Cache::forget('ebudget_system_settings');

            return response()->json([
                'status' => 'success',
                'is_frozen' => (bool) $settingsObj->is_frozen,
                'frozen_message' => $settingsObj->frozen_message,
            ]);
        } catch (\Throwable $e) {
            Log::error("toggleFreeze error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function setActiveYear(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! BudgetRoleService::isPrivilegedUserAccount($user, $request)) {
            return response()->json(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ในการดำเนินการนี้'], 403);
        }

        try {
            $data = $request->json()->all();
            $rawYear = $data['year'] ?? null;
            if (! is_numeric($rawYear)) {
                return response()->json(['status' => 'error', 'message' => 'ปีไม่ถูกต้อง']);
            }

            $year = (int) $rawYear;
            $current = null;

            DB::connection('pgsql')->transaction(function () use ($year, &$current) {
                $current = SystemSetting::current(true);
                $current->active_budget_year = $year;
                $current->save();
            });

            Cache::forget('ebudget_system_settings');

            return response()->json([
                'status' => 'success',
                'active_budget_year' => $current->active_budget_year,
            ]);
        } catch (\Throwable $e) {
            Log::error("setActiveYear error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
