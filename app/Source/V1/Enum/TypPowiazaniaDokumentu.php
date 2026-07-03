<?php

declare(strict_types=1);

namespace App\Source\V1\Enum;

use App\Source\V1\Enum\Concerns\PresentsInApiValue;
use App\Source\V1\Enum\Contracts\PresentsInApi;

enum TypPowiazaniaDokumentu: string implements PresentsInApi
{
    use PresentsInApiValue;

    case InicjujacySprawe = 'inicjujacy_sprawe';
    case WSprawie = 'w_sprawie';
    case BezSprawy = 'bez_sprawy';
    case Zpo = 'zpo';

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

    public static function tryFromWiersza(mixed $value): ?self
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }

    public function label(): string
    {
        return match ($this) {
            self::InicjujacySprawe => 'Inicjujący sprawę',
            self::WSprawie => 'W sprawie',
            self::BezSprawy => 'Bez sprawy',
            self::Zpo => 'Potwierdzenie odbioru',
        };
    }
}
