<?php namespace KEERill\Pay\Classes;

use ApplicationException;
use KEERill\Pay\Models\Payment as PaymentModel;
use KEERill\Pay\Models\PaymentSystem as PaymentSystemModel;

Class PaymentHandler
{
    use \System\Traits\ConfigMaker;

    /**
     * @var PaymentManager 
     */
    protected $paymentManager = null;

    /**
     * @var PaymentSystemModel Модель платежной системы
     */
    protected $paymentSystemModel = null;

    /**
     * {@inheritdoc}
     */
    public function __cunstruct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;

        $this->configPath = $this->guessConfigPathFrom($this);
    }

    /**
     * Основная информация платежного шлюза
     * @return array
     */
    public function getPaymentHandlerDetails()
    {
        return [
            'name' => 'PaymentHandler Class',
            'description' => 'Default PaymentHandler Class'
        ];
    }

    /**
     * Поля для платежа
     * @return string
     */
    public function defineFromFieldsPayments()
    {
        return 'fields_payment.yaml';
    }

    /**
     * Поля для платежного шлюза
     * @return string
     */
    public function defineFromFieldsPaymentSystem()
    {
        return 'fields_gateway.yaml';
    }


    /**
     * Возвращает название класса для модели платежной системы в базе
     * @return string
     */
    public function getPaymentSystemClass()
    {
        return null;
    }

    /**
     * Возвращает название класса для модели платежа
     * @return string
     */
    public function getPaymentClass()
    {
        return null;
    }

    /**
     * Возращает уникальный код платежного шлюза
     * @return string 
     */
    public function getAlias()
    {
        return 'default';
    }

    /**
     * Получение модели платежа по хэшу
     * 
     * @param string хэш платежа
     * @return KEERill\Pay\Models\Payment Модель платежа 
     */
    protected function getPaymentModelToHash($hash)
    {
        if (!$hash) {
            throw new ApplicationException('Hash is required');
        }

        if (!$payment = PaymentModel::active()->where('hash', $hash)->whereNotNull('hash')->where('pay_method', $this->paymentSystemModel->id)->first()) {
            throw new ApplicationException('Payment is not found');
        }

        return $payment;
    }

    /**
     * Добавляет новые поля для формы платежа
     * 
     * @param \Backend\Classes\Controller Контроллер платежей
     * @param \Backend\Widget\Form Виджет формы
     */
    public function getFormFieldsByPayment($paymentController, $widget)
    {
        if ($configFields = $this->makeConfig($this->defineFromFieldsPayments())) {
            $this->extendFormFields($widget, $configFields);
        }
    }

    /**
     * Добавляет новые поля для формы платежной модели
     * 
     * @param \Backend\Classes\Controller Контроллер платежных систем
     * @param \Backend\Widget\Form Виджет формы
     */
    public function getFormFieldsByPaymentSystem($paymentSystemController, $widget)
    {
        if ($configFields = $this->makeConfig($this->defineFromFieldsPaymentSystem())) {
            $this->extendFormFields($widget, $configFields);
        }
    }

    /**
     * Присваивает модель платежной системы
     * @return PaymentSystemModel Модель платежной системы
     */
    public function setPaymentSystemModelByModel(PaymentSystemModel $paymentSystemModel)
    {
        if ($this->paymentSystemModel != null) {
            return $this->paymentSystemModel;
        }

        return $this->paymentSystemModel = $paymentSystemModel;
    }

    /**
     * Наследование формы виджета для параметров
     * 
     * @param Backend\Widgets\Form $widget виджет формы
     * @param mixed Конфиг для виджета
     * @return void
     */
    public function extendFormFields($widget, $config) 
    {
        if (property_exists($config, 'fields') && count($config->fields) > 0) {
            $widget->addFields($config->fields);
        }

        if (property_exists($config, 'tabs') && count(array_get($config->tabs, 'fields')) > 0) {
            $widget->addFields(array_get($config->tabs, 'fields'), 'primary');
        }

        if (property_exists($config, 'secondaryTabs') && count(array_get($config->secondaryTabs, 'fields')) > 0) {
            $widget->addFields(array_get($config->secondaryTabs, 'fields'), 'secondary');
        }

        if ($widget->model instanceof PaymentSystemModel) {
            if (!$widget->getField('name')->value) {
                $widget->setFormValues([
                    'name' => array_get($this->getPaymentHandlerDetails(), 'name')
                ]);
            }

            if (!$widget->getField('description')->value) {
                $widget->setFormValues([
                    'description' => array_get($this->getPaymentHandlerDetails(), 'description')
                ]);
            }

            if (!$widget->getField('code')->value) {
                $widget->setFormValues([
                    'code' => $this->getAlias()
                ]);
            }
        }
    }

    /**
     * Добавить новый платежа или же подключить уже существующий платеж к платежному шлюзу
     * @return void
     */
    public function addNewPaymentToPaymentSystem(PaymentModel $paymentModel) 
    {
        $paymentModel->applyCustomClassByPaymentHandler($this);
    }

    /**
     * Регистрация методов для API, так же для регистрации методов для работы приема платежей
     * @return array Список методов
     */
    public function registerAccessPoints() 
    {
        return [];
    }

    /**
     * Регистрация AJAX handlers в компоненте Платежа, для воздействия PaymentHandler с пользователем
     * @return array Массив доступных методов
     */
    public function registerAjaxHandlers()
    {
        return [];
    } 
}