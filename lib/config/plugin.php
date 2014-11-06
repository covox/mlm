<?php
return array (
    'name' => 'Multi-level marketing',
    'icon' => 'img/mlm.gif',
    'img' => 'img/mlm.gif',
    'version' => '1.0.0',
    'vendor' => 964801,
    'shop_settings' => true,
    'handlers' =>
        array (
            'backend_settings_affiliate' => 'backendSettingsAffiliate',
            'frontend_my_affiliate' => 'frontendMyAffiliate',
            'order_action.complete' => 'orderActionComplete',
        ),
    'frontend' => true,
);
