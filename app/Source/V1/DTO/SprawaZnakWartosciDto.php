<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use Exception;

final class SprawaZnakWartosciDto
{
    public ?string $pelny = '';
    public ?string $oznaczenieDntas = null;
    public string $symbolKomorki = '';
    public string $symbolJrwa = '';
    public ?int $numerPodteczki = null;
    public ?string $opisPodteczki = null;
    public ?int $numer = null;
    public int|string|null $rok = null;

    public static function fromCaseData(
        ?string $pelny,
        ?string $oznaczenieDntas,
        object $caseData,
        string $caseUid,
        ?string $fallbackSymbolKomorki = null,
    ): self {
        if (empty($caseData->teczka_sygnatura)) {
            throw new Exception(
                "Brak JRWA dla ID: '{$caseUid}'"
            );
        }

        $jrwa = self::parseJrwaSymbol($caseData->teczka_sygnatura);
        $zbiorNr = str_replace([$jrwa, '-', '.'], '', $caseData->teczka_sygnatura);

        $dto = new self();
        $dto->pelny = $pelny;
        $dto->oznaczenieDntas = $oznaczenieDntas;
        $dto->symbolJrwa = $jrwa;
        $dto->numerPodteczki = $zbiorNr === '' ? null : (int) $zbiorNr;
        $dto->numer = $caseData->teczka_numer ?? null;
        $dto->rok = $caseData->teczka_rok_zalozenia ?? null;
        $dto->opisPodteczki = $caseData->opis_zbioru ?? null;
        $dto->symbolKomorki = $caseData->teczka_wydzial ?? '';

        if ($dto->symbolKomorki === '' && $fallbackSymbolKomorki !== null) {
            $dto->symbolKomorki = $fallbackSymbolKomorki;
        }

        return $dto;
    }

    private static function parseJrwaSymbol(string $sygnatura): string
    {
        foreach (['-', '.'] as $separator) {
            $parts = explode($separator, $sygnatura, 2);

            if (count($parts) > 1) {
                return $parts[0];
            }
        }

        return $sygnatura;
    }
}
