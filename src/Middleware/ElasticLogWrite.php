<?php

namespace Listen\LogCollector\Middleware;

use Elasticsearch\Client;
use Closure;

class ElasticLogWrite
{
    /**
     * @date   2019-05-08
     * @param         $request
     * @param Closure $next
     * @return mixed
     * @author <zhufengwei@aliyun.com>
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    /**
     * @date   2019-05-08
     * @author <zhufengwei@aliyun.com>
     */
    public function terminate()
    {
        $documents = app('elasticLog')->getDocuments();
        if (count($documents) > 0) {
            $body   = $this->setEsBody($documents);
            $client = app('elasticLog')->getClient();
            if ($client instanceof Client) {
                $client->bulk(compact('body'));
            }
        }
    }

    /**
     * @date   2019-05-08
     * @param array $documents
     * @return array
     * @author <zhufengwei@aliyun.com>
     */
    public function setEsBody(array $documents)
    {
        $body  = [];
        $index = strtolower(config('logcollector.elasticLog.log_index', 'elastic'));
        $type  = strtolower(config('logcollector.elasticLog.log_type', 'log'));

        foreach ($documents as $record) {
            $record             = $this->unsetIgnoreField($record);
            $record['datetime'] = $record['datetime']->format('Y-m-d H:i:s');

            array_push($body, ['index' => [
                '_index' => $index,
                '_type'  => $type,
            ],]);

            array_push($body, $record);
        }

        return $body;
    }

    /**
     * @date   2019-05-08
     * @param array $record
     * @return array
     * @author <zhufengwei@aliyun.com>
     */
    public function unsetIgnoreField(array $record)
    {
        $ignoreField = (array)config('logcollector.elasticLog.ignore_field', ['formatted']);
        foreach ($ignoreField as $field) {
            if (isset($record[$field])) {
                unset($record[$field]);
            }
        }

        return $record;
    }
}
