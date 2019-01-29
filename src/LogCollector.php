<?php

namespace Listen\LogCollector;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Handler\StreamHandler;
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
    private function setBaseInfo()
    {
        $product         = $this->config->get('logcollector.product', 'logcollector');
        $serviceName     = $this->config->get('logcollector.service_name', 'server');
        $this->prefix    = $product . "." . $serviceName;
        $this->startTime = microtime(true);
        $this->requestId = (string)Uuid::generate(4);
    }

    /**
     * @date   2019/1/25
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $name
     *
     * @return bool
     * @throws \Exception
     */
    public function checkLoggerName(string $name)
    {
        if (!in_array(Str::lower($name), array_keys(static::$loggers))) {
            throw new \Exception('logger name is illegal !');
        }

        return true;
    }

    /**
     * @date   2019/1/24
     * @author <zhufengwei@aliyun.com>
     */
    public function setLineFormater()
    {
        $formater = $this->config->get(
            'logcollector.formater',
            "[%datetime%] %channel%.%level_name%: %message% %extra%\n"
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
     *
     * @param string $name
     *
     * @throws \Exception
     */
    private function setLoggeies()
    {
        $loggers = $this->config->get('logcollector.loggers');

        // 实例化配置文件中的所有 logger 实例
        foreach ($loggers as $logName => $logSetting) {
            $channel  = $this->config->get("logcollector.loggers.${logName}.channel", 'access');
            $level    = $this->config->get("logcollector.loggers.${logName}.level", 'info');
            $mode     = $this->config->get("logcollector.loggers.${logName}.mode", 'daily');
            $fileName = $this->getFileName($logName);

            $this->addLogger($channel, $logName, $fileName, $level, $mode);
        }
    }

    /**
     * @date   2019/1/25
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $channel
     * @param string $logName
     * @param string $fileName
     * @param string $level
     * @param string $mode daily | single
     *
     * @throws \Exception
     */
    public function addLogger(string $channel, string $logName = '', string $fileName = '', string $level = 'info', string $mode = 'daily')
    {
        $logName  = $logName ?: $channel;
        $fileName = $fileName ?: $this->getFileName($logName);
        $logger   = new Logger($channel);

        if ($mode === 'daily') {
            // 日志切割成每日一个日志文件
            $rotate = new RotatingFileHandler($fileName, $this->config->get('logcollector.max_file'), $this->setLoggerLevel($level), false);
            $rotate->setFormatter($this->lineFormatter);
            $logger->pushHandler($rotate);

        } else if ($mode === 'single') {
            // 所有日志都记录到一个文件，此时的 handler 级别仅支持 info
            if ($level !== 'info') {
                throw new \Exception('single mode support info level only!');
            }

            $logger->pushHandler(new StreamHandler($fileName, Logger::INFO, false));
        } else {
            throw new \Exception('Mode is illegal !');
        }

        static::$loggers[strtolower($logName)] = $logger;

        return $this;
    }

    /**
     * @date   2019/1/25
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $logName
     *
     * @return mixed
     */
    public function getFileName(string $logName)
    {
        return $this->config->get(
            "logcollector.loggers.${logName}.file",
            storage_path("logs/${logName}.log")
        );
    }

    /**
     * @date   2019/1/25
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $level
     *
     * @return int
     * @throws \Exception
     */
    private function setLoggerLevel(string $level)
    {
        switch (strtolower($level)) {
            case 'debug':
                return Logger::DEBUG;
            case 'info':
                return Logger::INFO;
            case 'warning':
                return Logger::WARNING;
            case 'error':
                return Logger::ERROR;
            default:
                throw new \Exception('Log Level Must Be: \'debug\', \'info\', \'warning\', \'error\'!');
        }
    }

    /**
     * @date   2019/1/24
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $key
     * @param        $value
     *
     * @return $this
     * @throws \Exception
     */
    public function addLogInfo(string $key, $value)
    {
        if (!isset($key) || !isset($value)) {
            throw new \Exception('Key And Value Con\'t Be Null !');
        }

        $this->logInfos[$key] = $value;

        return $this;
    }

    /**
     * @date   2019/1/25
     * @author <zhufengwei@aliyun.com>
     * @return $this
     * @desc   初始化日志信息
     */
    public function initLogInfo()
    {
        $this->logInfos = [];

        return $this;
    }

    /**
     * @date   2019/1/29
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $name
     * @param array  $arguments
     * @param string $level
     *
     * @return $this
     */
    public function log(string $name, array $arguments, string $level = 'addInfo')
    {
        static::$loggers[$name]->pushProcessor(function ($record) use ($name, $arguments) {
            $record['extra'] = array_merge($this->logInfos, $arguments, [
                'clientIp'  => static::getClientIp(),
                'requestId' => $this->requestId
            ]);

            return $record;
        });

        static::$loggers[$name]->$level($this->prefix);
        static::$loggers[$name]->popProcessor();
        $this->initLogInfo();

        return $this;
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

    /**
     * @date   2019/1/25
     * @author <zhufengwei@aliyun.com>
     * @return array|false|string
     */
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

    /**
     * @date   2019/1/25
     * @author <zhufengwei@aliyun.com>
     *
     * @param $name
     * @param $arguments
     *
     * @return \Listen\LogCollector\LogCollector
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        list($name, $level) = $this->parseAction($name);
        if ($this->checkLoggerName($name)) {
            return $this->log($name, $arguments, $level);
        }

        throw new \Exception('Method Does Not Exists! ');
    }

    /**
     * @date   2019/1/29
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $name
     *
     * @return array
     */
    public function parseAction(string $name): array
    {
        $array = explode('_', Str::snake($name));

        $name  = Str::lower(array_first($array));
        $level = 'Info';

        if (count($array) >= 2) {
            $level = 'add' . Str::ucfirst(array_last($array));
        }

        return [$name, $level];
    }
}
