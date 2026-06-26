<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Scope;

readonly class WorkstationScopeResult
{
    /**
     * @param int[] $validatedWorkstationIds
     * @param int[] $activityIds
     * @param int[] $expandedWorkstationIds
     */
    public function __construct(
        public array $validatedWorkstationIds = [],
        public array $activityIds = [],
        public array $expandedWorkstationIds = [],
        public bool $isUnrestricted = false,
    ) {
    }
}
