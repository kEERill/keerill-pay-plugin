<?php namespace KEERill\Pay\Models;

use Str;
use Event;
use Model;
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
     * Добавление новых заполняемых полей
     * 
     * @param array Поля, которые нужно добавить
     */
    public function addFillableFields($fields = [])
    {
        return $this->fillable = array_merge($this->fillable, $fields);
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
            if (!$className = $this->payment->getPaymentClass()) {
                return;
            }
            $this->applyPaymentClass($className);
            $this->save();
        }
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
        if (!$this->checkPaymentClass()) {
            return;
        }
    }

    /**
     * Вызывается после заполнении модели данными, здесь мы наследуем класс платежной системы
     * Также в атрибуты полей добавляются значения параметров платежной системы
     * 
     * @return void
     */
    public function afterFetch()
    {
        $this->applyPaymentClass();
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
     * Проверка на существования класса платежного шлюза
     * 
     * @return mixed
     */
    public function checkPaymentClass($class = false)
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

        return Str::normalizeClassName($class);
    }

    /**
     * Наследование класса платежного шлюза
     *
     * @param string Класс
     * @return void
     */
    public function applyPaymentClass($class = false)
    {
        if (!$class = $this->checkPaymentClass($class)) {
            return false;
        }

        if (!$this->isClassExtendedWith($class)) {
            $this->extendClassWith($class);
        }

        $this->class_name = $class;
        $this->attributes = array_merge($this->getFilteredParams(), $this->attributes);

        return true;
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
