<?php namespace KEERill\Pay\Behaviors;

Class PaymentItem extends PaymentBehavior 
{

    use \System\Traits\ConfigMaker;
    
    /**
     * @var array Поля предмета платежа
     */
    private $configFields;

    public function __construct($model = null)
    {
        parent::__construct($model);

        $this->configPath = $this->guessConfigPathFrom($this);
        $this->configFields = $this->makeConfig($this->defineFromFields());
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
     * Новые поля для предмета
     *
     * @return Config
     */
    public function getFieldConfig()
    {
        return $this->configFields;
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
     * Наследование формы виджета для отображения формы создания и редактирования предмета
     * 
     * @param Backend\Widgets\Form $widget виджет формы
     * @return void
     */
    public function formExtendFields($widget, $fields) 
    {
        $config = $this->getFieldConfig();
        
        if ($config->fields) {
            $widget->addFields($config->fields, 'primary');
        }
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