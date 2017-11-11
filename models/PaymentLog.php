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
        'user_id',
        'payment_id',
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
     * @param string $message Сообщение
     * @param string $code Уникальный код операции
     * @param array $request_data Полученные параметры
     * @param Backend\Models\User Модель пользователя
     * @return PaymentLog модель
     */
    public static function add(Payment $payment, $message, $code, $request_data= [], $user = null)
    {
        $record = new static;
        
        $record->payment = $payment;
        $record->user = $user;

        $record->ip_address = Request::getClientIp();

        $record->message = $message;
        $record->code = $code;

        $record->request_data = $request_data;

        $record->save();

		return $record;
    }
}
