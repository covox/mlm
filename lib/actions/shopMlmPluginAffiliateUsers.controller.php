<?php
/**
 * Created by PhpStorm.
 * User: snark | itfrogs.ru
 * Date: 10/24/14
 * Time: 11:28 PM
  */

class shopMlmPluginAffiliateUsersController extends waController
{
    public function execute()
    {
        $um = new waUserModel();
        $searchstring = $um->escape(waRequest::get('term', '', 'string'));
        $users = $um->getBySQL('SELECT * FROM wa_contact WHERE name LIKE "%'.$searchstring.'%" OR login LIKE "%'.$searchstring.'%"');

//        var_dump('SELECT * FROM wa_contact WHERE name LIKE "%'.$searchstring.'%" OR login LIKE "%'.$searchstring.'%"');

        $responce = array();
        foreach ($users as $i => $user) {
            array_push($responce, array('id' => $i, 'label' => $user['name'], 'data' => $user));
        }
        print json_encode($responce);
    }
}