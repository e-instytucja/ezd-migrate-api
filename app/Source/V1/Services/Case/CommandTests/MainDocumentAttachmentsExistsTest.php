<?php
declare(strict_types=1);

namespace App\Source\V1\Services\Case\CommandTests;

use App\Source\V1\Services\Case\CaseService;
use App\Source\V1\Services\Attachment\AttachmentService;
use DateTimeImmutable;
use RuntimeException;

class MainDocumentAttachmentsExistsTest
{
    public function __construct(
        private readonly CaseService $caseService,
        private readonly AttachmentService $attachmentService   
    ) {
    }

    /**
     * @return array{
     *     scanned: int,
     *     total_rows: int,
     *     scanned_attachments: int,
     *     missing_in_eurzad_zalacznik: int,
     *     missing_on_disk: int,
     *     invalid_rows: int,
     *     ok: int,
     *     report_file: string
     * }
     */
    public function testMainDocumentAttachmentsExists(
        int $limit = 0,
        int $offset = 0,
        ?callable $onStart = null,
        ?callable $onProgress = null
    ): array
    {
        $reportFile = $this->createReportLogFilePath();
        $handle = fopen($reportFile, 'ab');
        if ($handle === false) {
            throw new RuntimeException('Cannot open report file: ' . $reportFile);
        }

        $totalRows = $this->caseService->countMainDocumentAttachmentsAuditCandidates($limit, $offset);

        $report = [
            'scanned' => 0,
            'total_rows' => $totalRows,
            'scanned_attachments' => 0,
            'missing_in_eurzad_zalacznik' => 0,
            'missing_on_disk' => 0,
            'invalid_rows' => 0,
            'ok' => 0,
            'report_file' => $reportFile,
        ];

        try {
            if ($onStart !== null) {
                $onStart($totalRows);
            }

            $this->writeLogLine($handle, [
                'type' => 'meta',
                'started_at' => (new DateTimeImmutable())->format(DATE_ATOM),
                'limit' => $limit,
                'offset' => $offset,
                'total_rows' => $totalRows,
            ]);

            foreach ($this->caseService->streamMainDocumentAttachmentsAuditCandidates($limit, $offset) as $row) {
                $report['scanned']++;
                if ($onProgress !== null) {
                    $onProgress($report['scanned'], $totalRows);
                }

                if (empty($row['form_dane_wartosc'])) {
                    //to nie błąd.
                    continue;
                }

                $attachments = $this->attachmentService->getAttachmentsDetails((string) $row['form_dane_wartosc']);

                if (empty($attachments)) {
                    $report['invalid_rows']++;
                    $this->writeLogLine($handle, [
                        'status' => 'invalid_row',
                        'reason' => 'No attachments parsed from form_dane_wartosc',
                        'main_document_uid' => $row['main_document_uid'] ?? null,
                    ]);
                    continue;
                }

                foreach ($attachments as $attachmentDetails) {
                    $report['scanned_attachments']++;

                    $auditRow = [
                        'main_document_uid' => $row['main_document_uid'] ?? null,
                        'attachment_uid' => $attachmentDetails->uid,
                        'filename' => $attachmentDetails->filename,
                        'attachment_createdate' => $attachmentDetails->dataUtworzenia,
                        'attachment_foreign_uid' => $attachmentDetails->zalacznikObcyUid,
                    ];

                    try {
                        $path = $this->attachmentService->buildAttachmentPath(
                            basePath: (string) env('FILES_URL'),
                            createdAt: $attachmentDetails->dataUtworzenia ?? '',
                            foreignUid: $attachmentDetails->zalacznikObcyUid ?? '',
                            storedFilename: $attachmentDetails->filename
                        );
                    } catch (RuntimeException $e) {
                        $report['invalid_rows']++;
                        $this->writeLogLine($handle, [
                            'status' => 'invalid_row',
                            'reason' => 'Cannot build attachment path',
                            'error' => $e->getMessage(),
                            'main_document_uid' => $auditRow['main_document_uid'],
                            'attachment_uid' => $auditRow['attachment_uid'],
                        ]);
                        continue;
                    }

                    if (!is_file($path) || !is_readable($path)) {
                        $report['missing_on_disk']++;
                        $this->writeLogLine($handle, [
                            'status' => 'missing_on_disk',
                            'main_document_uid' => $auditRow['main_document_uid'],
                            'attachment_uid' => $auditRow['attachment_uid'],
                            'path' => $path,
                        ]);
                        continue;
                    }

                    $report['ok']++;
                }
            }
        } finally {
            $this->writeLogLine($handle, [
                'type' => 'summary',
                'finished_at' => (new DateTimeImmutable())->format(DATE_ATOM),
                'scanned' => $report['scanned'],
                'scanned_attachments' => $report['scanned_attachments'],
                'ok' => $report['ok'],
                'invalid_rows' => $report['invalid_rows'],
                'missing_in_eurzad_zalacznik' => $report['missing_in_eurzad_zalacznik'],
                'missing_on_disk' => $report['missing_on_disk'],
            ]);

            fclose($handle);
        }

        return $report;
    }

    private function createReportLogFilePath(): string
    {
        $logDir = storage_path('logs');
        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            throw new RuntimeException('Cannot create log directory: ' . $logDir);
        }

        $timestamp = (new DateTimeImmutable())->format('Ymd_His');
        return $logDir . DIRECTORY_SEPARATOR . 'attachments_audit_' . $timestamp . '.ndjson';
    }

    private function writeLogLine($handle, array $line): void
    {
        $json = json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Cannot encode audit report line as JSON');
        }

        if (fwrite($handle, $json . PHP_EOL) === false) {
            throw new RuntimeException('Cannot write report line');
        }
    }
}
