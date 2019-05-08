<?php

namespace Listen\LogCollector;

use Illuminate\Support\Str;
use Webpatser\Uuid\Uuid;

class LogCollector
{
    /**
     * @desc 日志文本前缀
     */
    protected $prefix;

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
     * @var array
     */
    protected static $loggerNames = [];

    /**
     * LogCollector constructor.
     * @throws \Exception
     */
    public function __construct($registerConfigLogggers = false)
    {
        $this->setBaseInfo(
            config('logcollector.product', 'logcollector'),
            config('logcollector.service_name', 'default')
        );

        if ($registerConfigLogggers) {
            // 注册配置文件中的日志
            $this->registerConfigLogggers();
        }
    }

    /**
     * @date   2019/1/30
     * @param $product
     * @param $serviceName
     *
     * @throws \Exception
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function setBaseInfo($product, $serviceName)
    {
        $this->prefix = $product . "." . $serviceName . ": ";

        return $this;
    }

    /**
     * @date   2019/1/30
     * @return $this
     * @author <zhufengwei@aliyun.com>
     */
    public function registerConfigLogggers()
    {
        $configLoggers = array_keys(config('logcollector.loggers'));
        foreach ($configLoggers as $configLogger) {
            if (!in_array($configLogger, static::$loggerNames)) {
                array_push(static::$loggerNames, $configLogger);
            }
        }

        return $this;
    }

    /**
     * @date   2019/1/25
     * @param string $name
     *
     * @return bool
     * @throws \Exception
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function checkLogger(string $name)
    {
        if (!in_array(Str::lower($name), array_keys(static::$loggers))) {
            throw new \Exception('logger is illegal !');
        }

        return true;
    }

    /**
     * @date   2019/1/30
     * @param $name
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function registerLoggerName(string $name)
    {
        if (!in_array($name, static::$loggerNames)) {
            array_push(static::$loggerNames, $name);
        }

        return $this;
    }

    /**
     * @date   2019/1/30
     * @param $name
     *
     * @return bool
     * @throws \Exception
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function checkLoggerName($name)
    {
        if (!in_array(Str::lower($name), static::$loggerNames)) {
            \Log::error('logcollector', [$name]);
            throw new \Exception('logger name is illegal !');
        }

        return true;
    }

    /**
     * @date   2019/1/29
     * @return mixed
     * @author <zhufengwei@aliyun.com>
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @date   2019/1/30
     * @param                             $name
     * @param \Listen\LogCollector\Logger $logger
     *
     * @return $this
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function addLogger($name, Logger $logger)
    {
        static::$loggers[Str::lower($name)] = $logger;

        return $this->registerLoggerName($name);
    }

    /**
     * @date   2019/1/30
     * @param string $name
     *
     * @return $this
     * @throws \Listen\LogCollector\Exceptions\LoggerException
     * @author <zhufengwei@aliyun.com>
     *
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

        return $this->addLogger($name, $logger);
    }

    /**
     * @date   2019/1/25
     * @param string $logName
     *
     * @return mixed
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function getFile(string $logName)
    {
        return config("logcollector.loggers.${logName}.file", storage_path("logs/${logName}.log"));
    }

    /**
     * @date   2019/1/24
     * @param string $key
     * @param        $value
     *
     * @return $this
     * @throws \Exception
     * @author <zhufengwei@aliyun.com>
     *
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
     * @return $this
     * @desc   初始化日志信息
     * @author <zhufengwei@aliyun.com>
     */
    public function initLogInfo()
    {
        $this->logInfos = [];

        return $this;
    }

    /**
     * @date   2019-05-07
     * @param string $name
     * @param array  $arguments
     * @param string $level
     * @return $this
     * @throws Exceptions\LoggerException
     * @throws \Exception
     * @author <zhufengwei@aliyun.com>
     */
    private function log(string $name, array $arguments, string $level = 'addInfo')
    {
        $logger = $this->load($name)->getLogger($name);

        $logger->pushProcessor(function ($record) use ($arguments) {
            $record['extra'] = array_merge(
                $this->logInfos,
                [
                    'clientIp'  => static::getClientIp(),
                    'requestId' => (string)Uuid::generate(4),
                    'startTime' => microtime(true)
                ]
            );

            return $record;
        });

        $logger->$level($this->prefix . $this->formatArguments($arguments));
        $logger->popProcessor();
        $this->initLogInfo();

        return $this;
    }

    /**
     * @date   2019/1/29
     * @param string $loggerName
     *
     * @return mixed
     * @throws \Exception
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function getLogger(string $loggerName)
    {
        if ($this->checkLogger($loggerName)) {
            return static::$loggers[$loggerName];
        }
    }

    /**
     * @date   2019/1/25
     * @return array|false|string
     * @author <zhufengwei@aliyun.com>
     */
    private static function getClientIp()
    {
        if (function_exists('request') && class_exists('request')) {
            return request()->getClientIp();
        }

        $uip = '0.0.0.0';
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $uip = getenv('HTTP_CLIENT_IP');
        } else if (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $uip = getenv('HTTP_X_FORWARDED_FOR');
            strpos(',', $uip) && list($uip) = explode(',', $uip);
        } else if (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $uip = getenv('REMOTE_ADDR');
        } else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $uip = $_SERVER['REMOTE_ADDR'];
        }

        return $uip;
    }

    /**
     * @date   2019/1/25
     * @param $name
     * @param $arguments
     *
     * @return \Listen\LogCollector\LogCollector
     * @throws \Exception
     * @author <zhufengwei@aliyun.com>
     *
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
     * @param string $name
     *
     * @return array
     * @author <zhufengwei@aliyun.com>
     *
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

    /**
     * @date   2019-05-07
     * @param array $arguments
     * @return array|mixed|string
     * @author <zhufengwei@aliyun.com>
     */
    public function formatArguments(array $arguments)
    {
        try {
            return json_encode(current($arguments));
        } catch (\Exception $e) {
            return '记录日志出错：' . $e->getMessage();
        }
    }
}
