<?php
/**
 * Created by PhpStorm.
 * User: snark | itfrogs.ru
 * Date: 31.10.14
 * Time: 0:41
 */

class shopMlmPluginViewHelper extends waViewHelper
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

    public static function getSignupForm($errors = array()) {
       $plugin = self::getPlugin();
        return $plugin->signupForm($errors);
    }




}