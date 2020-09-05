<?php

namespace Tests;

use Faker\Generator;
use Faker\Factory as Faker;

class MigrationGeneratorHelper
{
    public static $idColumns = [
        'bigIncrements' => 'bigInteger',
        'increments' => 'integer',
        'mediumIncrements' => 'mediumInteger',
        'smallIncrements' => 'smallInteger',
        'tinyIncrements' => 'tinyInteger',
    ];

    public static $columnTypes = [
        // Simple columns with no parameter
        'bigInteger' => 'simple',
        'integer' => 'simple',
        'mediumInteger' => 'simple',
        'smallInteger' => 'simple',
        'tinyInteger' => 'simple',
        'unsignedBigInteger' => 'simple',
        'unsignedInteger' => 'simple',
        'unsignedMediumInteger' => 'simple',
        'unsignedSmallInteger' => 'simple',
        'unsignedTinyInteger' => 'simple',
        'binary' => 'simple',
        'boolean' => 'simple',
        'longText' => 'simple',
        'mediumText' => 'simple',
        'text' => 'simple',
        'date' => 'simple',
        'dateTime' => 'simple',
        'dateTimeTz' => 'simple',
        'time' => 'simple',
        'timeTz' => 'simple',
        'timestamp' => 'simple',
        'timestampTz' => 'simple',

        // String columns that need to know the length of the column
        'char' => 'char',
        'string' => 'char',

        // Decimal columns that need to know the precision
        'decimal' => 'decimal',
        'double' => 'decimal',
        'float' => 'decimal',
        'unsignedDecimal' => 'decimal',

        // Set columns that need to know the set of valid values
        'enum' => 'set',
    ];

    public static $defaultValues = [
        'bigInteger' => '0',
        'integer' => '0',
        'mediumInteger' => '0',
        'smallInteger' => '0',
        'tinyInteger' => '0',
        'unsignedBigInteger' => '0',
        'unsignedInteger' => '0',
        'unsignedMediumInteger' => '0',
        'unsignedSmallInteger' => '0',
        'unsignedTinyInteger' => '0',
        'binary' => '""',
        'boolean' => 'true',
        'longText' => '""',
        'mediumText' => '""',
        'text' => '""',
        'char' => '""',
        'string' => '""',
        'decimal' => '0',
        'double' => '0',
        'float' => '0',
        'unsignedDecimal' => '0',
    ];

    public static $countColumns = [
        'bigInteger',
        'integer',
        'mediumInteger',
        'smallInteger',
        'tinyInteger',
        'unsignedBigInteger',
        'unsignedInteger',
        'unsignedMediumInteger',
        'unsignedSmallInteger',
        'unsignedTinyInteger',
    ];

    public static $dateLikeCollumns = [
        'date',
        'dateTime',
        'dateTimeTz',
        'time',
        'timeTz',
        'timestamp',
        'timestampTz',
    ];

    /** @var Generator */
    public static $faker;

    public static function sqlName()
    {
        return static::$faker->sqlName(...func_get_args());
    }

    public static function words()
    {
        return static::$faker->words(...func_get_args());
    }

    public static function rand(int $min = null, int $max = null)
    {
        if (func_num_args() === 0) {
            return rand(0, getrandmax()) / getrandmax();
        } else {
            return rand($min, $max);
        }
    }

    public static function init()
    {
        static::$faker = Faker::create();
        static::$faker->addProvider(new EnglishNouns(static::$faker));
    }
}

MigrationGeneratorHelper::init();
