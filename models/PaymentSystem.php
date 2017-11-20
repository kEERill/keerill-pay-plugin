<?php namespace KEERill\Pay\Models;

use Model;
use KEERill\Pay\Exceptions\PayException;

/**
 * PaymentSystem Model
 */
class PaymentSystem extends Model
{
    use \October\Rain\Database\Traits\Validation;
    
    /**
     * @var string The database table used by the model.
     */
    public $table = 'oc_payment_systems';

    /**
     * @var string Правила валидации
     */
    public $rules = [
        'name' => 'required',
        'code' => 'required|regex:/^[\w]{3,}$/i',
        'min_pay' => 'integer|min: 0'
    ];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['name', 'code', 'is_enable', 'options'];

    /**
     * @var array Jsonable fields
     */
    public $jsonable = ['options'];

    public $hasMany = [
        'payments' => [
            Payment::class,
            'key' => 'id',
            'other_key' => 'pay_method'
        ]
    ];

    /**
     * Хэшированый код платежной системы
     * 
     * @return string Code
     */
    public function getHashCode()
    {
        return base64_encode($this->code . '!' . $this->gateway_name);
    }

    /**
     * Доступные типы оповещений
     *
     * @return array
     */
    public function getPartialNameOptions()
    {
        return ['' => 'Использовать стандартный шаблон'] + \Cms\Classes\Partial::lists('baseFilename', 'baseFilename');
    }

    /**
     * Получение параметров платежного шлюза
     *
     * @return array
     */
    public function getParams()
    {
        return $this->options;
    }

    /**
     * Получение состояния системы
     * 
     * @return bool
     */
    public function hasEnableSystem()
    {
        return $this->is_enable;
    }

    /**
     * Вызывается до сохранения модели
     * Здесь отфильтровываются поля платежного шлюза от полей модели
     * Иначе будет ошибка о не существовании полей
     * 
     * @return void
     */
    public function beforeSave()
    {
        $configData = [];
        $fieldConfig = $this->getFieldConfig();
        $fields = isset($fieldConfig->fields) ? $fieldConfig->fields : [];

        foreach ($fields as $name => $config) {
            if (!array_key_exists($name, $this->attributes)) {
                continue;
            }

            $configData[$name] = $this->attributes[$name];
            unset($this->attributes[$name]);
        }

        $this->options = $configData;
    }

    /**
     * Проверка на существования класса платежного шлюза
     * 
     * @return mixed
     */
    public function checkGatewayClass($class = false)
    {
        if (!$class && !$this->class_name) {
            return false;
        }

        if (!$class) {
            $class = $this->class_name;
        }

        if (!class_exists($class)) {
            return false;
        }

        return $class;
    }

    /**
     * Вызывается после заполнении модели данными, здесь мы наследуем класс платежной системы
     * Также в атрибуты полей добавляются значения параметров платежной системы
     * 
     * @return void
     */
    public function afterFetch()
    {
        $this->applyGatewayClass();

        $this->attributes = array_merge($this->options, $this->attributes);
    }

    /**
     * Наследование класса платежного шлюза
     *
     * @param string Класс
     * @return void
     */
    public function applyGatewayClass($class = false)
    {
        if (!$class = $this->checkGatewayClass($class)) {
            return false;
        }

        if (!$this->isClassExtendedWith($class)) {
            $this->extendClassWith($class);
        }

        $this->class_name = $class;
        $this->gateway_name = array_get($class::gatewayDetails(), 'name', 'Unknown');
    }

    /**
     * Scope активных платежных систем
     * 
     * @return void
     */
    public function scopeHasEnable($query)
    {
        $query->where('is_enable', '1');
    }
}
