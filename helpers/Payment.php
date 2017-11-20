<?php namespace KEERill\Pay\Helpers;

use Event;
use PaymentManager;
use KEERill\Pay\Models\PaymentItem;
use KEERill\Pay\Models\PaymentSystem;
use KEERill\Pay\Exceptions\PayException;
use KEERill\Pay\Models\Payment as PaymentModel;

Class Payment 
{
    use \October\Rain\Support\Traits\Emitter;

    /**
     * Создание нового платежа с предметами, передавая конкретный код платежной системы
     * 
     * @param string $code Код платежной системы, задаётся в настройках
     * @param array $data Данные платежа, например: Описание, и т.д.
     * @param array $items Предметы платежа, которые будут добавлены к новому платежу
     * @return void
     */
    public function createPaymentWithItemsAndCode($code, $data = [], $items = [])
    {
        /*
         * Проверка на существовании платежной системы
         * 
         * Ошибка PH-01: Платежная система с таким кодом не найдена или же она отключена
         */
        if (!$paymentSystem = PaymentSystem::where('code', $code)->hasEnable()->first()) {
            throw new PayException('При выполнении операции произошла ошибка с кодом PH-01', ['code' => $code] + post(), true);
        }

        /**
         * Создание платежа с предметами
         */
        if ($payment = $this->createPaymentWithItems($data, $items)) {   
            try {
                /**
                 * Присваивание способа оплаты в платежу
                 * 
                 * Ошибка PH-02: Не валидные аргументы платежа или уже выбран способ оплаты
                 */
                $this->setPaymentMethod($payment, $paymentSystem);
            }
            catch(\Exception $ex) {
                throw new PayException('При выполнении операции произошла ошибка с кодом PH-02', post());
            }
        }
    }

    /**
     * Создание нового платежа
     * 
     * @param array $data Данные платежа, например: Описание, и т.д.
     * @param array $items Предметы платежа, которые будут добавлены к новому платежу
     * @return KEERill\Pay\Models\Payment $payment Модель платежа
     */
    public function createPaymentWithItems($data = [], $items = [], $throwException = true)
    {
        /**
         * Ошибка PH-03: Переданные данные платежа не являются массивами [method: createpaymentWithItems]
         */
        if (!is_array($data) || !is_array($items)) {
            throw new PayException('При выполнении операции произошла ошибка с кодом PH-03', post(), true);
        }

        $payment = new PaymentModel;
        $payment->fill($data);

        $filteredItems = $this->filterAvailabilityItems($items, $throwException);

        $this->fireEvent('keerill.pay.extendCreatePayment', [$this, $payment, $data, $filteredItems]);

        $payment->save();

        $this->addItemsToPayment($payment, $filteredItems, $throwException);

        $this->fireEvent('keerill.pay.afterCreatePayment', [$this, $payment]);

        return $payment;
    }

    /**
     * Добавление новых предметов к платежу
     * 
     * @param KEERill\Pay\Models\Payment Модель платежа
     * @param array Предметы, которые нужно добавить к платежу
     * @param bool Вызывать исключение, если предмет не найден?
     * @return bool
     */
    public function addItemsToPayment(PaymentModel $payment, $items, $throwException = true)
    {
        if (!$items || !is_array($items)) {
            return false;
        }

        foreach ($items as $item => $params) {
            if (!$itemClass = PaymentManager::findPaymentItemByAlias(array_get($params, 'nameItem', false))) {
                if ($throwException) {
                    throw new PayException(sprintf(
                        'Предмет [%s] не найден',
                        array_get($params, 'nameItem', false)),
                        $params);
                }
                
                continue;
            }

            $newItem = new PaymentItem;
            $newItem->applyPaymentItemClass($itemClass->class);
            $newItem->fill($params);

            $payment->items()->add($newItem);
        }

        return true;
    }

    /** 
     * Проверка на доступность добавляемых предметов
     * 
     * @param array $items Предметы
     * @param bool Вызывать исключение, если предмет не найден
     * @return array Доступные предметы
     */
    public function filterAvailabilityItems($items, $throwException = true)
    {
        if (!$items || !is_array($items)) {
            return [];
        }

        $filteredItems = [];

        foreach ($items as $item => $params) {
            if (!$itemClass = PaymentManager::findPaymentItemByAlias(array_get($params, 'nameItem', false))) {
                if ($throwException) {
                    throw new PayException(sprintf(
                        'Предмет [%s] не найден',
                        array_get($params, 'nameItem', false)),
                        $params);
                }
                continue;
            }

            $filteredItems[] = $params;
        }

        return $filteredItems;
    }

    /**
     * Присваивание нового способа оплаты к платежу
     * 
     * @param KEERill\Pay\Models\Payment Модель платежа
     * @param KEERill\Pay\Models\PaymentSystem Модель платежной системы
     * @return void
     */
    public function setPaymentMethod(PaymentModel $payment, PaymentSystem $paymentSystem)
    {
        /**
         * Ошибка PH-04: У платежа уже выбран способ оплаты
         */
        if ($payment->payment) {
            throw new PayException('При выполнении операции произошла ошибка с кодом PH-04', post(), false);
        }

        $payment->payment = $paymentSystem;
        $paymentSystem->fireEvent('keerill.pay.extendPayment', [$payment]);
        $payment->save();
    }
}