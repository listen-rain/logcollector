<?php
/**
 * Created by PhpStorm.
 * User: <zhufengwei@aliyun.com>
 * Date: 2019/1/30
 * Time: 10:02
 */

namespace Listen\LogCollector\Exceptions;

use Throwable;

class LoggerException extends \Exception
{
    public function __construct(string $loggerName = " ", string $message = "", int $code = 0, Throwable $previous = null)
    {
        $message = $loggerName . '>> ' . $message;
        parent::__construct($message, $code, $previous);
    }
}
