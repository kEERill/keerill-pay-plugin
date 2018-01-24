<?php namespace KEERill\Pay\Behaviors;

use ApplicationException;
use KEERill\Pay\Models\Payment as PaymentModel;

Class Gateway extends PaymentBehavior 
{
    /**
     * Основная информация платежного шлюза
     *
     * @return array
     */
    public static function gatewayDetails()
    {
        return [
            'name' => 'None',
            'description' => 'None'
        ];
    }

    /**
     * Render setup help
     * @return string
     */
    public function getPartialPath()
    {
        return $this->configPath;
    }

    /**
     * Получение класса платежа
     * Верните false, чтобы отключить присвоение нового класса
     * 
     * @return string
     */
    public function getPaymentClass()
    {
        return false;
    }

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
     * Регистрация методов для API, так же для регистрации методов для работы приема платежей
     * @return array Список методов
     */
    public function registerAccessPoints() 
    {
        return [];
    }

    /**
     * Вызывается, когда присваивается платеж к данной платежной системе
     * 
     * @param KEERill\Pay\Models\Payment
     * @return void
     */
    public function changePaymentSystem($payment) {}

    /**
     * Получение модели платежа по хэшу
     * 
     * @param string хэш платежа
     * @return KEERill\Pay\Models\Payment Модель платежа 
     */
    protected function getPaymentModelToHash($hash)
    {
        if (!$hash) {
            throw new ApplicationException('Hash is required');
        }

        if (!$payment = PaymentModel::opened()->where('hash', $hash)->where('pay_method', $this->model->id)->first()) {
            throw new ApplicationException('Payment is not found');
        }

        if ($payment->hasOpen()) {
            $payment->changeStatusPayment(PaymentModel::PAYMENT_WAIT);
        }

        return $payment;
    }
}