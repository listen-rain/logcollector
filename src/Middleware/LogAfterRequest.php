<?php

namespace Listen\LogCollector\Middleware;

use Closure;

class LogAfterRequest {

    public function handle($request, Closure $next) {
        $requestId = app('logcollector')->getRequestId();

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId, false);
        return $response;
    }

    public function terminate($request, $response) {
        $this->logRequest($request);
        $this->logResponse($response);
        app('logcollector')->access();
    }

    public function logRequest($request) {
        //添加过滤信息
        $inputSafe = [
            'password'
        ];
        $inputs = $request->input();
        if (!empty($inputs)) {
            foreach($inputSafe as $safe) {
                if(!empty($inputs[$safe])) {
                    $inputs[$safe] = '[*** SENSOR ***]';
                }
            }
        }

        app('logcollector')->addLogInfo('request', $inputs);
    }

    public function logResponse($response) {
        $status = 0;
        if (method_exists($response, 'status')) {
            $status = $response->status();
        } elseif (method_exists($response, 'getStatusCode')) {
            $status = $response->getStatusCode();
        }
        $returns = [
            'status'  => $status,
        ];

        //只打印json格式的返回
        $content = [];
        if (method_exists($response, 'content')) {
            $content = $response->content();
        } elseif (method_exists($response, 'getContent')) {
            $content = $response->getContent();
        }
        $content = json_decode($content, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $returns['content'] = $content;
        }

        app('logcollector')->addLogInfo('response', $returns);
    }
}
