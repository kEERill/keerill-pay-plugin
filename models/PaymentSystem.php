<?php namespace KEERill\Pay\Models;

use Str;
use Model;
use PaymentManager;
use KEERill\Pay\Classes\PaymentHandler;
use KEERill\Pay\Exceptions\PayException;

/**
 * PaymentSystem Model
 */
class PaymentSystem extends Model
{
    use \KEERill\Pay\Traits\ClassExtendable;
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
        'pay_timeout' => 'required|integer|min:0'
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
            \KEERill\Pay\Models\Payment::class,
            'key' => 'id',
            'other_key' => 'pay_method'
        ]
    ];

    /**
     * @var array
     */
    protected $partialToRender = [];

    /**
     * @var PaymentHandler Платежный шлюз
     */
    protected $paymentHandler = false;

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
     * Доступные фрагменты
     *
     * @return array
     */
    public function getPartialNameOptions()
    {
        $partials = \Cms\Classes\Partial::all();

        $partials->each(function($partial) {
            $this->partialToRender[$partial->baseFileName] = sprintf('%s [%s]', $partial->description, $partial->baseFileName);
        });

        return ['' => 'Не использовать шаблон'] + $this->partialToRender;
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
     * Получить количество минут для автоотклонения платежа
     * 
     * @return integer
     */
    public function getTimeout()
    {
        return $this->pay_timeout;
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

        if ($this->methodExists('getAlias')) {
            if ($this->paymentHandler = PaymentManager::findPaymentHandlerByAlias($this->getAlias())) {
                $this->paymentHandler->setPaymentSystemModelByModel($this);
                return $this->paymentHandler;
            }
        }
        
        return null;
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
     * Проверка на испольнование автоотклонения
     * 
     * @return bool
     */
    public function hasUseTimeout()
    {
        return $this->pay_timeout > 0;
    }

    /**
     * Присваиваем класс модели в зависимости от платежного шлюза
     * 
     * @param PaymentHandler Платежный шлюз
     * @return void
     */
    public function applyCustomClassByPaymentHandler(PaymentHandler $paymentHandler)
    {
        if (method_exists($this, 'applyCustomClass') && $paymentHandler->getPaymentSystemClass()) {
            $this->applyCustomClass($paymentHandler->getPaymentSystemClass());
        }

        $this->gateway_name = array_get($paymentHandler->getPaymentHandlerDetails(), 'name');
    }

    /*
     *
     * Scopes
     * 
     */

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
