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
        $file = $this->service->getAttachmentContent($attachmentUid);

        return $this->streamAttachmentFile($file);
    }

    public function showEpuap(Request $request, string $fileId): SymfonyResponse
    {
        $file = $this->service->getEpuapAttachmentContent($fileId);

        return $this->streamAttachmentFile($file);
    }

    public function showEpuapWithZalacznikUid(Request $request, string $zalacznikUid, string $fileId): SymfonyResponse
    {
        return $this->showEpuap($request, $fileId);
    }

    public function caseAttachments(Request $request, string $caseUid): Response
    {
        return $this->renderResponse(
            $request,
            $this->service->getCaseAttachments($caseUid)
        );
    }

    public function dntasCaseAttachments(Request $request, string $caseUid): Response
    {
        return $this->renderResponse(
            $request,
            $this->service->getCaseAttachments($caseUid)
        );
    }

    public function documentAttachments(Request $request, string $documentId): Response
    {
        return $this->renderResponse(
            $request,
            $this->service->getDocumentAttachments($documentId)
        );
    }

    /**
     * @param array{
     *     path: string,
     *     mime: string,
     *     filename: string,
     *     content_length: int,
     *     extension: string,
     *     md5: string
     * } $file
     */
    private function streamAttachmentFile(array $file): SymfonyResponse
    {
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
    }
}
