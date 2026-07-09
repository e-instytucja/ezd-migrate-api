<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Attachment;

use Illuminate\Support\Facades\DB;

final class AttachmentQuery
{
    private string $tableName = 'eurzad_zalacznik';

    private string $epuapDownloadTableName = 'epuap_download_file';

    public function getAttachmentRows(array $attachmentUids)
    {
        $rows = DB::table($this->tableName)
            ->whereIn('zalacznik_uid', $attachmentUids)
            ->get();

        return $rows;
    }

    public function getAttachmentRow(string $zalacznikUid): ?object
    {
        $row = DB::table($this->tableName)
            ->where('zalacznik_uid', $zalacznikUid)
            ->first();

        return $row ?: null;
    }

    public function getEpuapDownloadFileRowByFileId(string $fileId): ?object
    {
        $row = DB::table($this->epuapDownloadTableName)
            ->where('file_id', $fileId)
            ->orderBy('id')
            ->first();

        return $row ?: null;
    }

    public function countEpuapDownloadFileRowsByFileId(string $fileId): int
    {
        return DB::table($this->epuapDownloadTableName)
            ->where('file_id', $fileId)
            ->count();
    }

    // Opcjonalna walidacja pary file_id + zalacznik_uid (tymczasowo nieużywana w API):
    // public function getEpuapDownloadFileRow(string $fileId, string $zalacznikUid): ?object
    // {
    //     $row = DB::table($this->epuapDownloadTableName)
    //         ->where('file_id', $fileId)
    //         ->where('zalacznik_uid', $zalacznikUid)
    //         ->first();
    //
    //     return $row ?: null;
    // }
}
