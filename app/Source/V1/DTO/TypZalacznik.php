<?php

namespace App\Source\V1\DTO;

readonly class TypZalacznik
{

    public function __construct(
        public string $nazwa,
        public int $rozmiar,
        public string $url,
        public string $md5,
        public string $opis,
    )
    {
    }


}
