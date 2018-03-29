<?php namespace KEERill\Pay\Helpers;

use Event;
use BackendAuth;
use Carbon\Carbon;
use PaymentManager;
use ApplicationException;
use KEERill\Pay\Models\PaymentLog;
use KEERill\Pay\Models\PaymentItem;
use KEERill\Pay\Models\PaymentSystem;
use KEERill\Pay\Exceptions\PayException;
use KEERill\Pay\Models\Payment as PaymentModel;

Class Payment 
{
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
            throw new PayException('При выполнении операции произошла ошибка с кодом PH-01');
        }

        /**
         * Создание платежа с предметами
         */
        if ($payment = $this->createPaymentWithItems($data, $items)) {   
            /**
             * Присваивание способа оплаты в платежу
             * 
             * Ошибка PH-02: Не валидные аргументы платежа или уже выбран способ оплаты
             */
             $this->setPaymentMethod($payment, $paymentSystem); 
        }

        return $payment;
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
         * Ошибка PH-03: Переданные данные платежа не являются массивами [method: createPaymentWithItems]
         */
        if (!is_array($data) || !is_array($items)) {
            throw new PayException('При выполнении операции произошла ошибка с кодом PH-03');
        }

        $payment = new PaymentModel;
        $payment->fill($data);

        Event::fire('keerill.pay.beforeCreatePayment', [$this, $payment, $data, $items]);

        $this->addTimeCancelled($payment, 15);
        $payment->save();

        Event::fire('keerill.pay.afterCreatePayment', [$this, $payment, $data, $items]);

        $this->addItemsToPayment($payment, $items, $throwException);

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
    public function addItemsToPayment(PaymentModel $payment, array $items, $throwException = true)
    {
        if (!count($items)) {
            throw new ApplicationException('Items is reqiured');
        }

        $filteredItems = $this->filterAvailabilityItems($items, $throwException);

        foreach ($filteredItems as $item => $params) {
            if (!$itemClass = PaymentManager::findPaymentItemByAlias(array_get($params, 'nameItem', false))) {
                if ($throwException) {
                    throw new PayException(
                        sprintf(
                            'Предмет [%s] не найден',
                            array_get($params, 'nameItem', false)
                        )
                    );
                }
                
                continue;
            }

            $newItem = new PaymentItem;
            $newItem->applyCustomClass($itemClass->class);
            $newItem->fill($params);

            $payment->items()->add($newItem);
        }

        $payment->getPay(true);
    }

    /** 
     * Проверка на доступность добавляемых предметов
     * 
     * @param array $items Предметы
     * @param bool Вызывать исключение, если предмет не найден
     * @return array Доступные предметы
     */
    public function filterAvailabilityItems(array $items, $throwException = true)
    {
        if (!count($items)) {
            throw new ApplicationException('Items is reqiured');
        }

        $filteredItems = [];

        foreach ($items as $item => $params) {
            if (!$itemClass = PaymentManager::findPaymentItemByAlias(array_get($params, 'nameItem', false))) {
                if ($throwException) {
                    throw new PayException(
                        sprintf(
                            'Предмет [%s] не найден',
                            array_get($params, 'nameItem', false)
                        )
                    );
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
            throw new PayException('При выполнении операции произошла ошибка с кодом PH-04');
        }

        try {
            $payment->payment = $paymentSystem;
            $payment->cancelled_at = null;

            if ($paymentHandler = $paymentSystem->getPaymentHandler()) {
                $paymentHandler->addNewPaymentToPaymentSystem($payment);
            }
            
            if ($paymentSystem->hasUseTimeout()) {
                $this->addTimeCancelled($payment, $paymentSystem->getTimeout());
            }
    
            $payment->save();
            
            $paymentSystem->fireEvent('keerill.pay.changePaymentSystem', [$payment]);

            Event::fire('keerill.pay.newPaymentToSystem', [$payment, $paymentSystem]);
        }
        catch (PayException $ex) {
            $this->errorPayment($payment, sprintf(
                'Произошла ошибка при создании платежа: %s', 
                $ex->getMessage()
            ));
            throw new ApplicationException('Создание платежа завершилось ошибкой. Пожалуйста, обратитесь к администратору');
        }
        catch (\Exception $ex) {
            $this->errorPaymentByException($payment, 'Произошла ошибка при создании платежа', $ex);
            throw new ApplicationException('Создание платежа завершилось ошибкой. Пожалуйста, обратитесь к администратору');
        }
    }

    /**
     * Добавление времени для автоотклонения
     * 
     * @param PaymentModel Модель платежа
     * @param integer Время, в минутах
     * @return void
     */
    public function addTimeCancelled(PaymentModel $payment, $minutes)
    {
        if (intval($minutes) <= 0) {
            return;
        }

        if (!$payment->cancelled_at) {
            $payment->cancelled_at = new Carbon;
        }

        $cancel = clone $payment->cancelled_at;
        $cancel->addMinutes(intval($minutes));

        $payment->cancelled_at = $cancel;
    }

    /*
     *
     * Изменения статуса платежа
     * 
     */

    /**
     * Подтверждение платежа
     * 
     * @param PaymentModel Модель платежа
     * @param array Входные данные
     * @return void
     */
    public function paymentSetSuccessStatus(PaymentModel $payment, array $requestData = [])
    {
        if ($payment->pay <= 0) {
            throw new PayException('Некорректная сумма, сумма должна быть больше 0');
        }
        
        try {
            if (!$payment->hasActive()) {
                throw new PayException('Платеж уже подтвержден и невозможно сделать это ещё раз');
            }

            Event::fire('keerill.pay.beforeSuccessStatus', [$this]);

            $payment->paid_at = $payment->freshTimestamp();
            $payment->cancelled_at = null;
            $payment->changeStatusPayment(PaymentModel::PAYMENT_SUCCESS);
            $payment->save();

            Event::fire('keerill.pay.afterSuccessStatus', [$payment]);
        } 
        catch (PayException $ex) {
            $this->errorPayment($payment, sprintf(
                'Произошла ошибка при подтверждении платежа: %s', 
                $ex->getMessage()
            ));
            throw $ex;   
        }
        catch (\Exception $ex) {
            $this->errorPaymentByException($payment, 'Произошла ошибка при подтверждении платежа', $ex);
            throw $ex;
        }

        PaymentLog::add($payment, [
            'message' => 'Платеж был успешно подтвержден',
            'code' => 'success',
            'request_data' => $requestData
        ], BackendAuth::getUser());
    }

    /**
     * Отклонение платежа
     * 
     * @param PaymentModel $payment Модель платежа
     * @param string $message Причина отказа платежа
     * @return void
     */
    public function paymentSetCancelledStatus(PaymentModel $payment, $message = '')
    {
        $message = ($message) ?: 'Причина не указана';

        try {
            Event::fire('keerill.pay.beforeCancelledStatus', [$payment]);

            $payment->message = $message;
            $payment->cancelled_at = null;
            $payment->changeStatusPayment(PaymentModel::PAYMENT_CANCEL);
            $payment->save();

            Event::fire('keerill.pay.afterCancelledStatus', [$payment]);

        } 
        catch (PayException $ex) {
            $this->errorPayment($payment, sprintf(
                'Произошла ошибка при отклонении платежа: %s', 
                $ex->getMessage()
            ));
            throw $ex;
        }
        catch (\Exception $ex) {
            $this->errorPaymentByException($payment, 'Произошла ошибка при отклонении платежа', $ex);
            throw $ex;
        }

        PaymentLog::add($payment, [
            'message' => sprintf('Платеж был успешно отклонен по причине: %s', $message),
            'code' => 'cancel'
        ], BackendAuth::getUser());
    }

    /**
     * Принудительное пересчитывание суммы платежа
     * 
     * @param PaymentModel $payment Модель платежа
     * @return void
     */
    public function paymentUpdatePay(PaymentModel $payment)
    {
        if (!$payment->hasActive()) {
            throw new ApplicationException('Платеж должен быть активным, чтобы пересчитать сумму');
        }

        Event::fire('keerill.pay.beforeUpdatePay', [$payment]);

        $payment->pay = 0;
        
        if (!$items = $payment->items) {
            throw new ApplicationException('В платеже отсутствуют предметы для пересчитывании суммы');
        }

        foreach ($items as $item) {
            $payment->pay += $item->getTotalPrice();
        }

        $payment->save();

        Event::fire('keerill.pay.afterUpdatePay', [$payment]);

        PaymentLog::add($payment, [
            'message' => 'Было произведено пересчитывание суммы платежа',
            'code' => 'update_pay'
        ], BackendAuth::getUser());
    }

    /**
     * Поставить статус ошибки платежу
     * 
     * @param PaymentModel Платеж
     * @param string Сообщение ошибки
     * @return void
     */
    protected function errorPayment(PaymentModel $payment, $message = "")
    {
        if ($payment->hasError()) {
            return;
        }

        $message = $message ?: 'Ошибка содержания PH-05';

        Event::fire('keerill.pay.paymentError', [$payment, $message]);

        PaymentLog::add($payment, [
            'message' => $message,
            'code' => 'error'
        ], BackendAuth::getUser());

        $payment->cancelled_at = null;
        $payment->changeStatusPayment(PaymentModel::PAYMENT_ERROR);
        $payment->save();
    }

    /**
     * Установка статуса ошибки по исключению
     * 
     * @param PaymentModel Модель платежа
     * @param string Сообщение
     * @return void
     */
    public function errorPaymentByException(PaymentModel $payment, $message, $ex) 
    {
        $this->errorPayment(
            $payment,
            sprintf(
                '%s: %s Файл: %s Строка: %s',
                $message,
                $ex->getMessage(),
                $ex->getFile(),
                $ex->getLine()
            )
        );
    }
}