<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\Services\Attachment\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AttachmentController extends BaseApiController
{
    public function __construct(
        private readonly AttachmentService $service,
        ApiResponseRenderer $renderer
    ) {
        parent::__construct($renderer);
    }

    public function show(Request $request, string $attachmentUid): SymfonyResponse
    {
        return $this->executeEndpoint($request, function () use ($attachmentUid): SymfonyResponse {
            $file = $this->service->getAttachmentContent($attachmentUid);

            $safeHeaderFilename = str_replace(["\r", "\n", '"'], '', $file['filename']);
            $headers = [
                'Content-Type' => $file['mime'],
                'Content-Length' => (string) $file['content_length'],
                'Content-Transfer-Encoding' => 'binary',
                'X-File-Extension' => $file['extension'],
                'X-File-MD5' => $file['md5'],
            ];

            return response()->streamDownload(
                static function () use ($file): void {
                    $handle = fopen($file['path'], 'rb');
                    if ($handle === false) {
                        throw new RuntimeException('Cannot open file stream');
                    }

                    try {
                        while (!feof($handle)) {
                            $chunk = fread($handle, 1024 * 1024);
                            if ($chunk === false) {
                                throw new RuntimeException('Cannot read file stream');
                            }

                            echo $chunk;

                            if (function_exists('ob_flush')) {
                                @ob_flush();
                            }
                            flush();
                        }
                    } finally {
                        fclose($handle);
                    }
                },
                $safeHeaderFilename,
                $headers
            );
        });
    }

    public function caseAttachments(Request $request, string $caseUid): Response
    {
        return $this->executeEndpoint($request, function () use ($request, $caseUid): Response {
            return $this->renderCaseAttachments($request, $caseUid, 0);
        });
    }

    public function dntasCaseAttachments(Request $request, string $caseUid): Response
    {
        return $this->executeEndpoint($request, function () use ($request, $caseUid): Response {
            return $this->renderCaseAttachments($request, $caseUid, 1);
        });
    }

    private function renderCaseAttachments(Request $request, string $caseUid, int $dntas): Response
    {
        $data = $this->service->getCaseAttachments($caseUid, $dntas);

        return $this->renderResponse($request, $data);
    }
}
