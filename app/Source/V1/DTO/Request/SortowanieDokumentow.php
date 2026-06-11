<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class SortowanieDokumentow
{
    private const DEFAULT_FIELD = 'data_rejestracji';

    private const DEFAULT_DIRECTION = 'desc';

    /** @var array<string, string> */
    private const FIELD_COLUMNS = [
        'nazwa_procesu' => 'nazwa_procesu',
        'status_procesu' => 'status_procesu',
        'interesant' => 'interesant',
        'data_rejestracji' => 'data_rejestracji',
    ];

    /** @var array<string, list<string>> */
    private const MULTI_FIELD_COLUMNS = [
        'oznaczenie' => ['nr_ksiegi', 'znak_sprawy', 'nr_na_pismie', 'id_dokumentu'],
        'opis_dokumentu' => ['dokument_tytul', 'tresc_wniosku'],
        'wlasciciel_stanowisko' => [
            'wlasciciel_nazwisko',
            'wlasciciel_imie',
            'wlasciciel_nazwisko2',
            'wlasciciel_nazwisko3',
        ],
    ];

    public function __construct(
        public string $field,
        public string $direction,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $sort = $payload['sort'] ?? [];

        if (!is_array($sort)) {
            $sort = [];
        }

        $field = (string) ($sort['field'] ?? self::DEFAULT_FIELD);
        if (!isset(self::FIELD_COLUMNS[$field]) && !isset(self::MULTI_FIELD_COLUMNS[$field])) {
            $field = self::DEFAULT_FIELD;
        }

        $direction = strtolower((string) ($sort['direction'] ?? self::DEFAULT_DIRECTION));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = self::DEFAULT_DIRECTION;
        }

        return new self($field, $direction);
    }

    public function toOrderBySql(): string
    {
        $dir = strtoupper($this->direction);

        if (isset(self::MULTI_FIELD_COLUMNS[$this->field])) {
            return implode(', ', array_map(
                fn (string $column) => "{$column} {$dir}",
                self::MULTI_FIELD_COLUMNS[$this->field],
            ));
        }

        return self::FIELD_COLUMNS[$this->field] . ' ' . $dir;
    }
}
