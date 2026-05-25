<?php

namespace App\Source\V1\DTO;

readonly class TypZalacznik
{

    public function __construct(
        public string $filename,
        public string $nazwa,
        public string $zalacznik_obcy_uid,
        public int $rozmiar,
        public string $url,
        public string $md5,
        public string $opis,
        public string $mime,
        public string $data_utworzenia,
        public string $extension
    )
    {
    }


}
