<?php
/**
 * Добавляем в контакты дополнительное поле, если его там еще нет
 */
try {
    $mlm_code_field = new waContactStringField(
            shopMlmPlugin::MLM_PROMO_CODE_CONTACTFIELD,
            array('en_US'=>'Promo Code', 'ru_RU'=>'Промо-код'),
            array('app_id' => 'shop.mlm')
    );

    waContactFields::updateField($field);
    waContactFields::enableField($field, 'person');
} catch (waException $ex) {
    // Что-то делать, если что-то пошло не так
}

