<?php
namespace OKSIA\Core;

class Plugin {

    private $loader;
    private $assets;

    public function __construct() {
        $this->loader = new Loader();
        $this->assets = new Assets();
    }

    public function run() {
        $this->define_admin_hooks();
        $this->define_frontend_hooks();
        $this->define_api_hooks();
        $this->loader->run();
    }

        private function define_admin_hooks() {
        $admin_menu = new \OKSIA\Admin\AdminMenu();
        $this->loader->add_action('admin_menu', $admin_menu, 'register_menus');

        // Add this line - it creates the AJAX handlers (constructor registers everything)
        $admin_ajax = new \OKSIA\Admin\AdminAjaxHandlers();

        $agency_list = new \OKSIA\Admin\AgencyList();
        $this->loader->add_action('admin_init', $agency_list, 'process_bulk_actions');

        $quote_list = new \OKSIA\Admin\QuoteList();
        $this->loader->add_action('admin_init', $quote_list, 'process_bulk_actions');

        $settings = new \OKSIA\Admin\Settings();
        $this->loader->add_action('admin_init', $settings, 'register_settings');
    }


    private function define_frontend_hooks() {
        $shortcodes = new \OKSIA\Frontend\Shortcodes();
        $this->loader->add_action('init', $shortcodes, 'register_all');

        $dashboard = new \OKSIA\Frontend\Dashboard();
        $this->loader->add_action('wp_ajax_oksia_get_stats', $dashboard, 'ajax_get_stats');
        $this->loader->add_action('wp_ajax_oksia_get_quotes', $dashboard, 'ajax_get_quotes');

        $intake = new \OKSIA\Intake\AjaxHandler();
        $this->loader->add_action('wp_ajax_oksia_submit_intake', $intake, 'handle_ajax');
        $this->loader->add_action('wp_ajax_nopriv_oksia_submit_intake', $intake, 'handle_ajax');

        $this->loader->add_action('template_redirect', $this, 'block_admin_access');
        $this->loader->add_filter('show_admin_bar', $this, 'hide_admin_bar');
    }

    private function define_api_hooks() {
        $rest_endpoints = new \OKSIA\API\RestEndpoints();
        $this->loader->add_action('rest_api_init', $rest_endpoints, 'register_routes');
    }

    public function block_admin_access() {
        if (is_admin() && !current_user_can('manage_options')) {
            $agency_user = get_user_meta(get_current_user_id(), 'oksia_agency_code', true);
            if ($agency_user) {
                wp_redirect(home_url('/agency-dashboard'));
                exit;
            }
        }
    }

    public function hide_admin_bar($show) {
        if (current_user_can('oksia_agency_staff') && !current_user_can('manage_options')) {
            return false;
        }
        return $show;
    }
}
