<?php

namespace Ferreira\AutoCrud;

// This class represents the possible types understood by this package. This is
// an abstraction over the database types, simplified to accommodate the use
// cases of this CRUD generator.
class Type
{
    public const INTEGER = 'integer';
    public const BOOLEAN = 'boolean';
    public const DATETIME = 'datetime';
    public const DATE = 'date';
    public const TIME = 'time';
    public const DECIMAL = 'decimal';
    public const STRING = 'string';
    public const TEXT = 'text';
    public const BINARY = 'binary'; // TODO: binary columns should render file inputs on create/edit forms
    public const ENUM = 'enum';

    public const UNRECOGNIZED = 'unrecognized';

    public static function dateTimeFormat(string $type): ?string
    {
        if ($type === self::DATETIME) {
            return "'Y-m-d H:i:s'";
        } elseif ($type === self::DATE) {
            return "'Y-m-d'";
        } elseif ($type === self::TIME) {
            return "'H:i:s'";
        } else {
            return null;
        }
    }
}
