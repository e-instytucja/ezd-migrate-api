<?php
namespace App\Source\V1\Queries\Structure;

use Illuminate\Support\Facades\DB;

class GroupQuery {

    public function getDepartamentInfo($groupId): ?object
    {
        return $this->getGroupInfo($groupId);

    }

    private function getGroupInfo(
        int $groupId
    ): ?object {
        $columns = [
            'g_ug.groupName as departament_name',
            'g_ug.groupDesc as departament_description',
            'g_ug.group_id as departament_id',
        ];
        return DB::table('users_groups as g_ug')
            ->where('g_ug.group_id', $groupId)
            ->where('g_ug.group_type', '=', 'G')
            ->select($columns)
            ->first();
    }
}