<?php

namespace Listen\LogCollector\Middleware;

use Closure;
use Listen\LogCollector\Facades\LogCollector;

class LogAfterRequest
{
    /**
     * @var LogCollector
     */
    protected $logCollector;

    /**
     * LogAfterRequest constructor.
     * @param LogCollector $logCollector
     */
    public function __construct()
    {
        $this->logCollector = app('logcollector');
    }

    /**
     * @date   2019-04-24
     * @param         $request
     * @param Closure $next
     * @return mixed
     * @author <zhufengwei@aliyun.com>
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    /**
     * @date   2019-04-24
     * @param $request
     * @param $response
     * @author <zhufengwei@aliyun.com>
     */
    public function terminate($request, $response)
    {
        $this->logRequest($request);
        $this->logResponse($response);

        $this->logCollector->access();
    }

    /**
     * @date   2019-04-24
     * @param $request
     * @author <zhufengwei@aliyun.com>
     */
    public function logRequest($request)
    {
        //添加过滤信息
        $inputSafe = [
            'password',
            'token'
        ];

        $inputSafe = array_merge($inputSafe, config('logcollector.safe'));
        $inputs    = $request->all();
        if (!empty($inputs)) {
            foreach ($inputSafe as $safe) {
                if (!empty($inputs[$safe])) {
                    $inputs[$safe] = '[*** SENSOR ***]';
                }
            }
        }

        $this->logCollector->addLogInfo('request', $inputs);
    }

    /**
     * @date   2019-04-24
     * @param $response
     * @author <zhufengwei@aliyun.com>
     */
    public function logResponse($response)
    {
        $status = 0;
        if (method_exists($response, 'status')) {
            $status = $response->status();
        } else if (method_exists($response, 'getStatusCode')) {
            $status = $response->getStatusCode();
        }

        $returns = compact('status');
        $content = json_encode([]);
        if (method_exists($response, 'content')) {
            $content = $response->content();
        } else if (method_exists($response, 'getContent')) {
            $content = $response->getContent();
        }

        $content = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $returns['content'] = $content;
        }

        $this->logCollector->addLogInfo('response', $returns);
    }
}
