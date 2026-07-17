<?php

declare(strict_types=1);

namespace App\Source\V1\Support\MaterializedViews;

final class CaseListMaterializedView
{
    public const NAME = 'api_case_list';

    public const REFRESH_COMMAND = 'cases:refresh-list-mv';
}
