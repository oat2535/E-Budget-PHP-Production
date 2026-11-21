<?php

namespace App\Http\Controllers;

use App\Services\BudgetRoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm(Request $request)
    {
        if ($request->query('session_expired')) {
            if (Auth::check()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            $warning = 'ท่านไม่ได้ทำรายการหรือไม่มีการเคลื่อนไหวบนหน้าจอเกิน 1 ชั่วโมง เพื่อความปลอดภัยของข้อมูล ระบบได้ออกจากระบบอัตโนมัติแล้ว กรุณาเข้าสู่ระบบใหม่อีกครั้ง';

            return view('auth.login', [
                'warning' => $warning,
                'error' => null,
            ]);
        }

        if (Auth::check()) {
            return redirect()->route('budget_list');
        }

        $warning = session('warning');
        $error = session('error');

        return view('auth.login', [
            'warning' => $warning,
            'error' => $error,
        ]);
    }

    public function showLogin(Request $request)
    {
        return $this->showLoginForm($request);
    }

    public function login(Request $request)
    {
        $username = trim((string) ($request->input('username') ?? $request->input('employee_id') ?? ''));
        $password = (string) $request->input('password', '');
        $credentials = [
            'username' => $username,
            'password' => $password,
        ];

        if ($credentials['username'] === '' || $credentials['password'] === '') {
            return back()->with('error', 'กรุณากรอก Username และ Password')->withInput();
        }

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $user = Auth::user();

            $branchInfo = BudgetRoleService::getBranchInfoFromImedx($user->username);
            $request->session()->put('branch_info', $branchInfo);
            $request->session()->put('_imedx_profile_last_checked', time());

            $nextUrl = $request->input('next');
            if ($nextUrl && str_starts_with($nextUrl, '/')) {
                return redirect($nextUrl);
            }

            return redirect()->intended(route('budget_list'));
        }

        $failureReason = $request->attributes->get('auth_failure_reason');
        if ($failureReason === 'inactive_employee') {
            $msg = 'บัญชีนี้ถูกระงับการใช้งานในระบบ iMedX กรุณาติดต่อ HR หรือผู้ดูแลระบบ';
        } elseif ($failureReason === 'missing_imedx_profile') {
            $msg = 'บัญชีของท่านยังไม่ได้รับสิทธิ์เข้าใช้งานระบบ E-Budget เนื่องจากไม่พบข้อมูลสาขาหรือเลขที่บัญชีในระบบ iMedX ให้ติดต่อแผนกบัญชี หรือแผนก IT เพื่อดำเนินการตรวจสอบและเปิดสิทธิ์การใช้งาน';
        } else {
            $msg = 'Username หรือ Password ไม่ถูกต้อง';
        }

        return back()->with('error', $msg)->withInput();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->query('session_expired')) {
            return redirect()->route('login', ['session_expired' => 1]);
        }

        return redirect()->route('login');
    }
}
