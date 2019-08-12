<?php

namespace Ferreira\AutoCrud;

class TheClass
{
    private $field;

    public function abc()
    {
        return 'abc';
    }

    private function withEmptyLines()
    {
        with_some();

        empty($nonIndented);

        lines();
    }

    private function def($method): int
    {
        if ($method === 'def') {
            return 1 + $this->field; // Comment
        } else {
            return 0;
        }
    }

    private function run()
    {
        step1();
        step2();
        step3();
    }
}
