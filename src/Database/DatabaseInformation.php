<?php

namespace Ferreira\AutoCrud\Database;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DatabaseInformation
{
    /**
     * The schema builder used by this application.
     *
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    private $doctrine;

    /**
     * The information for each table in the database.
     *
     * @var TableInformation[]
     */
    private $tables;

    /**
     * The information for each table in the database.
     *
     * @var Relationship[]
     */
    private $relationships;

    public function __construct()
    {
        $this->doctrine = app('db.connection')->getDoctrineSchemaManager();

        $this->tables = $this->computeTables();
        $this->relationships = $this->computeRelationships();
    }

    private function computeTables()
    {
        // TODO: Remove the pivots?
        // DO not forget to add the --pivot option to the commands

        return collect($this->doctrine->listTableNames())
            ->filter(function ($name) {
                return $name !== config('database.migrations');
            })
            ->mapWithKeys(function ($name) {
                return [$name => new TableInformation($name)];
            })
            ->all();
    }

    private function computeRelationships()
    {
        $result = [];

        foreach ($this->tables as $table) {
            $fks = $table->foreignKeys();

            // Tables with exactly two foreign keys are pivots
            if (count($fks) == 2) {
                $result[] = ManyToMany::fromKeys($table->name(), ...$fks);
            } else {
                foreach ($fks as $fk) {
                    // If there is a unique index on the local columns, this
                    // is a one to one relationship; otherwise, it is one
                    // to many relationship.
                    $result[] = $this->unique($table->name(), $fk->getLocalColumns())
                        ? OneToOne::fromKey($table->name(), $fk)
                        : OneToMany::fromKey($table->name(), $fk);
                }
            }
        }

        return $this->relationships = $result;
    }

    /**
     * Returns an array with the names of all tables in the database.
     * The name of the 'migrations' table is not included in the result.
     *
     * @return string[]
     */
    public function tablenames(): array
    {
        return array_keys($this->tables);
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
        return Arr::get($this->tables, $table);
    }

    /**
     * Retrieve the relevant relationships on the tables in the database.
     *
     * @return Relationship[]
     */
    public function relationships(): array
    {
        return $this->relationships;
    }

    /**
     * Determine whether there is a UNIQUE index on the provided column.
     * The input can be a single column, as a string, or an array of them,
     * in which case the function returns true if there is a UNIQUE index
     * that contains exactly the provided columns, in any order.
     *
     * Returns `null` when the table or the column do not exist.
     *
     * @param string $table
     * @param string|string[] $column
     *
     * @return null|bool
     */
    public function unique(string $table, $column): ?bool
    {
        // We want to compare disregarding order, so sort the columns
        $columns = Arr::sort(Arr::wrap($column));

        // Validate that the table is real and that all the given columns are in it
        if (! Arr::has($this->tables, $table)) {
            return null;
        } elseif (count(array_intersect($columns, $this->tables[$table]->columns())) !== count($columns)) {
            return null;
        }

        foreach ($this->doctrine->listTableIndexes($table) as $index) {
            $unique = $index->isUnique() || $index->isPrimary();

            if ($unique && Arr::sort($index->getColumns()) === $columns) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves the primary key of a table. This can be either a string,
     * if it is the only column, or an array of strings if the key is compound.
     *
     * If the table does not exist, return null.
     *
     * @param null|string|string[] $table
     */
    public function primaryKey(string $table)
    {
        $table = Arr::get($this->tables, $table);

        return $table === null ? null : $table->primaryKey();
    }

    /**
     * Determine the name that is expected for a column that has a foreign key
     * constraint to the given table.
     *
     * @param string $table
     *
     * @return string
     */
    public function foreignKey(string $table): ?string
    {
        $table = Arr::get($this->tables, $table);

        if ($table === null) {
            return null;
        }

        return Str::snake(Str::singular($table->name())) . '_' . $table->primaryKey();
    }
}
