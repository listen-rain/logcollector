<?php
/**
 * Created by PhpStorm.
 * User: <zhufengwei@aliyun.com>
 * Date: 2019/1/30
 * Time: 09:31
 */

namespace Listen\LogCollector;

use Elastica\Client;
use Listen\LogCollector\Exceptions\LoggerException;
use Listen\LogCollector\Handlers\ElasticLogHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ElasticSearchHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MLogger;

class Logger
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var string
     */
    protected $level;

    /**
     * @var string daily|single
     */
    protected $mode = 'daily';

    /**
     * @var integer
     */
    protected $maxFileNum;

    /**
     * @var bool
     */
    protected $bubble;

    /**
     * @var LineFormatter
     */
    protected $lineFormatter;

    /**
     * @var MLogger
     */
    protected $mlogger;

    /**
     * Logger constructor.
     *
     * @param string $name
     * @param string $level
     */
    public function __construct(string $name, string $level = 'info', $bubble = false)
    {
        $this->name = $name;
        $this->setChannel($name);
        $this->setFile(storage_path("logs/{$name}.log"));
        $this->setLevel($level);
        $this->setBubble($bubble);

        $formater = config('logcollector.formater', "[%datetime%] %channel%.%level_name%: %message% %extra%\n");
        $this->setLineFormater($formater);
    }

    /**
     * @date   2019/1/30
     * @param string $channel
     *
     * @return $this
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function setChannel(string $channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @param string $path
     *
     * @return $this
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function setFile(string $path)
    {
        $this->file = $path;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @param string $level
     *
     * @return $this
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function setLevel(string $level)
    {
        $this->level = $level;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @param string $mode
     *
     * @return $this
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function setMode(string $mode = 'single')
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @param int $num
     *
     * @return $this
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function setMaxFileNum(int $num)
    {
        $this->maxFileNum = $num;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @param string $formater
     *
     * @return $this
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function setLineFormater(string $formater)
    {
        $this->lineFormatter = new LineFormatter($formater);

        return $this;
    }

    /**
     * @date   2019/1/30
     * @param bool $bubble
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function setBubble(bool $bubble)
    {
        $this->bubble = $bubble;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     */
    public function makeDailyLogger()
    {
        $rotate = new RotatingFileHandler($this->file, $this->maxFileNum, $this->level, $this->bubble);
        $rotate->setFormatter($this->lineFormatter);
        $this->mlogger->pushHandler($rotate);

        return $this;
    }

    /**
     * @date   2019-05-07
     * @throws LoggerException
     * @throws \Exception                If a missing directory is not buildable
     * @throws \InvalidArgumentException If stream is not a resource or string
     * @author <zhufengwei@aliyun.com>
     */
    public function makeSingleLogger()
    {
        if ($this->level !== 'info') {
            throw new LoggerException($this->name, 'single mode support info level only!');
        }

        $this->mlogger->pushHandler(new StreamHandler($this->file, MLogger::INFO, false));

        return $this;
    }

    /**
     * @date   2019-05-07
     * @return $this
     * @author <zhufengwei@aliyun.com>
     */
    public function makeEsLogger()
    {
        $esConfig      = (array)config('logcollector.elastic.servers', ['servers' => [['host' => 'localhost', 'port' => 9200]]]);
        $client        = new Client($esConfig);
        $this->mlogger = new MLogger($this->channel);
        $this->mlogger->pushHandler(new ElasticSearchHandler($client, (array)config('logcollector.elastic.options')));

        return $this;
    }

    /**
     * @date   2019-05-08
     * @return $this
     * @author <zhufengwei@aliyun.com>
     */
    public function makeElasticLogLogger()
    {
        $this->mlogger = new MLogger($this->channel);
        $level         = in_array(intval($this->level), [MLogger::INFO, MLogger::DEBUG, MLogger::ERROR, MLogger::NOTICE, MLogger::WARNING])
            ? intval($this->level)
            : config('logcollector.elasticLog.log_level', MLogger::INFO);

        $this->mlogger->pushHandler(new ElasticLogHandler($level));

        return $this;
    }

    /**
     * @date   2019/1/30
     * @return $this
     * @throws \Listen\LogCollector\Exceptions\LoggerException
     * @author <zhufengwei@aliyun.com>
     */
    public function make()
    {
        $this->mlogger = new MLogger($this->channel);

        $this->mode === 'daily'
            ? $this->makeDailyLogger()
            : $this->makeSingleLogger();

        return $this;
    }

    /**
     * @date   2019/2/1
     * @return $this
     * @author <zhufengwei@aliyun.com>
     */
    public function resetMlogger()
    {
        $this->mlogger = null;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @return \Monolog\Logger
     * @author <zhufengwei@aliyun.com>
     */
    public function getMlogger()
    {
        return $this->mlogger;
    }

    /**
     * @date   2019/1/30
     * @param $name
     * @param $arguments
     *
     * @return mixed
     * @author <zhufengwei@aliyun.com>
     *
     */
    public function __call($name, $arguments)
    {
        if (!$this->mlogger) {
            $this->make();
        }

        return $this->mlogger->$name(...$arguments);
    }
}
