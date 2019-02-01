<?php
/**
 * Created by PhpStorm.
 * User: <zhufengwei@aliyun.com>
 * Date: 2019/1/30
 * Time: 09:31
 */

namespace Listen\LogCollector;

use Listen\LogCollector\Exceptions\LoggerException;
use Monolog\Formatter\LineFormatter;
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
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $channel
     *
     * @return $this
     */
    public function setChannel(string $channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $path
     *
     * @return $this
     */
    public function setFile(string $path)
    {
        $this->file = $path;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $level
     *
     * @return $this
     */
    public function setLevel(string $level)
    {
        $this->level = $level;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $mode
     *
     * @return $this
     */
    public function setMode(string $mode = 'single')
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param int $num
     *
     * @return $this
     */
    public function setMaxFileNum(int $num)
    {
        $this->maxFileNum = $num;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param string $formater
     *
     * @return $this
     */
    public function setLineFormater(string $formater)
    {
        $this->lineFormatter = new LineFormatter($formater);

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param bool $bubble
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
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @throws \Listen\LogCollector\Exceptions\LoggerException
     */
    public function makeSingleLogger()
    {
        if ($this->level !== 'info') {
            throw new LoggerException($this->name, 'single mode support info level only!');
        }

        $this->mlogger->pushHandler(new StreamHandler($this->file, MLogger::INFO, false));
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @return $this
     * @throws \Listen\LogCollector\Exceptions\LoggerException
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
     * @author <zhufengwei@aliyun.com>
     * @return $this
     */
    public function resetMlogger()
    {
        $this->mlogger = null;

        return $this;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     * @return \Monolog\Logger
     */
    public function getMlogger()
    {
        return $this->mlogger;
    }

    /**
     * @date   2019/1/30
     * @author <zhufengwei@aliyun.com>
     *
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (!$this->mlogger) {
            $this->make();
        }

        return $this->mlogger->$name(...$arguments);
    }
}
