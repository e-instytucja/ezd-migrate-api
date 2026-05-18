<?php
namespace App\Source\V1\Queries\Structure;

use Illuminate\Support\Facades\DB;

class WorkstationQuery {
    public function getUugId($workstationId)
    {
        $uugId = DB::table('users_usergroups')
            ->where('group_id', $workstationId)
            ->where('status', 'A')
            ->where('typ', 'Z')
            ->value('id');
        if(empty($uugId)) {
            throw new \Exception("Brak danych dla pracownika {$workstationId}");
        }
        return $uugId;
    }

    public function getDepartamentInfo($workstationId): array
    {
        return (array) $this->getWorkstationInfo($workstationId, ['g_ug.*']);

    }

    public function getWorkstationInfo(
        int $workstationId,
        array $columns = ['w_ug.*']
    ): ?object {
        return DB::table('users_groups as w_ug')
            ->join(
                'users_groups as g_ug',
                'g_ug.group_id',
                '=',
                'w_ug.parent_group_id'
            )
            ->where('w_ug.group_id', $workstationId)
            ->where('w_ug.group_type', '!=', 'G')
            ->select($columns)
            ->first();
    }
}