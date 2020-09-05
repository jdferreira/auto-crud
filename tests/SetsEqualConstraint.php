<?php

namespace Tests;

use PHPUnit\Framework\Constraint\Constraint;

/**
 * Constraint that asserts that the array it is evaluated for contains
 * all the values a given array, irrespective of order.
 *
 * The expected array of values is passed in the constructor.
 */
class SetsEqualConstraint extends Constraint
{
    /**
     * The expected array of values.
     *
     * @var array
     */
    protected $expected;

    /**
     * The first detected missing value during the match.
     *
     * @var mixed
     */
    protected $missing;

    /**
     * The first value found during the match that is not expected.
     *
     * @var mixed
     */
    protected $extra;

    /**
     * @param array $expected
     */
    public function __construct(array $expected)
    {
        $this->expected = $expected;
    }

    /**
     * Returns a string representation of the constraint.
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function toString(): string
    {
        return 'contains exactly the same values as ' . $this->exporter->export($this->expected);
    }

    /**
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     *
     * @param array $other array to evaluate
     */
    protected function matches($other): bool
    {
        foreach ($this->expected as $element) {
            if (! \in_array($element, $other)) {
                $this->missing = $element;
                $this->expected = null;

                return false;
            }
        }

        foreach ($other as $element) {
            if (! \in_array($element, $this->expected)) {
                $this->missing = null;
                $this->extra = $element;

                return false;
            }
        }

        return true;
    }

    /**
     * Returns the description of the failure.
     *
     * The beginning of failure messages is "Failed asserting that" in most
     * cases. This method should return the second part of that sentence.
     *
     * @param mixed $other evaluated value or object
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    protected function failureDescription($other): string
    {
        if ($this->missing !== null) {
            return 'an array contains the value ' . $this->exporter->export($this->missing);
        } elseif ($this->extra !== null) {
            return 'an array does not contain the value ' . $this->exporter->export($this->extra);
        } else {
            return 'an array ' . $this->toString();
        }
    }
}
