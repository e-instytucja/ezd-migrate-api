<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class DokumentDanePodstawoweDto implements JsonSerializable
{
    public function __construct(
        public DokumentDanePodstawoweWartosciDto $values,
        /** @var array<string, string> */
        public array $labels,
        public ?string $sectionLabel = null,
    ) {}

    public static function empty(): self
    {
        return new self(
            values: new DokumentDanePodstawoweWartosciDto(
                nazwaProcesu: null,
                idProcesu: null,
                statusProcesu: null,
                typDokumentu: null,
                znakSprawy: null,
                idDokumentu: null,
                nrNaPismie: null,
                wersja: null,
                dataRejestracji: null,
                dataUtworzenia: null,
                dokumentTytul: null,
                trescWniosku: null,
                nrKsiegi: null,
                documentGroupType: null,
            ),
            labels: self::defaultLabels(),
            sectionLabel: 'Dane podstawowe',
        );
    }

    public static function fromValues(
        DokumentDanePodstawoweWartosciDto $values,
        ?string $sectionLabel = 'Dane podstawowe',
    ): self {
        return new self($values, self::defaultLabels(), $sectionLabel);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDocumentRow(array $row): self
    {
        return self::fromValues(
            new DokumentDanePodstawoweWartosciDto(
                nazwaProcesu: $row['nazwa_procesu'] ?? null,
                idProcesu: isset($row['id_procesu']) ? (int) $row['id_procesu'] : null,
                statusProcesu: $row['status_procesu'] ?? null,
                typDokumentu: isset($row['typ']) ? (int) $row['typ'] : null,
                znakSprawy: $row['znak_sprawy'] ?? null,
                idDokumentu: $row['id_dokumentu'] ?? null,
                nrNaPismie: $row['nr_na_pismie'] ?? null,
                wersja: isset($row['wersja']) ? (int) $row['wersja'] : null,
                dataRejestracji: $row['data_rejestracji'] ?? null,
                dataUtworzenia: $row['data_utworzenia'] ?? null,
                dokumentTytul: $row['dokument_tytul'] ?? null,
                trescWniosku: $row['tresc_wniosku'] ?? null,
                nrKsiegi: ($row['nr_ksiegi'] ?? '') !== '' ? $row['nr_ksiegi'] : null,
                documentGroupType: isset($row['document_group_type']) ? (int) $row['document_group_type'] : null,
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function defaultLabels(): array
    {
        return [
            'nazwaProcesu' => 'Nazwa procesu',
            'idProcesu' => 'Identyfikator procesu',
            'statusProcesu' => 'Status procesu',
            'typDokumentu' => 'Typ dokumentu',
            'znakSprawy' => 'Znak sprawy',
            'idDokumentu' => 'Identyfikator dokumentu',
            'nrNaPismie' => 'Numer na piśmie',
            'wersja' => 'Wersja',
            'dataRejestracji' => 'Data rejestracji',
            'dataUtworzenia' => 'Data utworzenia',
            'dokumentTytul' => 'Tytuł dokumentu',
            'trescWniosku' => 'Treść wniosku',
            'nrKsiegi' => 'Numer księgi',
            'documentGroupType' => 'Typ grupy dokumentu',
        ];
    }

    /**
     * @return array{sectionLabel: ?string, labels: array<string, string>, values: DokumentDanePodstawoweWartosciDto}
     */
    public function jsonSerialize(): array
    {
        return [
            'sectionLabel' => $this->sectionLabel,
            'labels' => $this->labels,
            'values' => $this->values,
        ];
    }
}
