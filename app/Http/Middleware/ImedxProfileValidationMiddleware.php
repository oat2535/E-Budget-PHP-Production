<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ImedxProfileValidationMiddleware
{
    protected const EXEMPT_PATHS = ['login', 'logout', 'static'];
    protected const VALIDATION_INTERVAL_SECONDS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->username !== 'admin' && empty($user->is_superuser)) {
                foreach (self::EXEMPT_PATHS as $exempt) {
                    if ($request->is($exempt) || $request->is($exempt . '/*')) {
                        return $next($request);
                    }
                }

                $now = time();
                $lastChecked = (int) $request->session()->get('_imedx_profile_last_checked', 0);

                if (($now - $lastChecked) > self::VALIDATION_INTERVAL_SECONDS) {
                    $isValid = true;
                    $reason = null;

                    try {
                        $row = DB::connection('pgsql_imedx')->selectOne("
                            SELECT active, bank_sub_name, bank_account_note
                            FROM employee
                            WHERE LOWER(TRIM(employee_id)) = LOWER(TRIM(?))
                        ", [$user->username]);

                        if ($row) {
                            $active = trim((string) ($row->active ?? ''));
                            $bankSubName = trim((string) ($row->bank_sub_name ?? ''));
                            $bankAccountNote = trim((string) ($row->bank_account_note ?? ''));

                            if ($active !== '1') {
                                $reason = 'inactive_employee';
                                $isValid = false;
                            } elseif ($bankSubName === '' || $bankAccountNote === '') {
                                $reason = 'missing_imedx_profile';
                                $isValid = false;
                            } else {
                                $isValid = true;
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::error("ImedxProfileValidationMiddleware DB error for {$user->username}: " . $e->getMessage());
                    }

                    if (! $isValid) {
                        Log::warning("ImedxProfileValidationMiddleware: Revoking session for {$user->username} due to: {$reason}");
                        Auth::logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        if ($request->expectsJson() || $request->is('api/*')) {
                            return response()->json([
                                'status' => 'session_expired',
                                'message' => 'Your session permissions were revoked in iMedX. Please log in again.',
                                'reason' => $reason,
                            ], 401);
                        }

                        $msg = ($reason === 'missing_imedx_profile')
                            ? 'ไม่พบข้อมูลสิทธิ์สาขาหรือตำแหน่งในระบบ iMedX กรุณาติดต่อ HR หรือผู้ดูแลระบบ'
                            : 'บัญชีของคุณถูกระงับการใช้งานในระบบ iMedX กรุณาติดต่อ HR หรือผู้ดูแลระบบ';

                        return redirect()->route('login')->with('warning', $msg);
                    }

                    $request->session()->put('_imedx_profile_last_checked', $now);
                }
            }
        }

        return $next($request);
    }
}
