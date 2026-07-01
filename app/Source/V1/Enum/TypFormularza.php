<?php

declare(strict_types=1);

namespace App\Source\V1\Enum;

use App\Source\V1\Enum\Concerns\PresentsInApiValue;
use App\Source\V1\Enum\Contracts\PresentsInApi;

enum TypFormularza: string implements PresentsInApi
{
    use PresentsInApiValue;
    case Internal = 'internal';
    case External = 'external';

    /**
     * @return list<self>
     */
    public static function wszystkie(): array
    {
        return self::cases();
    }

    /**
     * @return array<string, self>
     */
    public static function mapaPoWartosci(): array
    {
        $mapa = [];
        foreach (self::cases() as $case) {
            $mapa[$case->value] = $case;
        }

        return $mapa;
    }

    /**
     * Wartość z wiersza SQL (kolumna form_typ) — bez walidacji requestu.
     */
    public static function tryFromWiersza(mixed $value): ?self
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }

    /**
     * Filtr requestu (filtry.typ_formularza) — nieznana wartość → null (filtr ignorowany).
     */
    public static function tryFromFiltra(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        return self::tryFrom($value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Wewnętrzna',
            self::External => 'Zewnętrzna',
        };
    }
}
