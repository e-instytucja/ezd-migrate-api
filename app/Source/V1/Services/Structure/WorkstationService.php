<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Structure;

use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\DTO\TypPracownik;

class WorkstationService
{
    use StructureHelpers;
    public function __construct(
        private readonly WorkstationQuery $workstationQuery,
    ) {}

    public function getWorkstations(): array
    {
        $workstationsList = [];
        $workstationsQueryList =  $this->workstationQuery->getWorkstations();
        foreach ($workstationsQueryList as $workstation) {
            $fullName = sprintf(
                '%s %s [%s] {%s} (%s)',
                $workstation->forename,
                $this->concatSurnames($workstation),
                $workstation->workstation_description,
                $workstation->departament_name,
                $workstation->login
            );
            $workstationsList[] = [
                'nazwa' => $fullName,
                'id' => $workstation->workstation_id,
            ];
        }
        return $workstationsList;
    }
}
