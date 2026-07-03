<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Document;

use App\Source\V1\Enum\TypPowiazaniaDokumentu;

enum TypUnionDokumentu: string
{
    case DokWychodzacyWSprawie = 'dok_wychodzacy_w_sprawie';
    case DokNiewychodzacyInicjujacySprawe = 'dok_niewychodzacy_inicjujacy_sprawe';
    case DokNiewychodzacyWSprawie = 'dok_niewychodzacy_w_sprawie';
    case DokNiewychodzacyBezSprawy = 'dok_niewychodzacy_bez_sprawy';
    case DokZpo = 'dok_zpo';

    /**
     * @return list<self>
     */
    public static function wszystkie(): array
    {
        return self::cases();
    }

    /**
     * @return list<self>
     */
    public static function niewychodzace(): array
    {
        return [
            self::DokNiewychodzacyInicjujacySprawe,
            self::DokNiewychodzacyWSprawie,
            self::DokNiewychodzacyBezSprawy,
        ];
    }

    public function isWychodzacy(): bool
    {
        return $this === self::DokWychodzacyWSprawie;
    }

    public function usesSprawaTable(): bool
    {
        return !$this->isWychodzacy();
    }

    public function isZpo(): bool
    {
        return $this === self::DokZpo;
    }

    public function isNiewychodzacyBezSprawy(): bool
    {
        return $this === self::DokNiewychodzacyBezSprawy;
    }

    public function powiazanie(): TypPowiazaniaDokumentu
    {
        return match ($this) {
            self::DokWychodzacyWSprawie,
            self::DokNiewychodzacyWSprawie => TypPowiazaniaDokumentu::WSprawie,
            self::DokNiewychodzacyInicjujacySprawe => TypPowiazaniaDokumentu::InicjujacySprawe,
            self::DokNiewychodzacyBezSprawy => TypPowiazaniaDokumentu::BezSprawy,
            self::DokZpo => TypPowiazaniaDokumentu::Zpo,
        };
    }
}
