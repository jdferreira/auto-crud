<?php

namespace Ferreira\AutoCrud\Generators;

use Faker\Generator;
use Faker\Provider\Base;
use Illuminate\Support\Collection;

class EnglishNouns extends Base
{
    private $words;

    public function __construct(Generator $generator)
    {
        parent::__construct($generator);

        $this->words = explode("\n", file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'words/nouns.txt'));
    }

    public function word(): string
    {
        return $this->randomElement($this->words);
    }

    /**
     * @return string[]
     */
    public function words(int $min = 1, int $max = 1): array
    {
        if (func_num_args() === 0) {
            $max = $min = 1;
        } elseif (func_num_args() === 1) {
            $max = $min;
        }

        return Collection::times(random_int($min, $max), function () {
            return $this->word();
        })->all();
    }

    public function sqlName(): string
    {
        return implode('_', $this->words(...func_get_args()));
    }
}
