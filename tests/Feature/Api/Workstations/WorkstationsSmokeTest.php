<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Workstations;

use Tests\Feature\Api\ApiTestCase;

final class WorkstationsSmokeTest extends ApiTestCase
{
    public function test_list_get_not_server_error(): void
    {
        $response = $this->getApi('/workstations');

        $this->assertNotServerError($response);
    }
}
