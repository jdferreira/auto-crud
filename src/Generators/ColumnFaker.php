<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ferreira\AutoCrud\Database\TableInformation;

class ColumnFaker
{
    /**
     * @var TableInformation
     */
    private $table;

    /**
     * @var string
     */
    private $column;

    /**
     * @var null|string
     */
    private $referencedTable;

    /**
     * @var bool
     */
    private $forceRequired;

    public function __construct(TableInformation $table, string $column, bool $forceRequired = false)
    {
        $this->table = $table;
        $this->column = $column;
        $this->forceRequired = $forceRequired;
        $this->referencedTable = null;
    }

    /**
     * Computes a PHP code string that can be used to fake values for the given
     * column.
     *
     * @return string
     */
    public function fake(): string
    {
        return $this->postProcess($this->raw());
    }

    private function raw()
    {
        $fakers = [
            'ignoredColumns',
            'enumFaker',
            'referencesFaker',
            'knownFakerFormatters',
            'default',
        ];

        foreach ($fakers as $method) {
            if (($fake = $this->$method()) !== null) {
                break;
            }
        }

        return $fake ?? 'null';
    }

    private function postProcess($fake)
    {
        if ($fake === '' || $fake === 'null') {
            return $fake;
        }

        if (Str::startsWith($fake, 'function')) {
            return $fake;
        }

        $unique = $this->table->unique($this->column);
        $nullable = ! $this->table->required($this->column) && ! $this->forceRequired;

        if ($unique && $nullable) {
            // If the column is both unique and nullable, we want to apply both
            // `unique()` and `optional(0.9)`. However, since Faker is not
            // prepared to handle both modifiers simultaneously, we must roll
            // out the potential for null ourselves.
            return "\$faker->randomFloat() <= 0.9 ? \$faker->unique()->$fake : null";
        } elseif ($nullable) {
            if (strpos($fake, '->') !== false) {
                return "\$faker->optional(0.9)->passthrough(\$faker->$fake)";
            } else {
                return "\$faker->optional(0.9)->$fake";
            }
        } elseif ($unique) {
            return "\$faker->unique()->$fake";
        } else {
            return "\$faker->$fake";
        }
    }

    private function default()
    {
        static $map = [
            Type::INTEGER => 'numberBetween(0, 10000)',
            Type::BOOLEAN => 'boolean',
            Type::DATETIME => 'dateTimeBetween(\'-10 years\', \'now\')->format(\'Y-m-d H:i:s\')',
            Type::DATE => 'date',
            Type::TIME => 'time',
            Type::DECIMAL => 'numerify(\'%##.##\')',
            Type::STRING => 'sentence',
            Type::TEXT => 'text',
            Type::BINARY => 'passthrough(random_bytes(1024))',
            // Type::ENUM was already processed before this `default` method was called
        ];

        if (($type = $this->table->type($this->column)) !== null) {
            return Arr::get($map, $type);
        }
    }

    private function enumFaker()
    {
        if (($choices = $this->table->getEnumValid($this->column)) !== null) {
            $choices = collect($choices)->map(function ($value) {
                return '\'' . str_replace('\'', '\\\'', str_replace('\\', '\\\\', $value)) . '\'';
            })->join(', ');

            return 'randomElement([' . $choices . '])';
        }
    }

    private function referencesFaker()
    {
        if (($reference = $this->table->reference($this->column)) !== null) {
            [$foreignTable, $foreignColumn] = $reference;

            $this->referencedTable = $foreignTable;

            $model = Str::studly(Str::singular($foreignTable));

            return implode("\n", [
                'function () {',
                "    return factory($model::class)->create()->$foreignColumn;",
                '}',
            ]);
        }
    }

    private function knownFakerFormatters()
    {
        $potential = [
            $this->column,
            Str::camel($this->column),
            'random' . Str::ucfirst($this->column),
            'random' . Str::studly($this->column),
        ];

        foreach ($potential as $name) {
            if ($this->fakerHasFormatter($name)) {
                return $name;
            }
        }
    }

    private function fakerHasFormatter($name)
    {
        try {
            app(\Faker\Generator::class)->getFormatter($name);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    private function ignoredColumns()
    {
        $toIgnore = [$this->table->primaryKey(), 'created_at', 'updated_at'];

        if (in_array($this->column, $toIgnore)) {
            return '';
        }
    }

    public function referencedTable()
    {
        return $this->referencedTable;
    }
}
