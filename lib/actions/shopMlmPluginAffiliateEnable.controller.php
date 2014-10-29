<?php

class shopMlmPluginAffiliateEnableController extends waJsonController
{
    public function execute()
    {
            $enabled = waRequest::post('enabled', 0, 'int');
        $type = waRequest::post('type', 'onoff', 'string');

        $app_settings_model = new waAppSettingsModel();

        if ($type == 'onoff') {
            $app_settings_model->set(array('shop', 'mlm'), 'enabled', $enabled);
        }
        elseif ($type == 'probability') {
            $app_settings_model->set(array('shop', 'mlm'), 'probability', $enabled);
        }
    }
}