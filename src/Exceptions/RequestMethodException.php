<?php

namespace Lolaji\LaravelControllerTrait\Exceptions;

class RequestMethodException {

    protected static $debug = false;

    public function __construct() {
        $this->debug = config("app.debug");
    }

    public static function response ($debug_message="", $message="", $debug_status=500, $status=500) 
    {
        $content = static::$debug && !is_null($debug_message)? $debug_message : $message;
        $status = static::$debug && !is_null($debug_message)? $debug_status : $status;

        return response(
            content: $content,
            status: $status
        );
    }
}