<?php namespace KEERill\Pay\Classes;

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
     * @var array Cache Gateways
     */
    private $gateways;

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
    public function registerGateways($owner, array $classes)
    {
        if (!$this->gateways)
            $this->gateways = [];

        foreach ($classes as $class => $alias) {
            $gateway = (object)[
                'owner' => $owner,
                'class' => $class,
                'alias' => $alias,
            ];

            $this->gateways[$alias] = $gateway;
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
     *
     * @return void
     */
    protected function loadGateways()
    {
        /*
        * Load plugin items
        */
        $plugins = $this->pluginManager->getPlugins();

        foreach ($plugins as $id => $plugin) {
            if (!method_exists($plugin, 'registerPaymentGateways'))
                continue;

            $gateways = $plugin->registerPaymentGateways();
            if (!is_array($gateways))
                continue;

            $this->registerGateways($id, $gateways);
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
    public function getGateways($getCollection = true)
    {
        if ($this->gateways === null) {
            $this->loadGateways();
        }

        if (!$getCollection) {
            return $this->gateways;
        }

        if ($this->gatewaysCollection) {
            return $this->gatewaysCollection;
        }

        /*
         * Enrich the collection with gateway objects
         */
        $collection = [];

        foreach ($this->gateways as $gateway) {
            if (!class_exists($gateway->class))
                continue;

            $class =  $gateway->class;
            $gatewayDetails = $class::gatewayDetails();

            $collection[$gateway->alias] = (object)[
                'owner'       => $gateway->owner,
                'class'       => $gateway->class,
                'alias'       => $gateway->alias,
                'name'        => array_get($gatewayDetails, 'name', 'Undefined'),
                'description' => array_get($gatewayDetails, 'description', 'Undefined'),
            ];
        }

        return $this->gatewaysCollection = new Collection($collection);
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
    public function findGatewayByAlias($alias)
    {
        if (!$alias) {
            return false;
        }
        
        $gateways = $this->getGateways(false);

        if (!isset($gateways[$alias])) {
            return false;
        }

        return $gateways[$alias];
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
     * Создание нового платежа
     * 
     * @param array $data Данные платежа, например: Описание, и т.д.
     * @param array $items Предметы платежа, которые будут добавлены к новому платежу
     * @return KEERill\Pay\Models\Payment $payment Модель платежа
     */
    public function createPaymentWithItems($data = [], $items = [])
    {
        Event::fire('keerill.pay.beforeCreatePayment', [$this, $data, $items]);

        $payment = Payment::create($data);

        if ($items && is_array($items)) {
            foreach ($items as $item => $params) {
                if (!$itemClass = $this->findPaymentItemByAlias($item)) {
                    continue;
                }

                $newItem = new PaymentItem;
                $newItem->applyPaymentItemClass($itemClass->class);
                $newItem->fill($params);

                $payment->items()->add($newItem);
            }
        }

        Event::fire('keerill.pay.afterCreatePayment', [$this, $payment]);

        return $payment;
    }

    /**
     * Создание нового платежа с предметами, передавая конкретных код платежной системы
     * Также производиться проверка на минимальную сумму платежа в соответсвии платежной системы
     * 
     * @param string $code Код платежной системы, задаётся в настройках
     * @param array $data Данные платежа, например: Описание, и т.д.
     * @param array $items Предметы платежа, которые будут добавлены к новому платежу
     * @return void
     */
    public function createPaymentWithItemsAndCode($code, $data = [], $items = [])
    {
        if (!$paySystem = PaymentSystem::where('code', $code)->first()) {
            throw new PayException(sprintf('Не существует платежного шлюза с таким кодом [%s]', $code), ['code' => $code]);
        }

        /*
         * Проверка на состояние работы платежной системы
         */
        if (!$paySystem->hasEnableSystem()) {
            throw new PayException('Данная платежная система отключена администрацией сайта', [], false);
        }

        /**
         * Проверка на минимальность суммы платежа в соответствии платежной системы
         */
        if (!$paySystem->checkMinPayItems($items)) {
            throw new PayException(sprintf('Минимальная сумма платежа %s', $paySystem->min_pay), [], false);
        }

        /**
         * Создание платежа с предметами
         */
        $payment = $this->createPaymentWithItems($data, $items);

        /**
         * Присваивание способа оплаты в платежу
         */
        $paySystem->setPaymentMethod($payment);
    }

    
    /**
     * Executes an entry point for registered gateways, defined in routes.php file.
     * @param  string $code Code payment system
     * @param  string $accessPoint 
     */
    public static function runAccessPoint($code = null, $accessPoint = null)
    {
        if (!$code || !$accessPoint) {
            return Response::make('Access Forbidden', '403');
        }
        
        if (!$gateway = \KEERill\Pay\Models\PaymentSystem::where('code', $code)->first()) {
            return Response::make('Access Forbidden', '403');
        }

        $points = $gateway->registerAccessPoints();

        if (isset($points[$accessPoint])) {
            return $gateway->{$points[$accessPoint]}();
        }

        return Response::make('Access Forbidden', '403');
    }
}