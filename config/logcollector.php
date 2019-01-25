<?php

return [
    'product'      => env('PRODUCT_NAME', 'logcollector'),
    'service_name' => env('service_name', 'default'),
    'formater'     => '[%datetime%] %channel%.%level_name%: %message% %extra%\n',

    'loggers' => [
        'access' => [
            'channel' => 'access',
            'file'    => storage_path("logs/access.log"),
            'level'   => ''
        ],

        'event' => [
            'channel' => 'event',
            'file'    => storage_path("logs/event.log"),
            'level'   => ''
        ],

        'exception' => [
            'channel' => 'exception',
            'file'    => storage_path("logs/exception.log"),
            'level'   => ''
        ]
    ]
];
