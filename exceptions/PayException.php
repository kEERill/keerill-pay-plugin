<?php namespace KEERill\Pay\Exceptions;

use Log;
use Exception;
use October\Rain\Exception\ApplicationException;

Class PayException extends ApplicationException
{

    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}