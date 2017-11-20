<?php namespace KEERill\Pay\Payments\Gateways;

use Request;
use KEERill\Pay\Behaviors\Gateway;

Class Bitcoin extends Gateway {

    /**
     * Подробности платежного шлюза
     * 
     * @return array
     */
    public static function gatewayDetails()
    {
        return [
            'name' => 'Bitcoin',
            'description' => 'Прием платежей через платежную систему Bitaps'
        ];
    }

    public function defineValidationRules()
    {
        return [
            'cash' => 'required',
            'max_confirmations' => 'required|integer|between: 1,12'
        ];
    }

    /**
     * Регистрация точек входа
     * 
     * @return array 
     */
    public function registerAccessPoints()
    {
        return [
            'confirm' => 'paymentConfirmation'
        ];
    }

    /**
     * Подтверждение платежа
     * 
     * @return void
     */
    public function paymentConfirmation()
    {
        return;
    }

    public function extendCreateNewPayment($payment)
    {
        $payment->setParam(['payment_code' => '123123123']);
    }
} 