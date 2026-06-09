<?php
namespace OKSIA\Core;

class Activator {

    public static function activate() {
        self::create_database_tables();
        self::create_uploads_directory();
        self::register_post_types();
        self::add_roles_and_capabilities();
        self::flush_rewrite_rules();
        self::set_default_options();
        self::create_demo_agency();
    }

    private static function create_uploads_directory() {
        if (!file_exists(OKSIA_UPLOAD_DIR)) {
            wp_mkdir_p(OKSIA_UPLOAD_DIR);
        }
    }

    private static function set_default_options() {
        add_option('oksia_base_currency', 'INR');
        add_option('oksia_openai_model', 'gpt-4.1-mini');
        add_option('oksia_allow_public_registration', 1);
        add_option('oksia_pdf_method', 'dompdf');
        add_option('oksia_pdf_page_size', 'A4');
        add_option('oksia_enable_smtp', 0);
    }
}

public static function create_demo_agency() {
    global $wpdb;
    $table = $wpdb->prefix . 'oksia_agencies';

    // Check if demo agency exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE code = %s",
        'OKSIA1'
    ));

    if ($exists == 0) {
        // Create demo agency
        $wpdb->insert($table, [
            'name' => 'Demo Agency',
            'code' => 'OKSIA1',
            'slug' => 'demo-agency',
            'status' => 'active',
            'settings' => serialize([
                'unlimited_quotes' => true,
                'unlimited_ai' => true,
                'unlimited_pdf' => true,
                'is_demo' => true
            ]),
            'created_by' => 1,
            'created_at' => current_time('mysql')
        ]);

        $agency_id = $wpdb->insert_id;

        // Assign platform admin (user ID 1) to demo agency
        update_user_meta(1, 'oksia_agency_code', 'OKSIA1');
        update_user_meta(1, 'oksia_agency_id', $agency_id);
        update_user_meta(1, 'oksia_is_demo_user', true);

        // Give admin all agency capabilities
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('oksia_create_quotes');
            $admin->add_cap('oksia_edit_quotes');
            $admin->add_cap('oksia_confirm_quotes');
            $admin->add_cap('oksia_view_own_quotes');
            $admin->add_cap('oksia_manage_staff');
            $admin->add_cap('oksia_agency_settings');
        }
    }
}

// Ensure demo agency exists and admin has access
add_action('admin_init', function() {
    if (current_user_can('manage_options')) {
        if (class_exists('OKSIA\Core\Activator')) {
            OKSIA\Core\Activator::create_demo_agency();
        }
    }
});
