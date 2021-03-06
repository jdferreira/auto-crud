<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Type;
use Ferreira\AutoCrud\Word;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ferreira\AutoCrud\VersionChecker;
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
            'specificTypeFakers',
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

        $unique = $this->table->unique($this->column);
        $nullable = ! $this->table->required($this->column) && ! $this->forceRequired;

        if ($unique) {
            if ($this->table->reference($this->column) === null) {
                $fake = $this->addModifier('unique()', $fake);
            }

            if ($nullable) {
                // If the column is both unique and nullable, we want to apply
                // both `unique()` and `optional(0.9)`. However, since Faker is
                // not prepared to handle both modifiers simultaneously, we must
                // roll out the potential for null ourselves.
                return $this->fakerPrefix() . "->randomFloat() <= 0.9 ? $fake : null";
            } else {
                return $fake;
            }
        }

        if ($nullable) {
            return $this->addModifier('optional(0.9)', $fake);
        }

        return $fake;
    }

    private function fakerPrefix(): string
    {
        return app(VersionChecker::class)->before('8.0.0')
            ? '$faker'
            : '$this->faker';
    }

    private function addModifier(string $modifier, string $fake)
    {
        $prefix = $this->fakerPrefix() . '->';

        if (Str::startsWith($fake, $prefix)) {
            $partial = Str::substr($fake, Str::length($prefix));

            if (! Str::contains($partial, '->')) {
                return $this->fakerPrefix() . "->$modifier->$partial";
            }
        }

        return $this->fakerPrefix() . "->{$modifier}->passthrough($fake)";
    }

    private function default()
    {
        static $map = [
            Type::STRING => 'sentence',
            Type::TEXT => 'text',
        ];

        if (($fake = Arr::get($map, $this->table->type($this->column))) !== null) {
            return $this->fakerPrefix() . "->$fake";
        }
    }

    private function enumFaker()
    {
        if (($choices = $this->table->getEnumValid($this->column)) !== null) {
            $choices = collect($choices)->map(function ($value) {
                return '\'' . str_replace('\'', '\\\'', str_replace('\\', '\\\\', $value)) . '\'';
            })->join(', ');

            return $this->fakerPrefix() . "->randomElement([$choices])";
        }
    }

    private function referencesFaker()
    {
        if (($reference = $this->table->reference($this->column)) !== null) {
            [$foreignTable, $foreignColumn] = $reference;

            $this->referencedTable = $foreignTable;

            $modelClass = Word::class($foreignTable, true);

            $laravelSeven = app(VersionChecker::class)->before('8.0.0');

            if ($laravelSeven) {
                $factory = "factory($modelClass)";
            } else {
                $factory = 'Pet::factory()';
            }

            if ($this->forceRequired) {
                $factory .= $laravelSeven
                    ? '->state(\'full\')'
                    : '->full()';
            }

            return implode("\n", [
                'function () {',
                "    return {$factory}->create()->$foreignColumn;",
                '}',
            ]);
        }
    }

    private function specificTypeFakers()
    {
        static $map = [
            Type::INTEGER => 'numberBetween(0, 10000)',
            Type::BOOLEAN => 'boolean',
            Type::DECIMAL => 'numerify(\'%##.##\')',
            Type::DATETIME => 'dateTimeBetween(\'-10 years\', \'now\')->format(\'Y-m-d H:i:s\')',
            Type::DATE => 'date',
            Type::TIME => 'time',
        ];

        if (($fake = Arr::get($map, $this->table->type($this->column))) !== null) {
            return $this->fakerPrefix() . "->$fake";
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
                return $this->fakerPrefix() . "->$name";
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
