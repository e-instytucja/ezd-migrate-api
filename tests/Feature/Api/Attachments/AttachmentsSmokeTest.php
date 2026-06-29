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
}
