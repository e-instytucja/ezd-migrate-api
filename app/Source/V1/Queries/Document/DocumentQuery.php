<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\Enum\DocumentQueryContext;
use App\Source\V1\Enum\TypDokumentu;
use Illuminate\Support\Facades\DB;
use stdClass;

class DocumentQuery
{
    public function __construct(
        private QueryBuilder $documentListQueryBuilder,
    )
    {
    }
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

    public function getDocumentListByCaseUid($caseUID) {
        $sql = $this->prepareSQLForDataFromCase($caseUID);
        $data = collect(DB::select($sql['query'], $sql['params']))
            ->map(fn($item) => (array) $item)
            ->toArray();
        return $data;

    }

    private function prepareSQLForDataFromCase($caseUID)
    {
        $sql = [
            'query'  =>
                '(' . $this->documentListQueryBuilder->buildSQLQuery(
                    DocumentQueryContext::CASE_UID, //pisma wiodące dołączone do sprawy
                    TypDokumentu::PISMO
                ) . ')
                UNION
                (' .
                $this->documentListQueryBuilder->buildSQLQuery(
                    DocumentQueryContext::CASE_UID,  //dokumenty dołączone do sprawy
                    TypDokumentu::DOKUMENT
                ) . ')
                UNION
                (' .
                $this->documentListQueryBuilder->buildSQLQuery(
                    DocumentQueryContext::CASE_UID_MAIN_DOCUMENT_ATTACHED_TO_CASE, //pismo wiodące na podstawie którego została utworzona sprawa -
                    TypDokumentu::PISMO
                ) . ')
                UNION
                (' .
                $this->documentListQueryBuilder->buildSQLQuery(
                    DocumentQueryContext::CASE_UID_MAIN_DOCUMENT_ATTACHED_TO_DOCUMENT,  //zwrot/zwrotka
                    TypDokumentu::PISMO
                ) . ')' .
                $this->documentListQueryBuilder->addDocumentGroupOrder(),
            'params' => [],
        ];

        // prepare params
        $sql['params'] = [$caseUID, $caseUID, $caseUID, $caseUID];

        return $sql;
    }
}
