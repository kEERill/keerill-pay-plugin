<?php namespace KEERill\Pay\Behaviors;

use System\Classes\ModelBehavior;

Class PaymentItem extends ModelBehavior {

    use \System\Traits\ConfigMaker;
    
    /**
     * @var array Поля предмета платежа
     */
    private $configFields;

    /**
     * @var \KEERill\Pay\Models\PaymentItem
     */
    protected $model;

    public function __construct($model = null)
    {
        parent::__construct($model);

        $this->configPath = $this->guessConfigPathFrom($this);
        $this->configFields = $this->makeConfig($this->defineFromFields());

        if (!$model) {
            return;
        }

        $this->model = $model;

        /**
         * Запуск класса
         *
         * @return void
         */
        $this->boot();
    }

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
     * Регистрация правил валидации
     *
     * @return array Массив с правилами валидации
     */
    public function defineValidationRules()
    {
        return [];
    }

    /**
     * Регистрация новых полей
     * 
     * @return string
     */
    public function defineFromFields()
    {
        return 'fields.yaml';
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
     * Новые поля для предмета
     *
     * @return Config
     */
    public function getFieldConfig()
    {
        return $this->configFields;
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
     * Инициализация
     *
     * @param $model \KEERill\Pay\Models\PaymentItem
     */
    public function boot()
    {
        if (!$this->model->rules) {
            $this->model->rules = [];
        }

        $this->model->rules = array_merge($this->model->rules, $this->defineValidationRules());
        $this->model->addFillableFields($this->defineFillableFields());
    }

    /**
     * Наследование формы виджета для отображения формы создания и редактирования предмета
     * 
     * @param Backend\Widgets\Form $widget виджет формы
     * @return void
     */
    public function extendFormWidget($widget) 
    {
        $config = $this->getFieldConfig();
        
        if ($config->fields) {
            $widget->addFields($config->fields, 'primary');
        }
    }

    /**
     * Вызывается котгда платеж меняет статус
     *
     * @return bool true если изменение прошло успешно
     */
    public function changeStatusPayment($payment)
    {
        return true;
    }
}