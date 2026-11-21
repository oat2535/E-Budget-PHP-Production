<?php

namespace Tests\Feature;

use App\Http\Controllers\BudgetCreateController;
use App\Models\User;
use App\Services\BudgetService;
use Tests\TestCase;

class FrontendContractsTest extends TestCase
{
    protected function getTestUser(): User
    {
        $user = new User(['username' => 'test_officer']);
        $user->is_superuser = false;
        return $user;
    }

    public function test_metropolis_font_preloads_in_layouts_app(): void
    {
        $html = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $weights = ['Regular', 'Medium', 'SemiBold', 'Bold'];
        foreach ($weights as $w) {
            $this->assertStringContainsString("Metropolis-{$w}.otf", $html);
        }
        $this->assertStringContainsString('rel="preload"', $html);
        $this->assertStringContainsString('as="font"', $html);
        $this->assertStringContainsString('crossorigin', $html);
    }

    public function test_metropolis_font_preloads_in_login(): void
    {
        $html = file_get_contents(resource_path('views/auth/login.blade.php'));
        $weights = ['Regular', 'Bold'];
        foreach ($weights as $w) {
            $this->assertStringContainsString("Metropolis-{$w}.otf", $html);
        }
        $this->assertStringContainsString('rel="preload"', $html);
        $this->assertStringContainsString('as="font"', $html);
    }

    public function test_doc_type_item_column_names_contract(): void
    {
        $expected = [
            'Medical Equipment' => 'เครื่องมือแพทย์',
            'Computer Equipment' => 'อุปกรณ์คอมพิวเตอร์',
            'Furniture' => 'เฟอร์นิเจอร์',
            'Tools & Equipment' => 'เครื่องมือและอุปกรณ์',
            'CAR' => 'ยานพาหนะ',
            'Renovate' => 'งานปรับปรุง',
            'Software' => 'ซอฟต์แวร์',
        ];
        $this->assertSame($expected, BudgetCreateController::DOC_TYPE_ITEM_COLUMN_NAMES);
        $this->assertSame(5000, BudgetCreateController::FIX_ASSET_MIN_PURCHASE_PRICE);
    }

    public function test_service_gl_codes_contract(): void
    {
        $this->assertSame('52000008', BudgetService::VET_CUSTOM_POSITION_GL_CODE);
        $this->assertSame('60100001', BudgetService::NON_VET_SALARY_GL_CODE);
        $this->assertSame('60100002', BudgetService::NON_VET_ALLOWANCE_GL_CODE);
    }

    public function test_budget_list_view_contains_datatable_and_modals(): void
    {
        $response = $this->actingAs($this->getTestUser())->get('/');
        $response->assertStatus(200);

        // Master datatable
        $response->assertSee('datatablesSimple');

        // All 4 category detail modals
        $response->assertSee('documentDetailModal');
        $response->assertSee('adjustmentDetailModal');
        $response->assertSee('glDocumentDetailModal');
        $response->assertSee('glatDocumentDetailModal');
    }

    public function test_budget_add_contains_jspreadsheet_container(): void
    {
        $response = $this->actingAs($this->getTestUser())->get('/add');
        $response->assertStatus(200);
        $response->assertSee('spreadsheet');
        $response->assertSee('calc-spreadsheet');
        $response->assertSee('save-budget-btn');
    }

    public function test_layouts_app_contains_inactivity_warning_and_test_helpers(): void
    {
        $html = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('showInactivityWarningModal', $html);
        $this->assertStringContainsString('checkIdleTimeout', $html);
        $this->assertStringContainsString('test_idle', $html);
        $this->assertStringContainsString('__testIdlePopup', $html);
        $this->assertStringContainsString('ebudget_last_activity', $html);
    }
}
