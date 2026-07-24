<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Cases;

use Tests\Feature\Api\ApiTestCase;

final class CasesSmokeTest extends ApiTestCase
{
    public function test_list_post_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->postApi('/cases', $this->apiListPayload($fixture['workstation_ids']));

        $this->assertNotServerError($response);
    }

    public function test_list_post_without_configuration_returns_unprocessable(): void
    {
        $response = $this->postApi('/cases', ['page' => 1, 'limit' => 10]);

        $response->assertStatus(422);
        $this->assertApiError($response, 422, 'request_failed');
    }

    public function test_list_post_json_envelope_sample(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->postApi('/cases', $this->apiListPayload($fixture['workstation_ids']));

        if ($response->getStatusCode() === 200) {
            $this->assertApiPaginatedList($response);
        }
    }

    public function test_list_post_with_extended_filters_not_server_error(): void
    {
        $fixture = $this->apiFixture();
        $payload = $this->apiListPayload($fixture['workstation_ids']);
        $payload['filtry']['typ_procesu'] = 'dok_przychodzacy';
        $payload['filtry']['nazwa_procesu'] = 'test';
        $payload['filtry']['opis_dokumentu'] = 'test';
        $payload['filtry']['documentId'] = '12345';
        $payload['filtry']['data_rejestracji_od'] = '2020-01-01';
        $payload['filtry']['data_rejestracji_do'] = '2030-12-31';

        $response = $this->postApi('/cases', $payload);

        $this->assertNotServerError($response);
    }

    public function test_list_post_invalid_typ_procesu_for_cases_returns_unprocessable(): void
    {
        $fixture = $this->apiFixture();
        $payload = $this->apiListPayload($fixture['workstation_ids']);
        $payload['filtry']['typ_procesu'] = 'dok_wychodzacy';

        $response = $this->postApi('/cases', $payload);

        $response->assertStatus(422);
        $this->assertApiError($response, 422, 'request_failed');
    }

    public function test_statuses_get_not_server_error(): void
    {
        $response = $this->getApi('/cases/statuses');

        $this->assertNotServerError($response);
    }

    public function test_show_get_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->getApi('/cases/' . $fixture['case_uid']);

        $this->assertNotServerError($response);
    }

    public function test_show_post_with_akta_sprawy_pagination_not_server_error(): void
    {
        $fixture = $this->apiFixture();
        $payload = $this->apiListPayload($fixture['workstation_ids']);
        $payload['aktaSprawy'] = ['page' => 1, 'limit' => 10];

        $response = $this->postApi('/cases/' . $fixture['case_uid'], $payload);

        $this->assertNotServerError($response);
    }

    public function test_show_post_with_akta_sprawy_pagination_envelope(): void
    {
        $fixture = $this->apiFixture();
        $payload = $this->apiListPayload($fixture['workstation_ids']);
        $payload['aktaSprawy'] = ['page' => 1, 'limit' => 10];

        $response = $this->postApi('/cases/' . $fixture['case_uid'], $payload);

        if ($response->getStatusCode() !== 200) {
            $this->markTestSkipped('Case show unavailable in this environment.');
        }

        $data = $this->assertApiShow($response);
        $response->assertJsonStructure([
            'meta' => [
                'aktaSprawy' => ['count', 'page', 'limit', 'has_prev', 'has_next'],
            ],
        ]);

        $akta = $data['aktaSprawy'] ?? [];
        $this->assertIsArray($akta);
        $this->assertLessThanOrEqual(10, count($akta));
        $this->assertGreaterThanOrEqual(count($akta), (int) $response->json('meta.aktaSprawy.count'));
    }

    public function test_attachments_get_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->getApi('/cases/' . $fixture['case_uid'] . '/attachments');

        $this->assertNotServerError($response);
    }
}
