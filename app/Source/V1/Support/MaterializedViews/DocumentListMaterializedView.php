<?php

declare(strict_types=1);

namespace App\Source\V1\Support\MaterializedViews;

final class DocumentListMaterializedView
{
    public const NAME = 'api_document_list';

    public const REFRESH_COMMAND = 'documents:refresh-list-mv';
}
