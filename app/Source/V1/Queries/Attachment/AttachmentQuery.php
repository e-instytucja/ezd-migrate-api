<?php
declare(strict_types=1);
namespace App\Source\V1\Queries\Attachment;

use Illuminate\Support\Facades\DB;

final class AttachmentQuery {

    private string $tableName = 'eurzad_zalacznik';

    public function getAttachmentRows(array $attachmentUids)
    {
        $rows = DB::table($this->tableName)
            ->whereIn('zalacznik_uid', $attachmentUids)
            ->get();

        return $rows;
    }
}