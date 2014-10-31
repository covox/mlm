<?php
/**
 * Created by PhpStorm.
 * User: snark | itfrogs.ru
 * Date: 31.10.14
 * Time: 0:41
 */

class shopMlmPluginViewHelper extends waViewHelper
{

    public static function getSignupForm($errors = array()) {
        $viewHelper = new waViewHelper(waSystem::getInstance()->getView());
        return $viewHelper->signupForm($errors);
    }

    public function signupUrl($absolute = false)
    {
        return $shopUrl = wa()->getRouteUrl('shop/frontend') . '/mlm/signup/';
    }
}