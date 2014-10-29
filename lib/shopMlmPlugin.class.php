<?php

class shopMlmPlugin extends shopPlugin
{
    public function backendSettingsAffiliate()
    {
        return array(
            'id' => 'Mlm',
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
}
