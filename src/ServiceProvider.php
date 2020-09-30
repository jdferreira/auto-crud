<?php

namespace Ferreira\AutoCrud;

use Illuminate\Support\Facades\App;
use Ferreira\AutoCrud\Commands\TestCommand;
use Ferreira\AutoCrud\Commands\ViewCommand;
use Ferreira\AutoCrud\Commands\ModelCommand;
use Ferreira\AutoCrud\Commands\RouteCommand;
use Ferreira\AutoCrud\Commands\SeederCommand;
use Ferreira\AutoCrud\Commands\FactoryCommand;
use Ferreira\AutoCrud\Commands\RequestCommand;
use Ferreira\AutoCrud\Commands\AutoCrudCommand;
use Ferreira\AutoCrud\Commands\ControllerCommand;
use Ferreira\AutoCrud\Generators\FactoryGenerator;
use Ferreira\AutoCrud\Database\DatabaseInformation;
use Ferreira\AutoCrud\Generators\LegacyFactoryGenerator;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register this service provider by adding the autocrud artisan command.
     */
    public function register()
    {
        $this->commands(AutoCrudCommand::class);
        $this->commands(ModelCommand::class);
        $this->commands(ControllerCommand::class);
        $this->commands(FactoryCommand::class);
        $this->commands(SeederCommand::class);
        $this->commands(RequestCommand::class);
        $this->commands(RouteCommand::class);
        $this->commands(ViewCommand::class);
        $this->commands(TestCommand::class);

        $this->app->singleton(DatabaseInformation::class);
        $this->app->singleton(VersionChecker::class);

        if (App::make(VersionChecker::class)->before('8.0.0')) {
            $this->app->bind(FactoryGenerator::class, LegacyFactoryGenerator::class);
        }
    }
}
