<?php

namespace Ferreira\AutoCrud;

use Exception;

trait AssertsField
{
    /** @var string */
    private $assertFieldRoutePath;

    /** @var string */
    private $assertFieldRouteMethod;

    /** @var string[] */
    private $defaultValues;

    /**
     * Prepares the `TestCase` to be able to use the `assertField` method. This
     * method specifies the route (URI path and HTTP method) that will be used
     * in asserting the fields' properties, as well as a set of default values
     * that will be provided to the route (and overwritten by specific calls to
     * `AssertField::accepts` and `AssertField::rejects`).
     *
     * @param string $method
     * @param string $path
     * @param string[] $defaultValues
     *
     * @return $this
     */
    public function beginAssertFields(string $method, string $path, array $defaultValues)
    {
        $this->assertFieldRouteMethod = $method;
        $this->assertFieldRoutePath = $path;
        $this->defaultValues = $defaultValues;

        return $this;
    }

    public function assertField(string $field): AssertField
    {
        if ($this->assertFieldRoutePath === null || $this->assertFieldRouteMethod === null) {
            throw new Exception('You must run `assertFieldsOnRoute` first');
        }

        return new AssertField(
            $this->assertFieldRoutePath,
            $this->assertFieldRouteMethod,
            $this->defaultValues,
            $field,
            $this
        );
    }
}
