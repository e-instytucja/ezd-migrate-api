<?php

namespace App\Source\V1\DTO;

/**
 * Class TypPozycjaInteresanta
 *
 * @package Docflow\ESBService\Proxy\Type
 */
readonly class TypPozycjaInteresanta
{
    /**
     * @var string
     */
    public string $id_interesanta;
    /**
     * @var string[]
     */
    public array $role;

    public string $opis;

    public array $szczegoly;

    public bool $glowny;


    public function __construct($interesantDane, $interesantRole, $interesantGlowny)
    {
        $this->id_interesanta = $interesantDane['petent_uid'];
        $this->role = $interesantRole;
        $this->opis = $interesantDane['petent_data_to_display'];
        $this->szczegoly = $interesantDane;
        $this->glowny = $interesantGlowny;

    }

}
