<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class Sortowanie
{
    private const DEFAULT_FIELD = 'data_wszczecia';

    private const DEFAULT_DIRECTION = 'desc';

    /** @var array<string, string> */
    private const FIELD_COLUMNS = [
        'znak'            => 'et.teczka_znak_sprawy',
        'tytul_sprawy'    => 'et.tytul_sprawy',
        'nazwa_procesu'   => 'gp.name',
        'interesant'      => 'ps_petent.view_podmiot',
        'status_procesu'  => 'ess.opis',
        'data_wszczecia'  => 'et.teczka_createdate',
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
        if (!isset(self::FIELD_COLUMNS[$field])) {
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
        return self::FIELD_COLUMNS[$this->field] . ' ' . strtoupper($this->direction);
    }
}
