<?php
namespace OKSIA\Templates;

class TemplateManager {

    private $template_paths = [];

    public function __construct() {
        $this->template_paths = [
            'dashboard' => OKSIA_PLUGIN_DIR . 'templates/dashboard/',
            'admin' => OKSIA_PLUGIN_DIR . 'templates/admin/',
            'emails' => OKSIA_PLUGIN_DIR . 'templates/emails/',
            'intake' => OKSIA_PLUGIN_DIR . 'templates/intake/',
            'pdf' => OKSIA_PLUGIN_DIR . 'templates/pdf/'
        ];

        add_filter('theme_template_roots', [$this, 'add_template_root']);
    }

    public function get_template($type, $template_name) {
        $template_path = $this->template_paths[$type] ?? '';

        if (empty($template_path)) {
            return false;
        }

        $full_path = $template_path . $template_name;

        if (file_exists($full_path)) {
            return $full_path;
        }

        return false;
    }

    public function render($type, $template_name, $data = []) {
        $template = $this->get_template($type, $template_name);

        if (!$template) {
            return false;
        }

        extract($data);
        ob_start();
        include $template;
        return ob_get_clean();
    }

    public function add_template_root($roots) {
        $roots[] = OKSIA_PLUGIN_DIR . 'templates/';
        return $roots;
    }

    public function override_template($template, $template_name, $theme_override = true) {
        if ($theme_override) {
            $theme_template = get_stylesheet_directory() . '/oksia/' . $template_name;
            if (file_exists($theme_template)) {
                return $theme_template;
            }
        }

        return $template;
    }
}
