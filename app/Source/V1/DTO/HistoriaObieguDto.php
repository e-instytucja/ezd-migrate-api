<?php
declare(strict_types=1);

namespace App\Source\V1\DTO;

readonly class HistoriaObieguDto
{

    public function __construct(
        public string $dokumentId,
        public int    $instanceId,
        public string $dataUtworzenia,
        public string $statusOpis,
        public string $stanowiskoOd,
        public string $stanowiskoDo,
        public bool $automat,  //czy dodane przez np. cron
    )
    {
    }


}
