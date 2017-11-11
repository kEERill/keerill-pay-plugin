<?php namespace KEERill\Pay\Components;

use Flash;
use Redirect;
use Validator;
use AuthManager;
use PaymentManager;
use ApplicationException;
use Cms\Classes\ComponentBase;

class Payments extends ComponentBase
{

    public function componentDetails()
    {
        return [
            'name'        => 'Платежи',
            'description' => 'Выводит всю информацию о платежах'
        ];
    }

    public function defineProperties()
    {
        return [
            'count' => [
                'title' => 'Количество платежей',
                'default' => '10'
            ]
        ];
    }

    public function onCreatePayment()
    {
        $data = [
            'description' => 'asdasd'
        ];

        $items = [
            'test' => [
                'price' => '50'
            ],
            'test1' => [
                'price' => '80'
            ]
        ];

        PaymentManager::createPaymentWithItemsAndCode('asd', $data, $items);
    }
}
