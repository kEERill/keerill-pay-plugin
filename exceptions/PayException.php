<?php namespace KEERill\Pay\Exceptions;

use Log;
use Exception;
use October\Rain\Exception\ApplicationException;

/**
 * Используется когда платеж или транзакции привели к ошибке
 *
 * @package kEERill\Pay
 * @author kEERill
 */
class PayException extends ApplicationException
{
    public function __construct($message = "", $params = [], $log = true, $code = 0, Exception $previous = null)
    {
        if ($log) {
            $adm_message = $message;
            
            if ($params) {
                $result = [];
    
                foreach($params as $name => $value) {
                    $result[] = "\n" . sprintf('%s = %s', $name, $value);
                }
    
                $adm_message .= "\n" . sprintf('Параметры запроса: %s', implode(', ', $result));
            }
    
            Log::error($adm_message);
        }

        parent::__construct($message, $code, $previous);
    }
}