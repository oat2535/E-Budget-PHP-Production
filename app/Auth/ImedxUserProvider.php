<?php

namespace App\Auth;

use App\Models\User;
use App\Services\BudgetRoleService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImedxUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return User::find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // No-op
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        if ($username === '' || $password === '') {
            return null;
        }

        // 1. Try iMedX SSO first
        try {
            $employee = DB::connection('pgsql_imedx')->selectOne("
                SELECT employee_id, password, prename, firstname, lastname,
                       bank_sub_name, bank_account_note, active
                FROM employee
                WHERE LOWER(TRIM(employee_id)) = LOWER(TRIM(?))
            ", [$username]);

            if ($employee) {
                $encodedPassword = md5($password);
                $dbPassword = strtolower(trim((string) ($employee->password ?? '')));

                if (hash_equals($dbPassword, strtolower($encodedPassword))) {
                    // Check active
                    if ((string) ($employee->active ?? '') === '0') {
                        Log::warning("ImedxUserProvider: Login rejected for inactive employee '{$username}'");
                        request()?->attributes->set('auth_failure_reason', 'inactive_employee');
                        return null;
                    }

                    // Check required fields
                    $subNameClean = trim((string) ($employee->bank_sub_name ?? ''));
                    $accountNoteClean = trim((string) ($employee->bank_account_note ?? ''));

                    if ($subNameClean === '' || $accountNoteClean === '') {
                        $missing = [];
                        if ($subNameClean === '') $missing[] = 'bank_sub_name';
                        if ($accountNoteClean === '') $missing[] = 'bank_account_note';

                        Log::warning("ImedxUserProvider: Login rejected for employee '{$username}' due to missing fields: " . implode(', ', $missing));
                        request()?->attributes->set('auth_failure_reason', 'missing_imedx_profile');
                        request()?->attributes->set('auth_missing_fields', $missing);
                        return null;
                    }

                    // Get or create local user
                    $firstNameStr = trim(($employee->prename ?? '') . ($employee->firstname ?? ''));
                    $firstName = mb_substr($firstNameStr, 0, 150);
                    $lastName = mb_substr(trim((string) ($employee->lastname ?? '')), 0, 150);

                    $user = User::where('username', $username)->first();
                    if (! $user) {
                        $user = new User();
                        $user->username = $username;
                        $user->first_name = $firstName;
                        $user->last_name = $lastName;
                        $user->email = '';
                        $user->password = '!'; // unusable password
                        $user->is_staff = false;
                        $user->is_superuser = false;
                        $user->is_active = true;
                        $user->date_joined = now();
                        $user->save();
                    } else {
                        $user->first_name = $firstName;
                        $user->last_name = $lastName;
                        $user->is_active = true;
                        $user->save();
                    }

                    $user->bank_sub_name = $subNameClean;
                    $user->bank_account_note = $accountNoteClean;

                    // Pre-cache branch_info in session
                    $branchInfo = BudgetRoleService::getBranchInfoFromImedx($username);
                    if (request()?->hasSession()) {
                        request()->session()->put('branch_info', $branchInfo);
                    }

                    return $user;
                }
            }
        } catch (\Throwable $e) {
            Log::error("ImedxUserProvider error authenticating {$username}: " . $e->getMessage());
        }

        // 2. Fallback to local admin account
        try {
            $localUser = User::where('username', $username)->first();
            if ($localUser && ! empty($localUser->password) && $localUser->password !== '!') {
                // If account exists in iMedX and is active, local login is strictly forbidden
                if ($username !== 'admin') {
                    $activeInImedx = DB::connection('pgsql_imedx')->selectOne("
                        SELECT 1 FROM employee WHERE LOWER(TRIM(employee_id)) = LOWER(TRIM(?)) AND active = '1'
                    ", [$username]);
                    if ($activeInImedx) {
                        return null;
                    }
                }

                if ($this->verifyPassword($password, $localUser->password)) {
                    return $localUser;
                }
            }
        } catch (\Throwable $e) {
            Log::error("Local auth fallback error for {$username}: " . $e->getMessage());
        }

        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        // Credentials already validated in retrieveByCredentials
        return true;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): bool
    {
        return false;
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        if (str_starts_with($hash, 'pbkdf2_sha256$')) {
            $parts = explode('$', $hash);
            if (count($parts) === 4) {
                [$algo, $iterations, $salt, $expectedHash] = $parts;
                $computed = base64_encode(hash_pbkdf2('sha256', $password, $salt, (int) $iterations, 32, true));
                return hash_equals($expectedHash, $computed);
            }
        }

        if (password_verify($password, $hash)) {
            return true;
        }

        return false;
    }
}
