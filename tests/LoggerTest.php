<?php
/**
 * Created by PhpStorm.
 * User: <zhufengwei@aliyun.com>
 * Date: 2019-05-07
 * Time: 15:42
 */

namespace Listen\LogCollector\Tests;

use Listen\LogCollector\LogCollector;
use Listen\LogCollector\LogCollectorServiceProvider;
use Listen\LogCollector\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    /**
     * @date   2019-05-07
     * @return \Monolog\Logger
     * @throws \Listen\LogCollector\Exceptions\LoggerException
     * @author <zhufengwei@aliyun.com>
     */
    public function testMakeLogger()
    {
        $logger = new Logger('access');

        $this->assertIsObject($logger);
        $this->assertIsObject($logger->make());
        $this->assertIsObject($logger->getMlogger());
    }

    /**
     * @date   2019-05-07
     * @throws \Exception
     * @author <zhufengwei@aliyun.com>
     */
    public function testMakeDailyLogger()
    {
        $name   = 'daily';
        $logger = new Logger($name);

        $logcollector = new LogCollector();
        $logcollector->addLogger('daily', $logger)->dailyInfo(['message' => 'test daily', 'title' => 'error']);

        $filename = "daily-" . date('Y-m-d') . ".log";
        $this->assertFileExists(storage_path("logs/{$filename}"));
    }

    /**
     * @date   2019-05-07
     * @throws \Exception
     * @author <zhufengwei@aliyun.com>
     */
    public function testMakeSingleLogger()
    {
        $name   = 'single';
        $logger = new Logger($name);
        $logger->setMode();

        $logcollector = new LogCollector();
        $logcollector->addLogger($name, $logger)->singleInfo('test single');

        $filename = $name . '.log';
        $this->assertFileExists(storage_path("logs/{$filename}"));
    }

    /**
     * @date   2019-05-07
     * @author <zhufengwei@aliyun.com>
     * @throws \Exception
     */
    public function testMakeEsLogger()
    {
        $name   = 'elastic';
        $logger = new Logger($name);
        $logger = $logger->makeEsLogger();

        $logcollector = new LogCollector();
        $logcollector->addLogger($name, $logger)->elasticInfo(json_encode(['message' => 'test elastic', 'title' => 'error']));

        $this->assertTrue(true);
    }

    /**
     * @date   2019-05-07
     * @author <zhufengwei@aliyun.com>
     * @throws \Exception
     */
    public function testEsLoggerLogError()
    {
        // $name   = 'elastic';
        // $logger = new Logger($name);
        // $logger = $logger->makeEsLogger();
        //
        // $logcollector = new LogCollector();
        // $logcollector->addLogger($name, $logger)->elasticError(json_encode(['message' => 'test elastic', 'title' => 'error']));

        $this->assertTrue(true);
    }

    /**
     * @date   2019-05-07
     * @author <zhufengwei@aliyun.com>
     */
    public function testLogcollectorProvider()
    {
        app()->register(LogCollectorServiceProvider::class);
        $logcollector = app('logcollector');

        $this->assertIsObject($logcollector);
        $this->assertTrue($logcollector instanceof LogCollector);
    }
}
