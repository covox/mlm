<?php
return array(
    'shop_mlm_customers' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'left_key' => array('int', 11),
        'right_key' => array('int', 11),
        'depth' => array('int', 11, 'null' => 0),
        'parent_id' => array('int', 11, 'null' => 0, 'default' => '0'),
        'contact_id' => array('int', 11, 'null' => 0),
        'parent_contact_id' => array('int', 11, 'null' => 0),
        'code' => array('int', 11, 'null' => 0),
        'create_datetime' => array('datetime'),
        ':keys' => array(
            'PRIMARY' => array('id', 'contact_id'),
            'parent_id' => array('parent_id', 'unique' => 1),
            'left_key' => 'left_key',
            'right_key' => 'right_key',
        ),
    ),
);
