<?php

namespace Ferreira\AutoCrud\Database;

use Illuminate\Support\Arr;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\Column;
use Illuminate\Database\Connection;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Ferreira\AutoCrud\Validation\MySqlEnumChecker;
use Ferreira\AutoCrud\Validation\SQLiteEnumChecker;
use Ferreira\AutoCrud\Validation\PostgresEnumChecker;
use Ferreira\AutoCrud\Validation\SqlServerEnumChecker;

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
     * @var string|null
     */
    private $labelColumn;

    /**
     * Create an instance of this class.
     *
     * @param  string  $name
     */
    public function __construct(string $name)
    {
        $doctrine = app('db.connection')->getDoctrineSchemaManager();

        if (! $doctrine->tablesExist($name)) {
            throw new DatabaseException("Table $name does not exist");
        }

        $this->name = $name;

        $this->columns = $this->computeColumns($doctrine);
        $this->primaryKey = $this->computePrimaryKey($doctrine);
        $this->foreignKeys = $this->computeForeignKeys($doctrine);
        $this->labelColumn = $this->computeLabelColumn();
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

    private function computeLabelColumn()
    {
        $stringColumns = collect($this->columns)->filter(function (Column $column) {
            return
                $column->getType()->getName() === Type::STRING && // String type
                $this->getEnumValid($column->getName()) === null; // Non-enum
        })->map(function (Column $column) {
            return $column->getName();
        })->values();

        return $stringColumns->contains('name') ? 'name' : $stringColumns->first();
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
     * by laravel documentation means either `TIMESTAMP` or `TIMESTAMPTZ`.
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

    /**
     * Determines whether this table is a pivot. Currently, a pivot is a table
     * that contains two foreign keys and, apart from possibly an identifier and
     * the creation and update timestamps, nothing else.
     *
     * @return bool
     */
    public function isPivot(): bool
    {
        return
            count($this->foreignKeys()) === 2 &&
            count(array_diff($this->columns(), ['id', 'created_at', 'updated_at'])) === 2;
        // TODO: What about soft deletes?
    }

    /**
     * Retrieves the name of the column that should be used to represent
     * instances of this table. A label column has type string and is either the
     * column `name`, if one exists, or the first string column of the table. If
     * no such column exists, return `null`. Note that `enum` columns are never
     * considered.
     *
     * @return string|null
     */
    public function labelColumn(): ?string
    {
        return $this->labelColumn;
    }

    /**
     * Retrieve the valid `enum` options of the given `enum` column. If the
     * column is of the `enum` type, return null.
     *
     * TODO: This is only implemented to MySQL and SQLite drivers; Postgres and
     * SqlServer are still required.
     *
     * @return array|null
     */
    public function getEnumValid(string $column): ?array
    {
        switch (app(Connection::class)->getDriverName()) {
            case 'mysql':
                return (new MySqlEnumChecker($this->name, $this->columns[$column]))->valid();
            case 'sqlite':
                return (new SQLiteEnumChecker($this->name, $this->columns[$column]))->valid();
            case 'pgsql':
                return (new PostgresEnumChecker($this->name, $this->columns[$column]))->valid();
            case 'sqlsrv':
                // TODO: We need to implement SqlServerEnumChecker
                return (new SqlServerEnumChecker($this->name, $this->columns[$column]))->valid();
            default:
                return null;
        }
    }
}
