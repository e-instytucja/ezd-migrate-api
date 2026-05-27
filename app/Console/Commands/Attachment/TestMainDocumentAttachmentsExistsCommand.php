<?php
declare(strict_types=1);

namespace App\Console\Commands\Attachment;

use App\Source\V1\Services\Case\CommandTests\MainDocumentAttachmentsExistsTest;
use Illuminate\Console\Command;
use Throwable;

class TestMainDocumentAttachmentsExistsCommand extends Command
{
    protected $signature = 'attachments:test-main-document-attachments-exists
                            {--limit=0 : Max liczba rekordow do sprawdzenia}
                            {--offset=0 : Offset startowy}';

    protected $description = 'Weryfikuje powiazania zalacznikow pism wiodacych i istnienie plikow na dysku.';

    public function __construct(
        private readonly MainDocumentAttachmentsExistsTest $testService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $progressBar = null;

        try {
            $report = $this->testService->testMainDocumentAttachmentsExists(
                $limit,
                $offset,
                function (int $totalRows) use (&$progressBar): void {
                    if ($totalRows <= 0) {
                        return;
                    }

                    $progressBar = $this->output->createProgressBar($totalRows);
                    $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
                    $progressBar->start();
                },
                function (int $scanned, int $totalRows) use (&$progressBar): void {
                    if ($progressBar === null || $totalRows <= 0) {
                        return;
                    }

                    $progressBar->setProgress($scanned);
                }
            );
        } catch (Throwable $e) {
            $this->error('Nie udalo sie wykonac testu: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($progressBar !== null) {
            $progressBar->finish();
            $this->newLine(2);
        }

        $this->newLine();
        $this->info('=== testMainDocumentAttachmentsExists() ===');
        $this->line('Scanned: ' . $report['scanned']);
        $this->line('Total rows: ' . ($report['total_rows'] ?? 0));
        $this->line('Scanned attachments: ' . ($report['scanned_attachments'] ?? 0));
        $this->line('OK: ' . $report['ok']);
        $this->line('Missing in eurzad_zalacznik: ' . $report['missing_in_eurzad_zalacznik']);
        $this->line('Missing on disk: ' . $report['missing_on_disk']);
        $this->line('Invalid rows: ' . $report['invalid_rows']);

        if ($report['scanned'] === 0) {
            $this->warn('Brak rekordow do testu. Uzupełnij CaseService::getMainDocumentAttachmentsAuditCandidates().');
        }

        $this->newLine();
        $this->info('Raport zapisano do pliku: ' . ($report['report_file'] ?? 'n/a'));

        return self::SUCCESS;
    }
}
