<?php

namespace Tests;

use Exception;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * This class is here because PHPUnit 8
 * [deprecated](https://github.com/sebastianbergmann/phpunit/issues/3494) the
 * `ArraySubset` class. This is a simplified version of that class, which allows
 * us to assert that a given associative array contains another one. In other
 * words, it is equivalent to checking that an array contains all the keys of a
 * second array, and that the values associated with those keys are equal.
 * Equality is the strict version.
 */
class ArrayHasSubset extends Constraint
{
    /**
     * @var array
     */
    private $subset;

    public function __construct(array $subset)
    {
        parent::__construct();

        $this->checkAssociative($subset);

        $this->subset = $subset;
    }

    private function checkAssociative($subset)
    {
        $is_string = function ($arg) {
            return is_string($arg);
        };

        $count_string_keys = count(array_filter(array_keys($subset), $is_string));

        if ($count_string_keys < count($subset)) {
            throw new Exception('Only exclusively associative arrays are allowed');
        }
    }

    public function matches($other): bool
    {
        foreach ($this->subset as $key => $value) {
            if (! array_key_exists($key, $other)) {
                return false;
            }

            if ($value !== $other[$key]) {
                return false;
            }
        }

        return true;
    }

    public function toString(): string
    {
        return 'has the subset ' . $this->exporter->export($this->subset);
    }
}
