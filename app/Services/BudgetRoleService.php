<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BudgetRoleService
{
    public const EXECUTIVE_VIEW_ONLY_ROLES = ['CHRO', 'CFO', 'COO'];
    public const DISTRICT_VIEW_ONLY_ROLES = ['DISTRICT MANAGER', 'DIRECTOR'];

    public const GL_ACTUAL_TEMPLATE_BRANCHES = [
        ['AP', 'สาขาอโศกประสานมิตร'],
        ['CA', 'สาขาเชียงใหม่แอร์พอร์ต'],
        ['CE', 'สาขาเซ็นทรัลอีสต์วิลล์'],
        ['CM', 'สาขาเชียงใหม่'],
        ['EC', 'โรงพยาบาลสัตว์ทองหล่อ Ecommerce'],
        ['PK', 'สาขาปิ่นเกล้า'],
        ['PR', 'สาขาประชาชื่น'],
        ['PY', 'สาขาพัทยา'],
        ['R2', 'สาขาพระราม2'],
        ['R5', 'สาขาพระราม5'],
        ['R6', 'สาขาพระราม6'],
        ['R8', 'สาขารามอินทรา'],
        ['R9', 'สาขาพระราม9'],
        ['RS', 'สาขารังสิต'],
        ['RY', 'สาขาระยอง'],
        ['SN', 'สาขาสาทรนราธิวาส'],
        ['SR', 'สาขาศรีนครินทร์'],
        ['SV', 'สาขาสุขุมวิท'],
        ['TN', 'สาขาติวานนท์'],
        ['TP', 'ทองหล่อฉลองภูเก็ต'],
        ['PM', 'สาขาประดิษฐ์มนูธรรม'],
        ['BN', 'สาขาบางนา'],
        ['HO', 'สำนักงานใหญ่'],
    ];

    public static function isAdminRole(?string $bankAccountNote): bool
    {
        if (! $bankAccountNote) {
            return false;
        }
        $note = strtoupper(trim($bankAccountNote));
        return str_ends_with($note, 'ADMIN') || str_contains($note, 'ADMIN');
    }

    public static function isExecutiveRole(?string $bankAccountNote): bool
    {
        if (! $bankAccountNote) {
            return false;
        }
        return in_array(strtoupper(trim($bankAccountNote)), self::EXECUTIVE_VIEW_ONLY_ROLES, true);
    }

    public static function isDistrictRole(?string $bankAccountNote): bool
    {
        if (! $bankAccountNote) {
            return false;
        }
        $note = strtoupper(trim($bankAccountNote));
        return in_array($note, self::DISTRICT_VIEW_ONLY_ROLES, true)
            || str_starts_with($note, 'DISTRICT MANAGER')
            || str_starts_with($note, 'DIRECTOR');
    }

    public static function isViewOnlyRole(?string $bankAccountNote): bool
    {
        return self::isExecutiveRole($bankAccountNote) || self::isDistrictRole($bankAccountNote);
    }

    public static function isManagerRole(?string $bankAccountNote): bool
    {
        if (! $bankAccountNote) {
            return false;
        }
        return str_contains(strtoupper(trim($bankAccountNote)), 'MANAGER');
    }

    public static function getBranchInfoFromImedx(?string $username): array
    {
        $empty = [
            'branches' => [],
            'needs_selection' => false,
            'department_id' => null,
            'is_manager' => false,
            'is_admin' => false,
            'bank_account_note' => null,
            'is_executive_view_only' => false,
            'is_district_view_only' => false,
            'is_view_only' => false,
        ];

        if (! $username) {
            return $empty;
        }

        try {
            $row = DB::connection('pgsql_imedx')->selectOne("
                SELECT emp.bank_sub_name, emp.bank_account_note, bsp.base_site_branch_id
                FROM employee emp
                LEFT JOIN base_service_point bsp ON emp.base_service_point_id = bsp.base_service_point_id
                WHERE LOWER(TRIM(emp.employee_id)) = LOWER(TRIM(?)) AND emp.active = '1'
            ", [$username]);
        } catch (\Throwable $e) {
            Log::error("Error fetching branch info for {$username}: " . $e->getMessage());
            return $empty;
        }

        if (! $row) {
            return $empty;
        }

        $bankSubName = trim((string) ($row->bank_sub_name ?? ''));
        $bankAccountNote = trim((string) ($row->bank_account_note ?? ''));
        $fallbackBranchId = $row->base_site_branch_id ? trim((string) $row->base_site_branch_id) : null;

        if ($bankSubName === '' || $bankAccountNote === '') {
            return $empty;
        }

        $subNameUpper = strtoupper($bankSubName);
        $isAdmin = self::isAdminRole($bankAccountNote);
        $isManager = str_contains(strtoupper($bankAccountNote), 'MANAGER');
        $isExecutive = self::isExecutiveRole($bankAccountNote);
        $isDistrict = self::isDistrictRole($bankAccountNote);
        $isViewOnly = ($isExecutive || $isDistrict) && ! $isAdmin;

        if (str_contains($subNameUpper, 'BACK')) {
            $validBranchIds = [];
            foreach (self::GL_ACTUAL_TEMPLATE_BRANCHES as [$code, $name]) {
                if ($code !== 'HO') {
                    $validBranchIds[strtoupper($code)] = true;
                }
            }

            try {
                $subBranches = DB::connection('pgsql')->select("SELECT sub_branch_id FROM base_sub_branch");
                foreach ($subBranches as $sb) {
                    $id = strtoupper(trim((string) ($sb->sub_branch_id ?? '')));
                    if ($id !== '' && $id !== 'HO') {
                        $validBranchIds[$id] = true;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Error fetching base_sub_branch for BACK user: " . $e->getMessage());
            }

            try {
                $siteBranches = DB::connection('pgsql_imedx')->select("SELECT base_site_branch_id FROM base_site_branch");
                foreach ($siteBranches as $sb) {
                    $id = strtoupper(trim((string) ($sb->base_site_branch_id ?? '')));
                    if ($id !== '') {
                        $validBranchIds[$id] = true;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Error fetching base_site_branch for BACK user: " . $e->getMessage());
            }

            $rawTokens = array_filter(array_map('trim', explode(',', $bankSubName)));
            $branchCodes = [];
            $deptTokens = [];

            foreach ($rawTokens as $token) {
                $tokenUpper = strtoupper($token);
                $marker = strpos($tokenUpper, 'BACK-');
                if ($marker !== false) {
                    $deptPart = trim(substr($token, $marker + strlen('BACK-')));
                    if ($deptPart !== '') {
                        $deptTokens[] = $deptPart;
                    }
                } elseif ($tokenUpper === 'BACK') {
                    continue;
                } elseif ($tokenUpper === 'HO' || isset($validBranchIds[$tokenUpper])) {
                    if (! in_array($tokenUpper, $branchCodes, true)) {
                        $branchCodes[] = $tokenUpper;
                    }
                } else {
                    $deptTokens[] = $token;
                }
            }

            $departmentId = ! empty($deptTokens) ? implode(',', $deptTokens) : null;

            if (! empty($branchCodes)) {
                $branches = $branchCodes;
                if (! in_array('HO', $branches, true)) {
                    $branches[] = 'HO';
                }
                return [
                    'branches' => $branches,
                    'needs_selection' => count($branches) > 1,
                    'department_id' => $departmentId,
                    'is_manager' => $isManager,
                    'is_admin' => $isAdmin,
                    'bank_account_note' => $bankAccountNote,
                    'is_executive_view_only' => $isExecutive,
                    'is_district_view_only' => $isDistrict,
                    'is_view_only' => $isViewOnly,
                ];
            }

            return [
                'branches' => ['HO'],
                'needs_selection' => false,
                'department_id' => $departmentId,
                'is_manager' => $isManager,
                'is_admin' => $isAdmin,
                'bank_account_note' => $bankAccountNote,
                'is_executive_view_only' => $isExecutive,
                'is_district_view_only' => $isDistrict,
                'is_view_only' => $isViewOnly,
            ];
        }

        if ($bankSubName !== '') {
            $branches = array_values(array_filter(array_map(fn($b) => strtoupper(trim($b)), explode(',', $bankSubName))));
            if (! empty($branches)) {
                return [
                    'branches' => $branches,
                    'needs_selection' => count($branches) > 1,
                    'department_id' => null,
                    'is_manager' => $isManager,
                    'is_admin' => $isAdmin,
                    'bank_account_note' => $bankAccountNote,
                    'is_executive_view_only' => $isExecutive,
                    'is_district_view_only' => $isDistrict,
                    'is_view_only' => $isViewOnly,
                ];
            }
        }

        if ($fallbackBranchId) {
            return [
                'branches' => [$fallbackBranchId],
                'needs_selection' => false,
                'department_id' => null,
                'is_manager' => $isManager,
                'is_admin' => $isAdmin,
                'bank_account_note' => $bankAccountNote,
                'is_executive_view_only' => $isExecutive,
                'is_district_view_only' => $isDistrict,
                'is_view_only' => $isViewOnly,
            ];
        }

        return $empty;
    }

    public static function isPrivilegedUserAccount(?User $user, ?Request $request = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->_is_admin_cached !== null) {
            return $user->_is_admin_cached;
        }

        if ($request && $request->hasSession()) {
            $branchInfo = $request->session()->get('branch_info');
            if (is_array($branchInfo) && isset($branchInfo['is_admin'])) {
                $isAdmin = (bool) $branchInfo['is_admin'];
                $user->_is_admin_cached = $isAdmin;
                return $isAdmin;
            }
        }

        if (! empty($user->bank_account_note)) {
            $isAdmin = self::isAdminRole($user->bank_account_note);
            $user->_is_admin_cached = $isAdmin;
            return $isAdmin;
        }

        $rawUsername = strtolower(trim((string) $user->username));
        if ($user->is_superuser || $rawUsername === 'admin') {
            if ($rawUsername === 'admin' || $rawUsername === '') {
                $user->_is_admin_cached = true;
                return true;
            }

            $info = self::getBranchInfoFromImedx($user->username);
            if (! empty($info['branches']) || ! empty($info['bank_account_note'])) {
                $isAdmin = (bool) ($info['is_admin'] ?? false);
                $user->_is_admin_cached = $isAdmin;
                return $isAdmin;
            }
            return true;
        }

        if ($rawUsername === '') {
            return false;
        }

        $info = self::getBranchInfoFromImedx($user->username);
        $isAdmin = (bool) ($info['is_admin'] ?? false);
        $user->_is_admin_cached = $isAdmin;
        return $isAdmin;
    }

    public static function isExecutiveViewOnlyUser(?Request $request): bool
    {
        if (! $request || ! $request->user()) {
            return false;
        }
        if (self::isPrivilegedUserAccount($request->user(), $request)) {
            return false;
        }
        $session = $request->hasSession() ? $request->session() : null;
        $info = $session?->get('branch_info') ?? self::getBranchInfoFromImedx($request->user()->username);
        return (bool) ($info['is_executive_view_only'] ?? false);
    }

    public static function isDistrictViewOnlyUser(?Request $request): bool
    {
        if (! $request || ! $request->user()) {
            return false;
        }
        if (self::isPrivilegedUserAccount($request->user(), $request)) {
            return false;
        }
        $session = $request->hasSession() ? $request->session() : null;
        $info = $session?->get('branch_info') ?? self::getBranchInfoFromImedx($request->user()->username);
        return (bool) ($info['is_district_view_only'] ?? false);
    }

    public static function isViewOnlyUser(?Request $request): bool
    {
        if (! $request || ! $request->user()) {
            return false;
        }
        if (self::isPrivilegedUserAccount($request->user(), $request)) {
            return false;
        }
        $session = $request->hasSession() ? $request->session() : null;
        $info = $session?->get('branch_info') ?? self::getBranchInfoFromImedx($request->user()->username);
        return (bool) ($info['is_view_only'] ?? false);
    }

    public static function canAccessGlActualTemplate(?Request $request): bool
    {
        if (! $request || ! $request->user()) {
            return false;
        }
        return self::isPrivilegedUserAccount($request->user(), $request);
    }

    public static function canAccessDashboard(?Request $request): bool
    {
        if (! $request || ! $request->user()) {
            return false;
        }

        $user = $request->user();
        $session = $request->hasSession() ? $request->session() : null;
        $info = $session?->get('branch_info') ?? self::getBranchInfoFromImedx($user->username);

        if (self::isPrivilegedUserAccount($user, $request)) {
            return true;
        }

        if (empty($info)) {
            return false;
        }

        if (! empty($info['is_district_view_only']) || ! empty($info['is_manager']) || ! empty($info['is_executive_view_only'])) {
            return true;
        }

        return false;
    }

    public static function getBranchInfo(Request $request): array
    {
        if ($request->hasSession() && $request->session()->has('branch_info')) {
            return $request->session()->get('branch_info');
        }
        $info = self::getBranchInfoFromImedx($request->user()?->username);
        if ($request->hasSession()) {
            $request->session()->put('branch_info', $info);
        }
        return $info;
    }

    public static function applyDocumentVisibilityFilter($query, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return $query->whereRaw('1=0');
        }
        if (self::isPrivilegedUserAccount($user, $request)) {
            return $query;
        }
        if (self::isExecutiveViewOnlyUser($request)) {
            return $query;
        }
        if (self::isDistrictViewOnlyUser($request)) {
            $info = self::getBranchInfo($request);
            $rawBranches = $info['branches'] ?? [];
            $branches = array_filter(array_map('strtoupper', array_map('trim', $rawBranches)));
            return $query->whereIn('base_branch_id', $branches);
        }
        return $query->where('create_eid', $user->username);
    }

    public static function getBranchSelectionContext(Request $request): array
    {
        $info = self::getBranchInfo($request);
        $user = $request->user();
        if ($user && self::isPrivilegedUserAccount($user, $request)) {
            $allBranches = DashboardService::getAllBranches();
            $options = [];
            foreach ($allBranches as $b) {
                $options[] = ['code' => $b['code'], 'name' => $b['name']];
            }
            $hasHo = false;
            foreach ($options as $o) {
                if ($o['code'] === 'HO') {
                    $hasHo = true;
                    break;
                }
            }
            if (! $hasHo) {
                array_unshift($options, ['code' => 'HO', 'name' => 'สำนักงานใหญ่ (HO)']);
            }
            return [
                'needs_branch_selection' => true,
                'branch_options_json' => $options,
            ];
        }

        if (empty($info['needs_selection'])) {
            return [
                'needs_branch_selection' => false,
                'branch_options_json' => [],
            ];
        }

        $allBranches = DashboardService::getAllBranches();
        $branchNameMap = [];
        foreach ($allBranches as $b) {
            $branchNameMap[$b['code']] = $b['name'];
        }

        $options = [];
        foreach ($info['branches'] ?? [] as $code) {
            $options[] = [
                'code' => $code,
                'name' => $branchNameMap[$code] ?? $code,
            ];
        }

        return [
            'needs_branch_selection' => true,
            'branch_options_json' => $options,
        ];
    }

    public static function resolveBranchForCreate(Request $request, array $data): array
    {
        $info = self::getBranchInfo($request);
        $branches = $info['branches'] ?? [];
        $deptId = $info['department_id'] ?? null;
        $user = $request->user();

        if ($user && self::isPrivilegedUserAccount($user, $request)) {
            $chosen = ! empty($data[0]['branch_code']) ? trim($data[0]['branch_code']) : null;
            if ($chosen) {
                return [$chosen, $deptId];
            }
            if (! empty($branches)) {
                return [$branches[0], $deptId];
            }
            return ['HO', $deptId];
        }

        if (empty($branches)) {
            return [null, $deptId];
        }

        if (count($branches) === 1) {
            return [$branches[0], $deptId];
        }

        $chosen = ! empty($data[0]['branch_code']) ? trim($data[0]['branch_code']) : null;
        $validBranches = array_map('strtoupper', $branches);
        if ($chosen && in_array(strtoupper($chosen), $validBranches, true)) {
            return [$chosen, $deptId];
        }

        return [$branches[0], $deptId];
    }

    public static function resolveBranchForUpdate(Request $request, array $data, ?string $currentBranchId): ?string
    {
        $info = self::getBranchInfo($request);
        $branches = $info['branches'] ?? [];
        $user = $request->user();

        if ($user && self::isPrivilegedUserAccount($user, $request)) {
            $chosen = ! empty($data[0]['branch_code']) ? trim($data[0]['branch_code']) : null;
            if ($chosen) {
                return $chosen;
            }
            return $currentBranchId;
        }

        if (count($branches) > 1) {
            $chosen = ! empty($data[0]['branch_code']) ? trim($data[0]['branch_code']) : null;
            $validBranches = array_map('strtoupper', $branches);
            if ($chosen && in_array(strtoupper($chosen), $validBranches, true)) {
                return $chosen;
            }
        }

        return $currentBranchId;
    }
}
