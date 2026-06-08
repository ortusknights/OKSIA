<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once OKSIA_PATH . 'includes/class-oksia-post-types.php';
require_once OKSIA_PATH . 'includes/class-oksia-ai-service.php';
require_once OKSIA_PATH . 'includes/class-oksia-agencies.php';
require_once OKSIA_PATH . 'includes/class-oksia-quote-templates.php';
require_once OKSIA_PATH . 'includes/class-oksia-workspace.php';
require_once OKSIA_PATH . 'includes/class-oksia-rest.php';
require_once OKSIA_PATH . 'includes/class-oksia-admin.php';
require_once OKSIA_PATH . 'includes/class-oksia-frontend.php';
require_once OKSIA_PATH . 'includes/class-oksia-intake.php';
require_once OKSIA_PATH . 'includes/class-oksia-support.php';

class OKSIA_Smart_Itinerary_Agent_Plugin {
    private static $instance = null;

    public $post_types;
    public $ai_service;
    public $agencies;
    public $workspace;
    public $rest;
    public $frontend;
    public $admin;
    public $intake;
    public $support;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        self::ensure_front_end_roles();
        self::ensure_admin_settings_cap();
        $this->post_types = new OKSIA_Post_Types();
        $this->ai_service = new OKSIA_AI_Service();
        $this->agencies   = OKSIA_Agencies::instance();
        $this->workspace  = OKSIA_Workspace::instance();
        $this->rest       = new OKSIA_REST();
        $this->frontend   = new OKSIA_Frontend();
        $this->admin      = new OKSIA_Admin($this->ai_service, $this->frontend, $this->agencies, $this->workspace);
        $this->intake     = new OKSIA_Intake();
        $this->support    = new OKSIA_Support();

        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    public function load_textdomain() {
        load_plugin_textdomain('oksia-smart-itinerary-agent', false, dirname(plugin_basename(OKSIA_FILE)) . '/languages');
    }

    public static function activate() {
        self::cleanup_roles_and_caps();
        update_option('oksia_legacy_roles_cleaned_up', '1', false);
        self::ensure_front_end_roles();
        self::ensure_admin_settings_cap();
        OKSIA_Workspace::activate();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        OKSIA_Workspace::deactivate();
        flush_rewrite_rules();
    }

    private static function maybe_cleanup_roles_and_caps() {
        if ('1' === (string) get_option('oksia_legacy_roles_cleaned_up', '0')) {
            return;
        }

        self::cleanup_roles_and_caps();
        update_option('oksia_legacy_roles_cleaned_up', '1', false);
    }

    private static function cleanup_roles_and_caps() {
        $legacy_roles = array(
            'oksia_admin' => 'administrator',
            'oksia_agency_owner' => 'subscriber',
            'oksia_agency_manager' => 'subscriber',
            'oksia_agency_staff' => 'subscriber',
        );

        foreach ($legacy_roles as $legacy_role => $fallback_role) {
            $users = get_users(array(
                'role' => $legacy_role,
                'fields' => array('ID'),
                'number' => -1,
            ));

            foreach ($users as $user_row) {
                $user = get_user_by('id', absint($user_row->ID));
                if ($user instanceof WP_User) {
                    $user->set_role($fallback_role);
                }
            }
        }

        remove_role('oksia_admin');
        remove_role('oksia_agency_owner');
        remove_role('oksia_agency_manager');
        remove_role('oksia_agency_staff');
    }

    private static function ensure_front_end_roles() {
        $role_map = array(
            'oksia_agency' => __('OKSIA Agency', 'oksia-smart-itinerary-agent'),
            'oksia_manager' => __('OKSIA Manager', 'oksia-smart-itinerary-agent'),
            'oksia_employee' => __('OKSIA Employee', 'oksia-smart-itinerary-agent'),
        );

        $caps = array(
            'read' => true,
            'upload_files' => true,
        );

        foreach ($role_map as $role_slug => $label) {
            if (null === get_role($role_slug)) {
                add_role($role_slug, $label, $caps);
            } else {
                $role = get_role($role_slug);
                if ($role instanceof WP_Role) {
                    foreach ($caps as $cap => $enabled) {
                        if ($enabled) {
                            $role->add_cap($cap);
                        }
                    }
                }
            }
        }
    }

    private static function ensure_admin_settings_cap() {
        $wp_admin_role = get_role('administrator');
        if ($wp_admin_role instanceof WP_Role) {
            $wp_admin_role->add_cap('oksia_manage_settings');
        }
    }
}
