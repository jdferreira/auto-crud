<?php

namespace Ferreira\AutoCrud\Commands;

use Illuminate\Console\Command;
use Ferreira\AutoCrud\Generators\ControllerGenerator;
use Ferreira\AutoCrud\Generators\ApiControllerGenerator;

class ControllerCommand extends Command
{
    use Concerns\TableBasedCommand;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        autocrud:controller
        {--table=* : The table names to base the generation on. Defaults to all tables in the database that are not pivot tables.}
        {--dir= : The directory where the models live.}
        {--skip-api : Whether to skip generating API routes.}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a controller file for the tables defined in this project\'s migrations';

    /**
     * Generate the requested files.
     */
    public function handle()
    {
        $this->handleMultipleTables(ControllerGenerator::class);

        if (! $this->option('skip-api')) {
            $this->handleMultipleTables(ApiControllerGenerator::class);
        }
    }
}
