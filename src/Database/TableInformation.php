<?php

namespace Ferreira\AutoCrud\Database;

use Illuminate\Support\Arr;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzType;

class TableInformation
{
    /**
     * The name of the table being analysed.
     *
     * @var string
     */
    private $name;

    /**
     * @var \Doctrine\DBAL\Schema\Column[]
     */
    private $columns;

    /**
     * The name of the column that acts as primary key (or an array thereof).
     *
     * @var null|string|array
     */
    private $primaryKey;

    /**
     * @var \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    private $foreignKeys;

    /**
     * Create an instance of this class.
     *
     * @param  string  $name
     */
    public function __construct(string $name)
    {
        $doctrine = app('db.connection')->getDoctrineSchemaManager();

        $this->name = $name;

        $this->columns = $this->computeColumns($doctrine);
        $this->primaryKey = $this->computePrimaryKey($doctrine);
        $this->foreignKeys = $this->computeForeignKeys($doctrine);
    }

    private function computeColumns($doctrine): array
    {
        return $doctrine->listTableColumns($this->name);
    }

    private function computePrimaryKey($doctrine)
    {
        $indexes = $doctrine->listTableIndexes($this->name);

        if (! isset($indexes['primary'])) {
            return;
        }

        $columns = $indexes['primary']->getColumns();

        if (count($columns) === 0) {
            return;
        } elseif (count($columns) === 1) {
            return $columns[0];
        } else {
            return $columns;
        }
    }

    private function computeForeignKeys($doctrine)
    {
        return $doctrine->listTableForeignKeys($this->name);
    }

    /**
     * Return the name of the table.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get the name of the columns in this table.
     *
     * @return string[]
     */
    public function columns(): array
    {
        return array_keys($this->columns);
    }

    /**
     * Determine whether the table has a column with the given name.
     *
     * @param string $column
     *
     * @return bool
     */
    public function has(string $column): bool
    {
        return Arr::has($this->columns, $column);
    }

    /**
     * Get a doctrine's Column instance for the provided column name.
     *
     * @param  string  @column
     *
     * @return null|Column
     */
    public function column($column): ?Column
    {
        return Arr::get($this->columns, $column);
    }

    /**
     * Determine whether the provided column is required.
     * A required column is one that is not nullable.
     *
     * @param  string  $column
     *
     * @return null|bool
     */
    public function required($column): ?bool
    {
        $column = $this->column($column);

        return $column === null ? null : $column->getNotnull();
    }

    /**
     * Get the Doctrine's string representation of the column's type.
     *
     * @param  string  $column
     * @return null|Type
     */
    public function type(string $column): ?Type
    {
        $column = $this->column($column);

        return $column === null ? null : $column->getType();
    }

    /**
     * Return the name of the column or columns of the primary key of this table.
     *
     * @return null|string|string[]
     */
    public function primaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * Determine whether this table has a column named `deleted_at` whose type
     * is one of the possible types used by laravel for this column, which,
     * by laravel documention means either `TIMESTAMP` or `TIMESTAMPTZ`.
     *
     * @return bool
     */
    public function softDeletes(): bool
    {
        $type = $this->type('deleted_at');

        return $type instanceof DateTimeType || $type instanceof DateTimeTzType;
    }

    /**
     * Return the foreign keys of this table.
     *
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     */
    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }
}
