<?php

namespace Faris\LogCollector;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Illuminate\Config\Repository;
use Webpatser\Uuid\Uuid;

class LogCollector
{
    protected $accessLogger;
    protected $eventLogger;
    protected $config;

    protected $product;
    protected $serviceName;
    protected $prefix;
    protected $logInfo;
    protected $startTime;
    protected $requestId;

    protected $eventName;
    protected $eventInfo;
    protected $eventUserId;

    public function __construct(Repository $config)
    {
        $this->config = $config;

        //info logger
        $this->accessLogger = new Logger($this->config->get('logcollector.access.log_channel'));
        $accessRotate = new RotatingFileHandler($this->config->get('logcollector.access.file_name'), Logger::INFO);
        $accessRotate->setFormatter(new LineFormatter("[%datetime%] [%level_name%] %channel% - %message% %extra%\n"));
        $this->accessLogger->pushHandler($accessRotate);

        //event logger
        $this->eventLogger = new Logger($this->config->get('logcollector.event.log_channel'));
        $eventRotate = new RotatingFileHandler($this->config->get('logcollector.event.file_name'), Logger::INFO);
        $eventRotate->setFormatter(new LineFormatter("[%datetime%] [%level_name%] %channel% - %message% %extra%\n"));
        $this->eventLogger->pushHandler($eventRotate);


        //exception logger
        $exception_channel =
            $this->config->has('logcollector.exception.log_channel') ?
            $this->config->get('logcollector.exception.log_channel') : 'EXCEPTION';

        $this->exceptionLogger = new Logger($exception_channel);
        $exception_file =
            $this->config->has('logcollector.exception.file_name') ?
            $this->config->get('logcollector.exception.file_name') :
            base_path("../logs/".$this->config->get('logcollector.service_name').'.exception.log');

        $errorRotate = new RotatingFileHandler($exception_file, Logger::INFO);
        $errorRotate->setFormatter(new LineFormatter("[%datetime%] [%level_name%] %channel% - %message% %extra%\n"));
        $this->exceptionLogger->pushHandler($errorRotate);

        //add info
        $extraFields = array(
            'url'         => 'REQUEST_URI',
            'http_method' => 'REQUEST_METHOD',
            'server'      => 'SERVER_NAME',
            'referrer'    => 'HTTP_REFERER',
        );
        $webProcessor = new WebProcessor(null, $extraFields);
        //$codeProcessor = new IntrospectionProcessor();
        $this->accessLogger->pushProcessor($webProcessor);
        //$this->eventLogger->pushProcessor($webProcessor);

        //basic info
        if ($this->config->has('logcollector.product')) {
            $this->product = $this->config->get('logcollector.product');
        } else {
            $this->product = 'haibian';
        }

        if ($this->config->has('logcollector.service_name')) {
            $this->serviceName = $this->config->get('logcollector.service_name');
        } else {
            $this->serviceName = 'server';
        }

        $this->startTime = defined(LARAVEL_START) ? LARAVEL_START * 1000 : microtime(true);
        $this->requestId = (string)Uuid::generate(4);

        $this->logInfo = [];
        $this->prefix = $this->product." ".$this->serviceName;
    }

    public function addLogInfo($key, $value)
    {
        if (!isset($key) || !isset($value)) {
            return false;
        }

        $this->logInfo[$key] = $value;
        return true;
    }

    public function logAfterRequest()
    {
        $this->accessLogger->pushProcessor(function($record) {
            $record['extra'] = array_merge($this->logInfo, $record['extra']);
            $record['extra']['request_id'] = $this->requestId;
            $record['extra']['response_time'] = sprintf('%dms', round(microtime(true) * 1000 - $this->startTime * 1000));
            $record['extra']['ip'] = $this->getClientIp();
            return $record;
        });

        $this->accessLogger->addInfo($this->prefix);
    }

    public function logEvent($userId, $eventName, $eventInfo)
    {
        $this->eventName = $eventName;
        $this->eventInfo = $eventInfo;
        $this->eventUserId = $userId;

        $this->eventLogger->pushProcessor(function($record) {
            $record['extra']['request_id'] = $this->requestId;
            $record['extra']['event_name'] = $this->eventName;
            $record['extra']['event_info'] = $this->eventInfo;
            $record['extra']['event_user_id'] = $this->eventUserId;
            return $record;
        });

        $this->eventLogger->addInfo($this->prefix);
    }


    public function logException($exceptionName, $msg, $dingtalk_token = '')
    {
        $this->exceptionName = $exceptionName;
        $this->exceptionMsg = $msg;
        $this->exceptionToken = '32c2a4305bb3b302551d0a308901b0b81d7f2e64ba0e0deff09665f7e9f58a54';
        if ($this->config->has('logcollector.exception.dingtalk_token')) {
            $this->exceptionToken = $this->config->get('logcollector.exception.dingtalk_token');
        }
        if (!empty($dingtalk_token)) {
            $this->exceptionToken = $dingtalk_token;
        }

        $this->exceptionLogger->pushProcessor(function($record) {
            $record['extra']['request_id'] = $this->requestId;
            $record['extra']['exception_name'] = $this->exceptionName;
            $record['extra']['exception_msg'] = $this->exceptionMsg;
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
        $this->logInfo['response_msg'] = $responseMsg;
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
