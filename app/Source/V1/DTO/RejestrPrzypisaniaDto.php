<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class RejestrPrzypisaniaDto implements JsonSerializable
{
    /**
     * @param RejestrPrzypisanieWartosciDto[] $values
     * @param array<string, string> $labels
     */
    public function __construct(
        public array $values,
        public array $labels,
        public ?string $sectionLabel = 'Rejestry',
    ) {
    }

    public static function empty(): self
    {
        return new self([], self::defaultLabels());
    }

    /**
     * @param RejestrPrzypisanieWartosciDto[] $values
     */
    public static function fromValues(array $values, ?string $sectionLabel = null): self
    {
        return new self($values, self::defaultLabels(), $sectionLabel);
    }

    /**
     * @return array<string, string>
     */
    public static function defaultLabels(): array
    {
        return [
            'registry_assignment_id' => 'ID przypisania',
            'registry_assignment_uid' => 'UID przypisania',
            'document_id' => 'ID dokumentu',
            'registry_assignment_number' => 'Numer',
            'registry_assignment_type' => 'Typ przypisania',
            'registry_uid' => 'UID rejestru',
            'registry_type' => 'Typ rejestru',
            'registry_description' => 'Opis rejestru',
            'created_at' => 'Data utworzenia',
            'lead_case_uid' => 'UID sprawy wiodącej',
            'process_name' => 'Nazwa procesu',
        ];
    }

    /**
     * @return array{sectionLabel: ?string, labels: array<string, string>, values: RejestrPrzypisanieWartosciDto[]}
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
