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
        if (!$paymentSystem = PaymentSystem::where('code', $code)->first()) {
            throw new PayException(sprintf('Не существует платежного шлюза с таким кодом [%s]', $code), ['code' => $code]);
        }

        /*
         * Проверка на состояние работы платежной системы
         */
        if (!$paymentSystem->hasEnableSystem()) {
            throw new PayException('Данная платежная система отключена администрацией сайта', [], false);
        }

        /**
         * Создание платежа с предметами
         */
        if ($payment = $this->createPaymentWithItems($data, $items)) {   
            /**
             * Присваивание способа оплаты в платежу
             */
            $paymentSystem->setPaymentMethod($payment);
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
        if (!is_array($data) || !is_array($items)) {
            throw new PayException('Invalid payment params', post(), true);
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
}