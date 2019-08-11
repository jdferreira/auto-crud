<?php

namespace Ferreira\AutoCrud\Database;

class DatabaseInformation
{
    /**
     * The schema builder used by this application.
     *
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    private $doctrine;

    public function __construct()
    {
        $this->doctrine = app('db.connection')->getDoctrineSchemaManager();
    }

    public function tablenames(): array
    {
        return collect($this->doctrine->listTableNames())
            ->filter(function ($name) {
                return $name !== config('database.migrations');
            })
            ->all();
    }
}
