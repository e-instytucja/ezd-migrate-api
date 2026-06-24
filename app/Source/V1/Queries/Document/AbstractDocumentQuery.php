<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\DTO\Request\ApiKonfiguracja;
use App\Source\V1\DTO\Request\TypFiltrDokument;
use InvalidArgumentException;

abstract class AbstractDocumentQuery
{
    public const TYP_DOK_PRZYCHODZACY_INICJUJACY = 1;
    public const TYP_DOK_WYCHADZACY_W_SPRAWIE = 2;
    public const TYP_DOK_PRZYCHODZACY_W_SPRAWIE = 3;
    public const TYP_DOK_PRZYCHODZACY_ZPO = 4; // Zwrotne potwierdzenie odbioru

    /** @var array<int, mixed> */
    protected array $bindings = [];

    protected function getWhereSql(
        int $unionType,
        ApiKonfiguracja $konfiguracja,
        TypFiltrDokument $filtry
    ): string {
        if ($filtry->isScopedToTeczka()) {
            return $this->getScopedWhereSql($filtry);
        }
        return $this->getGlobalWhereSql($unionType, $konfiguracja, $filtry);
    }

    protected function getScopedWhereSql(TypFiltrDokument $filtry): string
    {
        $conditions = [
            'et.teczka_uid = ' . $this->bind($filtry->teczkaUid),
        ];

        return implode("\n                AND ", $conditions);
    }

    protected function getGlobalWhereSql(
        int $unionType,
        ApiKonfiguracja $konfiguracja,
        TypFiltrDokument $filtry
    ): string {
        $conditions = [];

        $this->appendWorkstationScope($conditions, $konfiguracja, $filtry);

        if ($filtry->documentId !== null) {
            $conditions[] = match ($unionType) {
                self::TYP_DOK_WYCHADZACY_W_SPRAWIE => 'ep.pismo_uid = ' . $this->bind($filtry->documentId),
                default => 'es.sprawa_uid = ' . $this->bind($filtry->documentId),
            };
        }

        if ($filtry->rok !== null) {
            $conditions[] = $this->rokCondition($unionType, $filtry->rok);
        }

        if ($filtry->nazwaProcesu !== null) {
            $conditions[] = 'gp.normalized_name = ' . $this->bind($filtry->nazwaProcesu);
        }

        if ($filtry->statusProcesu !== null) {
            $conditions[] = match ($unionType) {
                self::TYP_DOK_WYCHADZACY_W_SPRAWIE => 'epo.status = ' . $this->bind($filtry->statusProcesu),
                default => 'eo.status = ' . $this->bind($filtry->statusProcesu),
            };
        }

        if ($filtry->wlascicielStanowisko !== null) {
            $conditions[] = $this->buildWorkstationCondition(
                [$filtry->wlascicielStanowisko],
                $filtry->pokazUdostepnione !== null,
            );
        }

        if ($filtry->dataRejestracjiOd !== null) {
            $conditions[] = $this->dateFromCondition($unionType, $filtry->dataRejestracjiOd);
        }

        if ($filtry->dataRejestracjiDo !== null) {
            $conditions[] = $this->dateToCondition($unionType, $filtry->dataRejestracjiDo);
        }

        if ($filtry->opisDokumentu !== null) {
            $conditions[] = $this->opisDokumentuCondition($unionType, $filtry->opisDokumentu);
        }

        if ($filtry->interesant !== null) {
            $conditions[] = '(ps_petent.view_podmiot ILIKE ' . $this->bind('%' . $filtry->interesant . '%')
                . ' OR ps_petent.view_adres_korespondencyjny ILIKE ' . $this->bind('%' . $filtry->interesant . '%') . ')';
        }

        if ($filtry->oznaczenie !== null) {
            $conditions[] = $this->oznaczenieCondition($unionType, $filtry->oznaczenie);
        }

        return implode("\n                AND ", $conditions);
    }

    protected function rokCondition(int $unionType, int $rok): string
    {
        return match ($unionType) {
            self::TYP_DOK_WYCHADZACY_W_SPRAWIE => 'EXTRACT(YEAR FROM ep.pismo_createdate) = ' . $this->bind($rok),
            default => 'EXTRACT(YEAR FROM COALESCE('
                . "NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp, "
                . 'esp.sprawa_createdate)) = ' . $this->bind($rok),
        };
    }

    protected function opisDokumentuCondition(int $unionType, string $opis): string
    {
        $pattern = '%' . $opis . '%';

        if ($unionType === self::TYP_DOK_WYCHADZACY_W_SPRAWIE) {
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

    protected function oznaczenieCondition(int $unionType, string $oznaczenie): string
    {
        $parts = [
            'et.teczka_znak_sprawy ILIKE ' . $this->bind('%' . $oznaczenie . '%'),
        ];

        if ($unionType !== self::TYP_DOK_WYCHADZACY_W_SPRAWIE) {
            $parts[] = 'fd_nr_na_pismie.form_dane_wartosc = ' . $this->bind($oznaczenie);
            $parts[] = '(ek.ksiega_numer || \'/\' || ek.ksiega_rok) = ' . $this->bind($oznaczenie);
        }

        if (ctype_digit($oznaczenie)) {
            $parts[] = 'gi."instanceId" = ' . $this->bind((int) $oznaczenie);
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    protected function dateFromCondition(int $unionType, string $dataOd): string
    {
        return match ($unionType) {
            self::TYP_DOK_WYCHADZACY_W_SPRAWIE => 'ep.pismo_createdate >= ' . $this->bind($dataOd . ' 00:00:00'),
            default => <<<SQL
COALESCE(
    NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp,
    esp.sprawa_createdate
) >=
SQL  . $this->bind($dataOd . ' 00:00:00'),
        };
    }

    protected function dateToCondition(int $unionType, string $dataDo): string
    {
        return match ($unionType) {
            self::TYP_DOK_WYCHADZACY_W_SPRAWIE => 'ep.pismo_createdate <= ' . $this->bind($dataDo . ' 23:59:59'),
            default => <<<SQL
COALESCE(
    NULLIF(TRIM(fd_data_rej.form_dane_wartosc), '')::timestamp,
    esp.sprawa_createdate
) <=
SQL  . $this->bind($dataDo . ' 23:59:59'),
        };
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
