<?php
/**
 * Created by PhpStorm.
 * User: snark | itfrogs.ru
 * Date: 10.11.14
 * Time: 22:31
  */

class shopMlmPluginFrontendTermsAction extends shopFrontendAction
{
    /**
     * @var shopMlmPlugin $plugin
     */
    private static $plugin;

    private static function getPlugin()
    {
        if (!empty(self::$plugin)) {
            $plugin = self::$plugin;
        } else {
            $plugin = wa()->getPlugin('mlm');
        }
        return $plugin;
    }

    public function execute()
    {
        $user = wa()->getUser();
        if (!$user->getId()) {
            $this->redirect(array('url' => '/'));
        }

        $plugin = self::getPlugin();
        $settings = $plugin->getSettings();

        if (!$settings['terms']) {
            return;
        }

        $mlmCustomersModel = new shopMlmCustomersModel();

        $customer = $mlmCustomersModel->getByContactId($user->getId());

        if ($customer) {
            $this->view->assign('parent', $mlmCustomersModel->getParent($customer));
        }

        $this->view->assign('affiliate_url', wa()->getRouteUrl('shop/frontend/my') . 'affiliate/');
        $this->view->assign('terms', $settings['terms']);
        $this->setLayout(new shopFrontendLayout());
        $this->getResponse()->setTitle(_wp('MLM terms'));
    }
}