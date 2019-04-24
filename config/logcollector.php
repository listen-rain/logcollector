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
    ]
];
