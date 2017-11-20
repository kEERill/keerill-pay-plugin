<?php namespace KEERill\Pay\Behaviors;

use System\Classes\ModelBehavior;

Class Gateway extends ModelBehavior {

    use \System\Traits\ConfigMaker;

    /**
     * @var array Новые поля для платежного шлюза
     */
    private $configFields;

    /**
     * @var array Поля для платежа
     */
    private $configPayFields;

    /**
     * @var \KEERill\Pay\Models\PaymentSystem
     */
    protected $model;

    /**
     * Основная информация платежного шлюза
     *
     * @return array
     */
    public static function gatewayDetails()
    {
        return [
            'name' => 'None',
            'description' => 'None'
        ];
    }

    public function __construct($model = null)
    {
        parent::__construct($model);

        $this->configPath = $this->guessConfigPathFrom($this);
        $this->configFields = $this->makeConfig($this->defineFromFields());
        $this->configPayFields = $this->makeConfig($this->definePayFields());

        if (!$model) {
            return;
        }

        $this->model = $model;

        $this->boot();
    }

    /**
     * Правила валидации
     *
     * @return array
     */
    public function defineValidationRules()
    {
        return [];
    }

    /**
     * Поля для платежного шлюза
     *
     * @return string
     */
    public function defineFromFields()
    {
        return 'fields.yaml';
    }

    /**
     * Поля для платежного шлюза
     *
     * @return string
     */
    public function definePayFields()
    {
        return 'pay.yaml';
    }

    /**
     * Получение новых полей, предусмотренной платежной системой
     * @return array Fields
     */
    public function getFieldConfig()
    {
        return $this->configFields;
    }

    /**
     * Получение новых полей, предусмотренной платежной системой
     * @return array Fields
     */
    public function getPayFieldConfig()
    {
        return $this->configPayFields;
    }

    /**
     * Render setup help
     * @return string
     */
    public function getPartialPath()
    {
        return $this->configPath;
    }

    /**
     * Инициализация платежного шлюза, с привязкой модели
     *
     * @param $model \KEERill\Pay\Models\PaymentSystem
     */
    public function boot()
    {
        if (!$this->model->exists) {
            $this->initConfigData($this->model);
        }

        if (!$this->model->rules) {
            $this->model->rules = [];
        }

        $this->model->rules = array_merge($this->model->rules, $this->defineValidationRules());

        $this->model->bindEvent('keerill.pay.extendCreatePayment', function($payment) {
            $this->extendCreateNewPayment($payment);
        });
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
     * Инициализация параметров шлюза
     *
     * @param $model \KEERill\Pay\Models\PaymentSystem
     */
    public function initConfigData($model) {}

    /**
     * Вызывается, когда присваивается платеж к данной платежной системе
     * 
     * @param KEERill\Pay\Models\Payment
     * @return void
     */
    public function extendCreateNewPayment($payment) {}
}