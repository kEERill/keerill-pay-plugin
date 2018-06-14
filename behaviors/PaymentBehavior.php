<?php namespace KEERill\Pay\Behaviors;

use Model;
use System\Classes\ModelBehavior;

Class PaymentBehavior extends ModelBehavior 
{
    /**
     * @var mixed Модель
     */
    protected $model;

    public function __construct($model = null)
    {
        parent::__construct($model);

        if (!$model) {
            return;
        }

        $this->model = $model;
        $this->boot();
    }

    /**
     * Уникальный код платежного шлюза
     * @return string
     */
    public function getAlias()
    {
        return 'default';
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
     * Инициализация модели
     */
    public function boot()
    {
        if (!$this->model->rules) {
            $this->model->rules = [];
        }

        $this->model->rules = array_merge($this->model->rules, $this->defineValidationRules());

        $this->model->bindEvent('model.beforeSave', function() {
            $this->model->options = $this->getSavedParams();
        });

        $this->model->bindEvent('model.afterSave', function() {
            $this->model->attributes = array_merge($this->getFilteredParams(), $this->model->attributes);
        });
    }
}