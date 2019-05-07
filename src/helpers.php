<?php
/**
 * Created by PhpStorm.
 * User: <zhufengwei@aliyun.com>
 * Date: 2019/2/1
 * Time: 15:09
 */

use Listen\LogCollector\Logger;
use Listen\LogCollector\LogCollector;

if (!function_exists('makeLogger')) {
    /**
     * @date   2019/2/1
     * @param string $name
     * @param bool   $isDaily
     * @author <zhufengwei@aliyun.com>
     */
    function makeLogger(string $name, bool $isDaily = true)
    {
        $logger = new Logger($name);
        !$isDaily && $logger->setMode();

        return (new LogCollector())->addLogger($name, $logger->make());
    }
}

if (!function_exists('makeEsLogger')) {
    /**
     * @date   2019-05-07
     * @param string $name
     * @return LogCollector
     * @throws Exception
     * @author <zhufengwei@aliyun.com>
     */
    function makeEsLogger(string $name)
    {
        $logger = (new Logger($name))->makeEsLogger();

        return (new LogCollector())->addLogger($name, $logger);
    }
}
