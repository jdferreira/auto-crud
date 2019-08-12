<?php

namespace Ferreira\AutoCrud\Database;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;

class OneToOneOrMany extends Relationship
{
    /**
     * The name of the tabel that contains the foreign key.
     *
     * @var string
     */
    public $table;

    /**
     * The name of the column that the foreign key acts on.
     *
     * @var string
     */
    public $column;

    /**
     * The name of the table beging referenced.
     *
     * @var string
     */
    public $foreignTable;

    /**
     * The name of the column in the referenced table.
     *
     * @var string
     */
    public $foreignColumn;

    public function __construct(string $table, string $column, string $foreignTable, string $foreignColumn)
    {
        $this->table = $table;
        $this->column = $column;
        $this->foreignTable = $foreignTable;
        $this->foreignColumn = $foreignColumn;
    }

    public function tables(): array
    {
        return [$this->table, $this->foreignTable];
    }

    public static function fromKey(string $table, ForeignKeyConstraint $key): self
    {
        return new static(
            $table,
            static::singleColumn($key->getLocalColumns()),
            $key->getForeignTableName(),
            static::singleColumn($key->getForeignColumns())
        );
    }
}
