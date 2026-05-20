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


    public function getWorkstations(): array
    {
        return DB::table('users_groups as u_w')
            ->select([
                'u_w.groupName as workstation_name',
                'u_w.groupDesc as workstation_description',
                'u_g.groupName as departament_name',
                'u_g.groupDesc as departament_description',
                'u_w.group_id as workstation_id',
                'u_g.group_id as departament_id',
                'uu.login',
                'uu.userId as user_id',
                'uu.forename',
                'uu.surname',
                'uu.surname2',
                'uu.surname3',
            ])
            ->join('users_groups as u_g', 'u_w.parent_group_id', '=', 'u_g.group_id')
            ->leftJoin('users_usergroups as uug', function ($join) {
                $join->on('u_w.group_id', '=', 'uug.group_id')
                     ->where('uug.status', '=', 'A')
                     ->where('uug.typ', '=', 'Z');
            })
            ->leftJoin('users_users as uu', function ($join) {
                $join->on('uu.userId', '=', 'uug.userId')
                     ->where('uu.u_status', '=', 'A');
            })
            ->where('u_w.groupStatus', 'A')
            ->whereIn('u_w.group_type', ['S', 'L', 'SL', 'P'])
            ->where('u_g.group_type', 'G')
            ->where('u_g.root_type', 'U')
            ->where('u_w.root_type', 'U')
            ->get()
            ->toArray();
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