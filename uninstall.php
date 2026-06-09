<?php
// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete custom tables
global $wpdb;
$tables = [
    $wpdb->prefix . 'oksia_quotes',
    $wpdb->prefix . 'oksia_agencies'
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Delete custom post types
$wpdb->delete($wpdb->posts, ['post_type' => 'oksia_quote'], ['%s']);
$wpdb->delete($wpdb->posts, ['post_type' => 'oksia_agency'], ['%s']);

// Delete all options
$options = [
    'oksia_base_currency',
    'oksia_openai_api_key',
    'oksia_openai_model',
    'oksia_allow_public_registration',
    'oksia_pdf_method',
    'oksia_chrome_path',
    'oksia_pdf_page_size',
    'oksia_enable_smtp',
    'oksia_smtp_host',
    'oksia_smtp_port',
    'oksia_smtp_encryption',
    'oksia_db_version'
];

foreach ($options as $option) {
    delete_option($option);
}

// Delete user meta
$wpdb->delete($wpdb->usermeta, ['meta_key' => 'oksia_agency_code'], ['%s']);
$wpdb->delete($wpdb->usermeta, ['meta_key' => 'oksia_agency_id'], ['%s']);

// Remove roles
remove_role('oksia_agency_owner');
remove_role('oksia_agency_manager');
remove_role('oksia_agency_staff');

// Delete upload directory
$upload_dir = WP_CONTENT_DIR . '/uploads/oksia-temp/';
if (is_dir($upload_dir)) {
    array_map('unlink', glob("$upload_dir/*.*"));
    rmdir($upload_dir);
}

// Clear any cached data
wp_cache_flush();
