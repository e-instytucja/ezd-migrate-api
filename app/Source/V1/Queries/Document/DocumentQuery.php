<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use Illuminate\Support\Facades\DB;
use stdClass;

class DocumentQuery
{
    public function getRowFromHistory(
        $documentUid,
        array $statuses = [],
        string $sortDirection = 'ASC'
    ): stdClass
    {
        $sortDirection = strtoupper($sortDirection);

        if (!in_array($sortDirection, ['ASC', 'DESC'], true)) {
            $sortDirection = 'ASC';
        }

        $query = DB::table('eurzad_pismo_obieg')
            ->where('pismo_uid', $documentUid);

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        return $query
            ->orderBy('pismo_obieg_id', $sortDirection)
            ->first();
    }

    public function getLastInsertedToPismo(
        $value = 0,
        string $column = 'instance_id'
    ): ?object {
        $allowedColumns = [
            'instance_id',
            'pismo_uid',
            'sprawa_uid',
        ];

        if (!in_array($column, $allowedColumns, true)) {
            throw new \InvalidArgumentException('Invalid column');
        }

        return DB::table('eurzad_pismo')
            ->where($column, $value)
            ->orderByDesc('pismo_wersja')
            ->first();
    }
}
