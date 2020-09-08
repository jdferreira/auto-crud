<?php

namespace Ferreira\AutoCrud;

use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Session;

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

    /** @var mixed[] */
    private $defaultValues;

    /** @var string[] */
    private $shouldPass;

    public function __construct(string $path, string $method, array $defaultValues, string $field, TestCase $test)
    {
        $this->path = $path;
        $this->method = $method;
        $this->defaultValues = $defaultValues;
        $this->field = $field;
        $this->test = $test;

        $this->shouldPass = collect(array_keys($this->defaultValues))->diff($field)->all();
    }

    /**
     * Asserts that the column accepts the given value.
     *
     * @param null|string $value
     *
     * @return $this
     */
    public function accepts($value): self
    {
        DB::beginTransaction();

        $this->request($value)
            ->assertSessionDoesntHaveErrors();

        // Rollback, because this previous statement added a row to the table
        // and, because of uniqueness, the following assertions could fail
        // Rolling back here is a way of refreshing the database state.
        DB::rollBack();

        return $this;
    }

    /**
     * Asserts that the column rejects the given value.
     *
     * @param null|string $value
     *
     * @return $this
     */
    public function rejects($value): self
    {
        $this->request($value)
            ->assertSessionHasErrors($this->field)
            ->assertSessionDoesntHaveErrors($this->shouldPass);

        return $this;
    }

    private function request($value): TestResponse
    {
        // TODO: Is there a PHP_CS fix to convert `$this->$methodName` into
        // `$this->{$methodName}`?

        $method = Str::lower($this->method);

        $data = $this->defaultValues;
        $data[$this->field] = $value;

        // We need to forget previous flash session items, because Laravel does
        // not clear them automatically inside a test case.
        Session::forget('errors');
        Session::forget('_old_input');
        Session::forget('_flash');

        return $this->test->{$method}($this->path, $data);
    }
}
