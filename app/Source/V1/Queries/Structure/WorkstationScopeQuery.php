<?php

declare(strict_types=1);

namespace App\Source\V1\Queries\Structure;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkstationScopeQuery
{
    /**
     * @param int[] $workstationIds
     * @return int[]
     */
    public function validateWorkstationIds(array $workstationIds): array
    {
        if ($workstationIds === []) {
            return [];
        }

        $validated = DB::table('users_groups')
            ->whereIn('group_id', $workstationIds)
            ->where('groupStatus', 'A')
            ->orderBy('group_id')
            ->pluck('group_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        sort($workstationIds);
        $sortedValidated = $validated;
        sort($sortedValidated);

        if ($sortedValidated !== $workstationIds) {
            throw new InvalidArgumentException('Nieprawidlowe identyfikatory stanowisk w konfiguracji');
        }

        return $validated;
    }

    /**
     * @param int[] $workstationIds
     * @return int[]
     */
    public function getActivityIdsForWorkstations(array $workstationIds): array
    {
        if ($workstationIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($workstationIds), '?'));
        $rows = DB::select(<<<SQL
            SELECT DISTINCT gar."activityId" AS activity_id
            FROM users_usergroups uug
            INNER JOIN galaxia_user_roles gur ON gur."userGroupId" = uug.id
            INNER JOIN galaxia_activity_roles gar ON gar."roleId" = gur."roleId"
            WHERE uug.group_id IN ({$placeholders})
              AND uug.status = 'A'
        SQL, $workstationIds);

        $activityIds = array_map(
            static fn ($row) => (int) $row->activity_id,
            $rows,
        );

        return array_values(array_unique($activityIds));
    }

    public function hasWorkstationPermission(int $workstationId, string $permissionSymbol): bool
    {
        return DB::table('users_permissions_content as upc')
            ->join('users_permissions as up', 'up.id', '=', 'upc.permission_id')
            ->join('users_usergroups as uug', 'uug.id', '=', 'upc.uug_id')
            ->where('uug.group_id', $workstationId)
            ->where('uug.status', 'A')
            ->where('up.symbol', $permissionSymbol)
            ->where('upc.value', 'y')
            ->exists();
    }

    /**
     * @param int[] $workstationIds
     * @return int[]
     */
    public function expandWorkstationIdsWithIncludedGroup(array $workstationIds): array
    {
        if ($workstationIds === []) {
            return [];
        }

        $expanded = $workstationIds;

        foreach ($workstationIds as $workstationId) {
            if (!$this->hasWorkstationPermission($workstationId, 'eurzad2_p_pismo_wychodzace_group_include')) {
                continue;
            }

            foreach ($this->getIncludedGroupWorkstationIds($workstationId) as $includedId) {
                $expanded[] = $includedId;
            }
        }

        $expanded = array_values(array_unique($expanded));
        sort($expanded);

        return $expanded;
    }

    /**
     * @return int[]
     */
    private function getIncludedGroupWorkstationIds(int $workstationId): array
    {
        $parentGroupId = DB::table('users_groups')
            ->where('group_id', $workstationId)
            ->value('parent_group_id');

        if ($parentGroupId === null) {
            return [];
        }

        return DB::table('users_groups')
            ->where('parent_group_id', $parentGroupId)
            ->where('groupStatus', 'A')
            ->where('group_type', '!=', 'G')
            ->pluck('group_id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }
}
