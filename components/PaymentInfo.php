<?php namespace KEERill\Pay\Components;

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
     * @var string Ошибка возникшая при работе
     */
    public $error;

    /**
     * @var Payment Модель платежа
     */
    protected $payment = null;

    /**
     * @var Collection Модели платежных шлюзов 
     */
    protected $systems;

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
            'paramCode' => [
                'title' => 'Хэш',
                'description' => 'Параметр, в котором передаётся хэш платежа',
                'type' => 'string',
                'default' => 'hash'
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
        return $this->payment;
    }

    /**
     * Получениe колекции моделей платежных шлюзов
     * 
     * @return Collection
     */
    public function getSystems()
    {
        return $this->systems;
    }

    /**
     * {@inheritdoc}
     */
    public function onRun()
    {
        try {
            $this->prepareVars();

            if (!$this->payment->payment) {
                $this->systems = PaymentSystem::hasEnable()->get();
            }
        } catch(ApplicationException $ex) {
            $this->error = $ex->getMessage();
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
     * Определение модели по хэшу
     */
    protected function prepareVars()
    {
        /**
         * Получение хэша платежа
         */
        if (!$hash = trim($this->param($this->property('paramCode')))) {
            throw new ApplicationException('Hash is required');
        }

        /**
         * Поиск платежа по полученному хэшу
         */
        if (!$this->payment = $this->getPaymentByHash($hash)) {
            throw new ApplicationException('Payment not found');
        }
    }
}
