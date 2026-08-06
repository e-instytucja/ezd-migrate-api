<?php

declare(strict_types=1);

namespace App\Source\V1\Support\MaterializedViews;

use Illuminate\Support\Facades\DB;

final class MaterializedViewRegistry
{
    /**
     * @return list<MaterializedViewDefinition>
     */
    public function definitions(): array
    {
        return [
            new MaterializedViewDefinition(
                CaseListMaterializedView::NAME,
                CaseListMaterializedView::REFRESH_COMMAND,
                'case_list',
            ),
            new MaterializedViewDefinition(
                DocumentListMaterializedView::NAME,
                DocumentListMaterializedView::REFRESH_COMMAND,
                'document_list',
            ),
        ];
    }

    public function exists(string $viewName): bool
    {
        $result = DB::selectOne(
            'SELECT to_regclass(?) AS regclass',
            [MaterializedViewNaming::qualified($viewName)],
        );

        return $result !== null && $result->regclass !== null;
    }

    public function rowCount(string $viewName): ?int
    {
        if (!$this->exists($viewName)) {
            return null;
        }

        $result = DB::selectOne(
            'SELECT COUNT(*) AS count FROM ' . MaterializedViewNaming::qualified($viewName),
        );

        return (int) $result->count;
    }

    public function allExist(): bool
    {
        foreach ($this->definitions() as $definition) {
            if (!$this->exists($definition->name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<MaterializedViewDefinition>
     */
    public function missing(): array
    {
        $missing = [];

        foreach ($this->definitions() as $definition) {
            if (!$this->exists($definition->name)) {
                $missing[] = $definition;
            }
        }

        return $missing;
    }

    /**
     * @return list<array{key: string, name: string, schema: string, exists: bool, row_count: int|null, refresh_command: string}>
     */
    public function viewsStatus(): array
    {
        $status = [];
        $schema = MaterializedViewNaming::schema();

        foreach ($this->definitions() as $definition) {
            $exists = $this->exists($definition->name);
            $status[] = [
                'key' => $definition->key,
                'name' => $definition->name,
                'schema' => $schema,
                'exists' => $exists,
                'row_count' => $exists ? $this->rowCount($definition->name) : null,
                'refresh_command' => $definition->refreshCommand,
            ];
        }

        return $status;
    }
}
