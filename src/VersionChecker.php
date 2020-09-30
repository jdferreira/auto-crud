<?php

namespace Ferreira\AutoCrud;

class VersionChecker
{
    /** @var null|string */
    private $mocked;

    /**
     * Return the laravel version, as defined by the application. This method
     * can be mocked to test the package under different conditions. This
     * necessity arises from the need to test a few of the generators.
     *
     * @return string
     */
    public function laravelVersion(): string
    {
        return $this->mocked ?? app()->version();
    }

    /**
     * Mock the version in tests.
     *
     * @param string $version
     */
    public function mockVersion(string $version): void
    {
        $this->mocked = $version;
    }

    /**
     * Compare laravel's version with a given one.
     *
     * @param string $version
     * @param bool $inclusive
     *
     * @return bool
     */
    public function after(string $version, bool $inclusive = true): bool
    {
        $operator = $inclusive ? '>=' : '>';

        return version_compare($this->laravelVersion(), $version, $operator);
    }

    /**
     * Compare laravel's version with a given one.
     *
     * @param string $version
     * @param bool $inclusive
     *
     * @return bool
     */
    public function before(string $version, bool $inclusive = false): bool
    {
        $operator = $inclusive ? '<=' : '<';

        return version_compare($this->laravelVersion(), $version, $operator);
    }
}
