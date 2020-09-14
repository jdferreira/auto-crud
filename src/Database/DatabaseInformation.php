<?php

namespace Ferreira\AutoCrud\Database;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Database\Connection;

class DatabaseInformation
{
    /**
     * The schema builder used by this application.
     *
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    private $doctrine;

    public function __construct(Connection $connection)
    {
        $this->doctrine = $connection->getDoctrineSchemaManager();
    }

    /**
     * Return an array of `TableInformation` objects that represent the tables
     * contained in the database this instance is connected to.
     *
     * @return TableInformation[]
     */
    public function tables()
    {
        return collect($this->doctrine->listTableNames())
            ->filter(function ($name) {
                return $name !== config('database.migrations');
            })
            ->mapWithKeys(function ($name) {
                return [$name => new TableInformation($name)];
            })
            ->all();
    }

    /**
     * Returns an array with the names of all tables in the database. The name
     * of the 'migrations' table is not included in the result.
     *
     * @param bool $pivots Whether to include pivot tables. Defaults to true
     *
     * @return string[]
     */
    public function tablenames(bool $pivots = true): array
    {
        $tables = collect($this->tables());

        if (! $pivots) {
            $tables = $tables->filter(function ($table) {
                return ! $table->isPivot();
            });
        }

        return $tables->keys()->all();
    }

    /**
     * Retrieve the relevant information about the provided table.
     *
     * @param string $table
     *
     * @return TableInformation|null
     */
    public function table(string $table): ?TableInformation
    {
        return Arr::get($this->tables(), $table);
    }

    /**
     * Retrieve the relevant relationships on the tables in the database.
     *
     * @return Relationship[]
     */
    public function relationships(): array
    {
        $result = [];

        foreach ($this->tables() as $table) {
            if ($table->isPivot()) {
                $references = $table->allReferences();
                $pivotColumns = array_keys($references);

                $result[] = new ManyToMany(
                    $table->name(),
                    $pivotColumns[0],
                    $references[$pivotColumns[0]][0],
                    $references[$pivotColumns[0]][1],
                    $pivotColumns[1],
                    $references[$pivotColumns[1]][0],
                    $references[$pivotColumns[1]][1]
                );

                continue;
            }

            foreach ($table->allReferences() as $localColumn => [$foreignTable, $foreignColumn]) {
                // If there is a unique index on the local columns, this
                // is a one to one relationship; otherwise, it is one
                // to many relationship.
                $result[] = $table->unique($localColumn)
                    ? new OneToOne($table->name(), $localColumn, $foreignTable, $foreignColumn)
                    : new OneToMany($table->name(), $localColumn, $foreignTable, $foreignColumn);
            }
        }

        return $result;
    }

    /**
     * Retrieve the relationships relevant for a given table.
     *
     * @param string $table
     *
     * @return Relationship[]
     */
    public function relationshipsFor(string $table): array
    {
        return collect($this->relationships())
            ->filter(function ($relation) use ($table) {
                return in_array($table, $relation->tables());
            })->all();
    }

    /**
     * Return the names of the tables that are pivots.
     *
     * @return string[]
     */
    public function pivots(): array
    {
        return collect($this->tables())
            ->filter(function (TableInformation $table) {
                return $table->isPivot();
            })
            ->keys()
            ->all();
    }
}
