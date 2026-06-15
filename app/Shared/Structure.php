<?php
namespace App\Shared;

class Structure
{
    public static function concatWorkstationData(object $workstation): string
    {
        return trim(sprintf(
            '%s %s [%s] {%s} (%s)',
            $workstation->forename ?? '',
            self::concatSurnames($workstation),
            $workstation->workstation_description ?? '',
            $workstation->departament_name ?? '',
            $workstation->login ?? ''
        ));
    }

    static public function concatGroupData(object $group): string
    {
        $fullName = sprintf(
            '%s (%s)',
            $group->departament_description ?? '',
            $group->departament_name ?? ''
        );
        return $fullName;

    }

    static public function concatSurnames($user): string
    {
        $surname = '';
        if (!empty($user->surname)) {
            $surname .= $user->surname;
        }
        if (!empty($user->surname2)) {
            if (!empty($surname)) {
                $surname .= '-';
            }
            $surname .= $user->surname2;
        }
        if (!empty($user->surname3)) {
            if (!empty($surname)) {
                $surname .= '-';
            }
            $surname .= $user->surname3;
        }

        return $surname;
    }
}