<?php
declare(strict_types=1);

namespace App\Source\V1\DTO;

readonly class TypHistoriaObiegu
{

    public function __construct(
        public string $dokumentId,
        public int    $instanceId,
        public string $dataUtworzenia,
        public string $akcja,
        public string $stanowiskoOd,
        public string $stanowiskoDo,
    )
    {
    }


}
