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
        $mlm_id = waRequest::get('mlm_id');
        $storage = new waSessionStorage();
        $storage->set('mlm_id', $mlm_id);

        var_dump($mlm_id);

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


        $this->setTemplate($plugin->getThemePath().'/signup.html');
        $this->setLayout(new shopFrontendLayout());
        $this->getResponse()->setTitle(_wp('Sign up'));


    }

    /**
     * @param array $data
     * @param array $errors
     * @return bool|waContact
     */
    public function signup($data, &$errors = array())
    {
        // check exists contacts
        $auth = wa()->getAuth();
        $field_id = $auth->getOption('login');
        if ($field_id == 'login') {
            $field_name = _ws('Login');
        } else {
            $field = waContactFields::get($field_id);
            if ($field) {
                $field_name = $field->getName();
            } else {
                $field_name = ucfirst($field_id);
            }
        }

        $is_error = false;

        // check passwords
        if ($data['password'] !== $data['password_confirm']) {
            $errors['password'] = array();
            $errors['password_confirm'] = array(
                _ws('Passwords do not match')
            );
            $is_error = true;
        } elseif (!$data['password']) {
            $errors['password'] = array();
            $errors['password_confirm'][] = _ws('Password can not be empty.');
            $is_error = true;
        }

        if (!$data[$field_id]) {
            $errors[$field_id] = array(
                sprintf(_ws("%s is required"), $field_name)
            );
            $is_error = true;
        }
        if (!$is_error) {
            $contact = $auth->getByLogin($data[$field_id]);
            if ($contact) {
                $errors[$field_id] = array(
                    sprintf(_ws('User with the same %s is already registered'), $field_name)
                );
                $is_error = true;
            }
        }

        // set unconfirmed status for email
        if (isset($data['email']) && $data['email']) {
            $data['email'] = array('value' => $data['email'], 'status' => 'unconfirmed');
        }

        // check captcha
        $auth_config = wa()->getAuthConfig();
        if (isset($auth_config['signup_captcha']) && $auth_config['signup_captcha']) {
            if (!wa()->getCaptcha()->isValid()) {
                $errors['captcha'] = _ws('Invalid captcha');
                $is_error = true;
            }
        }

        if (is_array($auth_config['fields'])) {
            foreach ($auth_config['fields'] as $fld_id => $fld) {
                if (array_key_exists('required', $fld) && !$data[$fld_id] && $fld_id !== 'password') {
                    $field = waContactFields::get($fld_id);
                    if (!empty($fld['caption'])) {
                        $field_name = $fld['caption'];
                    } else if ($field) {
                        $field_name = $field->getName();
                    } else {
                        $field_name = ucfirst($fld_id);
                    }
                    $errors[$fld_id] = array(
                        sprintf(_ws("%s is required"), $field_name)
                    );
                    $is_error = true;
                }
            }
        }


        if ($is_error) {
            return false;
        }

        if(isset($data['birthday']) && is_array($data['birthday']['value'])) {
            foreach ($data['birthday']['value'] as $bd_id => $bd_val) {
                if(strlen($bd_val) === 0) {
                    $data['birthday']['value'][$bd_id] = null;
                }
            }
        }

        // remove password_confirm field
        unset($data['password_confirm']);
        // set advanced data
        $data['create_method'] = 'signup';
        $data['create_ip'] = waRequest::getIp();
        $data['create_user_agent'] = waRequest::getUserAgent();
        // try save contact
        $contact = new waContact();

        if (!$errors = $contact->save($data, true)) {
            if (!empty($data['email'])) {
                $this->send($contact);
            }
            // after sign up callback

            //exit(12312);
            $plugin = self::getPlugin();
            $settings = $plugin->getSettings();
            $storage = new waSessionStorage();

            $mlm_id = $storage->get('mlm_id');
            $settings['mlm_id'] = $mlm_id;
            $plugin->saveSettings($settings);

            $this->afterSignup($contact);

            // try auth new contact
            try {
                if (wa()->getAuth()->auth($contact)) {
                    $this->logAction('signup', wa()->getEnv());
                }
            } catch (waException $e) {
                $errors = array('auth' => $e->getMessage());
            }

            return $contact;
        }
        if (isset($errors['name'])) {
            $errors['firstname'] = array();
            $errors['middlename'] = array();
            $errors['lastname'] = $errors['name'];
        }
        return false;
    }

    public function signupUrl($absolute = false)
    {
        $shopUrl = wa()->getRouteUrl('shop/frontend');
        $signup_url = $shopUrl . 'mlm/signup/';
        return $signup_url;
    }

    /**
     * @param waContact $contact
     */
    protected function afterSignup(waContact $contact)
    {
        exit(12312);
        $plugin = self::getPlugin();
        $settings = $plugin->getSettings();
        $storage = new waSessionStorage();

        $mlm_id = $storage->get('mlm_id');
        $settings['mlm_id'] = $mlm_id;
        $plugin->saveSettings($settings);
//        var_dump($mlm_id);
//        exit;
    }
}