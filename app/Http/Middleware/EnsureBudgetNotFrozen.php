<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Services\BudgetRoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBudgetNotFrozen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $settings = SystemSetting::loadSetting();

            if ((int) $settings->is_frozen === 1) {
                $user = $request->user();
                $isPrivileged = BudgetRoleService::isPrivilegedUserAccount($user, $request);

                if (! $isPrivileged) {
                    $msg = $settings->frozen_message ?: SystemSetting::DEFAULT_FROZEN_MESSAGE;

                    if ($request->expectsJson() || $request->is('api/*')) {
                        return response()->json([
                            'status' => 'error',
                            'message' => $msg,
                        ], 403);
                    }

                    return redirect()->back()->with('error', $msg);
                }
            }
        }

        return $next($request);
    }
}
