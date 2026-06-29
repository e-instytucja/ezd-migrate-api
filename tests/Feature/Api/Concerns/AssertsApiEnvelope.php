<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Concerns;

use Illuminate\Testing\TestResponse;

trait AssertsApiEnvelope
{
    protected function assertNotServerError(TestResponse $response): void
    {
        $this->assertNotSame(500, $response->getStatusCode());
    }

    protected function assertApiEnvelope(TestResponse $response, int $status = 200): void
    {
        $response->assertStatus($status);
        $response->assertJsonStructure([
            'success',
            'status_code',
        ]);
        $response->assertJson([
            'success' => $status < 400,
            'status_code' => $status,
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function assertApiList(TestResponse $response, int $status = 200): array
    {
        $this->assertApiEnvelope($response, $status);
        $response->assertJsonStructure(['data']);
        $data = $response->json('data');
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function assertApiShow(TestResponse $response, int $status = 200): array
    {
        $this->assertApiEnvelope($response, $status);
        $response->assertJsonStructure(['data']);
        $data = $response->json('data');
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * @return array<int, mixed>
     */
    protected function assertApiPaginatedList(TestResponse $response, int $status = 200): array
    {
        $data = $this->assertApiList($response, $status);
        $response->assertJsonStructure([
            'meta' => ['page', 'limit', 'count', 'has_prev', 'has_next'],
        ]);

        return $data;
    }

    protected function assertApiError(TestResponse $response, int $status, string $errorCode): void
    {
        $this->assertApiEnvelope($response, $status);
        $response->assertJson([
            'success' => false,
            'error' => $errorCode,
        ]);
    }

    protected function assertRegistryListItemEn(array $item): void
    {
        $this->assertArrayHasKey('registry_assignment_id', $item);
        $this->assertArrayHasKey('registry_assignment_uid', $item);
        $this->assertArrayHasKey('document_id', $item);
        $this->assertArrayHasKey('registry_uid', $item);
    }

    protected function assertRegistryRpwListItemEn(array $item): void
    {
        $this->assertRegistryListItemEn($item);
        $this->assertArrayHasKey('parent_shipment_uid', $item);
    }

    protected function assertRegistryRpwShowPl(TestResponse $response, int $status = 200): array
    {
        $data = $this->assertApiShow($response, $status);
        $response->assertJsonStructure([
            'data' => [
                'id_przypisania_rejestru',
                'uid_przypisania_rejestru',
                'id_dokumentu',
                'wysylka',
                'historia_obiegu',
            ],
        ]);

        return $data;
    }
}
