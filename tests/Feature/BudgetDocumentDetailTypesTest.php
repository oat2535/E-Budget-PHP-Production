<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\Renovate;
use App\Models\Software;
use App\Models\User;
use App\Models\VetManpower;
use App\Services\BudgetService;
use Tests\TestCase;

class BudgetDocumentDetailTypesTest extends TestCase
{
    /**
     * Verify that all known document types can be resolved by BudgetService::resolveModelClass.
     */
    public function test_all_budget_document_types_resolve_to_model(): void
    {
        $types = [
            'VET',
            'NON VET',
            'Position Adjustment',
            'Medical Equipment',
            'Computer Equipment',
            'Furniture',
            'Tools & Equipment',
            'CAR',
            'Renovate',
            'Software',
            'GL Entry',
            'Revenue',
            'Budget Plan',
            'GL Actual Template',
        ];

        foreach ($types as $type) {
            $modelClass = BudgetService::resolveModelClass($type);
            $this->assertNotNull($modelClass, "Document type '{$type}' must resolve to an Eloquent model class");
            $this->assertTrue(class_exists($modelClass), "Model class '{$modelClass}' for type '{$type}' must exist");
        }
    }

    /**
     * Verify that getDocumentDetail API preserves and returns the exact document type in doc_info.type.
     */
    public function test_document_detail_api_returns_correct_type_for_software_car_renovate(): void
    {
        $user = new User(['username' => 'test_user']);
        $user->is_superuser = true;

        // Test with a mock / database query or dummy document
        // Test software
        $softwareDoc = Software::orderByDesc('id')->first();
        if ($softwareDoc) {
            $resp = $this->actingAs($user)->getJson("/api/document/Software/{$softwareDoc->document_no}");
            $resp->assertStatus(200);
            $this->assertEquals('Software', $resp->json('doc_info.type'));
            $this->assertEquals($softwareDoc->document_no, $resp->json('doc_info.document_no'));
        }

        // Test car
        $carDoc = Car::orderByDesc('id')->first();
        if ($carDoc) {
            $resp = $this->actingAs($user)->getJson("/api/document/CAR/{$carDoc->document_no}");
            $resp->assertStatus(200);
            $this->assertEquals('CAR', $resp->json('doc_info.type'));
        }

        // Test renovate
        $renovateDoc = Renovate::orderByDesc('id')->first();
        if ($renovateDoc) {
            $resp = $this->actingAs($user)->getJson("/api/document/Renovate/{$renovateDoc->document_no}");
            $resp->assertStatus(200);
            $this->assertEquals('Renovate', $resp->json('doc_info.type'));
        }
    }

    /**
     * Verify frontend assets contract: budget_list.blade.php and budget_list.js
     * have all badge types, the section title element, and bumped cache-buster.
     */
    public function test_budget_list_frontend_badge_and_modal_contracts(): void
    {
        $bladePath = resource_path('views/budget/budget_list.blade.php');
        $this->assertFileExists($bladePath);
        $bladeContent = file_get_contents($bladePath);

        $this->assertStringContainsString('id="modalSectionTitle"', $bladeContent, 'documentDetailModal must have id="modalSectionTitle" for dynamic title switching');
        $this->assertStringContainsString('id="adjModalDocType"', $bladeContent, 'adjustmentDetailModal must have id="adjModalDocType"');
        $this->assertStringContainsString('id="glatModalDocType"', $bladeContent, 'glatDocumentDetailModal must have id="glatModalDocType"');
        $this->assertStringContainsString("budget_list.js') }}?v=20261126_01", $bladeContent, 'budget_list.js script tag must have bumped version query string');

        $jsPath = public_path('static/budget_app/js/budget_list.js');
        $this->assertFileExists($jsPath);
        $jsContent = file_get_contents($jsPath);

        $this->assertStringContainsString('function getDocTypeBadgeHtml(type)', $jsContent, 'budget_list.js must have unified getDocTypeBadgeHtml function');
        
        // Ensure all types are explicitly handled in getDocTypeBadgeHtml
        $expectedTypes = [
            'VET',
            'NON VET',
            'Position Adjustment',
            'Medical Equipment',
            'Computer Equipment',
            'Furniture',
            'Tools & Equipment',
            'CAR',
            'Renovate',
            'Software',
            'GL Entry',
            'Revenue',
            'Budget Plan',
            'GL Actual Template',
        ];

        foreach ($expectedTypes as $type) {
            $this->assertStringContainsString("case '{$type}':", $jsContent, "getDocTypeBadgeHtml must handle type '{$type}'");
        }

        // Verify that modalDocType uses getDocTypeBadgeHtml instead of hardcoded ternary with NON VET fallback
        $this->assertStringContainsString(
            "document.getElementById('modalDocType').innerHTML = getDocTypeBadgeHtml(result.doc_info.type);",
            $jsContent,
            'modalDocType must use getDocTypeBadgeHtml'
        );

        // Verify that modalSectionTitle updates according to isEquipment
        $this->assertStringContainsString(
            "modalSectionTitle.innerHTML = '<i data-feather=\"package\" class=\"me-2\"></i>รายละเอียดรายการ (Equipment Details)';",
            $jsContent,
            'modalSectionTitle must switch to Equipment Details for equipment docs'
        );
    }
}
