<?php namespace KEERill\Pay\Components;

use Flash;
use Redirect;
use Validator;
use AuthManager;
use PaymentManager;
use ApplicationException;
use Payment as PaymentHelper;
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
            'description' => 'asdasd',
            'user' => AuthManager::getUser()
        ];

        $items = [
            [
                'nameItem' => 'pay_balance',
                'price' => '50'
            ],
            [
                'nameItem' => 'pay_balance',
                'price' => '80'
            ],
        ];

        PaymentHelper::createPaymentWithItemsAndCode('asd', $data, $items);
    }
}
