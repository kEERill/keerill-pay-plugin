<?php namespace KEERill\Pay\Components;

use Flash;
use Request;
use Redirect;
use ApplicationException;
use Payment as PaymentHelper;
use Cms\Classes\ComponentBase;
use KEERill\Pay\Models\Payment;
use KEERill\Pay\Models\PaymentSystem;
use KEERill\Pay\Exceptions\PayException;

class PaymentInfo extends ComponentBase
{
    /**
     * @var Payment Модель платежа
     */
    protected $payment = null;

    /**
     * @var Collection Модели платежных шлюзов 
     */
    protected $systems = null;

    /**
     * {@inheritdoc}
     */
    public function componentDetails()
    {
        return [
            'name'        => 'Ифнормация о платеже',
            'description' => 'Выводит полную информацию о платеже по хэшу платежа'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function defineProperties()
    {
        return [
            'paymentHash' => [
                'title' => 'Хэш',
                'description' => 'Параметр, в котором передаётся хэш платежа',
                'type' => 'string',
                'default' => '{{ :hash }}'
            ],
        ];
    }

    /**
     * Получениe модели платежа
     * 
     * @return \KEERill\Pay\Models\Payment Модель платежа
     */
    public function getPayment()
    {
        if ($this->payment != null) {
            return $this->payment;
        }
        
        return $this->payment = $this->getPaymentByHash($this->property('paymentHash'));
    }

    /**
     * Возвращает список доступных платежных систем
     * @return Collection
     */
    public function getPaymentSystems()
    {
        if ($this->systems != null) {
            return $this->systems;
        }

        return $this->systems = PaymentSystem::hasEnable()->get();
    }

    /**
     * Возвращает платежный шлюз системы
     * @return PaymentHandler
     */
    public function getPaymentHandler()
    {
        if (!$payment = $this->getPayment()) {
            return false;
        }

        return $payment->getPaymentHandler();
    }

    /**
     * Получение модели по хэшу
     * 
     * @return Payment Модель платежа
     */
    protected function getPaymentByHash($hash)
    {
        return Payment::where('hash', $hash)->with(['payment', 'items'])->first();
    }

    /**
     * Получение модели платежного шлюза по коду
     * 
     * @return PaymentSystem
     */
    protected function getPaymentSystemByCode($code)
    {
        return PaymentSystem::hasEnable()->where('code', $code)->first();
    }

    /**
     * AJAX Handler!
     * 
     * Запуск сторонних обработчиков от платежного шлюза
     */
    public function onButtonPaymentHandler()
    {
        try {
            $data = post();

            if (!$actionHandler = post('ActionHandler', false)) {
                throw new ApplicationException('Параметр ActionHandler обязателен');
            }
    
            if (!$paymentHandler = $this->getPaymentHandler()) {
                throw new ApplicationException('Платежный шлюз отсутствует');
            }
    
            $registerHandlers = $paymentHandler->registerAjaxHandlers();
            
            if (!$handler = array_get($registerHandlers, $actionHandler, false)) {
                throw new ApplicationException(
                    sprintf(
                        'Обработчик %s не найден',
                        $actionHandler
                    )
                );
            }
    
            return $paymentHandler->{$handler}($this, $this->getPayment());
        }
        catch(\Exception $ex) {
            Flash::error($ex->getMessage());
            if (Request::ajax()) return;
            else return Redirect::back();
        }
    }

    /**
     * AJAX Handler!
     * 
     * Выбор платежного шлюза
     */
    public function onButtonChooseSystem()
    {
        $data = post();

        if (!$system = $this->getPaymentSystemByCode(trim(array_get($data, 'systemCode')))) {
            throw new ApplicationException('Payment system not found by code');
        }

        $this->prepareVars();

        PaymentHelper::setPaymentMethod($this->payment, $system);

        return Redirect::refresh();
    }
}
