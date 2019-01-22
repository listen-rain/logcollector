<?php

return [

    'product'      => 'product_name',
    'service_name' => 'server',

    'access' => [
        'log_channel' => 'ACCESS',
        'file_name'   => storage_path("logs/access.log"),
    ],

    'event' => [
        'log_channel' => 'EVENT',
        'file_name'   => storage_path("logs/event.log"),
    ],

    'exception' => [
        'log_channel'    => 'EXCEPTION',
        'file_name'      => storage_path("logs/exception.log"),
        'dingtalk_token' => 'dingtalk_token',
    ],
];
