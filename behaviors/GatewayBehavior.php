<?php namespace KEERill\Pay\Behaviors;

use ApplicationException;
use KEERill\Pay\Classes\PaymentHandler;
use KEERill\Pay\Models\Payment as PaymentModel;

Class GatewayBehavior extends PaymentBehavior 
{
    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        parent::boot();

        $this->model->bindEvent('keerill.pay.changePaymentSystem', function($payment) {
            $this->changePaymentSystem($payment);
        });
    }

    /**
     * Вызывается, когда присваивается платеж к данной платежной системе
     * 
     * @param KEERill\Pay\Models\Payment
     * @return void
     */
    public function changePaymentSystem($payment) {}
}