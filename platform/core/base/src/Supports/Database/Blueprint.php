<?php

namespace Botble\Base\Supports\Database;

use Botble\Base\Models\BaseModel as Model;
use Closure;
use Illuminate\Database\Schema\Blueprint as IlluminateBlueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;

class Blueprint extends IlluminateBlueprint
{
    public function __construct($table, Closure $callback = null, $prefix = '')
    {
        parent::__construct($table, $callback, $prefix);

        rescue(fn () => DB::statement('SET SESSION sql_require_primary_key=0'), report: false);
    }

    public function id($column = 'id'): ColumnDefinition
    {
        return match (Model::getTypeOfId()) {
            'UUID' => $this->uuid($column)->primary(),
            'ULID' => $this->ulid($column)->primary(),
            default => parent::id($column),
        };
    }

    public function foreignId($column): ColumnDefinition
    {
        return match (Model::getTypeOfId()) {
            'UUID' => $this->foreignUuid($column),
            'ULID' => $this->foreignUlid($column),
            default => parent::foreignId($column),
        };
    }

    public function morphs($name, $indexName = null): void
    {
        match (Model::getTypeOfId()) {
            'UUID' => $this->uuidMorphs($name, $indexName),
            'ULID' => $this->ulidMorphs($name, $indexName),
            default => parent::morphs($name, $indexName),
        };
    }

    public function nullableMorphs($name, $indexName = null): void
    {
        match (Model::getTypeOfId()) {
            'UUID' => $this->nullableUuidMorphs($name, $indexName),
            'ULID' => $this->nullableUlidMorphs($name, $indexName),
            default => parent::nullableMorphs($name, $indexName),
        };
    }
}
