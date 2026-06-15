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
        $columns = [
            'g_ug.groupName as departament_name',
            'g_ug.groupDesc as departament_description',
            'g_ug.group_id as departament_id',
        ];
        return (array) $this->getWorkstationInfo($workstationId, $columns);

    }

    public function getDepartament($workstationId)
    {
        return $this->getWorkstationsActive($workstationId);
    }


    public function getWorkstationsActive(): array
    {
        return DB::table('users_groups as w_ug')
            ->select([
                'w_ug.groupName as workstation_name',
                'w_ug.groupDesc as workstation_description',
                'g_ug.groupName as departament_name',
                'g_ug.groupDesc as departament_description',
                'w_ug.group_id as workstation_id',
                'g_ug.group_id as departament_id',
                'uu.login',
                'uu.userId as user_id',
                'uu.forename',
                'uu.surname',
                'uu.surname2',
                'uu.surname3',
            ])
            ->join('users_groups as g_ug', 'w_ug.parent_group_id', '=', 'g_ug.group_id')
            ->leftJoin('users_usergroups as uug', function ($join) {
                $join->on('w_ug.group_id', '=', 'uug.group_id')
                     ->where('uug.status', '=', 'A')
                     ->where('uug.typ', '=', 'Z');
            })
            ->leftJoin('users_users as uu', function ($join) {
                $join->on('uu.userId', '=', 'uug.userId')
                     ->where('uu.u_status', '=', 'A');
            })
            ->where('w_ug.groupStatus', 'A')
            ->whereIn('w_ug.group_type', ['S', 'L', 'SL', 'P'])
            ->where('g_ug.group_type', 'G')
            ->where('g_ug.root_type', 'U')
            ->where('w_ug.root_type', 'U')
            ->get()
            ->toArray();
    }

    public function getWorkstationInfo(
        int $workstationId,
        array $columns = []
    ): ?object {
        if(empty($columns)) {
            $columns = [
                'w_ug.groupName as workstation_name',
                'w_ug.groupDesc as workstation_description',
                'g_ug.groupName as departament_name',
                'g_ug.groupDesc as departament_description',
                'w_ug.group_id as workstation_id',
                'g_ug.group_id as departament_id',
                'uu.login',
                'uu.userId as user_id',
                'uu.forename',
                'uu.surname',
                'uu.surname2',
                'uu.surname3',
            ];
        }
        return DB::table('users_groups as w_ug')
            ->join(
                'users_groups as g_ug',
                'g_ug.group_id',
                '=',
                'w_ug.parent_group_id'
            )
            ->leftJoin('users_usergroups as uug', function ($join) {
                $join->on('w_ug.group_id', '=', 'uug.group_id')
                    ->where('uug.status', '=', 'A')
                    ->where('uug.typ', '=', 'Z');
            })
            ->leftJoin('users_users as uu', function ($join) {
                $join->on('uu.userId', '=', 'uug.userId')
                    ->where('uu.u_status', '=', 'A');
            })
            ->where('w_ug.group_id', $workstationId)
            ->where('w_ug.group_type', '!=', 'G')
            ->select($columns)
            ->first();
    }
}