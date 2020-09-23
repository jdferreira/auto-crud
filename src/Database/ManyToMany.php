<?php

namespace Ferreira\AutoCrud\Database;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;

class ManyToMany extends Relationship
{
    /**
     * The name of the pivot table.
     *
     * @var string
     */
    public $pivot;

    /**
     * The name of the first column in the pivot that references a foreign table.
     *
     * @var string
     */
    public $pivotColumnOne;

    /**
     * The table referenced by the first pivot column.
     *
     * @var string
     */
    public $foreignOne;

    /**
     * The column on the foreign table that is referenced by the first foreign key.
     *
     * @var string
     */
    public $foreignOneColumn;

    /**
     * The name of the second column in the pivot that references a foreign table.
     *
     * @var string
     */
    public $pivotColumnTwo;

    /**
     * The table referenced by the second pivot column.
     *
     * @var string
     */
    public $foreignTwo;

    /**
     * The column on the foreign table that is referenced by the second foreign key.
     *
     * @var string
     */
    public $foreignTwoColumn;

    public function __construct(
        string $pivot,
        string $pivotColumnOne,
        string $foreignOne,
        string $foreignOneColumn,
        string $pivotColumnTwo,
        string $foreignTwo,
        string $foreignTwoColumn
    ) {
        // if ($foreignOne > $foreignTwo) {
        //     [
        //         $pivotColumnOne, $foreignOne, $foreignOneColumn,
        //         $pivotColumnTwo, $foreignTwo, $foreignTwoColumn,
        //     ] = [
        //         $pivotColumnTwo, $foreignTwo, $foreignTwoColumn,
        //         $pivotColumnOne, $foreignOne, $foreignOneColumn,
        //     ];
        // }

        $this->pivot = $pivot;
        $this->pivotColumnOne = $pivotColumnOne;
        $this->foreignOne = $foreignOne;
        $this->foreignOneColumn = $foreignOneColumn;
        $this->pivotColumnTwo = $pivotColumnTwo;
        $this->foreignTwo = $foreignTwo;
        $this->foreignTwoColumn = $foreignTwoColumn;
    }

    public function tables(): array
    {
        return [$this->foreignOne, $this->foreignTwo];
    }

    public static function fromKeys(string $pivot, ForeignKeyConstraint $one, ForeignKeyConstraint $two): self
    {
        return new static(
            $pivot,
            static::singleColumn($one->getLocalColumns()),
            $one->getForeignTableName(),
            static::singleColumn($one->getForeignColumns()),
            static::singleColumn($two->getLocalColumns()),
            $two->getForeignTableName(),
            static::singleColumn($two->getForeignColumns())
        );
    }
}
