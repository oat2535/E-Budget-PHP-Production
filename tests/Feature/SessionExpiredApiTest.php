<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionExpiredApiTest extends TestCase
{
    public function test_get_budget_documents_api_unauthenticated_returns_json_401(): void
    {
        $response = $this->getJson('/api/get_documents/C01');
        $response->assertStatus(401);
    }

    public function test_get_document_detail_api_unauthenticated_returns_json_401(): void
    {
        $response = $this->getJson('/api/document/VET/DOC1');
        $response->assertStatus(401);
    }

    public function test_update_document_api_unauthenticated_returns_json_401(): void
    {
        $response = $this->postJson('/api/document/update/VET/DOC1', []);
        $response->assertStatus(401);
    }
}
