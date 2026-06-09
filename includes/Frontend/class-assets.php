<?php
namespace OKSIA\Frontend;

class Assets {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        global $post;

        if (!isset($post)) {
            return;
        }

        $has_shortcode = false;
        $shortcodes = ['oksia_client_intake', 'oksia_agent_intake', 'oksia_dashboard', 'oksia_quote_viewer'];

        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                $has_shortcode = true;
                break;
            }
        }

        if (!$has_shortcode) {
            return;
        }

        wp_enqueue_style('oksia-frontend', OKSIA_PLUGIN_URL . 'assets/css/frontend.css', [], OKSIA_VERSION);
        wp_enqueue_script('oksia-frontend', OKSIA_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], OKSIA_VERSION, true);
        wp_enqueue_script('oksia-intake', OKSIA_PLUGIN_URL . 'assets/js/intake.js', ['jquery'], OKSIA_VERSION, true);
        wp_enqueue_script('oksia-dashboard', OKSIA_PLUGIN_URL . 'assets/js/dashboard.js', ['jquery'], OKSIA_VERSION, true);

        wp_localize_script('oksia-intake', 'oksia_intake', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oksia_frontend_nonce'),
            'strings' => [
                'required' => 'This field is required',
                'submitting' => 'Submitting...',
                'success' => 'Request submitted successfully!',
                'error' => 'An error occurred. Please try again.'
            ]
        ]);

        wp_localize_script('oksia-dashboard', 'oksia_dashboard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oksia_frontend_nonce')
        ]);
    }
}
