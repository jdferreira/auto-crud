<?php

namespace Ferreira\AutoCrud\Database;

use Exception;

abstract class Relationship
{
    protected static function singleColumn(array $columns): string
    {
        if (count($columns) != 1) {
            throw new Exception('Multiple columns detected on foreign key');
        }

        return $columns[0];
    }

    abstract public function tables(): array;
}
