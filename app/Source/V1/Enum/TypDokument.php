<?php

declare(strict_types=1);

namespace App\Source\V1\Enum;

use App\Source\V1\Enum\Concerns\PresentsInApiValue;
use App\Source\V1\Enum\Contracts\PresentsInApi;

enum TypDokument: string implements PresentsInApi
{
    use PresentsInApiValue;
    case DokPrzychodzacy = 'dok_przychodzacy';
    case DokWewnetrzny = 'dok_wewnetrzny';
    case DokWychodzacy = 'dok_wychodzacy';
    case DokZpo = 'dok_zpo';

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
     * Wartość z wiersza SQL (kolumna typ_dokumentu) — bez walidacji requestu.
     */
    public static function tryFromWiersza(mixed $value): ?self
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }

    public function isWychodzacy(): bool
    {
        return $this === self::DokWychodzacy;
    }

    public function isNiewychodzacy(): bool
    {
        return $this === self::DokPrzychodzacy || $this === self::DokWewnetrzny;
    }

    public function usesSprawaTable(): bool
    {
        return $this->isNiewychodzacy() || $this === self::DokZpo;
    }

    public function isZpo(): bool
    {
        return $this === self::DokZpo;
    }

    public function formTyp(): ?string
    {
        return match ($this) {
            self::DokPrzychodzacy => 'external',
            self::DokWewnetrzny => 'internal',
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DokWychodzacy => 'Dokumenty wychodzące',
            self::DokPrzychodzacy => 'Dokumenty przychodzące',
            self::DokWewnetrzny => 'Dokumenty wewnętrzne',
            self::DokZpo => 'Potwierdzenia odbioru',
        };
    }
}
