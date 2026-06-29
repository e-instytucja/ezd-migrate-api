<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Registry;

use Tests\Feature\Api\ApiTestCase;

/**
 * Smoke wszystkich tras registry — głównie brak 500.
 */
final class RegistrySmokeTest extends ApiTestCase
{
    public function test_cases_registry_assignments_not_server_error(): void
    {
        $registry = $this->registryFixture();

        $response = $this->getApi('/cases/' . $registry['case_wniosek']['case_uid'] . '/registry-assignments');

        $this->assertNotServerError($response);
    }

    public function test_dntas_registry_assignments_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->getApi('/dntas/' . $fixture['dntas_case_uid'] . '/registry-assignments');

        $this->assertNotServerError($response);
    }

    public function test_documents_registry_assignments_not_server_error(): void
    {
        $registry = $this->registryFixture();

        $response = $this->getApi('/documents/' . $registry['case_wniosek']['document_id'] . '/registry-assignments');

        $this->assertNotServerError($response);
    }

    public function test_documents_registry_assignments_rpw_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->getApi('/documents/' . $fixture['document_rpw_id'] . '/registry-assignments-rpw');

        $this->assertNotServerError($response);
    }

    public function test_global_registry_assignments_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->postApi('/registry-assignments', $this->apiListPayload($fixture['workstation_ids']));

        $this->assertNotServerError($response);
    }

    public function test_global_registry_assignments_rpw_not_server_error(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->postApi(
            '/registry-assignments-rpw',
            $this->apiListPayload($fixture['workstation_ids_rpw']),
        );

        $this->assertNotServerError($response);
    }

    public function test_registry_assignments_show_not_server_error(): void
    {
        $registry = $this->registryFixture();

        $response = $this->getApi('/registry-assignments/' . $registry['case_wniosek']['registry_assignment_id']);

        $this->assertNotServerError($response);
    }

    public function test_registry_assignments_rpw_show_not_server_error(): void
    {
        $registry = $this->registryFixture();

        $response = $this->getApi(
            '/registry-assignments-rpw/' . $registry['document_rpw']['registry_assignment_id_rpw'],
        );

        $this->assertNotServerError($response);
    }

    public function test_registries_types_not_server_error(): void
    {
        $response = $this->getApi('/registries/types');

        $this->assertNotServerError($response);
    }
}
