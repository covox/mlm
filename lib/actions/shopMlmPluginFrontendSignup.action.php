<?php
/**
 * Created by PhpStorm.
 * User: snark | itfrogs.ru
 * Date: 28.10.14
 * Time: 19:57
 */

class shopMlmPluginFrontendSignupAction extends waSignupAction
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
        $mlm_id = waRequest::get('mlm_id', 0, 'int');
        if ($mlm_id > 0) {
            $storage = new waSessionStorage();
            $storage->set('mlm_id', $mlm_id);
            $mlmCustomersModel = new shopMlmCustomersModel();
            $customer = $mlmCustomersModel->getById($mlm_id);
            if (!empty($customer)) {
                $this->view->assign('parent', $mlmCustomersModel->getParent($customer));
            }

        }

        $confirm_hash = waRequest::get('confirm', false);
        if (wa()->getAuth()->isAuth() && !$confirm_hash) {
            $this->redirect(wa()->getAppUrl());
        }
        // check auth config
        $auth = wa()->getAuthConfig();
        if (!isset($auth['auth']) || !$auth['auth']) {
            throw new waException(_ws('Page not found'), 404);
        }
        // check auth app and url
        $shopUrl = wa()->getRouteUrl('shop/frontend');
        $signup_url = $shopUrl . 'mlm/signup/';
        //$signup_url = wa()->getRouteUrl((isset($auth['app']) ? $auth['app'] : '').'/signup');
        if (wa()->getConfig()->getRequestUrl(false, true) != $signup_url) {
            $this->redirect($signup_url);
            //http://plugins.snark.itfrogs.ru/shop/mlm/signup/?mlm_id=246425
        }
        $errors = array();
        if (waRequest::method() == 'post') {
            // try sign up
            if ($contact = $this->signup(waRequest::post('data'), $errors)) {
                // assign new contact to view
                $this->view->assign('contact', $contact);
            }
        } elseif($confirm_hash) {
            if ($contact = $this->confirmEmail($confirm_hash, $errors)) { // if we successfully confirmed email
                // assign contact with confirmed email to view
                $this->view->assign('contact', $contact);
                $this->view->assign('confirmed_email', true);
            } else { // else email is already confirmed or smth else happend
                if (wa()->getAuth()->isAuth()) {
                    // redirect to main page
                    $this->redirect(wa()->getAppUrl());
                }
            }
        }
        $this->view->assign('errors', $errors);
        wa()->getResponse()->setTitle(_ws('Sign up'));

        $plugin = self::getPlugin();
        $settings = $plugin->getSettings();
//        var_dump($settings['mlm_id']);




        $signup_path = wa()->getDataPath('plugins/mlm/templates/signup.html', false, 'shop', true);
        if (!file_exists($signup_path)) {
            $signup_path = wa()->getAppPath('plugins/mlm/templates/signup.html', 'shop');
        }

        $this->setTemplate($signup_path);
        //var_dump($signup_path);
        //$this->setTemplate($plugin->getThemePath().'/signup.html');
        $this->setLayout(new shopFrontendLayout());
        $this->getResponse()->setTitle(_wp('Sign up'));

    }

    /**
     * @param waContact $contact
     */
    protected function afterSignup(waContact $contact)
    {
//var_dump($contact);
//        exit;
        $plugin = self::getPlugin();
        $settings = $plugin->getSettings();
        $storage = new waSessionStorage();

        $mlm_id = $storage->get('mlm_id');
        if ($mlm_id > 0 && $settings['enabled'] == 1) {
            $contact_id = $contact->getId();
            $mlmCustomersModel = new shopMlmCustomersModel();
            $customer = $mlmCustomersModel->getByContactId($contact_id);

            if (!$customer) {
                $customer = array(
                    'contact_id' => $contact_id
                );

                $parent_code = $mlm_id;
                $customer['code'] = $mlmCustomersModel->add($contact_id, $parent_code);
            }
        }


    }
}