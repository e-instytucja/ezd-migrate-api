<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class RejestrRpwPrzypisanieDto implements JsonSerializable
{
    /**
     * @param array<string, string> $labels
     */
    public function __construct(
        public RejestrRpwPrzypisanieWartosciDto $values,
        public array $labels,
        public ?string $sectionLabel = 'Wysyłka RPW',
    ) {
    }

    public static function fromValues(
        RejestrRpwPrzypisanieWartosciDto $values,
        ?string $sectionLabel = null,
    ): self {
        return new self($values, RejestrRpwPrzypisaniaDto::defaultLabels(), $sectionLabel);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return self::fromValues(RejestrRpwPrzypisanieWartosciDto::fromRow($row));
    }

    /**
     * @return array{sectionLabel: ?string, labels: array<string, string>, values: RejestrRpwPrzypisanieWartosciDto}
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
