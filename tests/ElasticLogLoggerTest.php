<?php
/**
 * Created by PhpStorm.
 * User: <zhufengwei@aliyun.com>
 * Date: 2019-05-08
 * Time: 09:54
 */

namespace Listen\LogCollector\Tests;

use Listen\LogCollector\Middleware\ElasticLogWrite;
use Listen\LogCollector\Providers\LogCollectorServiceProvider;
use PHPUnit\Framework\TestCase;
use Listen\LogCollector\Providers\ElasticLogProvider;
use Listen\LogCollector\Logger;

class ElasticLogLoggerTest extends TestCase
{
    public function testEsLogger()
    {
        app()->register(ElasticLogProvider::class);
        app()->register(LogCollectorServiceProvider::class);
        $logger = (new Logger('es', \Monolog\Logger::INFO))->makeElasticLogLogger();

        // $logger->info('test es 1');
        // $logger->info('test es 2');
        // $logger->info('test es 3');
        // $logger->info('test es 4');

        $loggerCollector = app('logcollector')->addLogger('es', $logger);

        $loggerCollector->esInfo('test esCollector 1');
        $loggerCollector->esInfo('test esCollector 2');
        $loggerCollector->esInfo(['message' => 'test esCollector 3', 'title' => 'esCollector']);

        $documents = app('elasticLog')->getDocuments();
        if (count($documents) > 0) {
            (new ElasticLogWrite())->terminate();
        }

        $this->assertTrue(true);
    }
}
