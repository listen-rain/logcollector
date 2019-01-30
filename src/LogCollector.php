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
     * @author <zhufengwei@aliyun.com>
     *
     * @param $product
     * @param $serviceName
     *
     * @throws \Exception
     */
    public function setBaseInfo($product, $serviceName)
    {
        $this->prefix = $product . "." . $serviceName;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @return $this
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
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $name
     *
     * @return bool
     * @throws \Exception
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
     * @author <zhufengwei@aliyun.com>
     *
     * @param $name
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
     * @author <zhufengwei@aliyun.com>
     *
     * @param $name
     *
     * @return bool
     * @throws \Exception
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

        return $this->registerLoggerName($name);
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

        return $this->addLogger($name, $logger);
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
                'requestId' => (string)Uuid::generate(4),
                'startTime' => microtime(true)
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
        if ($this->checkLogger($loggerName)) {
            return static::$loggers[$loggerName];
        }
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
