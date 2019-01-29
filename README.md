# Logcollector

> 基于 Laravel5 的日志记录服务

## 安装配置

```
composer require listen/logcollector
```

修改 config/app.php, 添加服务
```php
'providers' => [
    Listen\LogCollector\LogCollectorServiceProvider::class,
],
```

修改 config/app.php, 添加 Facade
```php
'aliases' => [
    'LogCollector'  => Listen\LogCollector\Facades\LogCollector::class,
]
```

生成配置文件 config/logcollector.php
```
php artisan vendor:publish
```

## 示例

1、在配置文件 config/logcollector.php 中添加 Logger
```
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
    ],
    ......
]
```

2、动态添加 Logger
```
$logCollector = LogCollector::addLogger('event');
```

3、记录日志
```
$logCollector->event('事件日志文本');
$logCollector->eventError('事件日志错误');
```

4、获取 Loogger 并记录日志
```
$logger = $logCollector->getLogger($loggerName);
$logger->pushProcessor(function ($record) {
    $record['extra'] = [
        .....
    ];
    
    return $record;
})->addError($logCollector->getPrefix());

```
