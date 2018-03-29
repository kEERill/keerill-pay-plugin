<?php namespace KEERill\Pay\Models;

use Str;
use Event;
use Model;
use PaymentManager;
use ApplicationException;
use Doctrine\DBAL\Query\QueryBuilder;
use KEERill\Pay\Classes\PaymentHandler;
use KEERill\Pay\Exceptions\PayException;

/**
 * Payment Model
 */
class Payment extends Model
{
    use \KEERill\Pay\Traits\ClassExtendable;
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
        'payment_id' => 'integer|exists:oc_payment_systems,id',
        'pay' => 'numeric|min:0'
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
     * @var PaymentHandler Платежный шлюз
     */
    protected $paymentHandler = false;

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
            \KEERill\Pay\Models\PaymentSystem::class,
            'key' => 'pay_method'
        ]
    ];

    public $hasMany = [
        'logs' => [
            \KEERill\Pay\Models\PaymentLog::class,
            'delete' => true
        ],
        'items' => [
            \KEERill\Pay\Models\PaymentItem::class,
            'delete' => true
        ]
    ];

    /**
     * @var array Даты
     */
    public $dates = ['cancelled_at', 'paid_at', 'created_at', 'updated_at'];

    /**
     * @var array Статусы платежа
     */
    private static $typeMessages = [
        self::PAYMENT_NEW => 'Платеж открыт',
        self::PAYMENT_WAIT => 'Платеж обрабатывается',
        self::PAYMENT_SUCCESS => 'Платеж принят',
        self::PAYMENT_CANCEL => 'Платеж отклонен',
        self::PAYMENT_ERROR => 'Ошибка платежа'
    ];

    /**
     * Получение списка статусов 
     */
    public static function getStatuses()
    {
        return self::$typeMessages;
    }

    /**
     * Получение списка статусов платежа
     * 
     * @return array
     */
    public function getPaymentStatuses()
    {
        return self::$typeMessages;
    }

    /**
     * Получение локализованного текста статуса платежа
     * 
     * @return string
     */
    public function getLocalizationStatus()
    {
        return array_get($this->getPaymentStatuses(), $this->status);
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
     * Получение суммы платежа
     * 
     * @return integer
     */
    public function getPay($force = false)
    {
        if ($force) {
            $this->pay = 0;
            
            $this->items->each(function ($item) {
                $this->pay += $item->total_price;
            });
        }
        
        return $this->pay;
    }

    /**
     * Получение название партикла для вывода данных определенной
     * платежной системы
     * 
     * @return string
     */
    public function getCustomPartial()
    {
        if (!$this->payment) {
            return false;
        }

        return $this->payment->partial_name;
    }

    /**
     * Возвращает PaymentHandler платежной системы
     * @return PaymentHandler
     */
    public function getPaymentHandler()
    {
        if ($this->paymentHandler) { 
            return $this->paymentHandler;
        }
        
        if ($this->payment) {
            if ($this->paymentHandler = $this->payment->getPaymentHandler()) {
                return $this->paymentHandler;
            }
        }
        
        return null;
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

    /*
     *
     * Events
     * 
     */

    /**
     * Вставка нового класса если моделе была присвоена новая платежная система
     * т.е. новый способ оплаты
     */
    public function afterCreate()
    {
        if (!$this->class_name && $this->payment) {
            if (!$this->paymentHandler = $this->payment->getPaymentHandler()) {
                return;
            }
            $this->applyCustomClassByPaymentHandler($this->paymentHandler);
            $this->save();
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
        $this->status = $newStatus;
        $this->items->each(function($item) {
            try {
                $item->changeStatusPayment($this);
            }
            catch (\Exception $ex) {
                throw new PayException(sprintf('Предмет [%s] %s', $item->code, $ex->getMessage()));
            }
        });
    }

    /**
     * Присваиваем класс модели в зависимости от платежного шлюза
     * 
     * @param PaymentHandler Платежный шлюз
     * @return void
     */
    public function applyCustomClassByPaymentHandler(PaymentHandler $paymentHandler)
    {
        if (method_exists($this, 'applyCustomClass') && $paymentHandler->getPaymentClass()) {
            $this->applyCustomClass($paymentHandler->getPaymentClass());
        }
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
        $query->where('status', self::PAYMENT_NEW);
    }

    public function scopeActiveOrOpen($query)
    {
        $query->where(function ($query) {
            $query->where('status', self::PAYMENT_NEW)
                  ->orWhere('status', self::PAYMENT_WAIT);
        });
    }
    
    /**
     * @param $query QueryBuilder
     */
    public function scopeActive($query)
    {
        $query->Where('status', self::PAYMENT_WAIT);
    }

    /**
     * @param $query QueryBuilder
     */
    public function scopeSuccess($query)
    {
        $query->where('status', self::PAYMENT_SUCCESS);
    }
}
