<?php namespace KEERill\Pay\Models;

use Model;
use Request;

/**
 * PaymentLog Model
 */
class PaymentLog extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'oc_payment_logs';

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'request_data',
        'message',
        'code'
    ];

    /**
     * @var array Jsonable Fields
     */
    public $jsonable = ['request_data'];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'payment' => [ Payment::class ],
        'user' => [ \Backend\Models\User::class ]
    ];

    public function beforeUpdate()
    {
        // throw new \ApplicationException($this->attributes['user']);
    }
    /**
     * Protects the password from being reset to null.
     */
    public function setUserIdAttribute($value)
    {
        if ($this->exists && empty($value)) {
            unset($this->attributes['user_id']);
        }
        else {
            $this->attributes['user_id'] = $value;
        }
    }

    /**
     * Создание новой записи о действиях над платежом
     * 
     * @param KEERill\Pay\Models\Payment $payment Модель платежа
     * @param array Опции
     * @param Backend\Models\User Модель пользователя
     * @return PaymentLog модель
     */
    public static function add(Payment $payment, array $options, $user = null)
    {
        $record = new static;

        $data = array_merge([
            'message' => 'Параметр `message` не был передан',
            'code' => 'error'
        ], $options);

        $record->payment = $payment;
        $record->ip_address = Request::getClientIp();
        
        $record->fill($data);
        $record->save();

		return $record;
    }
}
