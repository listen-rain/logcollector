<?php

namespace Listen\LogCollector;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\WebProcessor;
use Illuminate\Config\Repository;
use Webpatser\Uuid\Uuid;

class LogCollector
{
    protected $accessLogger;
    protected $eventLogger;
    protected $config;
    protected $lineFormatter;

    protected $product;
    protected $serviceName;
    protected $prefix;
    protected $logInfo = [];
    protected $startTime;
    protected $requestId;

    protected $eventName;
    protected $eventInfo;
    protected $eventUserId;

    public function __construct(Repository $config)
    {
        $this->config        = $config;
        $this->lineFormatter = new LineFormatter("[%datetime%] [%level_name%] %channel% - %message% %extra%\n");

        //info logger
        $this->accessLogger = new Logger($this->config->get('logcollector.access.log_channel'));
        $accessRotate       = new RotatingFileHandler($this->config->get('logcollector.access.file_name'), Logger::INFO);
        $accessRotate->setFormatter($this->lineFormatter);
        $this->accessLogger->pushHandler($accessRotate);

        //event logger
        $this->eventLogger = new Logger($this->config->get('logcollector.event.log_channel'));
        $eventRotate       = new RotatingFileHandler($this->config->get('logcollector.event.file_name'), Logger::INFO);
        $eventRotate->setFormatter($this->lineFormatter);
        $this->eventLogger->pushHandler($eventRotate);


        //exception logger
        $exception_channel     = $this->config->get('logcollector.exception.log_channel', 'EXCEPTION');
        $exception_file        = $this->config->get('logcollector.exception.file_name', base_path("../logs/" . $this->config->get('logcollector.service_name') . '.exception.log'));
        $this->exceptionLogger = new Logger($exception_channel);

        $errorRotate = new RotatingFileHandler($exception_file, Logger::INFO);
        $errorRotate->setFormatter($this->lineFormatter);
        $this->exceptionLogger->pushHandler($errorRotate);

        //add info
        $webProcessor = new WebProcessor(null, [
            'url'         => 'REQUEST_URI',
            'http_method' => 'REQUEST_METHOD',
            'server'      => 'SERVER_NAME',
            'referrer'    => 'HTTP_REFERER',
        ]);
        $this->accessLogger->pushProcessor($webProcessor);

        //basic info
        $this->product     = $this->config->get('logcollector.product', 'logcollector');
        $this->serviceName = $this->config->get('logcollector.service_name', 'server');
        $this->startTime   = microtime(true);
        $this->requestId   = (string)Uuid::generate(4);
        $this->prefix      = $this->product . " " . $this->serviceName;
    }

    public function addLogInfo($key, $value)
    {
        if (!isset($key) || !isset($value)) {
            return false;
        }

        $this->logInfo[$key] = $value;
        return true;
    }

    public function makeLogger(string $fileName, string $channel, \Closure $processor)
    {
        $logger = new Logger($channel);

        try {
            $logger->pushHandler(new StreamHandler($fileName, Logger::INFO, false));
        } catch (\Exception $e) {
            $logger->info('pushHandlerError', $e->getMessage());
        }

        $logger->pushProcessor($processor);
        return $logger;
    }

    public function logAccess()
    {
        $this->accessLogger->pushProcessor(function ($record) {
            $record['extra']                  = array_merge($this->logInfo, $record['extra']);
            $record['extra']['request_id']    = $this->requestId;
            $record['extra']['response_time'] = sprintf('%dms', round(microtime(true) * 1000 - $this->startTime * 1000));
            $record['extra']['ip']            = $this->getClientIp();
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
