<?php namespace KEERill\Pay\Controllers;

use Flash;
use Exception;
use Validator;
use BackendMenu;
use PaymentManager;
use ApplicationException;
use Backend\Classes\Controller;
use KEERill\Pay\Models\Payment;
use KEERill\Pay\Models\PaymentLog;
use KEERill\Pay\Models\PaymentItem;
use KEERill\Pay\Exceptions\PayException;

/**
 * Payments Back-end Controller
 */
class Payments extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.RelationController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $bodyClass = 'slim-container';

    public $requiredPermissions = ['keerill.pay.payment'];

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('KEERill.Pay', 'pay', 'payments');

        $this->vars['payOptionsWidget'] = null;
    }

    public function create()
    {
        return false;
    }

    public function update() 
    {
        return false;
    }

    public function preview($recordId = null)
    {
        try {
            $model = $this->formFindModelObject($recordId);
            
            if ($model->payment && count($model->options)) {
                $config = $model->payment->getPayFieldConfig();
                $config->model = $model;
    
                $widget = $this->makeWidget('Backend\Widgets\Form', $config);
                $widget->bindToController();
                $widget->setFormValues($model->options);
    
                $this->vars['payOptionsWidget'] = $widget;
            }
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }

        return $this->asExtension('FormController')->preview($recordId);
    }

    public function onRelationButtonDelete()
    {
        if ((post('_relation_field') == 'logs' && !$this->user->hasAccess('keerill.pay.logs.remove')) ||
            (post('_relation_field') == 'items' && !$this->user->hasAccess('keerill.pay.items.remove'))
        ) {
            throw new \ApplicationException('Недостаточно прав для выполнения данной операции');
        }
        
        return $this->asExtension('RelationController')->onRelationButtonDelete();
    }

    public function index_onUpdateActionsPayments()
    {
        if (
            ($action = post('action')) &&
            ($checkedIds = post('checked')) &&
            is_array($checkedIds) &&
            ($count = count($checkedIds))
        ) {
            foreach ($checkedIds as $userId) {
                if (!$payment = Payment::find($userId)) {
                    continue;
                }
                switch ($action) {
                    case 'delete':
                        if (!$this->user->hasAccess('keerill.pay.payment.remove')) {
                            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
                        }
                        $payment->delete();
                        break;
                    case 'success':
                        if (!$this->user->hasAccess('keerill.pay.payment.confirm')) {
                            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
                        }
                        try {
                            $payment->paymentSetSuccessStatus();
                        } catch(\Exception $ex) {
                            $count--; 
                        }
                        break;
                    case 'cancel':
                        if (!$this->user->hasAccess('keerill.pay.payment.cancel')) {
                            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
                        }
                        try {
                            $payment->paymentSetCancelledStatus(post('message'));
                        } catch(\Exception $ex) {
                            $count--; 
                        }
                        break;
                }
            }

            Flash::success(sprintf('Успешно выполнено %s из %s', $count, count($checkedIds)));
        }


        return $this->listRefresh();
    }

    /**
     * Загрузка модального окна формы отмены платежа
     * 
     * @return Partial
     */
    public function onLoadCancelled()
    {
        $id = intval(post('id'));
        $list = post('list', false);

        return $this->makePartial('popup_cancel', [
                'paymentId' => $id, 
                'list' => $list
            ]);
    }

    /**
     * Загрузка формы для создания нового платежа
     * 
     * @return Partial
     */
    public function index_onCreateForm()
    {
        $this->asExtension('FormController')->create();
        return $this->makePartial('list_create_form');
    }

    /**
     * AJAX Handler! Создание нового платежа
     * 
     * @return mixed Обновление таблицы платежей
     */
    public function index_onCreate()
    {
        if (!$this->user->hasAccess('keerill.pay.payment.create')) {
            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
        }

        $this->asExtension('FormController')->create_onSave();
        return $this->listRefresh();
    }

    /**
     * AJAX Handler! Удаление платежа
     * 
     * @return mixed
     */
    public function preview_onDelete($recordId = null)
    {
        if (!$this->user->hasAccess('keerill.pay.payment.remove')) {
            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
        }

        return $this->asExtension('FormController')->update_onDelete($recordId);
    }

    /**
     * ================================================
     *            Добавление нового предмета
     * ================================================
     */

    /**
     * Рендер модального окна со списком зарегестрированных предметов
     * 
     * @return Partial Модальное окно с предметами
     */
    public function preview_onAddItem()
    {
        $items = PaymentManager::getPaymentItems();

        return $this->makePartial('relation_add_items', [
            'items' => $items,
            'paymentId' => post('paymentId')
        ]);
    }

    /**
     * Загрзука формы добавление нового предмета, учитывая тип предмета
     * Т.е. наследование новый полей соответствующего типа предмета
     * 
     * @return Partial Модальное окно с формой
     */
    public function preview_onLoadCreateItemForm()
    {
        /*
         * Создание виджета, в качестве параметра передается алиас типа нового предмета
         */
        if (!$widget = $this->makeItemFormWidget(post('alias'))) {
            throw new ApplicationException('Cannot create new form widget');
        }

        $widget->bindToController();
        $widget->model->extendFormWidget($widget);

        return $this->makePartial('relation_form_items', [
            'widget' => $widget,
            'paymentId' => post('paymentId'),
            'alias' => post('alias'),
            'itemId' => false
        ]);
    }

    /**
     * Загрзука формы редактирования нового предмета, учитывая тип предмета
     * Т.е. наследование новый полей соответствующего типа предмета
     * 
     * @return Partial Модальное окно с формой
     */
    public function onRelationClickViewList()
    {
        /*
         * Для предотвращения последствий ошибок RelationController, нам нужен лишь связь items
         */
        if (post('_relation_field') != 'items' && post('_relation_field') != 'logs') {
            return $this->asExtension('RelationController')->onRelationClickViewList();
        }

        if (post('_relation_field') == 'logs') {
            /*
             * Создание виджета для просмотра подробностей лога
             */
            if (!$widget = $this->makeLogFormWidget(post('manage_id'))) {
                throw new ApplicationException('Cannot create new form widget');
            }

            return $this->makePartial('relation_form_logs', ['widget' => $widget]);
        }

        /*
         * Создание виджета, алиас не передаётся так как у прелмета уже есть присвоеный тип 
         */
        if (!$widget = $this->makeItemFormWidget(false, post('manage_id'))) {
            throw new ApplicationException('Cannot create new form widget');
        }

        $widget->bindToController();
        $widget->model->extendFormWidget($widget);

        return $this->makePartial('relation_form_items', [
            'widget' => $widget,
            'itemId' => post('manage_id')
        ]);
    }

    /**
     * AJAX Handler! Создание нового предмета платежа
     * 
     * @return mixed Обновление таблицы с предметами
     */
    public function preview_onCreateItem()
    {
        /*
         * Создание виджета, в качестве параметра передается алиас типа нового предмета
         */
        if (!$widget = $this->makeItemFormWidget(post('alias'))) {
            throw new ApplicationException('Cannot create new form widget');
        }

        if (!$this->user->hasAccess('keerill.pay.payment.update_pay')) {
            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
        }

        $widget->bindToController();
        $widget->model->extendFormWidget($widget);


        $model = $widget->model;
        $saveData = $widget->getSaveData();

        /*
         * Проверка и получение модели платежа для создания связи,
         * так же для инициализации FormController'а
         */
        if (!$payment = Payment::find(post('paymentId'))) {
            throw new ApplicationException('Not found Payment model');
        }

        /*
         * Для того, чтобы изменять платеж, платеж должен быть со статусом "Открытый"
         */
        if (!$payment->hasOpen()) {
            throw new ApplicationException('Добавление предмета невозможно, платеж не является открытым');
        }

        foreach ($saveData as $key => $value) {
            $model->{$key} = $value;
        }

        $payment->items()->add($model);

        Flash::success('Предмет был успешно добавлен');
        
        /*
         * Перезагрузка страницы
         */
        if ($redirect = $this->makeRedirect('preview', $payment)) {
            return $redirect;
        }
    }

    /**
     * AJAX Handler! Редактирование уже существующего предмета
     * 
     * @return mixed Обновление таблицы с предметами
     */
    public function preview_onUpdateItem()
    {
        /*
         * Создание виджета, алиас не передаётся так как у прелмета уже есть присвоеный тип 
         */
        if (!$widget = $this->makeItemFormWidget(false, post('itemId'))) {
            throw new ApplicationException('Cannot create new form widget');
        }

        if (!$this->user->hasAccess('keerill.pay.items.edit')) {
            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
        }

        $widget->bindToController();
        $widget->model->extendFormWidget($widget);
        
        
        $model = $widget->model;
        $saveData = $widget->getSaveData();

        /*
         * Для того, чтобы изменять платеж, платеж должен быть со статусом "Открытый"
         */
        if (!$model->payment->hasOpen()) {
            throw new ApplicationException('Редактирование предмета невозможно, платеж не является открытым');
        }

        foreach ($saveData as $key => $value) {
            $model->{$key} = $value;
        }

        $model->save();

        Flash::success('Предмет был успешно изменён');
        
        /*
         * Перезагрузка страницы
         */
        if ($redirect = $this->makeRedirect('preview', $model->payment)) {
            return $redirect;
        }
    }

    /**
     * Создание виджета формы
     * 
     * @param string $alias Алиас типа предмета, служит для присвоения к модели класса и новых полей
     * @param integer $id ID предмета
     * @return \Backend\Widget\Form Виджет формы
     */
    protected function makeItemFormWidget($alias = false, $id = false)
    {
        $class = false;
        $model = null;

        if ($item = PaymentManager::findPaymentItemByAlias($alias)) {
            $class = $item->class;
        }

        if ($id) {
            $model = PaymentItem::find($id);
        } else {
            $model = new PaymentItem;
        }

        $model->applyPaymentItemClass($class);

        $config = $this->makeConfig('$/keerill/pay/models/paymentitem/fields.yaml');
        $config->model = $model;
        $config->arrayName = class_basename($model);
        $config->context = ($id) ? 'update' : 'create';

        $widget = $this->makeWidget('Backend\Widgets\Form', $config);

        return $widget;
    }

    /**
     * Создание виджета
     * 
     * @param integer $id ID лога
     * @return \Backend\Widget\Form Виджет формы
     */
    protected function makeLogFormWidget($id = false)
    {
        $config = $this->makeConfig('$/keerill/pay/models/paymentlog/fields.yaml');
        $config->model = PaymentLog::find($id);
        $config->context = 'preview';

        $widget = $this->makeWidget('Backend\Widgets\Form', $config);

        return $widget;
    }
    
    /**
     * ================================================
     *              Смена статуса платежа
     * ================================================
     */

    /**
     * AJAX Handler! Подтверждение платежа
     * 
     * @return mixed
     */
    public function preview_onSuccess()
    {
        $id = intval(post('id'));

        if (!$this->user->hasAccess('keerill.pay.payment.confirm')) {
            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
        }

        /*
         * Получение модели платежа
         */
        if (!$model = $this->formFindModelObject($id)) {
            return false;
        }

        try {
            $model->paymentSetSuccessStatus();
        } catch(Exception $ex) {
            Flash::error($ex->getMessage());
            return false;
        }
        
        Flash::success('Платеж успешно подтвержден');

        /*
         * Перезагрузка страницы платежа
         */
        if ($redirect = $this->makeRedirect('preview', $model)) {
            return $redirect;
        }
    }

    /**
     * AJAX Handler! Отмена платежа
     * 
     * @return mixed
     */
    public function preview_onCancel()
    {
        $id = intval(post('id'));

        if (!$this->user->hasAccess('keerill.pay.payment.cancel')) {
            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
        }

        /*
         * Получение модели платежа
         */
        if (!$model = $this->formFindModelObject($id)) {
            return false;
        }

        try {
            $model->paymentSetCancelledStatus(post('message'));
        } catch(Exception $ex) {
            Flash::error($ex->getMessage());
            return false;
        }

        Flash::success('Платеж успешно отклонен');

        /*
         * Перезагрузка страницы платежа
         */
        if ($redirect = $this->makeRedirect('preview', $model)) {
            return $redirect;
        }
    }

    /**
     * AJAX Handler! Принудительное обновление суммы платежа
     * 
     * @return mixed
     */
    public function preview_onUpdatePay()
    {
        $id = intval(post('id'));

        if (!$this->user->hasAccess('keerill.pay.payment.update_pay')) {
            throw new ApplicationException('Недостаточно прав для выполнения данной операции');
        }
        
        /*
         * Получение модели платежа
         */
        if (!$model = $this->formFindModelObject($id)) {
            return false;
        }

        try {
            $model->paymentUpdatePay();
        } catch(Exception $ex) {
            Flash::error($ex->getMessage());
            return false;
        }

        Flash::success('Сумма платежа была пересчитана успешно');

        /*
         * Перезагрузка страницы платежа
         */
        if ($redirect = $this->makeRedirect('preview', $model)) {
            return $redirect;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function listInjectRowClass($record, $definition = null)
    {
        if($record->status == Payment::PAYMENT_CANCEL || $record->status == Payment::PAYMENT_ERROR) {
            return 'negative';
        }
        
        if ($record->hasOpen()) {
            return 'new';
        }
    }
}
