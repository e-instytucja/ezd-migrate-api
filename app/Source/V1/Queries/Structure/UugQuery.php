<?php
namespace App\Source\V1\Queries\Structure;

use Illuminate\Support\Facades\DB;

class UugQuery {
    public function getInfo(
        $uugId,
        $columns = [
            'uu.login',
            'uu.userId as user_id',
            'uu.forename',
            'uu.surname',
            'uu.surname2',
            'uu.surname3',
            'w_ug.groupDesc as workstation_description',
            'w_ug.group_id as workstation_id',
            'g_ug.groupDesc as departament_description',
            'g_ug.groupName as departament_name',
            'g_ug.group_id as departament_id',

        ])
    {
        $res  = DB::table('users_usergroups as uug')
            ->join('users_users as uu', 'uug.userId', '=', 'uu.userId')
            ->join('users_groups as w_ug', 'uug.group_id', '=', 'w_ug.group_id')
            ->join('users_groups as g_ug', 'g_ug.group_id', '=', 'w_ug.parent_group_id')
            ->where('uug.id', $uugId)
            ->select($columns)
            ->first();

        return $res;
    }

    public function getDepartamentInfo($uugId): array
    {
        return (array) $this->getInfo($uugId, ['g_ug.*']);

    }
}