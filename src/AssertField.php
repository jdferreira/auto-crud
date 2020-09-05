<?php

namespace Ferreira\AutoCrud;

use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

class AssertField
{
    /** @var string */
    private $path;

    /** @var string */
    private $method;

    /** @var TestCase */
    private $test;

    /** @var string */
    private $field;

    /** @var string[] */
    private $defaultValues;

    public function __construct(string $path, string $method, array $defaultValues, string $field, TestCase $test)
    {
        $this->path = $path;
        $this->method = $method;
        $this->defaultValues = $defaultValues;
        $this->field = $field;
        $this->test = $test;
    }

    /**
     * Asserts that the column accepts the given value.
     *
     * @param null|string $value
     *
     * @return $this
     */
    public function accepts(?string $value): self
    {
        $this->request($value)
            ->assertSuccessful();

        return $this;
    }

    /**
     * Asserts that the column rejects the given value.
     *
     * @param null|string $value
     *
     * @return $this
     */
    public function rejects(?string $value): self
    {
        $this->request($value)
            ->assertRedirect()
            ->assertSessionHasErrors($this->field);

        return $this;
    }

    private function request(?string $value): TestResponse
    {
        // TODO: Is there a PHP_CS fix to convert `$this->$methodName` into
        // `$this->{$methodName}`?

        $method = Str::lower($this->method);

        $data = $this->defaultValues;
        $data[$this->field] = $value;

        return $this->test->{$method}($this->path, $data);
    }
}
