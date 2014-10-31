<?php

class shopMlmPluginBackendSaveController extends waJsonController {

    protected $plugin_id = array('shop', 'mlm');

    public function execute() {
        $templates = array(
            'signup' => array('name' => _wp('MLM sign up template (signup.html)'), 'tpl_path' => 'plugins/mlm/templates/signup.html', 'public' => false),
        );

        try {
            $app_settings_model = new waAppSettingsModel();
            $settings = waRequest::post('shop_mlm', array());

            foreach ($settings as $name => $value) {
                $app_settings_model->set($this->plugin_id, $name, $value);
            }

            $post_templates = waRequest::post('templates');
            $reset_tpls = waRequest::post('reset_tpls');

            foreach ($templates as $id => $template) {
                if (isset($reset_tpls[$id])) {
                    $template_path = wa()->getDataPath($template['tpl_path'], $template['public'], 'shop', true);
                    @unlink($template_path);
                } else {

                    if (!isset($post_templates[$id])) {
                        throw new waException('Не определён шаблон');
                    }
                    $post_template = $post_templates[$id];

                    $template_path = wa()->getDataPath($template['tpl_path'], $template['public'], 'shop', true);
                    if (!file_exists($template_path)) {
                        $template_path = wa()->getAppPath($template['tpl_path'], 'shop');
                    }

                    $template_content = file_get_contents($template_path);
                    if ($template_content != $post_template) {
                        $template_path = wa()->getDataPath($template['tpl_path'], $template['public'], 'shop', true);

                        $f = fopen($template_path, 'w');
                        if (!$f) {
                            throw new waException('Не удаётся сохранить шаблон. Проверьте права на запись ' . $template_path);
                        }
                        fwrite($f, $post_template);
                        fclose($f);
                    }
                }
            }

            $this->response['message'] = "Сохранено";
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }

}
