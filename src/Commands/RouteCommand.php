<?php

namespace Ferreira\AutoCrud\Commands;

use Illuminate\Console\Command;
use Ferreira\AutoCrud\Injectors\RouteInjector;
use Ferreira\AutoCrud\Database\DatabaseInformation;

class RouteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '
        autocrud:route
        {--table=* : The table names to base the generation on. Defaults to all tables in the database that are not pivot tables.}
        {--skip-api : Whether to skip API routes.}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inject routes into  a form request file for the tables defined in this project\'s migrations';

    /**
     * Generate the requested files.
     */
    public function handle()
    {
        $requested = $this->option('table');

        $tablenames = count($requested) > 0
            ? $requested
            : $this->laravel->make(DatabaseInformation::class)->tablenames(false);

        $this->laravel->make(RouteInjector::class, [
            'tables' => $tablenames,
            'api' => ! $this->option('skip-api'),
        ])->inject();
    }
}
