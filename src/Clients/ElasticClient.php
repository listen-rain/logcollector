<?php

namespace Listen\LogCollector\Clients;

use Elasticsearch\ClientBuilder;

class ElasticClient
{
    /**
     * @var \Elasticsearch\Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $documents = [];

    /**
     * ElasticClient constructor.
     */
    public function __construct()
    {
        $hosts        = config('logcollector.elasticLog.hosts');
        $this->client = ClientBuilder::create()->setHosts($hosts)->build();
    }

    /**
     * @date   2019-05-08
     * @return \Elasticsearch\Client
     * @author <zhufengwei@aliyun.com>
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @date   2019-05-08
     * @author <zhufengwei@aliyun.com>
     * @param array $document
     */
    public function addDocument(array $document)
    {
        $this->documents[] = $document;
    }

    /**
     * @date   2019-05-08
     * @author <zhufengwei@aliyun.com>
     * @return array
     */
    public function getDocuments()
    {
        return $this->documents;
    }
}
