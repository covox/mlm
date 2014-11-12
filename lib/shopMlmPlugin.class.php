<?php

class shopMlmPlugin extends shopPlugin
{
    const MLM_PROMO_CODE_CONTACTFIELD = 'mlm_promo_code';
    /**
     * @var waView $view
     */
    private static $view;

    /**
     * Эта модель пригодится в нескольких методах.
     *
     * @var shopMlmCustomersModel
     */
    private $MlmCustomers;

    public function __construct($info)
    {
        parent::__construct($info);
        $this->MlmCustomers = new shopMlmCustomersModel();
    }

    /**
     *
     * @return waSmarty3View
     */
    private static function getView()
    {
        if (!empty(self::$view)) {
            $view = self::$view;
        } else {
            $view = waSystem::getInstance()->getView();
        }
        return $view;
    }

    public function backendSettingsAffiliate()
    {
        return array(
            'id' => 'mlm',
            'name' => _wp('MLM program'),
            'url' => '?plugin=mlm&module=affiliate&action=settings'
        );
    }

    public function frontendMyAffiliate()
    {
        if (!$this->getSettings('enabled')) {
            return;
        }

        // Без жнецов плагин не работает
        $owners = $this->getSettings('owners');
        if(!$owners || empty($owners) || !is_array($owners)) {
            return;
        }

        $view = self::getView();
        $this->addJs('js/jstree/jstree.min.js');
        $this->addJs('js/qtip/jquery.qtip.min.js');
        $this->addCss('js/jstree/themes/default/style.min.css');
        $this->addCss('js/qtip/jquery.qtip.min.css');

        $contact = wa()->getUser();
        $contact_id = $contact->getId();
        $customer = $this->MlmCustomers->getByContactId($contact_id);

        if (!$customer && waRequest::post('terms_accept')) {

            $customer = array(
                'contact_id' => $contact_id
            );

            $parent_code = waRequest::get('mlm_id', 0, 'int');
            $code = $this->MlmCustomers->add($contact_id, $parent_code);
            $customer = $this->MlmCustomers->getByCode($code);
        }
        elseif (!$customer && !waRequest::post('terms_accept')) {
            wa()->getResponse()->redirect(array('url' => wa()->getRouteUrl('shop/frontend') . 'mlm/terms/'));
            $this->redirect(array('url' => '/'));
        }

        if ($customer) {
            $view->assign('code', $customer['code']);
            //$this->view->assign('data', $this->getReportsData($referral));

            $format = waDateTime::getFormat('date');
            $format = preg_replace('#[,/\.-]?(Y|y)[,/\.-]?#i', '', $format);
            $format = preg_replace('/([a-z])/i', '%$1', $format);

            $view->assign('date_format', $format);
            $view->assign('activity', $this->getSettings('activity'));
        }

        $customerClass = new shopMlmPluginCustomer();

        $view->assign('stats', $this->getStats($customer));
        $view->assign('contact', $contact);
        $view->assign('parent', $this->MlmCustomers->getParent($customer));
        $view->assign('subtree', $customerClass->customers($customer['id'], 3));
        $view->assign('promo', $this->getSettings('promo'));
        return $view->fetch($this->path . '/templates/frontendMyAffiliate.html');
    }

    /**
     * Handler for order_arction.complete Hook
     *
     * @param array $data
     */
    public function orderActionComplete($data)
    {
        $Order = new shopOrderModel();
        $AffiliateTransaction = new shopAffiliateTransactionModel(); // Правильный метод добавления стандартных бонусов
        $order = $Order->getOrder($data['order_id']);
        $bonuses = $this->calculateBonus($order);
        $buyer = new shopCustomer($order["contact_id"]);

        foreach($this->MlmCustomers->getThreeParents($order["contact_id"]) as $level => $mlm_customer) {

            $AffiliateTransaction->applyBonus(
                    $mlm_customer['contact_id'],
                    $bonuses[$level]['bonus'],
                    $order['id'],
                    sprintf(
                            _wp("Bonus for MLM customer %s's order %s"),
                            $buyer->getName(),
                            shopHelper::encodeOrderId($order['id'])),
                    'mlm_bonus');
        }

    }

    /**
     * Получаем GET-переменную mlm_id и, если ее еще нет в сессии и пользователь
     * не аутентифицирован, сохраняем в сессию. Если кто-то кликнул на
     * несколько разных партнерских ссылок, то он все равно будет к первому
     * привязан
     *
     * @return string
     */
    public function frontendHead()
    {
        $user = waSystem::getInstance()->getUser();
        $mlm_id = waRequest::get('mlm_id', NULL, waRequest::TYPE_STRING_TRIM);

        $view = $this->getView();

        if(!$user->isAuth() && $mlm_id && !waSystem::getInstance()->getStorage()->read('mlm_id')) { //такая переменная есть в GET, а сессии нет
            waSystem::getInstance()->getStorage()->write('mlm_id', $mlm_id);
        }

        $mlm_id = waSystem::getInstance()->getStorage()->read('mlm_id');

        if($mlm_id) {
            $view->assign('field_id', self::MLM_PROMO_CODE_CONTACTFIELD);
            $view->assign('mlm_id', $mlm_id);
            return $view->fetch($this->path . '/templates/frontendHeadSignup.html');
        }

        return "";
    }

    /**
     * Возвращает массив с посчитанными бонусами для каждого из трех уровней
     * [
     *   1 => [bonus => xxx]
     *   2 => [bonus => yyy]
     *   3 => [bonus => zzz]
     * ]
     *
     * Цифра уровень, указывающий на массив, в массиве ключ bonus указывает на
     * количество бонусов
     *
     * @param array $order
     * @return array
     */
    private function calculateBonus($order)
    {
        $result = array(
            1=>array('bonus'=>0),
            2=>array('bonus'=>0),
            3=>array('bonus'=>0),
        );

        for($i = 1; $i <= 3; $i++) {
            $percent = $this->getPercentByLevel($i);
            $result[$i]['bonus'] = $percent > 0 ? shopAffiliate::calculateBonus($order['id'], 100/$percent) : 0;
        }

        return $result;
    }

    /**
     * Возвращает массив со статистикой для каждого уровня реферралов
     * указанного контакта
     *
     * @param array $customer Массив данных о нашем пользователе из модели shopMlmCustomersModel
     * @return array Массив с тремя вложенными массивами со статистикой, по одному на каждый уровень
     */
    private function getStats($customer)
    {
        $result = array();

        for($level = 1; $level<=3; $level++) {
            $result[$level] = array(
                'percent' => $this->getPercentByLevel($level),
                'referral_count' => $this->MlmCustomers->countReferrals($customer, $level),
                'purchases_total' => $this->MlmCustomers->countReferralPurchasesTotals($customer, $level),
                'bonuses_total' => 0, // Пока рассчитывается в шаблоне простым умножением и делением
                'missed_bonuses_total' => 0,
                'reasons' => array()
            );
        }

        return $result;
    }

    /**
     * Возвращает процент начисления бонусов для указанного уровня
     *
     * @param int $level Уровень
     * @return float
     */
    private function getPercentByLevel($level)
    {
        $percent =  $this->getSettings("level_{$level}_percent");

        if(!$percent) {
            return 0.0;
        }

        return floatval($percent);
    }

}
