<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final class DaneFormularzaDto implements JsonSerializable
{
    /** @var array<string, DaneFormularzaPoleDto> */
    private array $pola = [];

    public function addPole(string $klucz, DaneFormularzaPoleDto $pole): void
    {
        $this->pola[$klucz] = $pole;
    }

    public function removePole(string $klucz): void
    {
        unset($this->pola[$klucz]);
    }

    public function hasPole(string $klucz): bool
    {
        return isset($this->pola[$klucz]);
    }

    public function getPole(string $klucz): ?DaneFormularzaPoleDto
    {
        return $this->pola[$klucz] ?? null;
    }

    public function appendToPoleValue(string $klucz, mixed $wartosc): void
    {
        $pole = $this->pola[$klucz] ?? null;

        if ($pole === null) {
            return;
        }

        $values = is_array($pole->value) ? $pole->value : [];
        $values[] = $wartosc;

        $this->pola[$klucz] = new DaneFormularzaPoleDto(
            label: $pole->label,
            value: $values,
        );
    }

    public function count(): int
    {
        return count($this->pola);
    }

    public function extractInteresanci(): InteresanciDto
    {
        $sectionLabel = null;
        $items = [];
        $poleInteresanci = $this->getPole('interesanci');

        if ($poleInteresanci !== null) {
            $sectionLabel = $poleInteresanci->label !== '' ? $poleInteresanci->label : null;

            if (is_array($poleInteresanci->value)) {
                foreach ($poleInteresanci->value as $item) {
                    if ($item instanceof InteresantDto) {
                        $items[] = $item;
                    }
                }
            }
        }

        $this->removePole('interesanci');

        return InteresanciDto::fromValues($items, $sectionLabel);
    }

    /**
     * @return ZalacznikDto[]
     */
    public function extractZalaczniki(): array
    {
        $items = [];
        $polePliki = $this->getPole('pliki');

        if ($polePliki !== null && is_array($polePliki->value)) {
            foreach ($polePliki->value as $item) {
                $mapped = $this->mapZalacznik($item);

                if ($mapped !== null) {
                    $items[] = $mapped;
                }
            }
        }

        $this->removePole('pliki');

        return $items;
    }

    private function mapZalacznik(mixed $item): ?ZalacznikDto
    {
        if ($item instanceof ZalacznikDto) {
            return $item;
        }

        if (!is_array($item)) {
            return null;
        }

        return new ZalacznikDto(
            uid: isset($item['uid']) ? (string) $item['uid'] : null,
            filename: isset($item['filename']) ? (string) $item['filename'] : null,
            nazwa: isset($item['nazwa']) ? (string) $item['nazwa'] : null,
            zalacznikObcyUid: isset($item['zalacznikObcyUid']) ? (string) $item['zalacznikObcyUid'] : null,
            rozmiar: isset($item['rozmiar']) ? (int) $item['rozmiar'] : null,
            mime: isset($item['mime']) ? (string) $item['mime'] : null,
            extension: isset($item['extension']) ? (string) $item['extension'] : null,
            md5: isset($item['md5']) ? (string) $item['md5'] : null,
            url: isset($item['url']) ? (string) $item['url'] : null,
            opis: isset($item['opis']) ? (string) $item['opis'] : null,
            dataUtworzenia: isset($item['dataUtworzenia']) ? (string) $item['dataUtworzenia'] : null,
        );
    }

    /**
     * @return array<string, array{label: string, value: mixed}>
     */
    public function jsonSerialize(): array
    {
        $result = [];

        foreach ($this->pola as $klucz => $pole) {
            $result[$klucz] = [
                'label' => $pole->label,
                'value' => $pole->value,
            ];
        }

        return $result;
    }
}
