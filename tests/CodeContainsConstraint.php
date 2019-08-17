<?php

namespace Tests;

use PHPUnit\Framework\Constraint\Constraint;

/**
 * Constraint that asserts that the code excerpt it is evaluated is contained
 * within the code given, irrespective of indentation.
 *
 * The expected excerpt is passed in the constructor.
 */
class CodeContainsConstraint extends Constraint
{
    /**
     * @var string
     */
    private $regex;

    /**
     * The lines of text that make up this excerpt.
     *
     * @var string[]
     */
    private $lines;

    /**
     * Whether the given excerpt starts directly on the first line or not. If
     * true, the first line is not analysed when detecting common indentation.
     *
     * @var bool
     */
    private $startsOnFirstLine;

    public function __construct(string $excerpt)
    {
        parent::__construct();

        $this->lines = $this->processExcerpt($excerpt);
        $this->regex = $this->toRegex();
    }

    /**
     * Explodes the exceprt into lines, removes heading and trailing empty lines,
     * and dedents all the remaining lines.
     *
     * @param string $excerpt
     *
     * @return array
     */
    private function processExcerpt(string $excerpt): array
    {
        $lines = $this->stripEmptyLines(explode("\n", $excerpt));

        $commonIndentation = strlen($this->commonIndentation($lines));

        for ($i = 0; $i < count($lines); $i++) {
            if ($i !== 0 || ! $this->startsOnFirstLine) {
                $lines[$i] = substr($lines[$i], $commonIndentation);
            }
        }

        return $lines;
    }

    public function matches($other): bool
    {
        return preg_match($this->regex, $other) === 1;
    }

    public function toString(): string
    {
        return \sprintf(
            'contains %s',
            $this->exporter->export(implode("\n", $this->lines))
        );
    }

    private function toRegex(): string
    {
        $lines = $this->lines;

        for ($i = 0; $i < count($lines); $i++) {
            // When a line is all spaces, the regular expression is not
            // those exact spaces: any combinaion of spaces works.
            if ($this->isOnlySpaces($lines[$i])) {
                $lines[$i] = '[ \t]*';
                continue;
            }

            if ($i == 0) {
                $prefix = '([ \t]*)';
            } else {
                $prefix = '\1';
            }

            $lines[$i] = $prefix . preg_quote($lines[$i], '/');
        }

        return '/^' . implode('\n', $lines) . '$/sm';
    }

    /**
     * Removes the heading and trailing empty lines.
     * A line is empty if it contains only whitespace.
     *
     * @return string[]
     */
    private function stripEmptyLines($lines): array
    {
        $first = 0;
        $last = count($lines);

        while ($this->isOnlySpaces($lines[$first])) {
            $first++;
        }

        $this->startsOnFirstLine = $first === 0;

        while ($last > $first && $this->isOnlySpaces($lines[$last - 1])) {
            $last--;
        }

        return array_slice($lines, $first, $last - $first);
    }

    private function isOnlySpaces($line): bool
    {
        return preg_match('/^[ \t]*$/', $line);
    }

    private function commonIndentation($lines): string
    {
        $firstLine = $this->startsOnFirstLine ? 1 : 0;

        if (count($lines) <= $firstLine) {
            return '';
        }

        if (preg_match('/^[ \t]*/', $lines[$firstLine], $matches) !== 1) {
            return '';
        }

        $indentation = $matches[0];

        for ($i = $firstLine + 1; $i < count($lines); $i++) {
            if (! $this->isOnlySpaces($line = $lines[$i])) {
                $indentation = $this->minimizeIndentation($indentation, $line);
            }
        }

        return $indentation;
    }

    private function minimizeIndentation($indentation, $line): string
    {
        $min = min(strlen($indentation), strlen($line));

        for ($end = 0; $end < $min; $end++) {
            if ($indentation[$end] !== $line[$end]) {
                break;
            }
        }

        return substr($indentation, 0, $end);
    }
}
