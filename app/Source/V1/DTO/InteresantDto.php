<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class InteresantDto
{
    public function __construct(
        public InteresantKontekstDto $kontekst,
        public InteresantOsobaDto|InteresantInstytucjaDto $podmiot,
        public InteresantAdresDto    $adres,
        public InteresantKontaktDto  $kontakt,
    ) {}
}
