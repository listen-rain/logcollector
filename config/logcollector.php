<?php

return [
    'product'      => env('PRODUCT_NAME', 'logcollector'),
    'service_name' => env('service_name', 'default'),
    'formater'     => "[%datetime%] %channel%.%level_name%: %message% %extra%\n",
    'max_file'     => 30,

    'loggers' => [
        'access' => [
            'channel' => 'access',
            'file'    => storage_path("logs/access.log"),
            'level'   => 'warning',
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
