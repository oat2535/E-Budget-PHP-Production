<?php

namespace Tests\Feature;

use App\Models\ToolsEquipment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentEditAuditTest extends TestCase
{
    public function test_newly_added_line_item_during_edit_records_editor_as_create_eid_and_preserves_doc_create_date(): void
    {
        // 1. Create a document originally created by 'user_creator' in the past
        $originalDate = Carbon::create(2026, 11, 6, 11, 10, 0, 'Asia/Bangkok');
        $docNo = 'TEST-TEQ-AUDIT-001';

        $oldIds = ToolsEquipment::where('document_no', $docNo)->pluck('id');
        \App\Models\BudgetMonthlyDetail::whereIn('tools_equip_id', $oldIds)->delete();
        ToolsEquipment::where('document_no', $docNo)->delete();

        $item1 = ToolsEquipment::create([
            'document_no' => $docNo,
            'item_name' => 'ตู้เป่าขนสัตว์ ขนาดL 67*106*105cm',
            'purchase_price' => 25000,
            'base_branch_id' => 'R5',
            'department_id' => 1,
            'cost_center_name' => 'PC (Pet Wellness+Pet Shop+GM+Pet Pool)',
            'budget_year' => 2026,
            'create_eid' => 'user_creator',
            'modify_eid' => 'user_creator',
            'status' => ToolsEquipment::STATUS_PENDING,
        ]);
        $item1->timestamps = false;
        $item1->create_date = $originalDate;
        $item1->modify_date = $originalDate;
        $item1->save();
        $item1->timestamps = true;

        // 2. Editor user 'editor_user' edits the document and adds a new item 'เครื่องซักผ้า'
        $editor = new User(['username' => 'editor_user']);
        $editor->bank_account_note = 'IT-ADMIN';

        $payload = [
            [
                'id' => $item1->id,
                'item_name' => 'ตู้เป่าขนสัตว์ ขนาดL 67*106*105cm',
                'purchase_price' => 25000,
                'cost_center_name' => 'PC (Pet Wellness+Pet Shop+GM+Pet Pool)',
                'monthly_data' => [
                    'jan' => ['headcount' => 1, 'cost' => 25000],
                ],
            ],
            [
                'id' => null, // newly added row
                'item_name' => 'เครื่องซักผ้า',
                'purchase_price' => 15000,
                'cost_center_name' => 'PC (Pet Wellness+Pet Shop+GM+Pet Pool)',
                'monthly_data' => [
                    'feb' => ['headcount' => 1, 'cost' => 15000],
                ],
            ],
        ];

        $response = $this->actingAs($editor)->postJson("/api/document/update/Tools%20%26%20Equipment/{$docNo}", $payload);
        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // 3. Verify in database
        $items = ToolsEquipment::where('document_no', $docNo)->orderBy('id')->get();
        $this->assertCount(2, $items);

        $dbItem1 = $items->firstWhere('item_name', 'ตู้เป่าขนสัตว์ ขนาดL 67*106*105cm');
        $this->assertEquals('user_creator', $dbItem1->create_eid);
        $this->assertEquals('editor_user', $dbItem1->modify_eid);

        $dbItem2 = $items->firstWhere('item_name', 'เครื่องซักผ้า');
        $this->assertNotNull($dbItem2);
        // Newly added row MUST have create_eid set to editor_user
        $this->assertEquals('editor_user', $dbItem2->create_eid);
        $this->assertEquals('editor_user', $dbItem2->modify_eid);

        // 4. Verify getDocuments API (Budget List)
        $listResponse = $this->actingAs($editor)->getJson('/api/get_documents/C02');
        $listResponse->assertStatus(200);

        $listData = collect($listResponse->json('data'));
        $docSummary = $listData->firstWhere('document_no', $docNo);

        $this->assertNotNull($docSummary);
        // Date created on budget list MUST remain the original creation date (06/11/2026), not modified date
        $this->assertEquals('06/11/2026 11:10', $docSummary['create_date']);
        // Created by on budget list MUST remain user_creator
        $this->assertEquals('user_creator', $docSummary['create_eid']);

        // Clean up
        $oldIds = ToolsEquipment::where('document_no', $docNo)->pluck('id');
        \App\Models\BudgetMonthlyDetail::whereIn('tools_equip_id', $oldIds)->delete();
        ToolsEquipment::where('document_no', $docNo)->delete();
    }
}
