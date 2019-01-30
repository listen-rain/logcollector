<?php

namespace Listen\LogCollector;

use Illuminate\Support\ServiceProvider;

class LogCollectorServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/logcollector.php' => config_path('logcollector.php')
        ], 'config');
    }

    public function register()
    {
        // Merge configs
        $this->mergeConfigFrom(
            __DIR__.'/../config/logcollector.php', 'logcollector'
        );

        // Bind captcha
        $this->app->singleton('logcollector', function($app)
        {
            return new LogCollector();
        });
    }
}
