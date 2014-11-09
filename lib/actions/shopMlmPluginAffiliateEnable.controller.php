<?php

class shopMlmPluginAffiliateEnableController extends waJsonController
{

    public function execute()
    {
        $enabled = waRequest::post('enabled', 0, 'int');
        $type = waRequest::post('type', 'onoff', 'string');

        $app_settings_model = new waAppSettingsModel();

        if($type == 'onoff') {
            $app_settings_model->set(array('shop', 'mlm'), 'enabled', $enabled);
            $this->setPromoCodeFieldInSignupForm($enabled ? 1 : 0);
        } elseif($type == 'probability') {
            $app_settings_model->set(array('shop', 'mlm'), 'probability', $enabled);
        }
    }

    /**
     * Добавлять-ли поле с запросом промо-кода в форму регистрации. Когда плагин
     * выключен — убираем, когда включен — показываем
     *
     * @todo Возможно, здесь также надо проверять наличие этого самого поля и добавлять, если его нет. Не полагаясь на install.php
     *
     * @param int $state 0 - выключить, 1 - включить
     */
    private function setPromoCodeFieldInSignupForm($state)
    {
        $auth_config = $this->getConfig()->getAuth();
        $domain = waSystem::getInstance()->getRouting()->getDomain();

        if(!isset($auth_config[$domain]) || !isset($auth_config[$domain]["fields"])) {
            // Это какая-то фигня, не знаю, что тут делать
            return;
        }

        // Просят выключить
        if($state === 0 && isset($auth_config[$domain]["fields"][shopMlmPlugin::MLM_PROMO_CODE_CONTACTFIELD])) {
            unset($auth_config[$domain]["fields"][shopMlmPlugin::MLM_PROMO_CODE_CONTACTFIELD]);
        }

        // Просят включить
        if($state === 1) {
            $field = waContactFields::get(shopMlmPlugin::MLM_PROMO_CODE_CONTACTFIELD);
            if($field) { // Если поля нет, то что-то надо придумать, но это потом
                $auth_config[$domain]["fields"][shopMlmPlugin::MLM_PROMO_CODE_CONTACTFIELD] = array(
                    'required' => TRUE,
                    'caption' => $field->getName()
                );
            }
        }

        if (!$this->getConfig()->setAuth($config)) {
            $this->errors = sprintf(_w('File could not be saved due to the insufficient file write permissions for the "%s" folder.'), 'wa-config/');
        }

        return;

//        waLog::log(print_r($auth_config, TRUE), 'mlm.log');
//        waLog::log(print_r($domain, TRUE), 'mlm.log');
    }

}
