<?php

namespace Listen\LogCollector\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class ElasticLogHandler extends AbstractProcessingHandler
{
    /**
     * @date   2019-05-08
     * @param array $record
     * @author <zhufengwei@aliyun.com>
     */
    protected function write(array $record)
    {
        if ($record['level'] >= Logger::INFO) {
            app('elasticLog')->addDocument($record);
        }
    }
}
