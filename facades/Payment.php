<?php namespace KEERill\Pay\Facades;

use October\Rain\Support\Facade;

class Payment extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'keerill.pay.helper';
    }
    protected static function getFacadeInstance()
    {
        return new \KEERill\Pay\Helpers\Payment;
    }
}