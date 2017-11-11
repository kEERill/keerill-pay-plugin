<?php namespace KEERill\Pay\Facades;

use October\Rain\Support\Facade;

class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     * @return string
     */
    protected static function getFacadeAccessor() { return 'payment.manager'; }
}
