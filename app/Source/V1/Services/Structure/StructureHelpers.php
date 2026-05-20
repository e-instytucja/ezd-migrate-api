<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Structure;

trait StructureHelpers
{
    private function concatSurnames($user): string
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
