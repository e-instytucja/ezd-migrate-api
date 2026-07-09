<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Attachments;

use Tests\Feature\Api\ApiTestCase;

final class AttachmentsSmokeTest extends ApiTestCase
{
    public function test_show_get_returns_file_not_json_envelope(): void
    {
        $fixture = $this->apiFixture();

        $response = $this->get('/api/v1/attachments/' . $fixture['attachment_uid']);

        $this->assertNotServerError($response);
        $response->assertSuccessful();
        $contentType = (string) $response->headers->get('Content-Type');
        $this->assertStringNotContainsString('application/json', $contentType);
    }

    public function test_show_epuap_get_with_invalid_file_id_returns_not_found(): void
    {
        $response = $this->get('/api/v1/attachments/epuap/invalid-epuap-file-id');

        $this->assertNotServerError($response);
        $response->assertNotFound();
        $response->assertJson([
            'success' => false,
            'status_code' => 404,
        ]);
    }

    public function test_show_epuap_get_with_fixture_file_id_returns_stream_or_pending(): void
    {
        $fixture = $this->apiFixture();

        if (empty($fixture['epuap_file_id'])) {
            $this->markTestSkipped('Brak fixture epuap_file_id w tests/Fixtures/api.php');
        }

        $response = $this->get('/api/v1/attachments/epuap/' . $fixture['epuap_file_id']);

        $this->assertNotServerError($response);
        $this->assertContains($response->status(), [200, 409]);

        if ($response->status() === 200) {
            $contentType = (string) $response->headers->get('Content-Type');
            $this->assertStringNotContainsString('application/json', $contentType);
        }

        if ($response->status() === 409) {
            $response->assertJson([
                'success' => false,
                'status_code' => 409,
            ]);
            $this->assertStringContainsString(
                'oczekuje na pobranie',
                (string) $response->json('message')
            );
        }
    }

    public function test_show_epuap_with_zalacznik_uid_get_returns_same_as_single_param_route(): void
    {
        $fixture = $this->apiFixture();

        if (empty($fixture['epuap_file_id'])) {
            $this->markTestSkipped('Brak fixture epuap_file_id w tests/Fixtures/api.php');
        }

        $zalacznikUid = $fixture['attachment_uid'] ?? '0000000000000';
        $fileId = $fixture['epuap_file_id'];

        $response = $this->get('/api/v1/attachments/epuap/' . $zalacznikUid . '/' . $fileId);

        $this->assertNotServerError($response);
        $this->assertContains($response->status(), [200, 409]);

        if ($response->status() === 200) {
            $contentType = (string) $response->headers->get('Content-Type');
            $this->assertStringNotContainsString('application/json', $contentType);
        }
    }
}
