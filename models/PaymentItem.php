<?php namespace KEERill\Pay\Models;

use Model;

/**
 * PaymentItem Model
 */
class PaymentItem extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'oc_payment_items';

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'payment_id',
        'options',
        'price'
    ];

    public $jsonable = ['options'];

    public $rules = [
        'payment_id' => 'required|exists:oc_payments,id',
        'class_name' => 'required'
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'payment' => [
            'KEERill\Pay\Models\Payment'
        ]
    ];

    /**
     * Добавление новых заполняемых полей, в зависимости от класса
     * 
     * @param array Поля, которые нужно добавить
     */
    public function addFillableFields($fields = [])
    {
        return $this->fillable = array_merge($this->fillable, $fields);
    }

    public function beforeCreate()
    {
        $this->code = $this->getCodeItem();

        if (!array_get($this->attributes, 'description')) {
            $this->description = $this->getMessageItem();
        }
    }

    /**
     * Вызывается до сохранения модели
     * Здесь отфильтровываются поля платежного шлюза от полей модели
     * Иначе будет ошибка о не существовании полей
     * Также идёт перерасчёт суммы платежа
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

        if ($this->payment) {
            $this->payment->pay += floatval(array_get($this->attributes, 'price', 0)) - array_get($this->original, 'price', 0);
            $this->payment->forceSave();
        }
    }

    /**
     * Удаление модели
     * Перед тем как удалить нужно вычесть сумму предмета из суммы платежа
     * 
     * @return mixed
     */
    public function delete()
    {
        if ($this->payment && $this->price > 0) {
            $this->payment->pay -= $this->price;
            $this->payment->forceSave();
        }

        return parent::delete();
    }

    /**
     * Проверка на существование класса предмета
     * 
     * @return mixed
     */
    public function checkPaymentItemClass($class = false)
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
     * Наследование класса взависимости от типа предмета
     *
     * @param class
     * @return bool
     */
    public function applyPaymentItemClass($class = false)
    {
        if (!$class = $this->checkPaymentItemClass($class)) {
            return false;
        }

        if (!$this->isClassExtendedWith($class)) {
            $this->extendClassWith($class);
        }

        $this->class_name = $class;
    }

    /**
     * Вызывается после заполнении модели данными, здесь мы наследуем класс типа предмета
     * Также в атрибуты полей добавляются значения параметров предмета
     * 
     * @return void
     */
    public function afterFetch()
    {
        $this->applyPaymentItemClass();

        $this->attributes = array_merge($this->options, $this->attributes);
    }
}
