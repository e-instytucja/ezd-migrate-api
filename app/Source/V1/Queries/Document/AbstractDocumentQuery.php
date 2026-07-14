<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\DTO\Request\ApiKonfiguracja;
use App\Source\V1\DTO\Request\TypFiltrDokument;
use App\Source\V1\Enum\TypDokument;
use InvalidArgumentException;

abstract class AbstractDocumentQuery
{
    /** @var array<int, mixed> */
    protected array $bindings = [];

    protected function getWhereSql(
        TypUnionDokumentu $unionPart,
        ?TypDokument $typProcesu,
        ApiKonfiguracja $konfiguracja,
        TypFiltrDokument $filtry,
    ): string {
        if ($filtry->isScopedToTeczka()) {
            return $this->getScopedWhereSql($unionPart, $filtry);
        }

        return $this->getGlobalWhereSql($unionPart, $typProcesu, $konfiguracja, $filtry);
    }

    protected function getScopedWhereSql(TypUnionDokumentu $unionPart, TypFiltrDokument $filtry): string
    {
        $conditions = match ($unionPart) {
            TypUnionDokumentu::DokNiewychodzacyInicjujacySprawe => [
                'et.sprawa_uid = es.sprawa_uid',
                'et.teczka_uid = ' . $this->bind($filtry->teczkaUid),
            ],
            TypUnionDokumentu::DokNiewychodzacyWSprawie => [
                'EXISTS (
                    SELECT 1
                    FROM eurzad_teczka_zawartosc etz_scope
                    WHERE etz_scope.teczka_zawartosc_uid = es.sprawa_uid
                      AND etz_scope.teczka_uid = ' . $this->bind($filtry->teczkaUid) . '
                )',
            ],
            TypUnionDokumentu::DokNiewychodzacyBezSprawy => [
                '1 = 0',
            ],
            default => [
                'et.teczka_uid = ' . $this->bind($filtry->teczkaUid),
            ],
        };

        return implode("\n                AND ", $conditions);
    }

    protected function getGlobalWhereSql(
        TypUnionDokumentu $unionPart,
        ?TypDokument $typProcesu,
        ApiKonfiguracja $konfiguracja,
        TypFiltrDokument $filtry,
    ): string {
        $conditions = [];

        $this->appendWorkstationScope($conditions, $konfiguracja, $filtry);

        if ($filtry->documentId !== null) {
            $conditions[] = $unionPart->isWychodzacy()
                ? 'ep.pismo_uid = ' . $this->bind($filtry->documentId)
                : 'es.sprawa_uid = ' . $this->bind($filtry->documentId);
        }

        if ($filtry->rok !== null) {
            $conditions[] = $this->rokCondition($unionPart, $filtry->rok);
        }

        if ($filtry->nazwaProcesu !== null) {
            $conditions[] = 'gp.normalized_name = ' . $this->bind($filtry->nazwaProcesu);
        }

        if ($filtry->typFormularza !== null) {
            $conditions[] = 'ef.form_typ = ' . $this->bind($filtry->typFormularza->value);
        }

        if ($filtry->statusProcesu !== null) {
            $conditions[] = $unionPart->isWychodzacy()
                ? 'epo.status = ' . $this->bind($filtry->statusProcesu)
                : 'eo.status = ' . $this->bind($filtry->statusProcesu);
        }

        if ($filtry->wlascicielStanowisko !== null) {
            $conditions[] = $this->buildWorkstationCondition(
                [$filtry->wlascicielStanowisko],
                $filtry->pokazUdostepnione !== null,
            );
        }

        if ($filtry->dataRejestracjiOd !== null) {
            $conditions[] = $this->dateFromCondition($unionPart, $filtry->dataRejestracjiOd);
        }

        if ($filtry->dataRejestracjiDo !== null) {
            $conditions[] = $this->dateToCondition($unionPart, $filtry->dataRejestracjiDo);
        }

        if ($filtry->opisDokumentu !== null) {
            $conditions[] = $this->opisDokumentuCondition($unionPart, $filtry->opisDokumentu);
        }

        if ($filtry->interesant !== null) {
            $conditions[] = '(ps_petent.view_podmiot ILIKE ' . $this->bind('%' . $filtry->interesant . '%')
                . ' OR ps_petent.view_adres_korespondencyjny ILIKE ' . $this->bind('%' . $filtry->interesant . '%') . ')';
        }

        if ($filtry->oznaczenie !== null) {
            $conditions[] = $this->oznaczenieCondition($unionPart, $filtry->oznaczenie);
        }

        return implode("\n                AND ", $conditions);
    }

    protected function rokCondition(TypUnionDokumentu $unionPart, int $rok): string
    {
        $from = $rok . '-01-01 00:00:00';
        $to = ($rok + 1) . '-01-01 00:00:00';

        if ($unionPart->isWychodzacy()) {
            return 'ep.pismo_createdate >= ' . $this->bind($from)
                . ' AND ep.pismo_createdate < ' . $this->bind($to);
        }

        return 'COALESCE('
            . "NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp, "
            . 'esp.sprawa_createdate) >= ' . $this->bind($from)
            . ' AND COALESCE('
            . "NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp, "
            . 'esp.sprawa_createdate) < ' . $this->bind($to);
    }

    protected function opisDokumentuCondition(TypUnionDokumentu $unionPart, string $opis): string
    {
        $pattern = '%' . $opis . '%';

        if ($unionPart->isWychodzacy()) {
            return "(fd_tytul.wartosc::jsonb)->>'textarea' ILIKE " . $this->bind($pattern);
        }

        $bindTresc = $this->bind($pattern);
        $bindTytul = $this->bind($pattern);

        return <<<SQL
    (
        fd_tresc_wniosku.form_dane_wartosc ILIKE {$bindTresc}
        OR (fd_tytul.form_dane_wartosc::jsonb)->>'textarea' ILIKE {$bindTytul}
    )
SQL;
    }

    protected function oznaczenieCondition(TypUnionDokumentu $unionPart, string $oznaczenie): string
    {
        $parts = [];

        if (!$unionPart->isNiewychodzacyBezSprawy()) {
            $parts[] = 'et.teczka_znak_sprawy ILIKE ' . $this->bind('%' . $oznaczenie . '%');
        }

        if (!$unionPart->isWychodzacy()) {
            $parts[] = 'fd_nr_na_pismie.form_dane_wartosc = ' . $this->bind($oznaczenie);
            $parts[] = '(ek.ksiega_numer || \'/\' || ek.ksiega_rok) = ' . $this->bind($oznaczenie);
        }

        if (ctype_digit($oznaczenie)) {
            $parts[] = 'gi."instanceId" = ' . $this->bind((int) $oznaczenie);
        }

        if ($parts === []) {
            return '1 = 0';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    protected function dateFromCondition(TypUnionDokumentu $unionPart, string $dataOd): string
    {
        if ($unionPart->isWychodzacy()) {
            return 'ep.pismo_createdate >= ' . $this->bind($dataOd . ' 00:00:00');
        }

        return <<<SQL
COALESCE(
    NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp,
    esp.sprawa_createdate
) >=
SQL  . $this->bind($dataOd . ' 00:00:00');
    }

    protected function dateToCondition(TypUnionDokumentu $unionPart, string $dataDo): string
    {
        if ($unionPart->isWychodzacy()) {
            return 'ep.pismo_createdate <= ' . $this->bind($dataDo . ' 23:59:59');
        }

        return <<<SQL
COALESCE(
    NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp,
    esp.sprawa_createdate
) <=
SQL  . $this->bind($dataDo . ' 23:59:59');
    }

    /**
     * @param string[] $conditions
     */
    protected function appendWorkstationScope(
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
    protected function buildWorkstationCondition(array $workstationIds, bool $includeShared): string
    {
        if ($workstationIds === []) {
            throw new InvalidArgumentException('Workstation IDs cannot be empty');
        }

        $placeholders = implode(', ', array_map(
            fn (int $id) => $this->bind($id),
            $workstationIds,
        ));
        $ownerCondition = "gi.workstation IN ({$placeholders})";

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
                        WHERE giu.instance_id = gi."instanceId"
                          AND giu.workstation IN ({$sharedPlaceholders})
                   )
                )
        SQL;
    }

    protected function bind(mixed $value): string
    {
        $this->bindings[] = $value;

        return '?';
    }
}
