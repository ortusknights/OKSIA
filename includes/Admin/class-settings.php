<?php
namespace OKSIA\Admin;

class Settings {

    public function register_settings() {
        register_setting('oksia_settings', 'oksia_base_currency');
        register_setting('oksia_settings', 'oksia_openai_api_key');
        register_setting('oksia_settings', 'oksia_openai_model');
        register_setting('oksia_settings', 'oksia_allow_public_registration');
        register_setting('oksia_settings', 'oksia_pdf_method');
        register_setting('oksia_settings', 'oksia_chrome_path');
        register_setting('oksia_settings', 'oksia_pdf_page_size');
        register_setting('oksia_settings', 'oksia_enable_smtp');
        register_setting('oksia_settings', 'oksia_smtp_host');
        register_setting('oksia_settings', 'oksia_smtp_port');
        register_setting('oksia_settings', 'oksia_smtp_encryption');
        register_setting('oksia_settings', 'oksia_smtp_username');
        register_setting('oksia_settings', 'oksia_smtp_password');

        add_settings_section('oksia_general', 'General Settings', null, 'oksia-settings');
        add_settings_section('oksia_openai', 'OpenAI Configuration', null, 'oksia-settings');
        add_settings_section('oksia_pdf', 'PDF Settings', null, 'oksia-settings');
        add_settings_section('oksia_smtp', 'SMTP Settings', null, 'oksia-settings');
    }

    public function render_field($args) {
        $option = get_option($args['option']);
        echo '<input type="' . $args['type'] . '" name="' . $args['option'] . '" value="' . esc_attr($option) . '" class="regular-text">';
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
}
