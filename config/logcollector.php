<?php

return [
    // 应用名
    'product'      => env('PRODUCT_NAME', 'logcollector'),

    // 服务名
    'service_name' => env('service_name', 'default'),

    // 日志格式
    'formater'     => "[%datetime%] %channel%.%level_name%: %message% %extra%\n",

    // 多文件模式保留的文件数
    'max_file'     => 30,

    // 过滤的敏感字段
    'safe'         => [
        //
    ],

    // 日志集群
    'loggers'      => [
        // 名称 => 配置项

        'access' => [
            'channel' => 'access',
            'file'    => storage_path("logs/access.log"),
            'level'   => 'info',
            'mode'    => 'single'
        ],

        'event' => [
            'channel' => 'event',
            'file'    => storage_path("logs/event.log"),
            'level'   => 'warning',
            'mode'    => 'single'
        ],

        'exception' => [
            'channel' => 'exception',
            'file'    => storage_path("logs/exception.log"),
            'level'   => 'error',
            'mode'    => 'single'
        ]
    ],

    // 使用 elasticsearch 做为日志服务
    'elastic'      => [
        'servers' => [
            'servers' => [
                [
                    'host' => env('ES_HOST', 'localhost'),
                    'port' => env('ES_PORT', 9200)
                ]
            ]
        ],
        'options' => [
            'index' => env('ES_INDEX', 'elastic'),
            'type'  => env('ES_TYPE', 'record')
        ]
    ],

    // 使用 elasticsearch 做为日志服务，并在请求后一次性记录日志
    'elasticLog'   => [
        'log_level'    => \Monolog\Logger::INFO,
        'hosts'        => env('ELASTIC_LOG_HOSTS', ['localhost:9200']),
        'log_index'    => env('ELASTIC_LOG_INDEX', 'elasticLog'),
        'log_type'     => env('ELASTIC_LOG_TYPE', 'log'),
        'ignore_field' => env('ELASTIC_LOG_IGNORE_FIELD', ['formatted', 'context'])
    ]
];
