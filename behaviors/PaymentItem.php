<?php namespace KEERill\Pay\Behaviors;

Class PaymentItem extends PaymentBehavior 
{

    /**
     * Информация о предмете платежа
     *
     * @return array
     */
    public static function paymentItemDetails()
    {
        return [
            'name'=> 'Unknown',
            'description' => 'Unknown'
        ];
    } 

    /**
     * Добавление новых заполняемых полей в модель
     * 
     * @return array
     */
    public function defineFillableFields()
    {
        return [];
    }

    /**
     * Получение кода предмета
     * 
     * @return string Code
     */
    public function getCodeItem()
    {
        return 'error_code';
    }

    /**
     * Стандартное описание предмета
     * 
     * @return string Message
     */
    public function getMessageItem()
    {
        return 'Error';
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        parent::boot();
        $this->model->addFillableFields($this->defineFillableFields());
    }

    /**
     * Вызывается котгда платеж меняет статус
     *
     * @param KEERill\Pay\Models\Payment Модель платежа
     * @return bool true если изменение прошло успешно
     */
    public function changeStatusPayment($payment)
    {
        return true;
    }
}