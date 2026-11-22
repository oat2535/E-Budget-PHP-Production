<?php

namespace Tests\Feature;

use Tests\TestCase;

class DiagnosisTest extends TestCase
{
    public function test_dianosis_url_renders_html_with_php_and_laravel_versions(): void
    {
        $response = $this->get('/dianosis');

        $response->assertStatus(200);
        $response->assertSee('PHP Version');
        $response->assertSee('Laravel Framework');
        $response->assertSee(PHP_VERSION);
        $response->assertSee(app()->version());
    }

    public function test_diagnosis_url_renders_successfully(): void
    {
        $response = $this->get('/diagnosis');

        $response->assertStatus(200);
        $response->assertSee('System Diagnostics');
    }

    public function test_dianosis_json_format_returns_correct_metadata(): void
    {
        $response = $this->getJson('/dianosis?format=json');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('databases', $data);
        $this->assertArrayHasKey('extensions', $data);
        $this->assertArrayHasKey('performance', $data);
        // Ensure no sensitive passwords leaked
        $jsonString = json_encode($data);
        $this->assertStringNotContainsString('Tlp@2023', $jsonString);
        $this->assertStringNotContainsString('Tlph@2026', $jsonString);
    }
}
