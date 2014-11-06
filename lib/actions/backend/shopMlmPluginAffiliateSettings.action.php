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
        $plugin = self::getPlugin();
        $settings = $plugin->getSettings();
        $this->view->assign('settings', $settings);
        $this->view->assign('enabled', (isset($settings['enabled']) ? $settings['enabled'] : 0 ));
        $this->view->assign('probability', (isset($settings['probability']) ? $settings['probability'] : 0 ));

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
}