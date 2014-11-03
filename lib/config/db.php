<?php
return array(
    'shop_mlm_customers' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'contact_id' => array('int', 11, 'null' => 0),
        'parent_id' => array('int', 11, 'null' => 0, 'default' => '0'),
        'code' => array('int', 11, 'null' => 0),
        'create_datetime' => array('datetime', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('id', 'contact_id'),
        ),
    ),
);
