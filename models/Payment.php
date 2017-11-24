<?php namespace KEERill\Pay\Models;

use Event;
use Model;
use BackendAuth;
use ApplicationException;
use Doctrine\DBAL\Query\QueryBuilder;
use KEERill\Pay\Exceptions\PayException;

/**
 * Payment Model
 */
class Payment extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var Константы для статуса платежа
     */
    const PAYMENT_NEW = '1';
    const PAYMENT_WAIT = '2';
    const PAYMENT_SUCCESS = '3';
    const PAYMENT_CANCEL = '4';
    const PAYMENT_ERROR = '5';

    /**
     * @var string The database table used by the model.
     */
    public $table = 'oc_payments';

    /**
     * @var array Jsonable fields
     */
    public $jsonable = ['options'];

    /**
     * @var string Правила валидации
     */
    public $rules = [
        'payment_id' => 'integer|exists:oc_payment_systems,id'
    ];

    /**
     * @var array Кастомные атрибуты
     */
    public $attributeNames = [
        'payment' => 'Платежная система',
        'status' => 'Статус платежа',
        'pay' => 'Сумма пополнения'
    ];

    /**
     * @var array Guarded  fields
     */
    protected $guarded = [
        'pay_method',
        'updated_at',
        'created_at',
        'options',
        'paid_at',
        'hash',
        'pay'
    ];


    /**
     * @var array Relations
     */
    public $belongsTo = [
        'payment' => [
            'KEERill\Pay\Models\PaymentSystem',
            'key' => 'pay_method'
        ]
    ];

    public $hasMany = [
        'logs' => [
            'KEERill\Pay\Models\PaymentLog',
            'delete' => true
        ],
        'items' => [
            'KEERill\Pay\Models\PaymentItem',
            'delete' => true
        ]
    ];

    /**
     * @var array Даты
     */
    public $dates = ['paid_at', 'created_at', 'updated_at'];

    /**
     * @var array Статусы платежа
     */
    private $typeMessages = [
        self::PAYMENT_NEW => 'Платеж открыт',
        self::PAYMENT_WAIT => 'Платеж обрабатывается',
        self::PAYMENT_SUCCESS => 'Платеж принят',
        self::PAYMENT_CANCEL => 'Платеж отклонен',
        self::PAYMENT_ERROR => 'Ошибка платежа'
    ];

    /**
     * Добавление новых заполняемых полей
     * 
     * @param array Поля, которые нужно добавить
     */
    public function addFillableFields($fields = [])
    {
        return $this->fillable = array_merge($this->fillable, $fields);
    }

    /**
     * Получение локализованного текста статуса платежа
     * 
     * @return string
     */
    public function getLocalizationStatus()
    {
        return array_get($this->typeMessages, $this->status);
    }

    /**
     * Получение списка статусов платежа
     * 
     * @return array
     */
    public function getPaymentStatuses()
    {
        return $this->typeMessages;
    }

    /**
     * Получение количество активных платежей
     * 
     * @return integer
     */
    public static function getOpenedCount()
    {
        return self::opened()->count();
    }

    /**
     * Проверка, выполнен ли платеж или нет
     * 
     * @return bool
     */
    public function hasSuccess()
    {
        return $this->status == self::PAYMENT_SUCCESS;
    }

    /**
     * Проверка, активен ли платеж или нет
     * 
     * @return bool
     */
    public function hasActive()
    {
        return $this->status == self::PAYMENT_NEW || $this->status == self::PAYMENT_WAIT;
    }

    /**
     * Проверка, открыт ли платеж или нет
     * 
     * @return bool
     */
    public function hasOpen()
    {
        return $this->status == self::PAYMENT_NEW;
    }

    /**
     * Проверка, выполнение платежа завершилося ошибкой
     * 
     * @return bool
     */
    public function hasError()
    {
        return $this->status == self::PAYMENT_ERROR;
    }

    /**
     * Проверка, не отменён ли платеж
     * 
     * @return bool
     */
    public function hasCancel()
    {
        return $this->status == self::PAYMENT_CANCEL;
    }

    /**
     * Вызывается для сохранения модели. После того, как у модели появиться способ оплаты,
     * статус платежа изменяется
     * 
     * @return void
     */
    public function beforeSave()
    {
        if ($this->payment && !$this->status) {
            $this->status = self::PAYMENT_WAIT;

            $this->items->each(function($item) {
                $item->changeStatusPayment($this);
            });
        }
    }

    /**
     * Генерация хэша перед созданием платежа
     * 
     * @return void
     */
    public function beforeCreate()
    {
        $this->generateHash();
    }

    /**
     * Смена статуса платежа, запуск обновления предметов платежа
     * 
     * @param integer Новый статус
     * @return void
     */
    public function changeStatusPayment($newStatus)
    {
        try {
            $this->status = $newStatus;
            $this->items->each(function($item) {
                $item->changeStatusPayment($this);
            });
        } 
        catch(PayException $ex) {
            $this->status = self::PAYMENT_ERROR;
            $this->save();

            throw $ex;
        }
    }
    
    /**
     * Подтверждение платежа
     * 
     * @return void
     */
    public function paymentSetSuccessStatus($requestData = [])
    {
        try {
            if (!$this->hasActive()) {
                throw new ApplicationException('Платеж уже подтвержден и невозможно сделать это ещё раз');
            }

            if ($this->pay <= 0) {
                throw new ApplicationException('Некорректная сумма, сумма должна быть больше 0');
            }

            Event::fire('keerill.pay.beforeSuccessStatus', [$this]);

            $this->paid_at = $this->freshTimestamp();

            $this->changeStatusPayment(self::PAYMENT_SUCCESS);

            $this->save();

            Event::fire('keerill.pay.afterSuccessStatus', [$this]);

        } catch(PayException $ex) {
            PaymentLog::add(
                $this, 
                sprintf(
                    'Произошла ошибка при подтверждении платежа: %s', 
                    $ex->getMessage()
                ), 
                'error' , 
                $requestData + $ex->getParams(), 
                BackendAuth::getUser()
            );

            $this->status = self::PAYMENT_ERROR;
            $this->paid_at = null;
            $this->save();

            throw $ex;
        }

        PaymentLog::add($this, 'Платеж был успешно подтвержден', 'success' , $requestData, BackendAuth::getUser());
    }

    /**
     * Отклонение платежа
     * 
     * @param string $message Причина отказа платежа
     * @return void
     */
    public function paymentSetCancelledStatus($message = '')
    {
        $message = ($message)?: 'Причина не указана';

        try {

            if (!$this->hasActive()) {
                throw new ApplicationException('Платеж уже подтвержден и невозможно отклонить его');
            }

            Event::fire('keerill.pay.beforeCancelledStatus', [$this]);

            $this->status = self::PAYMENT_CANCEL;
            $this->message = $message;

            $this->items->each(function($item) {
                $item->changeStatusPayment($this);
            });

            $this->save();

            Event::fire('keerill.pay.afterCancelledStatus', [$this]);

        } catch(PayException $ex) {
            PaymentLog::add(
                $this, 
                sprintf(
                    'Произошла ошибка при отклонении платежа: %s', 
                    $ex->getMessage()
                ), 
                'error' , 
                [], 
                BackendAuth::getUser()
            );

            $this->status = self::PAYMENT_ERROR;
            $this->save();

            throw $ex;
        }

        PaymentLog::add($this, sprintf('Платеж был успешно отклонен по причине: %s', $message), 'cancel' , [], BackendAuth::getUser());
    }
    
    /**
     * Принудительное пересчитывание суммы платежа
     * 
     * @return void
     */
    public function paymentUpdatePay()
    {
        try {
            Event::fire('keerill.pay.beforeUpdatePay', [$this]);

            if (!$this->hasActive()) {
                throw new ApplicationException('Платеж должен быть открыть, чтобы пересчитать сумму');
            }

            $this->pay = 0;
            
            $this->items->each(function($item) {
                $this->pay += $item->price;
            });

            $this->save();

            Event::fire('keerill.pay.afterUpdatePay', [$this]);

        } catch(PayException $ex) {
            PaymentLog::add($this, $ex->getMessage(), 'error' , [], BackendAuth::getUser());

            throw $ex;
        }

        PaymentLog::add($this, 'Было произведено пересчитывание суммы платежа', 'update_success' , [], BackendAuth::getUser());   
    }

    /**
     * Internal helper, and set generate a unique hash for this invoice.
     * @return string
     */
    protected function generateHash()
    {
        $this->hash = $this->createHash();
        while ($this->newQuery()->where('hash', $this->hash)->count() > 0) {
            $this->hash = $this->createHash();
        }
    }

    /**
     * Internal helper, create a hash for this invoice.
     * @return string
     */
    protected function createHash()
    {
        if (class_exists('\Ramsey\Uuid\Uuid')) {
            return \Ramsey\Uuid\Uuid::uuid1();
        }
        
        return md5(uniqid('Payment', microtime()));
    }

    /**
     * Фильтрация платежей
     * 
     * @param $query QueryBuilder
     * @param $filter integer
     * @return mixed
     */
    public function scopeFilterByPayment($query, $filter)
    {
        return $query->whereHas('payment', function($pay) use ($filter) {
            $pay->whereIn('id', $filter);
        });
    }

    /**
     * @param $query QueryBuilder
     */
    public function scopeOpened($query)
    {
        $query->where('status', self::PAYMENT_NEW)->orWhere('status', self::PAYMENT_WAIT);
    }

    /**
     * @param $query QueryBuilder
     */
    public function scopeSuccess($query)
    {
        $query->where('status', self::PAYMENT_SUCCESS);
    }

    /**
     * Изменение данных платежа
     * 
     * @param {array} Массив параметров ['param_name' => 'param_value', ...]
     * @return bool
     */
    public function setParam(array $data, $replace = true)
    {
        if (!is_array($data)) {
            return false;
        }

        $params = $this->options;

        foreach ($data as $key => $value) {
            if (!$replace && $this->getParam($key)) {
                continue;
            }

            $params[$key] = $value;
        }

        $this->options = $params;

        return false;
    }

    /**
     * Функция получения параметров платежа
     * 
     * @param string Название параметра
     * @return string Значение параметра $name
     */
    public function getParam($name = null) {

        if (!$name) {
            return false;
        }
        if (!$this->options) {
            $this->options = [];
        }

        return array_get($this->options, $name);
    }
}
