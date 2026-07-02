<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_api_response_includes_cors_headers_for_configured_frontend_origin(): void
    {
        $origin = config('cors.allowed_origins')[0];

        $response = $this->withHeaders(['Origin' => $origin])->getJson('/api/health');

        $response->assertHeader('Access-Control-Allow-Origin', $origin);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
