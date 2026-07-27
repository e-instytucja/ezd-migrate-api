<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use Tests\Feature\Api\ApiTestCase;

final class ApiTokenAuthTest extends ApiTestCase
{
    public function test_missing_token_returns_401_when_configured(): void
    {
        config(['app.madkom_api_token' => 'a1b2c3d4e5f6789012345678abcdef01']);

        $response = $this->getJson('/api/v1/workstations');

        $response->assertStatus(401);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error', 'unauthorized');
    }

    public function test_valid_token_is_accepted(): void
    {
        $token = 'a1b2c3d4e5f6789012345678abcdef01';
        config(['app.madkom_api_token' => $token]);

        $response = $this->withHeader('madkom-api-token', $token)
            ->getJson('/api/v1/workstations');

        $this->assertNotServerError($response);
        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(503, $response->status());
    }

    public function test_empty_token_config_returns_503(): void
    {
        config(['app.madkom_api_token' => '']);

        $response = $this->getJson('/api/v1/workstations');

        $response->assertStatus(503);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error', 'configuration_error');
    }
}
