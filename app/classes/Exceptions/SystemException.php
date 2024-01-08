<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class SystemException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        // $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        // $file = isset($trace[1]['file']) ? $trace[1]['file'] : '';
        // $line = isset($trace[1]['line']) ? $trace[1]['line'] : '';
        // $message = "Exception in file $file on line $line: $message";
        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString(): string
    {
        return __CLASS__ . ": [$this->code]: $this->message\n";
    }
}
