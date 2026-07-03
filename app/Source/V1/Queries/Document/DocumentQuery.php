<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaDokumentow;
use App\Source\V1\Enum\TypDokument;
use Illuminate\Support\Facades\DB;
use stdClass;

class DocumentQuery extends AbstractDocumentQuery
{
    public const DOCUMENT_TYPE_PISMO = 'pismo';
    public const DOCUMENT_TYPE_DOKUMENT = 'dokument';

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

    public function getLastRowFromHistory(
        $documentUid,
        array $statuses = [],
    ): stdClass
    {
        return $this->getRowFromHistory($documentUid, $statuses, 'DESC');
    }

    public function getFirstRowFromHistory(
        $documentUid,
        array $statuses = []
    ): stdClass
    {
        return $this->getRowFromHistory($documentUid, $statuses, 'ASC');
    }

    public function getHistory($documentUid)
    {
        $sql = <<<SQL
    SELECT
        po.pismo_uid,
        po.createdate,
        (
            SELECT p.instance_id
            FROM eurzad_pismo p
            WHERE p.pismo_uid = po.pismo_uid
            ORDER BY p.pismo_wersja DESC
            LIMIT 1
        ) AS instance_id,
        ss.opis AS status_opis,
        po.uugid_from,
        po.uugid_to,
        po.added_automatically
    FROM eurzad_pismo_obieg po
    INNER JOIN eurzad_slownik_status ss
        ON po.status = ss.symbol
    WHERE po.pismo_uid = :documentUid
    ORDER BY po.pismo_obieg_id DESC
SQL;

        return collect(DB::select($sql, [
            'documentUid' => $documentUid,
        ]));
    }

    public function getDocumentType($documentId)
    {
        if (
            DB::table('eurzad_sprawa')
                ->where('sprawa_uid', $documentId)
                ->exists()
        ) {
            return self::DOCUMENT_TYPE_PISMO;
        }

        if (
            DB::table('eurzad_pismo')
                ->where('pismo_uid', $documentId)
                ->exists()
        ) {
            return self::DOCUMENT_TYPE_DOKUMENT;
        }

        return null;


    }

    public function getProcessNames(KryteriaWyszukiwaniaDokumentow $criteria): array
    {
        $where = $this->getWhereSql(
            TypUnionDokumentu::DokNiewychodzacyInicjujacySprawe,
            TypDokument::DokPrzychodzacy,
            $criteria->konfiguracja,
            $criteria->filtry,
        );
        $sql = <<<SQL
            SELECT normalized_name,
                   name
            FROM (SELECT gp.normalized_name,
                         gp.name
                  FROM eurzad_sprawa sp
                           INNER JOIN galaxia_processes gp ON sp.form_name = gp.normalized_name
                           INNER JOIN eurzad_obieg o ON (o.sprawa_uid = sp.sprawa_uid AND o.max_status_sprawy_id > 0)
                           INNER JOIN galaxia_instances gi ON (gi."instanceId" = o."instanceId")
--                            INNER JOIN galaxia_instance_users giu ON (giu.instance_id = gi."instanceId")
                           WHERE
                  {$where}
                  GROUP BY gp.normalized_name,
                           gp.name
            
                  UNION
            
                  SELECT gp.normalized_name,
                         gp.name
                  FROM eurzad_pismo p
                           INNER JOIN galaxia_instances gi ON p.instance_id = gi."instanceId"
                           INNER JOIN galaxia_processes gp ON (gp."pId" = gi."pId")
--                            INNER JOIN galaxia_instance_users giu ON (giu.instance_id = gi."instanceId")
                           WHERE
                  {$where}
                  GROUP BY gp.normalized_name,
                           gp.name
                  ) processes
            ORDER BY name
SQL;
        $rows = DB::select($sql, array_merge($this->bindings, $this->bindings));
        return array_map(
            static fn ($row) => [
                'name' => $row->normalized_name,
                'label' => $row->name,
            ],
            $rows
        );
    }

    public function getStatuses(): array
    {
        $rows = DB::select(
            "
    SELECT
        status,
        opis
    FROM (
        SELECT
            o.status,
            ss.opis
        FROM
            eurzad_sprawa sp
        INNER JOIN
            eurzad_obieg o
                ON sp.sprawa_uid = o.sprawa_uid
               AND o.max_status_sprawy_id > 0
        INNER JOIN
            eurzad_slownik_status ss
                ON ss.symbol = o.status
        GROUP BY
            o.status,
            ss.opis

        UNION

        SELECT
            po.status,
            ss.opis
        FROM
            eurzad_pismo p
        INNER JOIN
            eurzad_pismo_obieg po
                ON po.pismo_uid = p.pismo_uid
               AND po.createdate = (
                    SELECT
                        MAX(po2.createdate)
                    FROM
                        eurzad_pismo_obieg po2
                    WHERE
                        po2.pismo_uid = p.pismo_uid
                )
        INNER JOIN
            eurzad_slownik_status ss
                ON ss.symbol = po.status
        GROUP BY
            po.status,
            ss.opis
    ) statuses
    ORDER BY
        opis
    "
        );

        return array_map(
            static fn ($row) => [
                'status' => $row->status,
                'opis' => $row->opis,
            ],
            $rows
        );
    }
}
