<?php

class shopMlmPlugin extends shopPlugin
{
    /**
     * @var waView $view
     */
    private static $view;

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

    public static function getThemePath()
    {
        $theme = waRequest::param('theme', 'default');
        $theme_path = wa()->getDataPath('themes', true) . '/' . $theme;
        if (!file_exists($theme_path) || !file_exists($theme_path . '/theme.xml')) {
            $theme_path = wa()->getAppPath() . '/themes/' . $theme;
        }
        return $theme_path;
    }

    public function signupForm($errors = array())
    {
        $fields = $this->signupFields($errors);
        //print '<pre>';
        //var_dump($fields);
        //print '</pre>';
        $html = '<div class="wa-form"><form action="'.$this->signupUrl().'" method="post">';
        foreach ($fields as $field_id => $field) {
            if ($field) {
                $f = $field[0];
                /**
                 * @var waContactField $f
                 */
                if (isset($errors[$field_id])) {
                    $field_error = is_array($errors[$field_id]) ? implode(', ', $errors[$field_id]): $errors[$field_id];
                } else {
                    $field_error = false;
                }
                $field[1]['id'] = $field_id;
                if ($f instanceof waContactCompositeField) {
                    foreach ($f->getFields() as $sf) {
                        /**
                         * @var waContactField $sf
                         */
                        $html .= $this->signupFieldHTML($sf, array('parent' => $field_id, 'id' => $sf->getId()), $field_error);
                    }
                } else {
                    $html .= $this->signupFieldHTML($f, $field[1], $field_error);
                }
            } else {
                $html .= '<div class="wa-field wa-separator"></div>';
            }
        }
        $config = wa()->getAuthConfig();
        if (isset($config['signup_captcha']) && $config['signup_captcha']) {
            $html .= '<div class="wa-field"><div class="wa-value">';
            $html .= wa($this->app_id)->getCaptcha()->getHtml(isset($errors['captcha']) ? $errors['captcha'] : '');
            if (isset($errors['captcha'])) {
                $html .= '<em class="wa-error-msg">'.$errors['captcha'].'</em>';
            }
            $html .= '</div></div>';
        }
        $signup_submit_name = !empty($config['params']['button_caption']) ? htmlspecialchars($config['params']['button_caption']) : _ws('Sign Up');
        $html .= '<div class="wa-field"><div class="wa-value wa-submit">
            <input type="submit" value="'.$signup_submit_name.'"> '.sprintf(_ws('or <a href="%s">login</a> if you already have an account'), wa()->getRouteUrl('/login', array(), false)).'
        </div></div>';
        $html .= '</form></div>';
        return $html;
    }

    private function signupFieldHTML(waContactField $f, $params, $error = '')
    {
        $data = waRequest::post('data');
        // get value
        if (isset($params['parent'])) {
            $parent_value = $data[$params['parent']];
            $params['value'] = isset($parent_value[$params['id']]) ? $parent_value[$params['id']] : '';
        } else {
            $params['value'] = isset($data[$params['id']]) ? $data[$params['id']] : '';
        }

        $config = wa()->getAuthConfig();
        if (!empty($config['fields'][$f->getId()]['caption'])) {
            $name = htmlspecialchars($config['fields'][$f->getId()]['caption']);
        } else {
            $name = $f->getName(null, true);

            if (isset($params['ext'])) {
                $exts = $f->getParameter('ext');
                if (isset($exts[$params['ext']])) {
                    $name .= ' ('._ws($exts[$params['ext']]).')';
                } else {
                    $name .= ' ('.$params['ext'].')';
                }
            }
        }
        $params['namespace'] = 'data';
        if ($f->isMulti()) {
            $f->setParameter('multi', false);
        }
        $attrs = $error !== false ? 'class="wa-error"' : '';
        if (!empty($config['fields'][$f->getId()]['placeholder'])) {
            $attrs .= ' placeholder="'.htmlspecialchars($config['fields'][$f->getId()]['placeholder']).'"';
        }

        if ($f instanceof waContactHiddenField) {
            $html = $f->getHTML($params, $attrs);
        } else {
            $html = '<div class="wa-field wa-field-'.$f->getId().'">
					<div class="wa-name">'.$name.'</div>
					<div class="wa-value">'.$f->getHTML($params, $attrs);
            if ($error) {
                $html .= '<em class="wa-error-msg">'.$error.'</em>';
            }
            $html .= '</div></div>';
        }
        return $html;
    }

    public function signupFields($errors = array())
    {
        //print '----------';
        $config = wa()->getAuthConfig();
        $mlm_id = waRequest::get('mlm_id', 0, 'int');

        $config_fields = isset($config['fields']) ? $config['fields']: array(
 //           'mlm_id',
            'firstname',
            'lastname',
            '',
            'email' => array('required' => true),
            'password' => array('required' => true),
        );
       // var_dump($config_fields);
        $format_fields = array();
        foreach ($config_fields as $k => $v) {
            if (is_numeric($k)) {
                if ($v) {
                    $format_fields[$v] = array();
                } else {
                    $format_fields[] = '';
                }
            } else {
                $format_fields[$k] = $v;
            }
        }
        $fields = array();
        foreach ($format_fields as $field_id => $field) {
            if (!is_numeric($field_id)) {
                if (strpos($field_id, '.')) {
                    $field_id_parts = explode('.', $field_id);
                    $id = $field_id_parts[0];
                    $field['ext'] = $field_id_parts[1];
                } else {
                    $id = $field_id;
                }
                $f = waContactFields::get($id);
                if ($f) {
                    $fields[$field_id] = array($f, $field);
                } elseif ($field_id == 'mlm_id') {
                    //$val = $mlm_id > 0 ? $mlm_id : '';
                   // var_dump($field);
                    //$waHtmlControl = new waHtmlControl();
                    //$fields[$field_id] = array(waHtmlControl::getControl('input', 'mlm_id', array('value' => $val)));
                } elseif ($field_id == 'login') {
                    $fields[$field_id] = array(new waContactStringField($field_id, _ws('Login')), $field);
                } elseif ($field_id == 'password') {
                    $fields[$field_id] = array(new waContactPasswordField($field_id, _ws('Password')), $field);
                    $field_id .= '_confirm';
                    $fields[$field_id] = array(new waContactPasswordField($field_id, _ws('Confirm password')), $field);
                }
            } else {
                $fields[] = '';
            }
        }
        return $fields;
    }

    public function signupUrl($absolute = false)
    {
        return $shopUrl = wa()->getRouteUrl('shop/frontend') . 'mlm/signup/';
    }

    public function frontendMyAffiliate()
    {
        if (!$this->getSettings('enabled')) {
            return;
        }
        $view = self::getView();
        $this->addJs('js/jstree/jstree.min.js');
        $this->addJs('js/qtip/jquery.qtip.min.js');
        $this->addCss('js/jstree/themes/default/style.min.css');
        $this->addCss('js/qtip/jquery.qtip.min.css');

        $contact = wa()->getUser();
        $contact_id = $contact->getId();
        $mlmCustomersModel = new shopMlmCustomersModel();
        $customer = $mlmCustomersModel->getByContactId($contact_id);



        if (!$customer) {
            $customer = array(
                'contact_id' => $contact_id
            );

            $parent_code = waRequest::get('mlm_id', 0, 'int');
            $customer['code'] = $mlmCustomersModel->add($contact_id, $parent_code);
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

        $view->assign('contact', $contact);
        $view->assign('parent', $mlmCustomersModel->getParent($customer));
        $view->assign('subtree', $customerClass->customers($customer['id'], 3));
        $view->assign('promo', $this->getSettings('promo'));
        return $view->fetch($this->path . '/templates/frontendMyAffiliate.html');
    }

    public function orderActionComplete($data)
    {
        $Order = new shopOrderModel();
        $MlmCustomer = new shopMlmCustomersModel();
        $order = $Order->getOrder($data['order_id']);

        $MlmCustomer->addBonusToParents($order["contact_id"], $this->calculateBonus($order));
        
       //exit;

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
     * FIXME: Возможно, для расчета бонусов надо использовать не общую стоимость
     *        заказа, а только стоимость товаров, без учета доставки и/или
     *        скидок
     * 
     * @param array $order
     * @return array
     */
    private function calculateBonus($order)
    {
        $settings = array(
            'level_1_percent' => 0,
            'level_2_percent' => 0,
            'level_3_percent' => 0,
        );
        
        $result = array(
            1=>array('bonus'=>0),
            2=>array('bonus'=>0),
            3=>array('bonus'=>0),
        );
        
        foreach($settings as $key => $value) {
            $settings[$key] = $this->getSettings($key) ? $this->getSettings($key) : $value;
        }
        
        for($i = 1; $i <= 3; $i++) {
            $result[$i]['bonus'] = $order["total"] * $settings["level_{$i}_percent"] / 100;
        }
        
        return $result;
    }
}
