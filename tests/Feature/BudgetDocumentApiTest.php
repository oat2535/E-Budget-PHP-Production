<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VetManpower;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BudgetDocumentApiTest extends TestCase
{
    public function test_unauthenticated_api_returns_redirect_or_401(): void
    {
        $response = $this->get('/api/get_documents/C01');
        $response->assertRedirect('/login');

        $responseJson = $this->getJson('/api/get_documents/C01');
        $responseJson->assertStatus(401);
    }

    public function test_authenticated_get_documents_c01(): void
    {
        $user = new User(['username' => 'test_officer']);
        $user->is_superuser = false;

        $response = $this->actingAs($user)->get('/api/get_documents/C01');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertIsArray($response->json('data'));
    }

    public function test_authenticated_get_documents_c02(): void
    {
        $user = new User(['username' => 'test_officer']);
        $response = $this->actingAs($user)->get('/api/get_documents/C02');
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_authenticated_get_documents_c03(): void
    {
        $user = new User(['username' => 'test_officer']);
        $response = $this->actingAs($user)->get('/api/get_documents/C03');
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_general_ledgers_api(): void
    {
        $user = new User(['username' => 'test_officer']);
        $response = $this->actingAs($user)->get('/api/general_ledgers?doc_type=GL+Entry');
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $data = $response->json('data') ?? [];
        foreach ($data as $gl) {
            $this->assertFalse(str_starts_with($gl['gl_name'] ?? '', 'ค่าเสื่อม'), 'Expense GL list must not contain depreciation items starting with ค่าเสื่อม');
        }

        $byDocType = $response->json('by_doc_type') ?? [];
        $this->assertArrayHasKey('GL Entry', $byDocType);
        $this->assertArrayHasKey('EXPENSE', $byDocType);
        foreach ($byDocType['GL Entry'] as $gl) {
            $this->assertFalse(str_starts_with($gl['gl_name'] ?? '', 'ค่าเสื่อม'), 'by_doc_type GL Entry must not contain depreciation items');
        }
    }

    public function test_check_duplicate_api_returns_ok_when_no_doc(): void
    {
        $user = new User(['username' => 'test_officer']);
        $response = $this->actingAs($user)->get('/api/check_duplicate_document?doc_type=VET&branch_code=TEST_NONEXISTENT_BRANCH');
        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok', 'is_duplicate' => false]);
    }

    public function test_session_keepalive(): void
    {
        $user = new User(['username' => 'test_officer']);
        $response = $this->actingAs($user)->get('/api/session/keepalive');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'authenticated' => true,
        ]);
    }

    public function test_cancel_document_requires_reason(): void
    {
        $user = new User(['username' => 'test_officer']);
        $response = $this->actingAs($user)->postJson('/api/document/cancel/VET/NON_EXISTENT_DOC', []);
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'error',
            'message' => 'กรุณาระบุเหตุผลการยกเลิก',
        ]);
    }

    public function test_get_document_detail_nonexistent_returns_error(): void
    {
        $user = new User(['username' => 'test_officer']);
        $response = $this->actingAs($user)->getJson('/api/document/VET/NON_EXISTENT_DOC_NO');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'error',
            'message' => 'ไม่พบเอกสารนี้',
        ]);
    }

    public function test_update_document_nonexistent_returns_error(): void
    {
        $user = new User(['username' => 'test_officer']);
        $payload = [
            [
                'cost_center_name' => 'CC1',
                'position_name' => 'Veterinarian',
                'salary' => 30000,
            ],
        ];
        $response = $this->actingAs($user)->postJson('/api/document/update/VET/NON_EXISTENT_DOC_NO', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'error',
            'message' => 'ไม่พบเอกสาร',
        ]);
    }
}
