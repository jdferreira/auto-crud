<?php

namespace Ferreira\AutoCrud\Generators;

use Ferreira\AutoCrud\Word;
use Illuminate\Filesystem\Filesystem;
use Ferreira\AutoCrud\Database\TableInformation;
use Ferreira\AutoCrud\Database\DatabaseInformation;

/**
 * Abstract class used by all the generator commands in this package.
 */
abstract class TableBasedGenerator extends PhpGenerator
{
    /**
     * @var DatabaseInformation
     */
    protected $db;

    /**
     * @var TableInformation
     */
    protected $table;

    /**
     * @var string
     */
    protected $dir;

    /**
     * Create a new generator, responsible for generating the CRUD files for a certain table.
     *
     * @param TableInformation $table
     */
    public function __construct(Filesystem $files, DatabaseInformation $db, TableInformation $table)
    {
        parent::__construct($files);

        $this->db = $db;
        $this->table = $table;
        $this->dir = '';

        $this->initialize();
    }

    /**
     * Set the directory where models are saved, relative to the base path of
     * the laravel application (usually `app/`). If not provided, this defaults
     * to the empty string. Forward slashes are translated to the systems's
     * directory separator character before assignment.
     *
     * @param string $dir
     *
     * @return $this
     */
    public function setModelDirectory(string $dir): self
    {
        $this->dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);

        return $this;
    }

    public function modelNamespace()
    {
        if ($this->dir === '') {
            return 'App';
        } else {
            return 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $this->dir);
        }
    }

    public function modelClass()
    {
        return Word::class($this->table->name());
    }
}
