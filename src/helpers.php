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
     * @author <zhufengwei@aliyun.com>
     * @param string $name
     * @param bool   $isDaily
     */
    function makeLogger(string $name, bool $isDaily = true)
    {
        $logger = new Logger($name);
        !$isDaily && $logger->setMode();

        return (new LogCollector())->addLogger($name, $logger->make());
    }
}
