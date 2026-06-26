<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Structure;

use App\Source\V1\DTO\Request\ApiKonfiguracja;
use App\Source\V1\DTO\Scope\WorkstationScopeResult;
use App\Source\V1\Enum\WorkstationScopeProfile;
use App\Source\V1\Queries\Structure\WorkstationScopeQuery;
use Exception;

class WorkstationScopeService
{
    public function __construct(
        private readonly WorkstationScopeQuery $workstationScopeQuery,
    ) {
    }

    public function resolve(ApiKonfiguracja $konfiguracja, WorkstationScopeProfile $profile): WorkstationScopeResult
    {
        $this->assertNonEmpty($konfiguracja);

        $validatedIds = $this->workstationScopeQuery->validateWorkstationIds(
            $konfiguracja->madkomWorkstationIds,
        );

        return match ($profile) {
            WorkstationScopeProfile::RegistryBrowse => new WorkstationScopeResult(
                validatedWorkstationIds: $validatedIds,
                activityIds: $this->workstationScopeQuery->getActivityIdsForWorkstations($validatedIds),
            ),
            WorkstationScopeProfile::RpwEntryList => $this->resolveRpwEntryList($validatedIds),
        };
    }

    public function assertNonEmpty(ApiKonfiguracja $konfiguracja): void
    {
        if ($konfiguracja->madkomWorkstationIds === []) {
            throw new Exception('Brak wskazanych wlascicieli [err_10_appendWorkstationScope]');
        }
    }

    /**
     * @param int[] $validatedIds
     */
    private function resolveRpwEntryList(array $validatedIds): WorkstationScopeResult
    {
        foreach ($validatedIds as $workstationId) {
            if ($this->workstationScopeQuery->hasWorkstationPermission($workstationId, 'pisma_wychodzace')) {
                return new WorkstationScopeResult(
                    validatedWorkstationIds: $validatedIds,
                    isUnrestricted: true,
                );
            }
        }

        return new WorkstationScopeResult(
            validatedWorkstationIds: $validatedIds,
            expandedWorkstationIds: $this->workstationScopeQuery->expandWorkstationIdsWithIncludedGroup($validatedIds),
        );
    }
}
