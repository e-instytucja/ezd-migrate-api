<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class RejestrRpwFormaDoreczeniaDto implements JsonSerializable
{
    public function __construct(
        public string $klucz,
        public ?string $nazwa,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            klucz: (string) $row['klucz'],
            nazwa: isset($row['nazwa']) ? (string) $row['nazwa'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'klucz' => $this->klucz,
            'nazwa' => $this->nazwa,
        ];
    }
}
