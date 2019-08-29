<?php

namespace Ferreira\AutoCrud\Validation;

use Doctrine\DBAL\Schema\Column;
use Illuminate\Support\Facades\DB;

class MySqlEnumChecker
{
    /**
     * @var string
     */
    private $tablename;

    /**
     * @var Column
     */
    private $column;

    public function __construct(string $tablename, Column $column)
    {
        // TODO: /!\ Needs tests
        //       ‾‾‾
        $this->tablename = $tablename;
        $this->column = $column;
    }

    public function valid(): ?array
    {
        if (! $enum = $this->getEnum()) {
            return null;
        }

        if ($possibilities = $this->extractValid($enum)) {
            return $possibilities;
        }

        return null;
    }

    private function getEnum()
    {
        $results = DB::select(DB::raw(
            "SHOW COLUMNS FROM {$this->table} WHERE Field = '{$this->column->getName()}'"
        ))->first();

        return $results === null ? null : $results->Type;
    }

    private function extractValid($definition)
    {
        static $checkPattern = '/
            ^            # Start of string
            (?:enum|set) # We might one day be interested in the SET type
            \s*          # Zero or more spaces
            \(           # Start of the valid values
                (.*)     # The various possibilities, with single quotes
            \)
            $
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
