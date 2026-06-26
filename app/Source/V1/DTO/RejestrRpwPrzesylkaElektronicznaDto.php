<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class RejestrRpwPrzesylkaElektronicznaDto implements JsonSerializable
{
    public function __construct(
        public int $idPrzesylkiRpw,
        public ?string $status,
        public ?string $identyfikatorZewnetrzny,
        public ?string $dataWyslania,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $identyfikator = $row['identyfikator_zewnetrzny']
            ?? $row['identyfikator']
            ?? $row['id_wiadomosci']
            ?? null;

        return new self(
            idPrzesylkiRpw: (int) $row['rpw_shipment_id'],
            status: isset($row['status']) ? (string) $row['status'] : null,
            identyfikatorZewnetrzny: $identyfikator !== null ? (string) $identyfikator : null,
            dataWyslania: isset($row['data_wyslania']) ? (string) $row['data_wyslania'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id_przesylki_rpw' => $this->idPrzesylkiRpw,
            'status' => $this->status,
            'identyfikator_zewnetrzny' => $this->identyfikatorZewnetrzny,
            'data_wyslania' => $this->dataWyslania,
        ];
    }
}
