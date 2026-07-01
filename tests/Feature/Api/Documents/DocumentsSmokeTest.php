<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Documents;

use Tests\Feature\Api\ApiTestCase;

final class DocumentsSmokeTest extends ApiTestCase
{
    public function test_list_post_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->postApi('/documents', $this->apiListPayload($fixture['workstation_ids']));

        $this->assertNotServerError($response);
    }

    public function test_list_post_without_configuration_returns_unprocessable(): void
    {
        $response = $this->postApi('/documents', ['page' => 1, 'limit' => 10]);

        $response->assertStatus(422);
        $this->assertApiError($response, 422, 'request_failed');
    }

    public function test_list_post_invalid_typ_procesu_returns_unprocessable(): void
    {
        $fixture = $this->apiFixture();
        $payload = $this->apiListPayload($fixture['workstation_ids']);
        $payload['filtry']['typ_procesu'] = 'bogus';

        $response = $this->postApi('/documents', $payload);

        $response->assertStatus(422);
        $this->assertApiError($response, 422, 'request_failed');
    }

    public function test_show_get_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->getApi('/documents/' . $fixture['case_document_id']);

        $this->assertNotServerError($response);
    }

    public function test_attachments_get_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->getApi('/documents/' . $fixture['case_document_id'] . '/attachments');

        $this->assertNotServerError($response);
    }

    public function test_statuses_get_not_server_error(): void
    {
        $response = $this->getApi('/documents/statuses');

        $this->assertNotServerError($response);
    }

    public function test_types_get_not_server_error(): void
    {
        $response = $this->getApi('/documents/types');

        $this->assertNotServerError($response);
    }

    public function test_process_names_post_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->postApi('/documents/process_names', $this->apiListPayload($fixture['workstation_ids']));

        $this->assertNotServerError($response);
    }
}
