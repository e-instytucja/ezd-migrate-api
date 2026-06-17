<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class SprawaZnakDto implements JsonSerializable
{
    public function __construct(
        public SprawaZnakWartosciDto $values,
        /** @var array<string, string> */
        public array $labels,
        public ?string $sectionLabel = null,
    ) {}

    public static function empty(): self
    {
        return new self(
            values: new SprawaZnakWartosciDto(),
            labels: self::defaultLabels(),
            sectionLabel: 'Znak sprawy',
        );
    }

    public static function fromValues(
        SprawaZnakWartosciDto $values,
        ?string $sectionLabel = 'Znak sprawy',
    ): self {
        return new self($values, self::defaultLabels(), $sectionLabel);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromCaseRow(
        array $row,
        object $caseData,
        string $caseUid,
        ?string $fallbackSymbolKomorki = null,
    ): self {
        return self::fromValues(
            SprawaZnakWartosciDto::fromCaseData(
                pelny: $row['znak'],
                oznaczenieDntas: $row['oznaczenie_dntas'],
                caseData: $caseData,
                caseUid: $caseUid,
                fallbackSymbolKomorki: $fallbackSymbolKomorki,
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function defaultLabels(): array
    {
        return [
            'pelny' => 'Znak sprawy',
            'oznaczenieDntas' => 'Oznaczenie DNTAS',
            'symbolKomorki' => 'Symbol komórki',
            'symbolJrwa' => 'Symbol JRWA',
            'numerPodteczki' => 'Numer podteczki',
            'opisPodteczki' => 'Opis podteczki',
            'numer' => 'Numer',
            'rok' => 'Rok',
        ];
    }

    /**
     * @return array{sectionLabel: ?string, labels: array<string, string>, values: SprawaZnakWartosciDto}
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
