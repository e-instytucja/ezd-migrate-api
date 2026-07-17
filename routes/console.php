<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('attachments:test-main-document-attachments-exists')->dailyAt('02:00');

Artisan::command(
    'cases:refresh-list-mv
    {--drop : DROP MATERIALIZED VIEW IF EXISTS przed ponownym CREATE}',
    function (): int {
        /** @var \Illuminate\Console\Command $this */
        $drop = (bool) $this->option('drop');

        try {
            $report = app(\App\Source\V1\Services\Case\CaseListMvRefreshService::class)
                ->refresh($drop);
        } catch (\Throwable $e) {
            $this->error('Nie udalo sie odswiezyc api_case_list: ' . $e->getMessage());

            return \Illuminate\Console\Command::FAILURE;
        }

        $this->info('=== cases:refresh-list-mv ===');
        $this->line('created: ' . ($report['created'] ? 'yes' : 'no'));
        $this->line('refreshed: ' . ($report['refreshed'] ? 'yes' : 'no'));
        $this->line('row_count: ' . $report['row_count']);
        $this->line('elapsed_ms: ' . $report['elapsed_ms']);

        return \Illuminate\Console\Command::SUCCESS;
    }
)->purpose('Tworzy lub odswieza materialized view api_case_list (listy spraw).');

Artisan::command(
    'documents:refresh-list-mv
    {--drop : DROP MATERIALIZED VIEW IF EXISTS przed ponownym CREATE}',
    function (): int {
        /** @var \Illuminate\Console\Command $this */
        $drop = (bool) $this->option('drop');

        try {
            $report = app(\App\Source\V1\Services\Document\DocumentListMvRefreshService::class)
                ->refresh($drop);
        } catch (\Throwable $e) {
            $this->error('Nie udalo sie odswiezyc api_document_list: ' . $e->getMessage());

            return \Illuminate\Console\Command::FAILURE;
        }

        $this->info('=== documents:refresh-list-mv ===');
        $this->line('created: ' . ($report['created'] ? 'yes' : 'no'));
        $this->line('refreshed: ' . ($report['refreshed'] ? 'yes' : 'no'));
        $this->line('row_count: ' . $report['row_count']);
        $this->line('elapsed_ms: ' . $report['elapsed_ms']);

        return \Illuminate\Console\Command::SUCCESS;
    }
)->purpose('Tworzy lub odswieza materialized view api_document_list (listy dokumentow).');

Artisan::command(
    'materialized-views:refresh
    {--drop : DROP MATERIALIZED VIEW IF EXISTS przed ponownym CREATE dla kazdego widoku}',
    function (): int {
        /** @var \Illuminate\Console\Command $this */
        $drop = (bool) $this->option('drop');
        $registry = app(\App\Source\V1\Support\MaterializedViews\MaterializedViewRegistry::class);
        $failed = false;

        $this->info('=== materialized-views:refresh ===');

        foreach ($registry->definitions() as $definition) {
            $this->line('');
            $this->line('--- ' . $definition->name . ' (' . $definition->refreshCommand . ') ---');

            try {
                $report = match ($definition->key) {
                    'case_list' => app(\App\Source\V1\Services\Case\CaseListMvRefreshService::class)->refresh($drop),
                    'document_list' => app(\App\Source\V1\Services\Document\DocumentListMvRefreshService::class)->refresh($drop),
                    default => throw new \RuntimeException('Nieznany widok: ' . $definition->key),
                };
            } catch (\Throwable $e) {
                $this->error('Blad: ' . $e->getMessage());
                $failed = true;
                continue;
            }

            $this->line('created: ' . ($report['created'] ? 'yes' : 'no'));
            $this->line('refreshed: ' . ($report['refreshed'] ? 'yes' : 'no'));
            $this->line('row_count: ' . $report['row_count']);
            $this->line('elapsed_ms: ' . $report['elapsed_ms']);
        }

        return $failed ? \Illuminate\Console\Command::FAILURE : \Illuminate\Console\Command::SUCCESS;
    }
)->purpose('Tworzy lub odswieza wszystkie materialized views list API (api_case_list, api_document_list).');
