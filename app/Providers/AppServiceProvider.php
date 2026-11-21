<?php

namespace App\Providers;

use App\Auth\ImedxUserProvider;
use App\Models\SystemSetting;
use App\Services\BudgetRoleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://') || app()->environment('production', 'uat')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Auth::provider('imedx', function ($app, array $config) {
            return new ImedxUserProvider();
        });

        View::composer('*', function ($view) {
            $request = request();
            $user = Auth::user();

            $isViewOnly = BudgetRoleService::isViewOnlyUser($request);
            $isExecutive = BudgetRoleService::isExecutiveViewOnlyUser($request);
            $isDistrict = BudgetRoleService::isDistrictViewOnlyUser($request);
            $isPrivileged = BudgetRoleService::isPrivilegedUserAccount($user, $request) && ! $isViewOnly;

            $settings = SystemSetting::loadSetting();
            $currentYear = (int) date('Y');
            $canMutateWhenFrozen = $isPrivileged || ! (bool) $settings->is_frozen;
            $isAuthWritable = Auth::check() && ! $isViewOnly;

            $canEditOwnDocuments = $isAuthWritable && $canMutateWhenFrozen;
            $canAddBudget = $isAuthWritable && $canMutateWhenFrozen;

            $view->with([
                'session_idle_timeout_seconds' => config('session.lifetime', 120) * 60,
                'system_frozen' => (int) $settings->is_frozen,
                'frozen_message' => $settings->frozen_message,
                'active_budget_year' => (int) $settings->active_budget_year,
                'is_privileged_user' => $isPrivileged,
                'is_executive_view_only' => $isExecutive,
                'is_district_view_only' => $isDistrict,
                'is_view_only' => $isViewOnly,
                'can_edit_own_documents' => $canEditOwnDocuments,
                'can_add_budget' => $canAddBudget,
                'can_access_dashboard' => BudgetRoleService::canAccessDashboard($request),
                'can_access_gl_actual_template' => BudgetRoleService::canAccessGlActualTemplate($request),
                'budget_year_options' => range($currentYear - 1, $currentYear + 4),
            ]);
        });
    }
}
