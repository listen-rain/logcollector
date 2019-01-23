<?php

return [

    'product'      => 'oc',
    'service_name' => 'chinese_classroom',
    'formater'     => '[%datetime%] %channel%.%level_name%: %message% %extra%\n',

    'logs' => [
        'access' => [
            'channel'   => 'access',
            'file' => storage_path("logs/access.log"),
            'level' => ''
        ],

        'event' => [
            'channel'   => 'event',
            'file' => storage_path("logs/event.log"),
            'level' => ''
        ],

        'exception' => [
            'channel'   => 'exception',
            'file' => storage_path("logs/exception.log"),
            'level' => ''
        ]
    ]
];
