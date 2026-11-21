<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class BudgetCreateTest extends TestCase
{
    protected function getTestUser(): User
    {
        $user = new User(['username' => 'test_officer']);
        $user->is_superuser = false;
        return $user;
    }

    public function test_budget_add_vet_renders(): void
    {
        $response = $this->actingAs($this->getTestUser())->get('/add');
        $response->assertStatus(200);
        $response->assertSee('VET Manpower');
    }

    public function test_budget_add_non_vet_renders(): void
    {
        $response = $this->actingAs($this->getTestUser())->get('/add_non_vet');
        $response->assertStatus(200);
        $response->assertSee('NON VET Manpower');
    }

    public function test_budget_add_adjustment_renders(): void
    {
        $response = $this->actingAs($this->getTestUser())->get('/add_adjustment');
        $response->assertStatus(200);
        $response->assertSee('Position Adjustment');
    }

    public function test_budget_add_medical_equipment_renders(): void
    {
        $response = $this->actingAs($this->getTestUser())->get('/add_medical_equipment');
        $response->assertStatus(200);
        $response->assertSee('Medical Equipment');
    }

    public function test_budget_add_gl_entry_renders(): void
    {
        $response = $this->actingAs($this->getTestUser())->get('/add_gl_entry');
        $response->assertStatus(200);
        $gls = $response->viewData('general_ledgers_json') ?? [];
        foreach ($gls as $gl) {
            $this->assertFalse(str_starts_with($gl['gl_name'] ?? '', 'ค่าเสื่อม'), 'Add Expense view data must not contain GL starting with ค่าเสื่อม');
        }
    }

    public function test_budget_add_revenue_renders(): void
    {
        $response = $this->actingAs($this->getTestUser())->get('/add_revenue');
        $response->assertStatus(200);
    }

    public function test_budget_add_post_missing_cost_center_returns_error(): void
    {
        $payload = [
            [
                'position_name' => 'Veterinarian',
                'salary' => 30000,
                'monthly_data' => [
                    'jan' => ['headcount' => 1, 'cost' => 30000],
                ],
            ],
        ];

        $response = $this->actingAs($this->getTestUser())->postJson('/add', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'error',
            'message' => 'กรุณาเลือก Cost Center',
        ]);
    }

    public function test_budget_add_post_invalid_headcount_returns_error(): void
    {
        $payload = [
            [
                'cost_center_name' => 'CC1',
                'position_name' => 'Veterinarian',
                'salary' => 30000,
                'monthly_data' => [
                    'jan' => ['headcount' => 1.5, 'cost' => 45000],
                ],
            ],
        ];

        $response = $this->actingAs($this->getTestUser())->postJson('/add', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'error',
        ]);
        $this->assertStringContainsString('จำนวนเต็มเท่านั้น', $response->json('message'));
    }

    public function test_fix_asset_purchase_price_under_5000_rejected(): void
    {
        $payload = [
            [
                'cost_center_name' => 'CC1',
                'item_name' => 'Thermometer',
                'purchase_price' => 2500,
                'monthly_data' => [
                    'jan' => ['headcount' => 1, 'cost' => 2500],
                ],
            ],
        ];

        $response = $this->actingAs($this->getTestUser())->postJson('/add_medical_equipment', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'error',
        ]);
        $this->assertStringContainsString('5,000 บาทขึ้นไป', $response->json('message'));
    }

    public function test_budget_add_and_update_document_saves_with_status_pending(): void
    {
        $adminUser = new User(['username' => 'admin']);
        $adminUser->is_superuser = true;
        $adminUser->is_admin = true;
        $adminUser->bank_account_note = 'IT-ADMIN';

        $session = [
            'branch_info' => [
                'branches' => ['HO'],
                'needs_selection' => false,
                'department_id' => 'IT',
                'is_admin' => true,
            ],
        ];

        $docNo = null;

        try {
            $payload = [
                [
                    'branch_code' => 'HO',
                    'cost_center_name' => 'Center',
                    'position_name' => 'สัตวแพทย์เฉพาะทาง - คลินิกผิวหนัง',
                    'description' => 'Test VET Creation',
                    'salary' => 50000,
                    'monthly_data' => [
                        'jan' => ['headcount' => 1, 'cost' => 50000, 'salary' => 50000],
                    ],
                ],
            ];

            // 1. Create Document via POST /add
            $response = $this->actingAs($adminUser)->withSession($session)->postJson('/add', $payload);
            $response->assertStatus(200);
            $response->assertJson(['status' => 'success']);

            // 2. Verify in PostgreSQL that status is 'PENDING'
            $createdRow = \App\Models\VetManpower::where('create_eid', 'admin')
                ->orderBy('id', 'desc')
                ->first();

            $this->assertNotNull($createdRow, 'Created VetManpower row must exist in PostgreSQL');
            $this->assertSame('PENDING', $createdRow->status, 'status must be PENDING on save');
            $docNo = $createdRow->document_no;
            $this->assertNotEmpty($docNo);

            // 3. Verify getDocumentDetail API
            $detailResp = $this->actingAs($adminUser)->withSession($session)->getJson("/api/document/VET/{$docNo}");
            $detailResp->assertStatus(200);
            $detailResp->assertJson([
                'status' => 'success',
                'doc_info' => [
                    'status' => 'PENDING',
                    'document_no' => $docNo,
                ],
            ]);

            // 4. Update Document via POST /api/document/update/VET/{docNo}
            $updatePayload = [
                [
                    'id' => $createdRow->id,
                    'branch_code' => 'HO',
                    'cost_center_name' => 'Center',
                    'position_name' => 'สัตวแพทย์เฉพาะทาง - คลินิกผิวหนัง',
                    'description' => 'Updated Description Test',
                    'salary' => 55000,
                    'monthly_data' => [
                        'jan' => ['headcount' => 1, 'cost' => 55000, 'salary' => 55000],
                    ],
                ],
            ];

            $updateResp = $this->actingAs($adminUser)->withSession($session)->postJson("/api/document/update/VET/{$docNo}", $updatePayload);
            $updateResp->assertStatus(200);
            $updateResp->assertJson(['status' => 'success']);

            $updatedRow = $createdRow->fresh();
            $this->assertSame('Updated Description Test', $updatedRow->description);
            $this->assertEquals(55000, (float) $updatedRow->salary);
            $this->assertSame('PENDING', $updatedRow->status);
        } finally {
            // Clean up test data
            if ($docNo) {
                $rows = \App\Models\VetManpower::where('document_no', $docNo)->get();
                foreach ($rows as $r) {
                    \App\Models\BudgetMonthlyDetail::where('vet_budget_id', $r->id)->delete();
                    $r->delete();
                }
            }
        }
    }

    public function test_expense_add_and_update_document_persists_to_database(): void
    {
        $adminUser = new User(['username' => 'admin']);
        $adminUser->is_superuser = true;
        $adminUser->is_admin = true;
        $adminUser->bank_account_note = 'IT-ADMIN';

        $session = [
            'branch_info' => [
                'branches' => ['HO'],
                'needs_selection' => false,
                'department_id' => 'IT',
                'is_admin' => true,
            ],
        ];

        $docNo = null;

        try {
            // Pick a non-depreciation GL code
            $gl = \App\Models\GeneralLedgerMaster::where('gl_name', 'not like', 'ค่าเสื่อม%')->first();
            $glCode = $gl ? $gl->gl_code : '60100001';

            $payload = [
                [
                    'branch_code' => 'HO',
                    'cost_center_name' => 'Center',
                    'general_ledger_code' => $glCode,
                    'detail_note' => 'Initial Expense Note Test',
                    'monthly_data' => [
                        'jan' => ['amount' => 12000, 'cumulative_amount' => 12000, 'detail_note' => 'Jan Note'],
                    ],
                ],
            ];

            // 1. Create Document via POST /add_gl_entry
            $response = $this->actingAs($adminUser)->withSession($session)->postJson('/add_gl_entry', $payload);
            $response->assertStatus(200);
            $response->assertJson(['status' => 'success']);

            // 2. Verify in PostgreSQL that GlEntry and monthly data were inserted
            $createdRow = \App\Models\GlEntry::where('create_eid', 'admin')
                ->where('detail_note', 'Initial Expense Note Test')
                ->orderBy('id', 'desc')
                ->first();

            $this->assertNotNull($createdRow, 'Created GlEntry row must exist in PostgreSQL');
            $this->assertSame('PENDING', $createdRow->status);
            $this->assertSame($glCode, $createdRow->general_ledger_code);
            $docNo = $createdRow->document_no;
            $this->assertNotEmpty($docNo);

            $monthlyRow = \App\Models\GlEntryMonthlyDetail::where('gl_entry_id', $createdRow->id)
                ->where('month', 1)
                ->first();
            $this->assertNotNull($monthlyRow, 'Created GlEntryMonthlyDetail must exist in PostgreSQL');
            $this->assertEquals(12000, (float) $monthlyRow->amount);

            // 3. Update Document via POST /api/document/update/GL Entry/{docNo}
            $updatePayload = [
                [
                    'id' => $createdRow->id,
                    'branch_code' => 'HO',
                    'cost_center_name' => 'Center',
                    'general_ledger_code' => $glCode,
                    'detail_note' => 'Updated Expense Note In Database',
                    'monthly_data' => [
                        'jan' => ['amount' => 25000, 'cumulative_amount' => 25000, 'detail_note' => 'Jan Updated Note'],
                    ],
                ],
            ];

            $updateResp = $this->actingAs($adminUser)->withSession($session)->postJson("/api/document/update/GL Entry/{$docNo}", $updatePayload);
            $updateResp->assertStatus(200);
            $updateResp->assertJson(['status' => 'success']);

            // 4. Verify in PostgreSQL that changes persisted
            $updatedRow = $createdRow->fresh();
            $this->assertSame('Updated Expense Note In Database', $updatedRow->detail_note);
            $this->assertSame('PENDING', $updatedRow->status);

            $updatedMonthly = \App\Models\GlEntryMonthlyDetail::where('gl_entry_id', $createdRow->id)
                ->where('month', 1)
                ->first();
            $this->assertNotNull($updatedMonthly);
            $this->assertEquals(25000, (float) $updatedMonthly->amount);
            $this->assertSame('Jan Updated Note', $updatedMonthly->detail_note);
        } finally {
            // Clean up test data
            if ($docNo) {
                $rows = \App\Models\GlEntry::where('document_no', $docNo)->get();
                foreach ($rows as $r) {
                    \App\Models\GlEntryMonthlyDetail::where('gl_entry_id', $r->id)->delete();
                    $r->delete();
                }
            }
        }
    }
}
