<?php

class shopMlmPluginSettingsAction extends waViewAction
{
    protected $plugin_id = array('shop', 'mlm');

    public function execute()
    {
        $templates = array(
            'signup' => array('name' => _wp('MLM sign up template (signup.html)'), 'tpl_path' => 'plugins/mlm/templates/signup.html', 'public' => false),
        );

        $app_settings_model = new waAppSettingsModel();
        $settings = $app_settings_model->get($this->plugin_id);


        foreach ($templates as &$template) {
            $template['full_path'] = wa()->getDataPath($template['tpl_path'], $template['public'], 'shop', true);
            if (file_exists($template['full_path'])) {
                $template['change_tpl'] = true;
            } else {
                $template['full_path'] = wa()->getAppPath($template['tpl_path'], 'shop');
                $template['change_tpl'] = false;
            }
            $template['template'] = file_get_contents($template['full_path']);
        }
        unset($template);


        $this->view->assign('settings', $settings);
        $this->view->assign('templates', $templates);
    }
}