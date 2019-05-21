<?php
/**
 * Created by PhpStorm.
 * User: <zhufengwei@aliyun.com>
 * Date: 2019-05-07
 * Time: 16:33
 */

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Log\LogManager;

require __DIR__ . '/../vendor/autoload.php';

$app = new Application(__DIR__ . '/../');

$app->singleton('config', function () {
    $config = new Repository();
    $config->set('logcollector', require __DIR__ . '/../config/logcollector.php');

    return $config;
});

$app->singleton('log', function () use ($app) {
    return new LogManager($app);
});

\Illuminate\Support\Facades\Facade::setFacadeApplication($app);
