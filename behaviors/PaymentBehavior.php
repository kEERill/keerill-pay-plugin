<?php namespace KEERill\Pay\Behaviors;

use Model;
use System\Classes\ModelBehavior;

Class PaymentBehavior extends ModelBehavior 
{
    use \System\Traits\ConfigMaker;

    /**
     * @var array Новые поля для платежного шлюза
     */
    private $configFields;

    /**
     * @var \KEERill\Pay\Models\PaymentSystem
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
     * Получение новых полей, предусмотренной платежной системой
     * @return array Fields
     */
    public function getFieldConfig()
    {
        return $this->configFields;
    }

    /**
     * Получение параметров, которые используються в платежа
     * Если параметров нет, то верните пустой массив
     * Возращать массив нужно в таком ввиде:
     * 
     *      [
     *          'Название параметра' => 'Стандартное значение параметра'
     *      ]
     * 
     * @return array
     */
    public function getParamsClass()
    {
        return [];
    }

    /**
     * Получение отфильтрованных парамеров платежа
     * Т.е. избавляемся от параметров, которые возможно были вписаны по ошибке
     * и которые не относятся к данному платежу
     * 
     * @return array
     */
    public function getFilteredParams()
    {
        if (!($paymentParams = $this->getParamsClass()) && count($paymentParams) > 0) {
            return [];
        }

        if (!$paymentOptions = $this->model->options) {
            return $paymentParams;
        }

        $filteredParams = [];

        foreach($paymentParams as $param => $default) {
            if ($value = array_get($paymentOptions, $param, false)) {
                $filteredParams[$param] = $value;
                continue;
            }

            $filteredParams[$param] = $default;
        }

        return $filteredParams;
    }


    /**
     * Получение параметров для сохранения в базу данных
     * 
     * @return array Параметры готовые для сохранения
     */
    public function getSavedParams()
    {
        if (!($paymentParams = $this->getParamsClass()) && count($paymentParams) > 0) {
            return [];
        }

        $savedParams = [];

        foreach ($paymentParams as $param => $default) {
            if ($value = array_get($this->model->attributes, $param, false)) {
                $savedParams[$param] = $value;
            }

            unset($this->model->attributes[$param]);
        }

        return $savedParams;
    }

    /**
     * Инициализация параметров
     */
    public function initConfigData() {}

    /**
     * Инициализация модели
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
    }

    /**
     * Наследование формы виджета для параметров
     * 
     * @param Backend\Widgets\Form $widget виджет формы
     * @return void
     */
    public function extendFields($widget) 
    {
        $config = $this->getFieldConfig();
    
        if (property_exists($config, 'fields') && count($config->fields) > 0) {
            $widget->addFields($config->fields);
        }

        if (property_exists($config, 'tabs') && count(array_get($config->tabs, 'fields')) > 0) {
            $widget->addFields(array_get($config->tabs, 'fields'), 'primary');
        }

        if (property_exists($config, 'secondaryTabs') && count(array_get($config->secondaryTabs, 'fields')) > 0) {
            $widget->addFields(array_get($config->secondaryTabs, 'fields'), 'secondary');
        }
    }
}