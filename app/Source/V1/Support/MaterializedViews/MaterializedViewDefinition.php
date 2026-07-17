<?php

declare(strict_types=1);

namespace App\Source\V1\Support\MaterializedViews;

readonly class MaterializedViewDefinition
{
    public function __construct(
        public string $name,
        public string $refreshCommand,
        public string $key,
    ) {
    }
}
