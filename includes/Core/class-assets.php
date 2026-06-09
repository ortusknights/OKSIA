<?php
namespace OKSIA\Core;

class Assets {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'oksia') === false) {
            return;
        }

        wp_enqueue_style('oksia-admin', OKSIA_PLUGIN_URL . 'assets/css/admin.css', [], OKSIA_VERSION);
        wp_enqueue_script('oksia-admin', OKSIA_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], OKSIA_VERSION, true);

        wp_localize_script('oksia-admin', 'oksia_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oksia_admin_nonce')
        ]);
    }

    public function enqueue_frontend_assets() {
        if (!is_page() && !has_shortcode(get_post()->post_content, 'oksia_dashboard') &&
            !has_shortcode(get_post()->post_content, 'oksia_client_intake') &&
            !has_shortcode(get_post()->post_content, 'oksia_agent_intake') &&
            !has_shortcode(get_post()->post_content, 'oksia_quote_viewer')) {
            return;
        }

        wp_enqueue_style('oksia-frontend', OKSIA_PLUGIN_URL . 'assets/css/frontend.css', [], OKSIA_VERSION);
        wp_enqueue_script('oksia-frontend', OKSIA_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], OKSIA_VERSION, true);

        wp_localize_script('oksia-frontend', 'oksia_frontend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oksia_frontend_nonce')
        ]);
    }
}
