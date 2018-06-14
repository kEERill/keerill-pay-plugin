<?php namespace KEERill\Pay\Models;

use Str;
use Model;

/**
 * PaymentItem Model
 */
class PaymentItem extends Model
{
    use \KEERill\Pay\Traits\ClassExtendable;
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
        'description',
        'options',
        'quantity',
        'price'
    ];

    public $jsonable = ['options'];

    public $rules = [
        'class_name' => 'required',
        'quantity' => 'required|integer|min:1',
        'price' => 'required|numeric|min:0'
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
     * Получение общей суммы предмета
     * 
     * @return integer
     */
    public function getTotalPrice()
    {
        return $this->price * $this->quantity;
    }

    /**
     * Добавление новых заполняемых полей, в зависимости от класса
     * 
     * @param array Поля, которые нужно добавить
     */
    public function addFillableFields($fields = [])
    {
        return $this->fillable = array_merge($this->fillable, $fields);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCreate()
    {
        $this->code = $this->getCodeItem();
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
        if (!array_get($this->attributes, 'description')) {
            $this->description = $this->getMessageItem();
        }

        $this->total_price = $this->getTotalPrice();

        if ($this->payment) {
            $this->payment->pay += floatval($this->total_price - array_get($this->original, 'total_price', 0));
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
            $this->payment->pay -= $this->total_price;
            $this->payment->forceSave();
        }

        return parent::delete();
    }
}
