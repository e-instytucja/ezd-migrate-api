<?php
namespace App\Source\V1\Queries\Structure;

use Illuminate\Support\Facades\DB;

class UugQuery {
    public function getInfo($uugId)
    {
        $res  = DB::table('users_usergroups')
            ->join('users_users', 'users_usergroups.userId', '=', 'users_users.userId')
            ->join('users_groups', 'users_usergroups.group_id', '=', 'users_groups.group_id')
            ->where('users_usergroups.id', $uugId)
            ->select(
                'users_users.login',
                'users_users.forename',
                'users_users.surname',
                'users_users.surname2',
                'users_users.surname3',
                'users_groups.groupDesc as workstation_description',
                'users_groups.group_id as workstation_id'
            )
            ->first();

        return $res;
    }
}