<?php
declare(strict_types=1);

namespace App\Source\V1\DTO;
final readonly class ZalacznikDto
{
    public function __construct(
        public ?string $uid,
        public ?string $filename,
        public ?string $nazwa,

        public ?string $zalacznikObcyUid,

        public ?int $rozmiar,
        public ?string $mime,
        public ?string $extension,

        public ?string $md5,
        public ?string $url,

        public ?string $opis,
        public ?string $dataUtworzenia,
    ) {}
}