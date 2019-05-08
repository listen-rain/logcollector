<?php

namespace Listen\LogCollector\Providers;

use Illuminate\Support\ServiceProvider;
use Listen\LogCollector\LogCollector;

class LogCollectorServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        if (!file_exists(config_path('logcollector.php'))) {
            $this->publishes([
                __DIR__ . '/../config/logcollector.php' => config_path('logcollector.php')
            ], 'config');
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/logcollector.php', 'logcollector');
    }

    public function register()
    {
        // Bind captcha
        $this->app->singleton('logcollector', function ($app) {
            return new LogCollector(true);
        });
    }
}
