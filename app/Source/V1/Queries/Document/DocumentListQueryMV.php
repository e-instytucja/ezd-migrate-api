<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\DTO\Request\ApiKonfiguracja;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaDokumentow;
use App\Source\V1\DTO\Request\TypFiltrDokument;
use App\Source\V1\Enum\TypDokument;
use App\Source\V1\Support\MaterializedViews\DocumentListMaterializedView;
use App\Source\V1\Support\MaterializedViews\MaterializedViewNaming;
use App\Source\V1\Support\MaterializedViews\MaterializedViewRegistry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentListQueryMV implements DocumentListQueryInterface
{
    /** @var array<int, mixed> */
    private array $bindings = [];

    public function __construct(
        private readonly MaterializedViewRegistry $materializedViewRegistry,
    ) {
    }

    public function getList(KryteriaWyszukiwaniaDokumentow $criteria): array
    {
        $this->assertMvReady($criteria);
        $this->bindings = [];

        $sql = <<<SQL
                SELECT
                    {$this->getSelectSql()}
                FROM {$this->viewName()} adl
                WHERE
                    {$this->getWhereSql($criteria->konfiguracja, $criteria->filtry)}
                ORDER BY
                    {$criteria->sortowanie->toOrderBySql()}, id_dokumentu ASC
                LIMIT
                    {$criteria->paginacja->limit}
                OFFSET
                    {$criteria->paginacja->offset}
        SQL;

        $rows = DB::select($sql, $this->bindings);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    public function getListCount(KryteriaWyszukiwaniaDokumentow $criteria): int
    {
        $this->assertMvReady($criteria);
        $this->bindings = [];

        $sql = <<<SQL
            SELECT COUNT(*) AS count
            FROM {$this->viewName()} adl
            WHERE
                {$this->getWhereSql($criteria->konfiguracja, $criteria->filtry)}
        SQL;

        $result = DB::select($sql, $this->bindings);

        return (int) $result[0]->count;
    }

    private function assertMvReady(KryteriaWyszukiwaniaDokumentow $criteria): void
    {
        if (!$this->materializedViewRegistry->exists(DocumentListMaterializedView::NAME)) {
            throw new RuntimeException(
                'Materialized view ' . MaterializedViewNaming::qualified(DocumentListMaterializedView::NAME)
                . ' nie istnieje. Uruchom: php artisan documents:refresh-list-mv',
            );
        }
    }

    private function viewName(): string
    {
        return MaterializedViewNaming::qualified(DocumentListMaterializedView::NAME);
    }

    private function getSelectSql(): string
    {
        return <<<SQL
                adl.nazwa_procesu,
                adl.nazwa_znormalizowana_procesu,
                adl.id_procesu,
                adl.typ_formularza,
                adl.status_procesu,
                adl.znak_sprawy,
                adl.wlasciciel_stanowisko_id,
                adl.wlasciciel_stanowisko_skrot,
                adl.wlasciciel_stanowisko_nazwa,
                adl.wlasciciel_komorka_skrot,
                adl.wlasciciel_komorka_nazwa,
                adl.wlasciciel_nazwisko,
                adl.wlasciciel_imie,
                adl.wlasciciel_nazwisko2,
                adl.wlasciciel_nazwisko3,
                adl.wlasciciel_imie_nazwisko,
                adl.interesant,
                adl.interesant_adres,
                adl.interesant_type,
                adl.id_dokumentu,
                adl.nr_na_pismie,
                adl.wersja,
                adl.data_rejestracji,
                adl.data_utworzenia,
                adl.zalaczniki,
                adl.dokument_tytul,
                adl.tresc_wniosku,
                adl.nr_ksiegi,
                adl.has_pozostali_interesanci,
                adl.typ_dokumentu,
                adl.typ_powiazania_dokumentu
        SQL;
    }

    private function getWhereSql(ApiKonfiguracja $konfiguracja, TypFiltrDokument $filtry): string
    {
        if ($filtry->isScopedToTeczka()) {
            return 'adl.teczka_uid = ' . $this->bind($filtry->teczkaUid);
        }

        $conditions = [];

        $this->appendWorkstationScope($conditions, $konfiguracja, $filtry);
        $this->appendTypProcesuCondition($conditions, $filtry);

        if ($filtry->documentId !== null) {
            $conditions[] = 'adl.id_dokumentu = ' . $this->bind($filtry->documentId);
        }

        if ($filtry->rok !== null) {
            $from = $filtry->rok . '-01-01 00:00:00';
            $to = ($filtry->rok + 1) . '-01-01 00:00:00';
            $conditions[] = 'adl.data_rejestracji >= ' . $this->bind($from)
                . ' AND adl.data_rejestracji < ' . $this->bind($to);
        }

        if ($filtry->nazwaProcesu !== null) {
            $conditions[] = 'adl.nazwa_znormalizowana_procesu = ' . $this->bind($filtry->nazwaProcesu);
        }

        if ($filtry->typFormularza !== null) {
            $conditions[] = 'adl.typ_formularza = ' . $this->bind($filtry->typFormularza->value);
        }

        if ($filtry->statusProcesu !== null) {
            $conditions[] = 'adl.status = ' . $this->bind($filtry->statusProcesu);
        }

        if ($filtry->wlascicielStanowisko !== null) {
            $conditions[] = $this->buildWorkstationCondition(
                [$filtry->wlascicielStanowisko],
                $filtry->pokazUdostepnione !== null,
            );
        }

        if ($filtry->dataRejestracjiOd !== null) {
            $conditions[] = 'adl.data_rejestracji >= ' . $this->bind($filtry->dataRejestracjiOd . ' 00:00:00');
        }

        if ($filtry->dataRejestracjiDo !== null) {
            $conditions[] = 'adl.data_rejestracji <= ' . $this->bind($filtry->dataRejestracjiDo . ' 23:59:59');
        }

        if ($filtry->opisDokumentu !== null) {
            $pattern = '%' . $filtry->opisDokumentu . '%';
            $conditions[] = '(adl.tresc_wniosku ILIKE ' . $this->bind($pattern)
                . ' OR adl.dokument_tytul ILIKE ' . $this->bind($pattern) . ')';
        }

        if ($filtry->interesant !== null) {
            $conditions[] = '(adl.interesant ILIKE ' . $this->bind('%' . $filtry->interesant . '%')
                . ' OR adl.interesant_adres ILIKE ' . $this->bind('%' . $filtry->interesant . '%') . ')';
        }

        if ($filtry->oznaczenie !== null) {
            $conditions[] = $this->oznaczenieCondition($filtry->oznaczenie);
        }

        if ($conditions === []) {
            return '1 = 1';
        }

        return implode("\n                    AND ", $conditions);
    }

    /**
     * @param string[] $conditions
     */
    private function appendTypProcesuCondition(array &$conditions, TypFiltrDokument $filtry): void
    {
        if ($filtry->typProcesu === null) {
            return;
        }

        $conditions[] = 'adl.typ_dokumentu = ' . $this->bind($filtry->typProcesu->value);
    }

    /**
     * @param string[] $conditions
     */
    private function appendWorkstationScope(
        array &$conditions,
        ApiKonfiguracja $konfiguracja,
        TypFiltrDokument $filtry,
    ): void {
        if ($konfiguracja->madkomWorkstationIds === []) {
            throw new \Exception('Brak wskazanych wlascicieli [err_10_appendWorkstationScope]');
        }

        $conditions[] = $this->buildWorkstationCondition(
            $konfiguracja->madkomWorkstationIds,
            $filtry->pokazUdostepnione !== null,
        );
    }

    /**
     * @param int[] $workstationIds
     */
    private function buildWorkstationCondition(array $workstationIds, bool $includeShared): string
    {
        if ($workstationIds === []) {
            throw new \InvalidArgumentException('Workstation IDs cannot be empty');
        }

        $placeholders = implode(', ', array_map(
            fn (int $id) => $this->bind($id),
            $workstationIds,
        ));
        $ownerCondition = "adl.wlasciciel_stanowisko_id IN ({$placeholders})";

        if (!$includeShared) {
            return $ownerCondition;
        }

        $sharedPlaceholders = implode(', ', array_map(
            fn (int $id) => $this->bind($id),
            $workstationIds,
        ));

        return <<<SQL
                ( {$ownerCondition} OR
                EXISTS (
                        SELECT 1
                        FROM galaxia_instance_users giu
                        WHERE giu.instance_id = adl.instance_id
                          AND giu.workstation IN ({$sharedPlaceholders})
                   )
                )
        SQL;
    }

    private function oznaczenieCondition(string $oznaczenie): string
    {
        $parts = [
            'adl.znak_sprawy ILIKE ' . $this->bind('%' . $oznaczenie . '%'),
            'adl.nr_na_pismie = ' . $this->bind($oznaczenie),
            'adl.nr_ksiegi = ' . $this->bind($oznaczenie),
        ];

        if (ctype_digit($oznaczenie)) {
            $parts[] = 'adl.instance_id = ' . $this->bind((int) $oznaczenie);
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    private function bind(mixed $value): string
    {
        $this->bindings[] = $value;

        return '?';
    }
}
