<?php

namespace Ferreira\AutoCrud;

use Illuminate\Support\Str;

class Word
{
    public static function class(string $tablename, bool $classOperator = false): string
    {
        $result = Str::studly(Str::singular($tablename));

        if ($classOperator) {
            $result .= '::class';
        }

        return $result;
    }

    public static function classPlural(string $tablename): string
    {
        return Str::studly($tablename);
    }

    public static function label(string $value, bool $removeId = false): string
    {
        if ($removeId) {
            $value = preg_replace('/_id$/', '', $value);
        }

        return str_replace('_', ' ', $value);
    }

    public static function labelSingular(string $value, bool $removeId = false): string
    {
        return Str::singular(static::label($value, $removeId));
    }

    public static function labelPlural(string $value, bool $removeId = false): string
    {
        return Str::plural(static::label($value, $removeId));
    }

    public static function labelUpper(string $value, bool $removeId = false): string
    {
        if ($value === 'id') {
            return 'ID';
        }

        return Str::ucfirst(static::label($value, $removeId));
    }

    public static function labelUpperSingular(string $value, bool $removeId = false): string
    {
        return Str::singular(static::labelUpper($value, $removeId));
    }

    public static function labelUpperPlural(string $value, bool $removeId = false): string
    {
        return Str::plural(static::labelUpper($value, $removeId));
    }

    public static function snake(string $value): string
    {
        return $value;
    }

    public static function snakeSingular(string $value): string
    {
        return Str::singular($value);
    }

    public static function snakePlural(string $value): string
    {
        return Str::plural($value);
    }

    public static function kebab(string $value): string
    {
        return str_replace('_', '-', $value);
    }

    public static function kebabSingular(string $value): string
    {
        return Str::singular(static::kebab($value));
    }

    public static function kebabPlural(string $value): string
    {
        return Str::plural(static::kebab($value));
    }

    public static function variable(string $value, bool $dollar = true): string
    {
        $result = Str::camel($value);

        if ($dollar) {
            $result = '$' . $result;
        }

        return $result;
    }

    public static function variableSingular(string $value, bool $dollar = true): string
    {
        return Str::singular(static::variable($value, $dollar));
    }

    public static function variablePlural(string $value, bool $dollar = true): string
    {
        return Str::plural(static::variable($value, $dollar));
    }

    public static function method(string $value): string
    {
        $value = preg_replace('/_id$/', '', $value);

        return Str::camel($value);
    }

    public static function methodSingular(string $value): string
    {
        return Str::singular(static::method($value));
    }

    public static function methodPlural(string $value): string
    {
        return Str::plural(static::method($value));
    }
}
