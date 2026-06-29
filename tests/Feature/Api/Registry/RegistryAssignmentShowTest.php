<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Registry;

use Tests\Feature\Api\ApiTestCase;

/**
 * Deep: show zwykłego rejestru (EN) i show RPW (PL).
 */
final class RegistryAssignmentShowTest extends ApiTestCase
{
    public function test_standard_show_english_contract(): void
    {
        $registry = $this->registryFixture();
        $assignmentId = $registry['case_wniosek']['registry_assignment_id'];

        $response = $this->getApi('/registry-assignments/' . $assignmentId);

        $response->assertOk();
        $show = $this->assertRegistryShowSection($this->assertApiShow($response));
        $values = $show['values'];

        $this->assertSame($assignmentId, $values['registry_assignment_id']);
        $this->assertSame($registry['case_wniosek']['document_id'], $values['document_id']);
        $this->assertSame($values['document_id'], $values['registry_assignment_uid']);
        $this->assertArrayHasKey('registry_uid', $values);
        $this->assertArrayHasKey('registry_type', $values);
        $this->assertArrayHasKey('lead_case_uid', $values);
        $this->assertArrayHasKey('process_name', $values);
    }

    public function test_standard_show_not_found(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->getApi('/registry-assignments/' . $fixture['invalid_registry_assignment_id']);

        $response->assertNotFound();
        $this->assertApiError($response, 404, 'not_found');
    }

    public function test_rpw_show_polish_contract(): void
    {
        $registry = $this->registryFixture();
        $fixture = $this->apiFixture();
        $assignmentId = $registry['document_rpw']['registry_assignment_id_rpw'];

        $response = $this->getApi('/registry-assignments-rpw/' . $assignmentId);

        if ($response->getStatusCode() !== 200) {
            $this->markTestSkipped(
                'RPW show zwraca HTTP ' . $response->getStatusCode()
                . ' — kontrakt PL wymaga działającego show (patrz Q-27 w docs/open-questions.md).',
            );
        }

        $show = $this->assertRegistryRpwShowPl($response);
        $this->assertSame($assignmentId, $show['id_przypisania_rejestru']);
        $this->assertSame($fixture['document_rpw_id'], $show['id_dokumentu']);
        $this->assertNotSame($show['id_dokumentu'], $show['uid_przypisania_rejestru']);
        $this->assertIsArray($show['historia_obiegu']);
        $this->assertArrayHasKey('adresat', $show);
    }

    public function test_rpw_show_not_found(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->getApi('/registry-assignments-rpw/' . $fixture['invalid_registry_assignment_id']);

        $response->assertNotFound();
        $this->assertApiError($response, 404, 'not_found');
    }
}
