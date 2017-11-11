<?php namespace KEERill\Pay\Components;

use Cms\Classes\ComponentBase;
use KEERill\Pay\Models\Payment;

class PaymentInfo extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Ифнормация о платеже',
            'description' => 'Выводит полную информацию о платеже по хэшу платежа'
        ];
    }

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

    public function onRun()
    {
        /**
         * Запись стандартных переменных
         */
        $this->prepareVariables();

        /**
         * Получение хэша платежа
         */
        if (!$hash = $this->param($this->property('paramCode'))) {
            return;
        }

        /**
         * Поиск платежа по полученному хэшу
         */
        if (!strlen(trim($hash)) || !($payment = Payment::where('hash', $hash)->with('payment')->first())) {
            return;
        }

        $this->page['pay_payment'] = $payment;
        
        if ($payment->payment) {
            $this->page['pay_partialName'] = $payment->payment->partial_name;
        }
    }

    /**
     * Запись стандартных переменных на страницу
     * 
     * @return void
     */
    protected function prepareVariables() 
    {
        $this->page['pay_payment'] = null;
        $this->page['pay_partialName'] = false;
    }
}
