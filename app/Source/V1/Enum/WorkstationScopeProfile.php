<?php

declare(strict_types=1);

namespace App\Source\V1\Enum;

enum WorkstationScopeProfile: string
{
    case RegistryBrowse = 'registry_browse';
    case RpwEntryList = 'rpw_entry_list';
}
