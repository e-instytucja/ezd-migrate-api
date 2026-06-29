<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Dntas;

use Tests\Feature\Api\ApiTestCase;

final class DntasSmokeTest extends ApiTestCase
{
    public function test_list_post_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->postApi('/dntas', $this->apiListPayload($fixture['workstation_ids']));

        $this->assertNotServerError($response);
    }

    public function test_statuses_get_not_server_error(): void
    {
        $response = $this->getApi('/dntas/statuses');

        $this->assertNotServerError($response);
    }

    public function test_show_get_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->getApi('/dntas/' . $fixture['dntas_case_uid']);

        $this->assertNotServerError($response);
    }

    public function test_attachments_get_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->getApi('/dntas/' . $fixture['dntas_case_uid'] . '/attachments');

        $this->assertNotServerError($response);
    }
}
