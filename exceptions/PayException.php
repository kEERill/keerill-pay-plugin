<?php namespace KEERill\Pay\Exceptions;

use Log;
use Exception;
use October\Rain\Exception\ApplicationException;

Class PayException extends ApplicationException
{
    /**
     * @var array Параметры ошибки
     */
    protected $params = [];

    /**
     * Получение параметров ошибки
     * 
     * @return array Параметры
     */
    public function getParams()
    {
        return $this->params;
    }
    
    public function __construct($message = "", $params = [], $useLog = false, $code = 0, Exception $previous = null)
    {
        $this->params = $params;

        if ($useLog == true) {
            $logMessage = $message;
            
            if (count($params) > 0) {
                $result = [];
    
                foreach($params as $name => $value) {
                    $result[] = "\n" . sprintf('%s = %s', $name, $value);
                }
    
                $logMessage .= "\n" . sprintf(
                    'Параметры запроса: %s', 
                    implode(', ', $result)
                );
            }
    
            Log::error($logMessage);
        }

        parent::__construct($message, $code, $previous);
    }
}