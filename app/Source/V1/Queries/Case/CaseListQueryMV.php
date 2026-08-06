<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Case;

use App\Source\V1\DTO\Request\ApiKonfiguracja;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaSpraw;
use App\Source\V1\DTO\Request\SortowanieSpraw;
use App\Source\V1\DTO\Request\TypFiltrSpraw;
use App\Source\V1\Support\MaterializedViews\CaseListMaterializedView;
use App\Source\V1\Support\MaterializedViews\MaterializedViewNaming;
use App\Source\V1\Support\MaterializedViews\MaterializedViewRegistry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CaseListQueryMV implements CaseListQueryInterface
{
    /** @var array<string, string> */
    private const ORDER_COLUMNS = [
        'znak' => 'acl.znak',
        'tytul_sprawy' => 'acl.tytul_sprawy',
        'nazwa_procesu' => 'acl.nazwa_procesu',
        'interesant' => 'acl.interesant',
        'status_procesu' => 'acl.status_procesu',
        'data_wszczecia' => 'acl.data_wszczecia',
    ];

    /** @var array<string, list<string>> */
    private const MULTI_ORDER_COLUMNS = [
        'wlasciciel_stanowisko' => [
            'acl.wlasciciel_nazwisko',
            'acl.wlasciciel_imie',
            'acl.wlasciciel_nazwisko2',
            'acl.wlasciciel_nazwisko3',
        ],
    ];

    /** @var array<int, mixed> */
    private array $bindings = [];

    public function __construct(
        private readonly MaterializedViewRegistry $materializedViewRegistry,
    ) {
    }

    public function getList(KryteriaWyszukiwaniaSpraw $criteria): array
    {
        $this->assertMvReady();
        $this->bindings = [];

        $sql = <<<SQL
                SELECT
                    {$this->getSelectSql()}
                FROM {$this->viewName()} acl
                WHERE
                    {$this->getWhereSql($criteria->konfiguracja, $criteria->filtry, $criteria->dntas)}
                ORDER BY
                    {$this->getOrderSql($criteria->sortowanie)}
                LIMIT
                    {$criteria->paginacja->limit}
                OFFSET
                    {$criteria->paginacja->offset}
        SQL;

        $rows = DB::select($sql, $this->bindings);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    public function getListCount(KryteriaWyszukiwaniaSpraw $criteria): int
    {
        $this->assertMvReady();
        $this->bindings = [];

        $sql = <<<SQL
            SELECT COUNT(*) AS count
            FROM {$this->viewName()} acl
            WHERE
                {$this->getWhereSql($criteria->konfiguracja, $criteria->filtry, $criteria->dntas)}
        SQL;

        $result = DB::select($sql, $this->bindings);

        return (int) $result[0]->count;
    }

    private function assertMvReady(): void
    {
        if (!$this->materializedViewRegistry->exists(CaseListMaterializedView::NAME)) {
            throw new RuntimeException(
                'Materialized view ' . MaterializedViewNaming::qualified(CaseListMaterializedView::NAME)
                . ' nie istnieje. Uruchom: php artisan cases:refresh-list-mv',
            );
        }
    }

    private function viewName(): string
    {
        return MaterializedViewNaming::qualified(CaseListMaterializedView::NAME);
    }

    private function getSelectSql(): string
    {
        return <<<SQL
                acl.id_sprawy,
                acl.znak,
                acl.main_document_uid,
                acl.data_rejestracji_dokumentu,
                acl.czas_realizacji,
                acl.sprawa_finishdate,
                acl.status,
                acl.data_utworzenia_dokumentu,
                acl.nazwa_procesu,
                acl.nazwa_procesu_znormalizowana,
                acl.id_procesu,
                acl.typ_formularza,
                acl.status_procesu,
                acl.data_wszczecia,
                acl.opis_sprawy,
                acl.tytul_sprawy,
                acl.oznaczenie_dntas,
                acl.zalaczniki,
                acl.interesant,
                acl.interesant_adres,
                acl.interesant_type,
                acl.has_pozostali_interesanci,
                acl.wlasciciel_stanowisko_id,
                acl.wlasciciel_stanowisko_skrot,
                acl.wlasciciel_stanowisko_nazwa,
                acl.wlasciciel_komorka_skrot,
                acl.wlasciciel_komorka_nazwa,
                acl.wlasciciel_imie_nazwisko
        SQL;
    }

    private function getWhereSql(ApiKonfiguracja $konfiguracja, TypFiltrSpraw $filtry, int $dntas): string
    {
        $conditions = ['acl.dntas = ' . $dntas];

        $this->appendWorkstationScope($conditions, $konfiguracja, $filtry);

        if ($filtry->sprawaUid !== null) {
            $conditions[] = 'acl.id_sprawy = ' . $this->bind($filtry->sprawaUid);
        }

        if ($filtry->rok !== null) {
            $conditions[] = 'acl.rok = ' . $this->bind($filtry->rok);
        }

        if ($filtry->znak !== null) {
            $conditions[] = 'acl.znak ILIKE ' . $this->bind('%' . $filtry->znak . '%');
        }

        if ($filtry->oznaczenieDntas !== null) {
            $conditions[] = 'acl.oznaczenie_dntas ILIKE ' . $this->bind('%' . $filtry->oznaczenieDntas . '%');
        }

        if ($filtry->statusProcesu !== null) {
            $conditions[] = 'acl.status = ' . $this->bind($filtry->statusProcesu);
        }

        if ($filtry->typFormularza !== null) {
            $conditions[] = 'acl.typ_formularza = ' . $this->bind($filtry->typFormularza->value);
        }

        if ($filtry->typProcesu !== null) {
            $conditions[] = 'acl.typ_formularza = ' . $this->bind($filtry->typProcesu->formTyp());
        }

        if ($filtry->nazwaProcesu !== null) {
            $conditions[] = 'acl.nazwa_procesu_znormalizowana = ' . $this->bind($filtry->nazwaProcesu);
        }

        if ($filtry->documentId !== null) {
            $conditions[] = 'acl.main_document_uid = ' . $this->bind($filtry->documentId);
        }

        if ($filtry->opisDokumentu !== null) {
            $pattern = '%' . $filtry->opisDokumentu . '%';
            $conditions[] = '(acl.tresc_wniosku ILIKE ' . $this->bind($pattern)
                . ' OR acl.dokument_tytul ILIKE ' . $this->bind($pattern) . ')';
        }

        if ($filtry->dataRejestracjiOd !== null) {
            $conditions[] = 'acl.data_rejestracji >= ' . $this->bind($filtry->dataRejestracjiOd . ' 00:00:00');
        }

        if ($filtry->dataRejestracjiDo !== null) {
            $conditions[] = 'acl.data_rejestracji <= ' . $this->bind($filtry->dataRejestracjiDo . ' 23:59:59');
        }

        if ($filtry->wlascicielStanowisko !== null) {
            $conditions[] = $this->buildWorkstationCondition(
                [$filtry->wlascicielStanowisko],
                $filtry->pokazUdostepnione !== null,
            );
        }

        if ($filtry->tytulSprawy !== null) {
            $conditions[] = 'acl.tytul_sprawy ILIKE ' . $this->bind('%' . $filtry->tytulSprawy . '%');
        }

        if ($filtry->interesant !== null) {
            $conditions[] = '(acl.interesant ILIKE ' . $this->bind('%' . $filtry->interesant . '%')
                . ' OR acl.interesant_adres ILIKE ' . $this->bind('%' . $filtry->interesant . '%') . ')';
        }

        if ($filtry->dataWszczeciaOd !== null) {
            $conditions[] = 'acl.data_wszczecia >= ' . $this->bind($filtry->dataWszczeciaOd . ' 00:00:00');
        }

        if ($filtry->dataWszczeciaDo !== null) {
            $conditions[] = 'acl.data_wszczecia <= ' . $this->bind($filtry->dataWszczeciaDo . ' 23:59:59');
        }

        return implode("\n                    AND ", $conditions);
    }

    /**
     * @param string[] $conditions
     */
    private function appendWorkstationScope(
        array &$conditions,
        ApiKonfiguracja $konfiguracja,
        TypFiltrSpraw $filtry,
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
        $ownerCondition = "acl.wlasciciel_stanowisko_id IN ({$placeholders})";

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
                        WHERE giu.instance_id = acl.instance_id
                          AND giu.workstation IN ({$sharedPlaceholders})
                   )
                )
        SQL;
    }

    private function getOrderSql(SortowanieSpraw $sortowanie): string
    {
        $dir = strtoupper($sortowanie->direction);

        if (isset(self::MULTI_ORDER_COLUMNS[$sortowanie->field])) {
            return implode(', ', array_map(
                fn (string $column) => "{$column} {$dir}",
                self::MULTI_ORDER_COLUMNS[$sortowanie->field],
            ));
        }

        $column = self::ORDER_COLUMNS[$sortowanie->field] ?? self::ORDER_COLUMNS['data_wszczecia'];

        return "{$column} {$dir}";
    }

    private function bind(mixed $value): string
    {
        $this->bindings[] = $value;

        return '?';
    }
}
