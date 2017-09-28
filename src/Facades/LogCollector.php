<?php

namespace Faris\LogCollector\Facades;

use Illuminate\Support\Facades\Facade;

class LogCollector extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'logcollector';
    }
}
