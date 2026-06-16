<?php

namespace App\Source\V1\Services\Structure;

use App\Source\V1\DTO\PracownikDto;
use App\Source\V1\Queries\Structure\UugQuery;

class EmployeeService
{
    public function __construct(
        private UugQuery $uugQuery,
    ) {
    }

    public function getEmployeeFullNameByUugId($uugid): string
    {
        $uugInfo = $this->uugQuery->getInfo($uugid);
        return PracownikDto::fromUugInfo($uugInfo)->displayName();
    }
}
