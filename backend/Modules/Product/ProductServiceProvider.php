<?php

namespace Modules\Product;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schedule;
use Modules\Product\Commands\MeiliMakeIndexesCommand;
use MeiliSearch\Client;

class ProductServiceProvider extends ServiceProvider
{
    function getNamespace()
    {
        return 'Bijac\Controllers';
    }

    function getDir()
    {
        return __DIR__;
    }


    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            return new Client(
                config('scout.meilisearch.host'),
                config('scout.meilisearch.key')
            );
        });


        $helpers = __DIR__ . '/helpers.php';
        if (file_exists($helpers)) {
            require_once $helpers;
        }
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/Database/migrations');

        // $this->registerSchedule();
        $this->commands([
            MeiliMakeIndexesCommand::class,
        ]);
    }

    protected function registerSchedule()
    {
        $scheduleFile = $this->getDir() . '/schedule.php';

        if (file_exists($scheduleFile)) {
            $this->app->booted(function () use ($scheduleFile) {
                // Get Schedule instance from container or facade
                $schedule = $this->app->bound('schedule')
                    ? $this->app->make('schedule')
                    : Schedule::getFacadeRoot();

                require $scheduleFile;
            });
        }
    }
}
