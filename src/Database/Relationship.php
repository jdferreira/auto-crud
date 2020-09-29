<?php

namespace Ferreira\AutoCrud\Database;

abstract class Relationship
{
    protected static function singleColumn(array $columns): string
    {
        if (count($columns) != 1) {
            throw new DatabaseException('Multiple columns detected on foreign key');
        }

        return $columns[0];
    }

    abstract public function tables(): array;
}
