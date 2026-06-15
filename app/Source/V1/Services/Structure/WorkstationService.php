<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Structure;

use App\Shared\Structure;
use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\DTO\TypPracownik;

class WorkstationService
{
    public function __construct(
        private readonly WorkstationQuery $workstationQuery,
    ) {}

    public function getWorkstations(): array
    {
        $workstationsList = [];
        $workstationsQueryList =  $this->workstationQuery->getWorkstationsActive();
        foreach ($workstationsQueryList as $workstation) {
            $fullName = Structure::concatWorkstationData($workstation);
            $workstationsList[] = [
                'nazwa' => $fullName,
                'id' => $workstation->workstation_id,
            ];
        }
        return $workstationsList;
    }
}
