<?php namespace KEERill\Pay;

use App;
use Log;
use Event;
use Backend;
use System\Classes\PluginBase;
use KEERill\Users\Models\User;
use KEERill\Pay\Models\Payment;
use Illuminate\Foundation\AliasLoader;
use KEERill\Users\Exceptions\PayException;

/**
 * Pay Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Платежные системы',
            'description' => 'Плагин для управления платежами и балансом пользователя',
            'author'      => 'KEERill',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {

        $alias = AliasLoader::getInstance();
        $alias->alias('PaymentManager', 'KEERill\Pay\Facades\PaymentManager');
        $alias->alias('Payment', 'KEERill\Pay\Facades\Payment');

        App::singleton('keerill.pay.paymentmanager', function() {
            return \KEERill\Pay\Classes\PaymentManager::instance();
        });

        Event::listen('backend.menu.extendItems', function ($manager) {
            $openCount = Payment::getOpenedCount();

            if ($openCount) {
                $manager->addSideMenuItems('KEERill.Pay', 'pay', [
                    'payments' => [
                        'counter' => $openCount,
                    ]
                ]);
            }
        });
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        /**
        * Создаем новые связи с пользователем
        *
        * @return array
        */
        User::extend(function($model) {
            $model->hasMany['payment'] = ['KEERill\Pay\Models\Payment', 'delete' => true];
        });
    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        $this->components = [
            'KEERill\Pay\Components\PaymentInfo' => 'pay_payment',
            'KEERill\Pay\Components\Payments' => 'payments',
        ];

        Event::fire('keerill.pay.extendsComponents', [$this]);

        return $this->components;
    }

    /**
     * Добавление нового компонента
     * @param array ['Namespace' => 'name']
     * @return array Components
     */
    public function addComponent($components)
    {
        return $this->components = array_replace($this->components, $components);
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'keerill.pay.payment' => [
                'tab' => 'Платежи',
                'label' => 'Доступ к просмотру списка платежей',
                'order' => 11
            ],
            'keerill.pay.payment.create' => [
                'tab' => 'Платежи',
                'label' => 'Возможность создавать новые платежи',
                'order' => 12
            ],
            'keerill.pay.payment.update_pay' => [
                'tab' => 'Платежи',
                'label' => 'Возможность выполнить пересчет суммы платежа',
                'order' => 13
            ],
            'keerill.pay.payment.confirm' => [
                'tab' => 'Платежи',
                'label' => 'Возможность подтверждать платежи',
                'order' => 14
            ],
            'keerill.pay.payment.cancel' => [
                'tab' => 'Платежи',
                'label' => 'Возможность отклонять платежи',
                'order' => 15
            ],
            'keerill.pay.payment.remove' => [
                'tab' => 'Платежи',
                'label' => 'Возможность удалять платежи',
                'order' => 16
            ],
            'keerill.pay.items.add' => [
                'tab' => 'Платежи',
                'label' => 'Возможность добавлять новые предметы',
                'order' => 17
            ],
            'keerill.pay.items.edit' => [
                'tab' => 'Платежи',
                'label' => 'Возможность редактировать предметы',
                'order' => 18
            ],
            'keerill.pay.items.remove' => [
                'tab' => 'Платежи',
                'label' => 'Возможность удалять предметы',
                'order' => 19
            ],
            'keerill.pay.logs.access' => [
                'tab' => 'Платежи',
                'label' => 'Доступ к просмотру логов платежа',
                'order' => 20
            ],
            'keerill.pay.items.remove' => [
                'tab' => 'Платежи',
                'label' => 'Возможность удалять записи лога платежа',
                'order' => 21
            ],
            'keerill.pay.params.custom' => [
                'tab' => 'Платежи',
                'label' => 'Доступ к просмотру кастомных параметров платежа',
                'order' => 22
            ],
            'keerill.pay.params.base' => [
                'tab' => 'Платежи',
                'label' => 'Доступ к просмотру основных параметров платежа',
                'order' => 23
            ],
            'keerill.pay.payment_system' => [
                'tab' => 'Платежные системы',
                'label' => 'Доступ к просмотру списка платежных систем',
                'order' => 24
            ],
            'keerill.pay.payment_system.create' => [
                'tab' => 'Платежные системы',
                'label' => 'Возможность создавать новые платежные системы',
                'order' => 25
            ],
            'keerill.pay.payment_system.edit' => [
                'tab' => 'Платежные системы',
                'label' => 'Возможность редактировать параметры платежной системы',
                'order' => 26
            ],
            'keerill.pay.payment_system.remove' => [
                'tab' => 'Платежные системы',
                'label' => 'Возможность удалять платежную систему',
                'order' => 27
            ]
        ];
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return [
            'pay' => [
                'label'       => 'Платежи',
                'url'         => Backend::url('keerill/pay/payments'),
                'icon'        => 'icon-exchange',
                'permissions' => ['keerill.pay.*'],
                'order'       => 234,
                'sideMenu' => [
                    'payments' => [
                        'label'       => 'Платежи',
                        'icon'        => 'icon-exchange',
                        'url'         => Backend::url('keerill/pay/payments'),
                        'permissions' => ['keerill.pay.payment']
                    ],
                    'paymentsystems' => [
                        'label'       => 'Платежные системы',
                        'icon'        => 'icon-credit-card',
                        'url'         => Backend::url('keerill/pay/paymentsystems'),
                        'permissions' => ['keerill.pay.payment_systems']
                    ]
                ]   
            ]
        ];
    }

    /** 
     * Регистрация новых платежных шлюзов
     * 
     * @return array
     */
    public function registerPaymentGateways()
    {
        return [
            'KEERill\Pay\Payments\Gateways\Bitcoin' => 'bitcoin'
        ];
    }
}
