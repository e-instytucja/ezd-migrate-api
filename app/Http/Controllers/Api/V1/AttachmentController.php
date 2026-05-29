<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\Services\Attachment\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AttachmentController extends BaseApiController
{
    public function __construct(
        private readonly AttachmentService $service,
        ApiResponseRenderer $renderer
    ) {
        parent::__construct($renderer);
    }

    public function show(Request $request, string $attachmentUid): Response
    {
        try {
            $file = $this->service->getAttachmentContent($attachmentUid);
        } catch (RuntimeException $e) {
            return response()->json([
                'error'   => 'not_found',
                'message' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Cannot download attachment.',
            ], 500);
        }

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
                        $chunk = fread($handle, 1024 * 1024); // 1 MB chunk
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
    }

    /**
     * @throws \JsonException
     */
    public function caseAttachments(Request $request, string $caseUid): JsonResponse
    {
        $data = $this->service->getCaseAttachments($caseUid);

        return response()->json([
            'data' => $data,
        ]);
    }


}