<?php

namespace Listen\LogCollector;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\WebProcessor;
use Illuminate\Config\Repository;
use Webpatser\Uuid\Uuid;

class LogCollector
{
    protected        $config;
    protected        $lineFormatter;
    protected        $prefix;
    protected        $startTime;
    protected        $requestId;
    protected        $logInfo = [];
    protected static $loggers = [];

    public function __construct(Repository $config)
    {
        $this->config = $config;
        $this->setBaseInfo();
        $this->setLineFormater();
        $this->setLoggeies();
    }

    public function setBaseInfo()
    {
        $product         = $this->config->get('logcollector.product', 'logcollector');
        $serviceName     = $this->config->get('logcollector.service_name', 'server');
        $this->prefix    = $product . "." . $serviceName;
        $this->startTime = microtime(true);
        $this->requestId = (string)Uuid::generate(4);
    }

    public function setLineFormater()
    {
        $formater = $this->config->get(
            'logcollector.formater',
            '[%datetime%] %channel%.%level_name%: %message% %extra%\n'
        );

        $this->lineFormatter = new LineFormatter($formater);
    }

    public function webProcessor()
    {
        return new WebProcessor(null, [
            'url'         => 'REQUEST_URI',
            'http_method' => 'REQUEST_METHOD',
            'server'      => 'SERVER_NAME',
            'referrer'    => 'HTTP_REFERER',
        ]);
    }

    private function setLoggeies()
    {
        $logs = $this->config->get('logcollector.logs');
        foreach ($logs as $logName => $logSetting) {
            $channel  = $this->config->get("logcollector.${logName}.channel", 'access');
            $fileName = $this->config->get("logcollector.${logName}.name", storage_path("logs/${logName}.log"));
            $level    = $this->config->get("logcollector.${logName}.level", 'info');

            $rotate = $this->setLoggerHandler($fileName, $level);
            $rotate->setFormatter($this->lineFormatter);

            $logger = new Logger($channel);
            $logger->pushHandler($rotate);
            static::$loggers[$logName] = $logger;
        }

        dd(static::$loggers);
    }

    private function setLoggerHandler($fileName, $level)
    {
        switch ($level) {
            case 'debug':
                return new RotatingFileHandler($fileName, Logger::DEBUG);
            case 'info':
                return new RotatingFileHandler($fileName, Logger::INFO);
            case 'warning':
                return new RotatingFileHandler($fileName, Logger::WARNING);
            case 'error':
                return new RotatingFileHandler($fileName, Logger::ERROR);
            default:
                throw new \Exception('Log Level Must Be: \'debug\', \'info\', \'warning\', \'error\'!');
        }
    }

    public function addLogInfo($key, $value)
    {
        if (!isset($key) || !isset($value)) {
            throw new \Exception('Key And Value Con\'t Be Null !');
        }

        $this->logInfo[$key] = $value;

        return $this;
    }

    public function initLogInfo()
    {
        $this->logInfo = [];
    }

    public function logAccess()
    {
        static::$loggers['access']->pushProcessor(function ($record) {
            $record['extra'] = array_merge($this->logInfo, $record['extra'], [
                'request_id'    => $this->requestId,
                'response_time' => sprintf('%dms', round(microtime(true) * 1000 - $this->startTime * 1000)),
                'ip'            => $this->getClientIp()
            ]);

            return $record;
        });

        static::$loggers['access']->addInfo($this->prefix);
        $this->initLogInfo();
    }

    public function logEvent($userId, $eventName, $eventInfo)
    {
        $this->eventName   = $eventName;
        $this->eventInfo   = $eventInfo;
        $this->eventUserId = $userId;

        static::$loggers['event']->pushProcessor(function ($record) use ($eventName, $eventInfo, $userId) {
            $record['extra'] = [
                'request_id'    => $this->requestId,
                'event_name'    => $eventName,
                'event_info'    => $eventInfo,
                'event_user_id' => $userId
            ];

            return $record;
        });

        static::$loggers['event']->addInfo($this->prefix);
        $this->initLogInfo();
    }

    public function logException($exceptionName, $msg, $dingtalkToken = '')
    {
        $exceptionToken = $this->config->get('logcollector.exception.dingtalk_token', '');

        static::$loggers['exception']->pushProcessor(function ($record) use ($exceptionName, $msg, $exceptionToken) {
            $record['extra'] = [
                'request_id'      => $this->requestId,
                'exception_name'  => $exceptionName,
                'exception_msg'   => $msg,
                'exception_token' => $exceptionToken
            ];

            return $record;
        });

        static::$loggers['exception']->addInfo($this->prefix);
        $this->initLogInfo();
    }

    public function logOther($name, $arguments)
    {
        static::$loggers[$name]->pushProcessor(function ($record) use ($arguments) {
            $record['extra'] = $arguments;
            return $record;
        });

        static::$loggers[$name]->addInfo($this->prefix);
    }

    public function getRequestId()
    {
        return $this->requestId;
    }

    public static function getClientIp()
    {
        $uip = '0.0.0.0';
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $uip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $uip = getenv('HTTP_X_FORWARDED_FOR');
            strpos(',', $uip) && list($uip) = explode(',', $uip);
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $uip = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $uip = $_SERVER['REMOTE_ADDR'];
        }

        return $uip;
    }

    public function __call($name, $arguments)
    {
        if (in_array($name, static::$loggers)) {
            if (in_array($name, ['access', 'event', 'exception'])) {
                $methodName = 'log' . ucfirst($name);
                $this->$methodName($arguments);
            } else {
                $this->logOther($name, ...$arguments);
            }
        }

        throw new \Exception('Method Does Not Exists! ');
    }
}
