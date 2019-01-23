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
    protected        $logInfo  = [];
    protected static $loggeies = [];

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
            static::$loggeies[$logName] = $logger;
        }

        dd(static::$loggeies);
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

    public function logAccess()
    {
        $this->accessLogger->pushProcessor(function ($record) {
            $record['extra'] = array_merge($this->logInfo, $record['extra'], [
                'request_id'    => $this->requestId,
                'response_time' => sprintf('%dms', round(microtime(true) * 1000 - $this->startTime * 1000)),
                'ip'            => $this->getClientIp()
            ]);

            return $record;
        });

        $this->accessLogger->addInfo($this->prefix);
    }

    public function logEvent($userId, $eventName, $eventInfo)
    {
        $this->eventName   = $eventName;
        $this->eventInfo   = $eventInfo;
        $this->eventUserId = $userId;

        $this->eventLogger->pushProcessor(function ($record) {
            $record['extra']['request_id']    = $this->requestId;
            $record['extra']['event_name']    = $this->eventName;
            $record['extra']['event_info']    = $this->eventInfo;
            $record['extra']['event_user_id'] = $this->eventUserId;
            return $record;
        });

        $this->eventLogger->addInfo($this->prefix);
    }

    public function logException($exceptionName, $msg, $dingtalk_token = '')
    {
        $this->exceptionName  = $exceptionName;
        $this->exceptionMsg   = $msg;
        $this->exceptionToken = $this->config->get('logcollector.exception.dingtalk_token');
        $this->exceptionLogger->pushProcessor(function ($record) {
            $record['extra']['request_id']      = $this->requestId;
            $record['extra']['exception_name']  = $this->exceptionName;
            $record['extra']['exception_msg']   = $this->exceptionMsg;
            $record['extra']['exception_token'] = $this->exceptionToken;
            return $record;
        });

        $this->exceptionLogger->addInfo($this->prefix);
    }

    public function addUserId($userId)
    {
        $this->logInfo['user_id'] = $userId;
    }

    public function addResponseCode($responseCode, $responseMsg)
    {
        $this->logInfo['response_code'] = $responseCode;
        $this->logInfo['response_msg']  = $responseMsg;
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
}
