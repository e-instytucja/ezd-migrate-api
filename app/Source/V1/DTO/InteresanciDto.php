<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class InteresanciDto implements JsonSerializable
{
    public function __construct(
        /** @var InteresantDto[] */
        public array $values,
        /** @var array<string, string> */
        public array $labels,
        public ?string $sectionLabel = 'Dane interesantów',
    ) {}

    public static function empty(): self
    {
        return new self([], self::defaultLabels(), null);
    }

    /**
     * @param InteresantDto[] $values
     */
    public static function fromValues(array $values, ?string $sectionLabel = null): self
    {
        return new self($values, self::defaultLabels(), $sectionLabel);
    }

    /**
     * @return array<string, string>
     */
    public static function defaultLabels(): array
    {
        return [
            'kontekst.uid' => 'Identyfikator',
            'kontekst.glowny' => 'Główny interesant',
            'kontekst.role' => 'Rola',
            'podmiot.typ' => 'Typ',
            'podmiot.nazwa' => 'Nazwa',
            'podmiot.imie' => 'Imię',
            'podmiot.nazwisko' => 'Nazwisko',
            'podmiot.pesel' => 'PESEL',
            'podmiot.instytucja' => 'Instytucja',
            'podmiot.nip' => 'NIP',
            'podmiot.regon' => 'REGON',
            'podmiot.krs' => 'KRS',
            'adres.ulica' => 'Ulica',
            'adres.numerDomu' => 'Numer domu',
            'adres.numerLokalu' => 'Numer lokalu',
            'adres.kodPocztowy' => 'Kod pocztowy',
            'adres.miasto' => 'Miasto',
            'adres.poczta' => 'Poczta',
            'adres.kraj' => 'Kraj',
            'adres.pelny' => 'Adres korespondencyjny',
            'kontakt.adresEpuap' => 'Adres ePUAP',
            'kontakt.telefon' => 'Telefon',
            'kontakt.kontakt' => 'Kontakt',
            'kontakt.adresWww' => 'Adres WWW',
            'kontakt.odbiorElektroniczny' => 'Odbiór pism w formie elektronicznej',
        ];
    }

    /**
     * @return array{sectionLabel: ?string, labels: array<string, string>, values: InteresantDto[]}
     */
    public function jsonSerialize(): array
    {
        return [
            'sectionLabel' => $this->sectionLabel,
            'labels' => $this->labels,
            'values' => $this->values,
        ];
    }
}
