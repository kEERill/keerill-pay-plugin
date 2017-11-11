<?php namespace KEERill\Pay\Payments\Items;

use KEERill\Pay\Behaviors\PaymentItem;

Class Test1 extends PaymentItem {

    public static function paymentItemDetails()
    {
        return [ 
            'name' => 'Тестовый тип предмета1',
            'description' => 'Тут всегда будет описание данного предмета1'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getCodeItem()
    {
        return 'test2_code';
    }

    public function extendFormWidget($widget) 
    {
        parent::extendFormWidget($widget);

        $widget->removeField('price');
    }
} 