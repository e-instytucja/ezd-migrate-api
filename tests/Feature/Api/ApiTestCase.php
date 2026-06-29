<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\Concerns\AssertsApiEnvelope;
use Tests\TestCase;

/**
 * Testy endpointów wyłącznie przez HTTP — bez ingerencji w strukturę ani dane bazy EZD.
 */
abstract class ApiTestCase extends TestCase
{
    use AssertsApiEnvelope;

    /**
     * @param int[] $workstationIds
     *
     * @return array<string, mixed>
     */
    protected function apiListPayload(array $workstationIds = [142], int $page = 1, int $limit = 10): array
    {
        return [
            'konfiguracja' => [
                'madkomWorkstationIds' => $workstationIds,
            ],
            'page' => $page,
            'limit' => $limit,
        ];
    }

    protected function getApi(string $uri): TestResponse
    {
        return $this->getJson('/api/v1' . $uri);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function postApi(string $uri, array $payload = []): TestResponse
    {
        return $this->postJson('/api/v1' . $uri, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function apiFixture(): array
    {
        return require __DIR__ . '/../../Fixtures/api.php';
    }

    /**
     * @return array<string, mixed>
     */
    protected function registryFixture(): array
    {
        return require __DIR__ . '/../../Fixtures/registry_assignments.php';
    }
}
