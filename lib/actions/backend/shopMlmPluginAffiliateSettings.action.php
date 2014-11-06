<?php

class shopMlmPluginAffiliateSettingsAction extends waViewAction
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
        $default_settings = array(
            'enabled' => 0,
            'probability' => 0,
            'rate' => 100,
            'level_1_percent' => 1,
            'level_2_percent' => 1,
            'level_3_percent' => 1,
            'notifications' => 0,
            'promo' => '',
            'terms' => ''
        );
        
        $settings = array_merge($default_settings, self::getPlugin()->getSettings());
        $this->view->assign('settings', $settings);
        $this->view->assign('enabled', $settings['enabled']);
        $this->view->assign('probability', $settings['probability']);
        
        if(!$this->mlmContactFieldExists()) {
            $this->mlmContactFieldCreate();
        }

        $owners = array();
        $um = new waUserModel();
        if (!empty($settings['owners'])) {
            foreach ($settings['owners'] as $key => $owner) {
                $owners[$key]['user'] = $um->getById($key);
                $owners[$key]['mlmweight'] = $owner['mlmweight'];
            }

        }

//        print '<pre>';
//        var_dump($owners);
//        print '</pre>';


        $this->view->assign('owners', $owners);

        $c = waCurrency::getInfo(wa()->getConfig()->getCurrency());
        $this->view->assign('currency', ifset($c['sign'], wa()->getConfig()->getCurrency()));
    }
    
    private function mlmContactFieldCreate()
    {
        $field_options = array(
            'app_id' => 'shop.mlm',
            
        );
        $field = new waContactStringField(shopMlmPlugin::MLM_PROMO_CODE_CONTACTFIELD, 'Code', $field_options);
        
        waContactFields::updateField($field);
        waContactFields::enableField($field, 'person');
    }

    private function mlmContactFieldExists()
    {
        return !is_null(waContactFields::get(shopMlmPlugin::MLM_PROMO_CODE_CONTACTFIELD));
    }
}