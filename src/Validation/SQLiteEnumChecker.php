<?php

namespace Ferreira\AutoCrud\Validation;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SQLiteEnumChecker
{
    /**
     * @var string
     */
    private $tablename;

    /**
     * @var string
     */
    private $column;

    /**
     * @var string
     */
    private $statement;

    /**
     * @var int
     */
    private $pos;

    public function __construct($tablename, $column)
    {
        $this->tablename = $tablename;
        $this->column = $column;
    }

    // We implement a rough SQL parser for CREATE TABLE statements that can give
    // us the CHECK constraint on the relevant column.

    public function valid(): ?array
    {
        if (! $this->statement = $this->getCreateStatement()) {
            return null;
        }

        if (! $column = $this->getColumn()) {
            return null;
        }

        if ($possibilities = $this->extractCheck($column)) {
            return $possibilities;
        }

        return null;
    }

    private function getCreateStatement()
    {
        $row = DB::table('sqlite_master')
            ->select('sql')
            ->where('type', 'table')
            ->where('name', $this->tablename)
            ->first();

        return $row !== null ? $row->sql : null;
    }

    private function getColumn()
    {
        preg_match(
            '/create (?:temporary )?table "?[^"]*"?\s*(?=\()/i',
            $this->statement,
            $matches
        );

        $this->pos = strlen($matches[0]) + 1;

        $columns = $this->getColumnDefinitions();

        return Arr::get($columns, $this->column);
    }

    private function getColumnDefinitions()
    {
        $columns = [];

        while (true) {
            [$name, $definition] = $this->consumeColumn();

            $columns[$name] = $definition;

            if ($this->next() === ')') {
                return $columns;
            }

            $this->pos++; // For the comma

            $this->consumeSpaces();
        }
    }

    private function consumeSpaces()
    {
        while ($this->next() === ' ') {
            $this->pos++;
        }
    }

    private function consumeColumn()
    {
        if ($this->next() === '"') {
            $name = str_replace('\\', '', substr($this->consumeQuoted(), 1, -1));
            $this->consumeSpaces();
        } else {
            $name = null;
        }

        $definition = '';

        while (true) {
            $start = $this->pos;

            $next = $this->advanceTo('\'"(,)');

            $definition .= substr($this->statement, $start, $this->pos - $start);

            if (Str::contains('\'"', $next)) {
                $definition .= $this->consumeQuoted();
            } elseif (Str::contains('(', $next)) {
                $definition .= $this->consumeParenthesised();
            } elseif (Str::contains(',)', $next)) {
                break;
            }
        }

        return [$name, $definition];
    }

    private function consumeQuoted()
    {
        $quote = substr($this->statement, $this->pos, 1);
        $start = $this->pos;

        $this->pos++;

        while (true) {
            $next = $this->next();

            if ($next === $quote) {
                $this->pos++;
                break;
            } elseif ($next === '\\') {
                $this->pos += 2;
                continue;
            }

            $this->pos++;
        }

        return substr($this->statement, $start, $this->pos - $start);
    }

    private function consumeParenthesised()
    {
        static $endMap = [
            '(' => ')',
            '{' => '}',
            '[' => ']',
        ];

        $begin = substr($this->statement, $this->pos, 1);
        $end = $endMap[$begin];
        $start = $this->pos;

        $this->pos++;

        while (true) {
            $next = $this->advanceTo('\'"()');

            if (Str::contains('\'"', $next)) {
                $this->consumeQuoted();
            } elseif (Str::contains('([{', $next)) {
                $this->consumeParenthesised();
            } elseif (Str::contains($end, $next)) {
                $this->pos++;
                break;
            }
        }

        return substr($this->statement, $start, $this->pos - $start);
    }

    private function next()
    {
        return substr($this->statement, $this->pos, 1);
    }

    private function advanceTo($chars)
    {
        if (is_string($chars)) {
            $original = $chars;
            $chars = [];

            for ($i = 0; $i < strlen($original); $i++) {
                $chars[] = $original[$i];
            }
        }

        while (! in_array($this->next(), $chars)) {
            $this->pos++;
        }

        return $this->next();
    }

    private function extractCheck($definition)
    {
        static $checkPattern = '/
            ^            # Start of column definition
            .*           # The type of the column
            \bcheck\s+\( # The beginning of the CHECK constraint
                ".*"     # The name of the column again
                \sin\s\( # The literal string " in ("
                    (.*) # The various possibilities, with single quotes
                \)
            \)           # The end of the CHECK constraint
            .*           # More definition, if any
            $            # The end of the column definition
        /ix';

        static $itemPattern = '/
            ^            # The start of the string
            \'           # The single quote that starts the item
            (            # Capture the first item in the string
                (?:
                    [^\'\\\\] # Match anything other than \' or \\
                    |
                    \\.  # Or match an escaped character
                )*       # 0 or more, to find the first item
            )
            \'           # The single quote that ends the item
            (?:,\s*|$)   # Consume the comma and spaces at the end, if we are not at the end of the string
        /x';

        if (! preg_match($checkPattern, $definition, $matches)) {
            return;
        }

        $possibilities = $matches[1];
        $valid = [];

        while (strlen($possibilities) > 0) {
            preg_match($itemPattern, $possibilities, $matches);
            $valid[] = str_replace('\\', '', $matches[1]);
            $possibilities = substr($possibilities, strlen($matches[0]));
        }

        return $valid;
    }
}
