<?php namespace KEERill\Pay\Payments\Items;

use KEERill\Pay\Behaviors\PaymentItem;

Class Test extends PaymentItem {

    public static function paymentItemDetails()
    {
        return [ 
            'name' => 'Тестовый тип предмета',
            'description' => 'Тут всегда будет описание данного предмета'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getCodeItem()
    {
        return 'test_code';
    }
} 