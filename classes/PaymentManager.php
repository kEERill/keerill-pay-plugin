<?php namespace KEERill\Pay\Classes;

use Str;
use Event;
use Response;
use KEERill\Pay\Models\Payment;
use System\Classes\PluginManager;
use KEERill\Pay\Models\PaymentItem;
use October\Rain\Support\Collection;
use KEERill\Pay\Models\PaymentSystem;
use KEERill\Pay\Exceptions\PayException;

Class PaymentManager
{
    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var array Кэш платежных шлюзов зарегистрированных в системе
     */
    private $cachePaymentHandlers;

    /**
     * @var Collection Cache colletion
     */
    private $gatewaysCollection;

    /**
     * @var array Cache payment types
     */
    private $paymentItems;

    /**
     * @var Collection Cache colletion
     */
     private $paymentItemsCollection;
    
    /**
     * @var \System\Classes\PluginManager Plugin Manager
     */
    protected $pluginManager;

    /**
     * Initialize this singleton.
     */
    protected function init()
    {
        $this->pluginManager = PluginManager::instance();
    }

    /**
     * Регистрация платежных шлюзов
     *
     * @param string $owner ID плагина Author.Plugin.
     * @param array $classes Классы платежных шлюзов.
     */
    public function registerPaymentHandlers($owner, array $classes)
    {
        if (!$this->cachePaymentHandlers)
            $this->cachePaymentHandlers = new Collection([]);

        foreach ($classes as $class) {
            $className = Str::normalizeClassName($class);
            $paymentHandler = new $className($this);

            $this->cachePaymentHandlers->put($paymentHandler->getAlias(), $paymentHandler);
        }
    }

    /**
     * Регистрация платежных типов
     *
     * @param string $owner ID плагина Author.Plugin.
     * @param array $classes Классы платежных шлюзов.
     */
    public function registerPaymentItems($owner, array $classes)
    {
        if (!$this->paymentItems)
            $this->paymentItems = [];

        foreach ($classes as $class => $alias) {
            $type = (object)[
                'owner' => $owner,
                'class' => $class,
                'alias' => $alias,
            ];

            $this->paymentItems[$alias] = $type;
        }
    }

    /**
     * Получение и регистрация платежных шлюзов, через плагины
     * @return void
     */
    protected function loadPaymentHandlers()
    {
        $plugins = $this->pluginManager->getPlugins();

        foreach ($plugins as $id => $plugin) {
            if (!method_exists($plugin, 'registerPaymentHandlers'))
                continue;

            $paymentHandlers = $plugin->registerPaymentHandlers();
            if (!is_array($paymentHandlers))
                continue;

            $this->registerPaymentHandlers($id, $paymentHandlers);
        }
    }

    /**
     * Получение и регистрация платежных шлюзов, через плагины
     *
     * @return void
     */
    protected function loadPaymentItems()
    {
        /*
        * Load plugin items
        */
        $plugins = $this->pluginManager->getPlugins();

        foreach ($plugins as $id => $plugin) {
            if (!method_exists($plugin, 'registerPaymentItems'))
                continue;

            $items = $plugin->registerPaymentItems();
            if (!is_array($items))
                continue;

            $this->registerPaymentItems($id, $items);
        }
    }

    /**
     * Возвращает список платежных шлюзов
     *
     * @param boolean $asObject Если true то возвращаяет, объект платежных щлюзов
     * @return mixed
     */
    public function getPaymentHandlers()
    {
        if ($this->cachePaymentHandlers === null) {
            $this->loadPaymentHandlers();
        }

        return $this->cachePaymentHandlers;
    }

    /**
     * Получение списка типов платежей
     *
     * @return array
     */
    public function getPaymentItems($getCollection = true)
    {
        if (!$this->paymentItems) {
            $this->loadPaymentItems();
        }

        if (!$getCollection) {
            return $this->paymentItems;
        }
        
        if ($this->paymentItemsCollection) {
            return $this->paymentItemsCollection;
        }

        /*
         * Enrich the collection with gateway objects
         */
        $collection = [];

        if (!is_array($this->paymentItems)) {
            return new Collection($collection);
        }

        foreach ($this->paymentItems as $item) {
            if (!class_exists($item->class))
                continue;

            $class =  $item->class;
            $itemDetails = $class::paymentItemDetails();

            $collection[$item->alias] = (object)[
                'owner'       => $item->owner,
                'class'       => $item->class,
                'alias'       => $item->alias,
                'name'        => array_get($itemDetails, 'name', 'Undefined'),
                'description' => array_get($itemDetails, 'description', 'Undefined'),
            ];
        }

        return $this->paymentItemsCollection = new Collection($collection);

    }

    /**
     * Returns a gateway based on its unique alias.
     */
    public function findPaymentHandlerByAlias($alias)
    {
        if (!$paymentHandlers = $this->getPaymentHandlers()) {
            return false;
        }

        return $paymentHandlers->get($alias);
    }

    /**
     * Returns a payment type based on its unique alias.
     */
    public function findPaymentItemByAlias($alias)
    {
        if (!$alias) {
            return false;
        }

        $items = $this->getPaymentItems(false);

        if (!isset($items[$alias])) {
            return false;
        }

        return $items[$alias];
    }

    /**
     * Executes an entry point for registered gateways, defined in routes.php file.
     * @param  string $code Code payment system
     * @param  string $accessPoint 
     */
    public static function runAccessPoint($code = null, $accessPoint = null)
    {
        if (!$paymentSystemModel = \KEERill\Pay\Models\PaymentSystem::where('code', $code)->whereNotNull('code')->first()) {
            return Response::make('Access Forbidden', '403');
        }

        if (!$paymentHandler = $paymentSystemModel->getPaymentHandler()) {
            return Response::make('Access Forbidden', '403');
        }

        $points = $paymentHandler->registerAccessPoints();

        if (isset($points[$accessPoint])) {
            return $paymentHandler->{$points[$accessPoint]}();
        }

        return Response::make('Access Forbidden', '403');
    }
}