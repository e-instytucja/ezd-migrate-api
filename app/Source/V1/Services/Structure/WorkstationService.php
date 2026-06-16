<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Structure;

use App\Source\V1\DTO\PracownikDto;
use App\Source\V1\Queries\Structure\WorkstationQuery;

class WorkstationService
{
    public function __construct(
        private readonly WorkstationQuery $workstationQuery,
    ) {}

    public function getWorkstations(): array
    {
        return array_map(
            function (object $row): array {
                $dto = PracownikDto::fromWorkstationRow($row);

                return [
                    'id' => $dto->stanowiskoId,
                    'nazwa' => $dto->formatWorkstationListLabel($row->login ?? null),
                ];
            },
            $this->workstationQuery->getWorkstationsActive(),
        );
    }
}
