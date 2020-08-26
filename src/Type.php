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
    public const BINARY = 'binary';
    public const ENUM = 'enum';

    public const UNRECOGNIZED = 'unrecognized';
}
