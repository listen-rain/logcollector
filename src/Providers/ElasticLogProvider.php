<?php

namespace Listen\LogCollector\Providers;

use Illuminate\Support\ServiceProvider;
use Listen\LogCollector\Clients\ElasticClient;

class ElasticLogProvider extends ServiceProvider
{
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('elasticLog', function () {
            return new ElasticClient();
        });
    }

    public function provides()
    {
        return ['elasticLog'];
    }
}
