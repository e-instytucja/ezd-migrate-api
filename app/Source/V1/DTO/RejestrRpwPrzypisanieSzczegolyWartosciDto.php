<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class RejestrRpwPrzypisanieSzczegolyWartosciDto implements JsonSerializable
{
    /**
     * @param HistoriaObieguDto[] $historiaObiegu
     */
    public function __construct(
        public int $idPrzypisaniaRejestru,
        public string $uidPrzypisaniaRejestru,
        public string $idDokumentu,
        public ?string $numerPrzypisania,
        public ?string $typPrzypisania,
        public string $uidRejestru,
        public ?string $typRejestru,
        public ?string $opisRejestru,
        public ?string $dataUtworzenia,
        public ?string $uidPrzesylkiNadrzednej,
        public ?string $nazwaProcesu,
        public ?RejestrRpwWysylkaDto $wysylka = null,
        public ?InteresantDto $adresat = null,
        public array $historiaObiegu = [],
    ) {
    }

    public static function fromPodstawa(
        RejestrRpwPrzypisanieWartosciDto $podstawa,
        ?RejestrRpwWysylkaDto $wysylka = null,
        ?InteresantDto $adresat = null,
        array $historiaObiegu = [],
    ): self {
        return new self(
            idPrzypisaniaRejestru: $podstawa->registryAssignmentId,
            uidPrzypisaniaRejestru: $podstawa->registryAssignmentUid,
            idDokumentu: $podstawa->documentId,
            numerPrzypisania: $podstawa->registryAssignmentNumber,
            typPrzypisania: $podstawa->registryAssignmentType,
            uidRejestru: $podstawa->registryUid,
            typRejestru: $podstawa->registryType,
            opisRejestru: $podstawa->registryDescription,
            dataUtworzenia: $podstawa->createdAt,
            uidPrzesylkiNadrzednej: $podstawa->parentShipmentUid,
            nazwaProcesu: $podstawa->processName,
            wysylka: $wysylka,
            adresat: $adresat,
            historiaObiegu: $historiaObiegu,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id_przypisania_rejestru' => $this->idPrzypisaniaRejestru,
            'uid_przypisania_rejestru' => $this->uidPrzypisaniaRejestru,
            'id_dokumentu' => $this->idDokumentu,
            'numer_przypisania' => $this->numerPrzypisania,
            'typ_przypisania' => $this->typPrzypisania,
            'uid_rejestru' => $this->uidRejestru,
            'typ_rejestru' => $this->typRejestru,
            'opis_rejestru' => $this->opisRejestru,
            'data_utworzenia' => $this->dataUtworzenia,
            'uid_przesylki_nadrzednej' => $this->uidPrzesylkiNadrzednej,
            'nazwa_procesu' => $this->nazwaProcesu,
            'wysylka' => $this->wysylka,
            'adresat' => $this->adresat,
            'historia_obiegu' => $this->historiaObiegu,
        ];
    }
}
