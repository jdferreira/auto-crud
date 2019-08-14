<?php

namespace Ferreira\AutoCrud;

use Ferreira\AutoCrud\Commands\AutoCrudCommand;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register this service provider by adding the autocrud artisan command.
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands(AutoCrudCommand::class);
        }
    }
}
