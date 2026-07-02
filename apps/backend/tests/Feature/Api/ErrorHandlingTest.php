<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_unknown_api_route_returns_standardized_404_json(): void
    {
        $response = $this->getJson('/api/nonexistent-route');

        $response->assertStatus(404);
        $response->assertJson([
            'error' => [
                'code' => 'not_found',
            ],
        ]);
    }
}
