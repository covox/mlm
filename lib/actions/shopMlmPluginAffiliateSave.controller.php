<?php

class shopMlmPluginAffiliateSaveController extends waJsonController
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
        $post = waRequest::post();
        $plugin = self::getPlugin();
        $plugin->saveSettings($post);
    }
}