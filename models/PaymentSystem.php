<?php namespace KEERill\Pay\Models;

use Str;
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

    /*
     *
     * Events
     * 
     */

    /**
     * Вызывается после заполнении модели данными, здесь мы наследуем класс платежной системы
     * Также в атрибуты полей добавляются значения параметров платежной системы
     * 
     * @return void
     */
    public function afterFetch()
    {
        $this->applyGatewayClass();
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

        return Str::normalizeClassName($class);
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
        $this->attributes = array_merge($this->getFilteredParams(), $this->attributes);
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
