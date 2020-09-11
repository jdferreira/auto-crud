<?php

namespace Ferreira\AutoCrud\Database;

use Ferreira\AutoCrud\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Doctrine\DBAL\Schema\Column;
use Illuminate\Database\Connection;
use Doctrine\DBAL\Types\Type as DoctrineType;
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
     * @var string[][]
     */
    private $references;

    /**
     * @var string|null
     */
    private $labelColumn;

    /**
     * @var string[][]
     */
    private $uniqueColumns;

    /**
     * @var array
     */
    private $defaults;

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
        $this->references = $this->computeReferences($doctrine);
        $this->uniqueColumns = $this->computeUniqueColumns($doctrine);
        $this->labelColumn = $this->computeLabelColumn();
        $this->defaults = $this->computeDefaults();
    }

    private function computeColumns($doctrine): array
    {
        $columns = $doctrine->listTableColumns($this->name);

        $this->unescapeColumnNames($columns);

        return $columns;
    }

    private function unescapeColumnNames(&$columns)
    {
        $names = array_keys($columns);

        foreach ($names as $name) {
            $firstChar = Str::substr($name, 0, 1);

            if (in_array($firstChar, ['"', '`']) && Str::substr($name, -1, 1) === $firstChar) {
                $unescaped = Str::substr($name, 1, Str::length($name) - 2);
            } else {
                $unescaped = $name;
            }

            // We need to replace all keys, even if the name is not escaped, to
            // preserve order
            $columns[$unescaped] = Arr::pull($columns, $name);
        }
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

    private function computeReferences($doctrine)
    {
        $result = [];

        foreach ($doctrine->listTableForeignKeys($this->name) as $key) {
            $localColumns = $key->getLocalColumns();

            if (count($localColumns) === 1) {
                $result[$localColumns[0]] = [$key->getForeignTableName(), $key->getForeignColumns()[0]];
            }
        }

        return $result;
    }

    private function computeUniqueColumns($doctrine)
    {
        $result = [];

        foreach ($doctrine->listTableIndexes($this->name) as $index) {
            if ($index->isUnique() || $index->isPrimary()) {
                $result[] = Arr::sort($index->getColumns());
            }
        }

        return $result;
    }

    private function computeLabelColumn()
    {
        $stringColumns = collect($this->columns)->filter(function (Column $column) {
            return
                $column->getType()->getName() === DoctrineType::STRING && // String type
                $this->getEnumValid($column->getName()) === null; // Non-enum
                // TODO: The previous should use our own Type class, not Doctrine's type
        })->map(function (Column $column) {
            return $column->getName();
        })->values();

        return $stringColumns->contains('name') ? 'name' : $stringColumns->first();
    }

    private function computeDefaults()
    {
        $result = [];

        foreach ($this->columns as $name => $column) {
            if (($default = $column->getDefault()) !== null) {
                $result[$name] = $default;
            }
        }

        return $result;
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
     * Determine whether the provided column is required.
     * A required column is one that is not nullable.
     *
     * @param  string  $column
     *
     * @return null|bool
     */
    public function required($column): ?bool
    {
        return Arr::has($this->columns, $column)
            ? Arr::get($this->columns, $column)->getNotnull()
            : null;
    }

    /**
     * Determines whether the provided column is unique. A unique column is one
     * for which there is a UNIQUE index on only that column. Multiple columns
     * can be given (in the form of an array), in which case the method
     * determines whether there is a UNIQUE index spanning exactly those
     * columns, regardless of order.
     *
     * @param  string|string[]  $column
     *
     * @return null|bool
     */
    public function unique($column): ?bool
    {
        $column = Arr::wrap($column);

        return in_array($column, $this->uniqueColumns);
    }

    /**
     * Get the type of the column. This is given in terms of the constants
     * defined in `Type`.
     *
     * @see \Ferreira\AutoCrud\Database\Type
     * TODO: Move the Type class into the Database namespace
     *
     * @param  string  $column
     * @return null|string
     */
    public function type(string $column): ?string
    {
        if (! $this->has($column)) {
            return null;
        } elseif ($valid = $this->getEnumValid($column)) {
            return Type::ENUM;
        }

        switch ($type = $this->columns[$column]->getType()->getName()) {
            case DoctrineType::BIGINT:
            case DoctrineType::INTEGER:
            case DoctrineType::SMALLINT:
                return Type::INTEGER;

            case DoctrineType::BOOLEAN:
                return Type::BOOLEAN;

            case DoctrineType::DATETIME:
            case DoctrineType::DATETIME_IMMUTABLE:
            case DoctrineType::DATETIMETZ:
            case DoctrineType::DATETIMETZ_IMMUTABLE:
                return Type::DATETIME;

            case DoctrineType::DATE:
            case DoctrineType::DATE_IMMUTABLE:
                return Type::DATE;

            case DoctrineType::TIME:
            case DoctrineType::TIME_IMMUTABLE:
                return Type::TIME;

            case DoctrineType::DECIMAL:
            case DoctrineType::FLOAT:
                return Type::DECIMAL;

            case DoctrineType::STRING:
                return Type::STRING;

            case DoctrineType::TEXT:
                return Type::TEXT;

            default:
                // TODO: Add a test for this throw
                throw new DatabaseException(
                    "Table $this->name contains an unrecognized column type: $type"
                );
        }
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
        return $this->type('deleted_at') === Type::DATETIME;
    }

    /**
     * Determines whether a column references another column in another table
     * (potentially the same!), and returns the pair [$table, $column]
     * describing this reference.
     *
     * @param string $column
     */
    public function reference(string $column)
    {
        return Arr::get($this->references, $column, null);
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
            count($this->references) === 2 &&
            count(array_diff($this->columns(), ['id', 'created_at', 'updated_at'])) === 2;
        // TODO: What about soft deletes?
    }

    /**
     * Returns all references of a table.
     *
     * @return array
     */
    public function allReferences(): array
    {
        return $this->references;
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
     * @param string $column
     *
     * @return null|string[]
     */
    public function getEnumValid(string $column): ?array
    {
        // TODO: This should run exactly once for all enum columns and the
        // result stored somewhere in this class; future calls should instead
        // query the internal data structure
        switch (app(Connection::class)->getDriverName()) {
            case 'mysql':
                return (new MySqlEnumChecker($this->name, $column))->valid();

            case 'sqlite':
                return (new SQLiteEnumChecker($this->name, $column))->valid();

            case 'pgsql':
                return (new PostgresEnumChecker($this->name, $column))->valid();

            case 'sqlsrv':
                // TODO: We need to implement SqlServerEnumChecker
                return (new SqlServerEnumChecker($this->name, $column))->valid();

            default:
                return null;
        }
    }

    /**
     * Determines whether a function contains a default value.
     *
     * @param string $column
     *
     * @return bool
     */
    public function hasDefault(string $column)
    {
        return Arr::has($this->defaults, $column);
    }

    /**
     * Returns the default value of a function.
     *
     * @param string $column
     *
     * @return mixed
     */
    public function default(string $column)
    {
        return Arr::get($this->defaults, $column);
    }
}
