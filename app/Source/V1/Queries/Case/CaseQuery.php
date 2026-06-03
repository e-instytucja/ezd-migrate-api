<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Case;

use Illuminate\Support\Facades\DB;

class CaseQuery
{
    /**
     * Placeholder pod audyt zalacznikow pism wiodacych.
     * Docelowo dodaj tutaj zapytanie SQL do pobrania danych testowych.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMainDocumentAttachmentsAuditCandidates(int $limit = 0, int $offset = 0): array
    {
        return [];
    }

    public function getTeczkaSyg($caseUid, $dntas = 0)
    {
        return DB::table('eurzad_teczka')
            ->where('teczka_uid', $caseUid)
            ->where('dntas', $dntas)
            ->value('teczka_znak_sprawy');
    }

    public function getMainDocumentUidByCaseUid($caseUid, int $dntas = 0)
    {
        return DB::table('eurzad_teczka')
            ->where('teczka_uid', $caseUid)
            ->where('dntas', $dntas)
            ->value('sprawa_uid');
    }
    public function getStatus($uid)
    {
        $symbol = $this->getStatusSymbol($uid);

        $status = DB::table('eurzad_slownik_status')
            ->where('symbol', $symbol)
            ->value('opis');
        if(empty($status)) {
            throw new \Exception("Brak danych dla statusu {$symbol}");
        }
        return $status;
    }



    private function getStatusSymbol($uid)
    {
        $status = DB::table('eurzad_obieg')
            ->where('sprawa_uid', $uid)
            ->value('status');

        if(empty($status)) {
            throw new \Exception("Brak danych dla statusu {$status}");
        }
        return $status;
    }

    public function getSprawaPrzedluzanie($mainDocumentUid, $column)
    {
        $createdate = DB::table('eurzad_sprawa_przedluzanie')
            ->where('sprawa_uid', $mainDocumentUid)
            ->value($column);

        if(empty($createdate)) {
            throw new \Exception("Brak danych dla sprawy {$mainDocumentUid}");
        }

        return $createdate;
    }

    public function getMainDocumentCreateDateByCaseUid($mainDocumentUid)
    {
        $createdate = DB::table('eurzad_sprawa')
            ->where('sprawa_uid', $mainDocumentUid)
            ->value('sprawa_createdate');
        if(empty($createdate)) {
            throw new \Exception("Brak danych dla sprawy {$mainDocumentUid}");
        }
        return $createdate;
    }

    public function getFirstRowFromHistory(
        $mainDocumentUid,
        array $statuses = [],
        string $sortDirection = 'ASC'
    ) {
        $sortDirection = strtoupper($sortDirection);

        if (!in_array($sortDirection, ['ASC', 'DESC'], true)) {
            $sortDirection = 'ASC';
        }

        $query = DB::table('eurzad_obieg')
            ->where('sprawa_uid', $mainDocumentUid);

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        return $query
            ->orderBy('status_sprawy_id', $sortDirection)
            ->first();
    }

    public function getSprawaUidByTeczkaZawartoscUid(
        $teczkaZawartoscUid,
        string $returnKeys = 'o."instanceId", o.sprawa_uid'
    ) {
        $allowedColumns = [
            'o."instanceId", o.sprawa_uid',
            'o.sprawa_uid',
            'o."instanceId"',
        ];

        if (!in_array($returnKeys, $allowedColumns, true)) {
            throw new \InvalidArgumentException('Invalid return keys');
        }

        return DB::table('eurzad_teczka_zawartosc as tz')
            ->distinct()
            ->selectRaw($returnKeys)
            ->join('eurzad_teczka as t', 't.teczka_uid', '=', 'tz.teczka_uid')
            ->join('eurzad_obieg as o', function ($join) {
                $join->on('o.sprawa_uid', '=', 't.sprawa_uid')
                    ->where('o.status_sprawy_id', '>', 0);
            })
            ->join('galaxia_instances as gi', 'gi.instanceId', '=', 'o.instanceId')
            ->where('tz.teczka_zawartosc_uid', $teczkaZawartoscUid)
            ->first();
    }

    public function getCaseOwnerByInstanceId($instanceId)
    {
        return DB::table('galaxia_instances')
            ->where('instanceId', $instanceId)
            ->value('workstation');
    }

    public function getAllFromTeczkaBySprawaUid(
        $caseUid,
        int $dntas = 0,
    ): object {
        $ret = DB::table('eurzad_teczka as t')
            ->leftJoin(
                'eurzad_teczka_podteczki as tp',
                't.podteczka_id',
                '=',
                'tp.id'
            )
            ->where('t.teczka_uid', $caseUid)
            ->where('t.dntas', $dntas)
            ->select(
                't.*',
                'tp.opis as opis_zbioru'
            )
            ->first();

        if ($ret) {
            return $ret;
        }

        return (object) [
            'opis_sprawy' => '',
            'opis_zbioru' => '',
        ];
    }

    public function getTitleAndDescription($caseUid, int $dntas = 0): ?object
    {
        return DB::table('eurzad_teczka')
            ->where('teczka_uid', $caseUid)
            ->where('dntas', $dntas)
            ->first([
                'tytul_sprawy',
                'opis_sprawy',
            ]);
    }

    public function getFormNameByMainDocumentUid(
        $mainDocumentUid
    ): ?string {
        return DB::table('eurzad_sprawa')
            ->where('sprawa_uid', $mainDocumentUid)
            ->value('form_name');
    }

    public function getCaseOwnerByCaseUid($mainDocumentUid)
    {
        return $this->getCaseOwnerByInstanceId(
            $this->getInstanceIdByCaseUid($mainDocumentUid)
        );
    }

    public function getInstanceIdByCaseUid($caseUid): int
    {
        $row = $this->getFirstRowFromHistory($caseUid);
        return $row->instanceId;
    }

    public function getSprawaCreateDate($documentId = 0)
    {
        if (!$documentId) {
            return null;
        }

        $caseData = (array)DB::table('eurzad_sprawa')
            ->where('sprawa_uid', $documentId)
            ->first();

        if (empty($caseData) || $caseData['rodzaj_pisma'] != 'internal') {
            return null;
        }

        $hour = date('H', strtotime($caseData['sprawa_createdate']));
        if ((int)$hour > 0) {
            return $caseData['sprawa_createdate'];
        }

        $createdate = DB::table('eurzad_teczka_zawartosc')
            ->where('teczka_zawartosc_uid', $documentId)
            ->value('createdate');

        return !empty($createdate) ? $createdate : null;
    }

    public function getHistory($caseUid)
    {
        return DB::table('eurzad_obieg as o')
            ->join('eurzad_slownik_status as ss', 'o.status', '=', 'ss.symbol')
            ->where('o.sprawa_uid', $caseUid)
            ->select(
                'o.sprawa_uid',
                'o.createdate',
                'o.instanceId',
                'ss.opis as action',
                'o.uugid_from',
                'o.uugid_to'
            )
            ->orderBy('o.status_sprawy_id', 'DESC')
            ->get();
    }

    /**
     * @return array<int, array{status: string, opis: string}>
     */
    public function getStatuses(int $dntas = 0): array
    {
        return DB::table('eurzad_teczka as t')
            ->join('eurzad_obieg as o', function ($join) {
                $join->on('t.sprawa_uid', '=', 'o.sprawa_uid')
                    ->where('o.max_status_sprawy_id', '>', 0);
            })
            ->join('eurzad_slownik_status as s', 's.symbol', '=', 'o.status')
            ->where('t.dntas', $dntas)
            ->select('o.status', 's.opis')
            ->groupBy('o.status', 's.opis')
            ->orderBy('s.opis')
            ->get()
            ->map(static fn ($row) => [
                'status' => $row->status,
                'opis' => $row->opis,
            ])
            ->all();
    }
}
