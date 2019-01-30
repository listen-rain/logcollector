<?php

namespace Listen\LogCollector;

use Illuminate\Support\Str;
use Monolog\Handler\RotatingFileHandler;
use Webpatser\Uuid\Uuid;

class LogCollector
{
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
     * @throws \Exception
     */
    public function __construct()
    {
        $product         = config('logcollector.product', 'logcollector');
        $serviceName     = config('logcollector.service_name', 'server');
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
     * @date   2019/1/29
     * @author <zhufengwei@aliyun.com>
     * @return mixed
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param                             $name
     * @param \Listen\LogCollector\Logger $logger
     *
     * @return $this
     */
    public function addLogger($name, Logger $logger)
    {
        static::$loggers[Str::lower($name)] = $logger;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $name
     *
     * @return $this
     * @throws \Listen\LogCollector\Exceptions\LoggerException
     */
    public function load(string $name = '')
    {
        if (isset(static::$loggers[Str::lower($name)]) && static::$loggers[Str::lower($name)] instanceof Logger) {
            return $this;
        }

        $channel = config("logcollector.loggers.{$name}.channel", $name);
        $level   = config("logcollector.loggers.{$name}.level", 'info');
        $mode    = config("logcollector.loggers.{$name}.mode", 'daily');
        $file    = $this->getFile($name);

        $logger = new Logger($name, $level);
        $logger->setFile($file)
               ->setChannel($channel)
               ->setMode($mode)
               ->make();

        $this->addLogger($name, $logger);

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
    public function getFile(string $logName)
    {
        return config("logcollector.loggers.${logName}.file", storage_path("logs/${logName}.log"));
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
    private function log(string $name, array $arguments, string $level = 'addInfo')
    {
        $logger = $this->load($name)->getLogger($name);
        $logger->pushProcessor(function ($record) use ($name, $arguments) {
            $record['extra'] = array_merge($this->logInfos, $arguments, [
                'clientIp'  => static::getClientIp(),
                'requestId' => $this->requestId
            ]);

            return $record;
        });

        $logger->$level($this->prefix);
        $logger->popProcessor();
        $this->initLogInfo();

        return $this;
    }

    /**
     * @date   2019/1/29
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $loggerName
     *
     * @return mixed
     * @throws \Exception
     */
    public function getLogger(string $loggerName)
    {
        if ($this->checkLoggerName($loggerName)) {
            return static::$loggers[$loggerName];
        }
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
    private static function getClientIp()
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
    private function parseAction(string $name): array
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
