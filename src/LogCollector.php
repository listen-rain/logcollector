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
    /**
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * @var \Monolog\Formatter\LineFormatter
     */
    protected $lineFormatter;

    /**
     * @desc 日志文本前缀
     */
    protected $prefix;

    /**
     * @desc 记录开始时间
     */
    protected $startTime;

    /**
     * @desc 请求标识
     */
    protected $requestId;

    /**
     * @var array
     * @desc 日志内容
     */
    protected $logInfos = [];

    /**
     * @var array
     * @desc 日志实例
     */
    protected static $loggers = [];

    /**
     * LogCollector constructor.
     *
     * @param \Illuminate\Config\Repository $config
     */
    public function __construct(Repository $config)
    {
        $this->config = $config;
        $this->setBaseInfo();
        $this->setLineFormater();
        $this->setLoggeies();
    }

    /**
     * @date   2019/1/24
     * @author <zhufengwei@aliyun.com>
     * @throws \Exception
     */
    public function setBaseInfo()
    {
        $product         = $this->config->get('logcollector.product', 'logcollector');
        $serviceName     = $this->config->get('logcollector.service_name', 'server');
        $this->prefix    = $product . "." . $serviceName;
        $this->startTime = microtime(true);
        $this->requestId = (string)Uuid::generate(4);
    }

    /**
     * @date   2019/1/24
     * @author <zhufengwei@aliyun.com>
     */
    public function setLineFormater()
    {
        $formater = $this->config->get(
            'logcollector.formater',
            '[%datetime%] %channel%.%level_name%: %message% %extra%\n'
        );

        $this->lineFormatter = new LineFormatter($formater);
    }

    /**
     * @date   2019/1/24
     * @author <zhufengwei@aliyun.com>
     * @return \Monolog\Processor\WebProcessor
     */
    public function webProcessor()
    {
        return new WebProcessor(null, [
            'url'         => 'REQUEST_URI',
            'http_method' => 'REQUEST_METHOD',
            'server'      => 'SERVER_NAME',
            'referrer'    => 'HTTP_REFERER',
        ]);
    }

    /**
     * @date   2019/1/25
     * @author <zhufengwei@aliyun.com>
     * @throws \Exception
     */
    private function setLoggeies()
    {
        $logs = $this->config->get('logcollector.loggers');

        foreach ($logs as $logName => $logSetting) {
            $channel  = $this->config->get("logcollector.${logName}.channel", 'access');
            $level    = $this->config->get("logcollector.${logName}.level", 'info');
            $fileName = $this->getFileName($logName);

            $this->addLogger($channel, $logName, $fileName, $level);
        }
    }

    /**
     * @date   2019/1/25
     * @author <zhufengwei@aliyun.com>
     * @param string $channel
     * @param string $logName
     * @param string $fileName
     * @param string $level
     *
     * @throws \Exception
     */
    public function addLogger(string $channel, string $logName, string $fileName, string $level = 'info')
    {
        $rotate = $this->setLoggerHandler($fileName, $level);
        $rotate->setFormatter($this->lineFormatter);

        $logger = new Logger($channel);
        $logger->pushHandler($rotate);
        static::$loggers[$logName] = $logger;
    }

    /**
     * @date   2019/1/25
     * @author <zhufengwei@aliyun.com>
     * @param $logName
     *
     * @return mixed
     */
    public function getFileName($logName)
    {
        $fileName = $this->config->get("logcollector.${logName}.name", storage_path("logs/${logName}.log"));

        return $fileName;
    }

    /**
     * @date   2019/1/24
     * @author <zhufengwei@aliyun.com>
     *
     * @param $fileName string
     * @param $level    string
     *
     * @return \Monolog\Handler\RotatingFileHandler
     * @throws \Exception
     */
    private function setLoggerHandler(string $fileName, string $level)
    {
        switch (strtolower($level)) {
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

    /**
     * @date   2019/1/24
     * @author <zhufengwei@aliyun.com>
     *
     * @param $key
     * @param $value
     *
     * @return $this
     * @throws \Exception
     */
    public function addLogInfo($key, $value)
    {
        if (!isset($key) || !isset($value)) {
            throw new \Exception('Key And Value Con\'t Be Null !');
        }

        $this->logInfos[$key] = $value;

        return $this;
    }

    /**
     * @desc   初始化日志信息
     * @date   2019/1/24
     * @author <zhufengwei@aliyun.com>
     */
    public function initLogInfo()
    {
        $this->logInfos = [];
    }

    /**
     * @date   2019/1/24
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $name
     * @param array  $arguments
     */
    public function log(string $name, array $arguments)
    {
        static::$loggers[$name]->pushProcessor(function ($record) use ($arguments) {
            $record['extra']              = array_merge($this->logInfos, $arguments);
            $record['extra']['clientIp']  = static::getClientIp();
            $record['extra']['requestId'] = $this->requestId;
            return $record;
        });

        static::$loggers[$name]->addInfo($this->prefix);
        $this->initLogInfo();
    }

    /**
     * @date   2019/1/24
     * @author <zhufengwei@aliyun.com>
     * @return mixed
     */
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
            $this->log($name, ...$arguments);
        }

        throw new \Exception('Method Does Not Exists! ');
    }
}
