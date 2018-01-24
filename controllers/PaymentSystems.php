<?php namespace KEERill\Pay\Controllers;

use File;
use Flash;
use Validator;
use BackendMenu;
use PaymentManager;
use ValidationException;
use ApplicationException;
use Backend\Classes\Controller;

/**
 * Payment Systems Back-end Controller
 */
class PaymentSystems extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['keerill.pay.payment_system'];

    public $gatewayAlias;

    public $gatewayClass;
    
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('KEERill.Pay', 'pay', 'paymentsystems');
    }

    /**
     * Загрузка модального окна со списком доступных платежных шлюзов
     * 
     * @return Partial
     */
    protected function index_onLoadAddPopup()
    {
        try {
            $gateways = PaymentManager::getGateways();
            $gateways->sortBy('name');
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        return $this->makePartial('list_add_gateway_form', ['gateways' => $gateways]);
    }

    /**
     * Создание новой платежной системы
     * 
     * @return mixed
     */
    public function create($gatewayAlias, $context = null)
    {
        try {
            if (!$this->user->hasAccess('keerill.pay.payment_system.create')) {
                throw new ApplicationException('Недостаточно прав для выполнения данной операции');
            }
            
            $this->gatewayAlias = $gatewayAlias;
            return $this->asExtension('FormController')->create();
        }
        catch (\Exception $ex) {
            $this->handleError($ex);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create_onSave($gatewayAlias, $context = null) 
    {
        return $this->asExtension('FormController')->create_onSave($context);
    }

    /**
     * Создание новой платежной системы
     * 
     * @return mixed
     */
    public function update($recordId = null)
    {
        try {
            if (!$this->user->hasAccess('keerill.pay.payment_system.edit')) {
                throw new ApplicationException('Недостаточно прав для выполнения данной операции');
            }
            
            return $this->asExtension('FormController')->update($recordId);
        }
        catch (\Exception $ex) {
            $this->handleError($ex);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update_onDelete($recordId = null)
    {
        if (!$this->user->hasAccess('keerill.pay.payment_system.remove')) {
            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
        }

        return $this->asExtension('FormController')->update_onDelete($recordId);
    }

    /**
     * Наследование модели, наследуем класс платежного шлюза
     * 
     * @retrun void
     */
    public function formExtendModel($model)
    {
        if (!$model->exists) {
            $model->applyGatewayClass($this->getGatewayClass());
        }

        return $model;
    }

    /**
     * Добавляем в форму поля, в зависимости от платежного шлюза
     * 
     * @return void
     */
    public function formExtendFields($widget)
    {
        $model = $widget->model;
        
        if (!$model->checkGatewayClass()) {
            return;
        }
        
        $model->extendFields($widget);
    }
 
    /**
     * Получение класса платежного шлюза
     * 
     * @return string Класс
     */
    protected function getGatewayClass()
    {
        $alias = post('gateway_alias', $this->gatewayAlias);

        if ($this->gatewayClass !== null) {
            return $this->gatewayClass;
        }

        if (!$gateway = PaymentManager::findGatewayByAlias($alias)) {
            throw new ApplicationException('К сожалению данный платежный шлюз не зарегистрирован в системе. '. $alias);
        }

        return $this->gatewayClass = $gateway->class;
    }

    /**
     * {@inheritDoc}
     */
    public function listInjectRowClass($record, $definition = null)
    {
        if (!$record->is_enable) {
            return 'safe disabled';
        }
    }
}
