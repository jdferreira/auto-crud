<?php

namespace Ferreira\AutoCrud\Database;

use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Word;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Database\Connection;
use Doctrine\DBAL\Types\Type as DoctrineType;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Ferreira\AutoCrud\Validation\MySqlEnumChecker;
use Ferreira\AutoCrud\Validation\SQLiteEnumChecker;
use Ferreira\AutoCrud\Validation\PostgresEnumChecker;
use Ferreira\AutoCrud\Validation\SqlServerEnumChecker;

class TableInformation
{
    /** @var Connection */
    private $connection;

    /**
     * The name of the table being analysed.
     *
     * @var string
     */
    public $name;

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
    public function __construct(Connection $connection, string $name)
    {
        $this->connection = $connection;
        $this->name = $name;

        $this->assertTableExists();

        $this->computeColumns();
        $this->computePrimaryKey();
        $this->computeReferences();
        $this->computeUniqueColumns();
        $this->computeLabelColumn();
        $this->computeDefaults();

        $this->assertForeignKeysDontHaveDefaults();
        $this->assertNonUniqueBooleans();
    }

    private function schema(): AbstractSchemaManager
    {
        return $this->connection->getDoctrineSchemaManager();
    }

    private function assertTableExists()
    {
        if (! $this->schema()->tablesExist($this->name)) {
            throw new DatabaseException("Table $this->name does not exist");
        }
    }

    private function computeColumns(): void
    {
        $columns = $this->schema()->listTableColumns($this->name);

        $this->unescapeColumnNames($columns);

        $this->columns = $columns;
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

    private function computePrimaryKey(): void
    {
        $indexes = $this->schema()->listTableIndexes($this->name);

        if (! isset($indexes['primary'])) {
            return;
        }

        $columns = $indexes['primary']->getColumns();

        if (count($columns) === 1) {
            $this->primaryKey = $columns[0];
        } else {
            throw new DatabaseException("Table $this->name has a primary key spanning more than 1 column.");
        }
    }

    private function computeReferences(): void
    {
        $this->references = [];

        foreach ($this->schema()->listTableForeignKeys($this->name) as $key) {
            $localColumns = $key->getLocalColumns();

            if (count($localColumns) === 1) {
                $this->references[$localColumns[0]] = [$key->getForeignTableName(), $key->getForeignColumns()[0]];
            }
        }

        if ($this->isPivot()) {
            // Sort the two foreign keys based on the order of the columns. This
            // is important because we want to use that order to decide which
            // model will show in its create/edit forms the dropdown boxes

            $columnsInOrder = collect($this->columns)
                ->filter(function ($column, $name) {
                    return ! in_array($name, [$this->primaryKey(), 'created_at', 'updated_at']);
                })
                ->keys()
                ->all();

            $this->references = [
                $columnsInOrder[0] => $this->references[$columnsInOrder[0]],
                $columnsInOrder[1] => $this->references[$columnsInOrder[1]],
            ];
        }
    }

    private function computeUniqueColumns(): void
    {
        $this->uniqueColumns = [];

        foreach ($this->schema()->listTableIndexes($this->name) as $index) {
            if ($index->isUnique() || $index->isPrimary()) {
                $this->uniqueColumns[] = Arr::sort($index->getColumns());
            }
        }
    }

    private function computeLabelColumn(): void
    {
        $stringColumns = collect($this->columns())->filter(function ($column) {
            return $this->type($column) === Type::STRING;
        })->values();

        $this->labelColumn = $stringColumns->contains('name') ? 'name' : $stringColumns->first();
    }

    private function computeDefaults(): void
    {
        $this->defaults = [];

        foreach ($this->columns as $name => $column) {
            if (($default = $column->getDefault()) !== null) {
                $this->defaults[$name] = $default;
            }
        }
    }

    private function assertForeignKeysDontHaveDefaults()
    {
        foreach ($this->columns as $name => $column) {
            if ($this->reference($name) !== null && $column->getDefault() !== null) {
                throw new DatabaseException("Column $name of table $this->name has a foreign key and a default value");
            }
        }
    }

    private function assertNonUniqueBooleans()
    {
        foreach ($this->columns() as $column) {
            if ($this->type($column) === Type::BOOLEAN && $this->unique($column) && $this->required($column)) {
                throw new DatabaseException("Column $column of table $this->name is a unique non-nullable boolean column");
            }
        }
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
        }

        if ($this->getEnumValid($column)) {
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
                    "Column $column on table $this->name contains an unrecognized column type: $type"
                );
        }
    }

    /**
     * Return the name of the column or columns of the primary key of this table.
     *
     * @return null|string
     */
    public function primaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * Return the name that is expected for a column that has a foreign key
     * constraint to this table.
     *
     * @return string
     */
    public function foreignKey(): ?string
    {
        return Word::snakeSingular($this->name()) . '_' . $this->primaryKey();
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
            count($this->allReferences()) === 2 &&
            count(array_diff($this->columns(), [$this->primaryKey(), 'created_at', 'updated_at'])) === 2;
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
        switch ($this->connection->getDriverName()) {
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
