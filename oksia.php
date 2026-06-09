<?php
/**
 * Plugin Name: OKSIA - Ortus Knights Structured Itinerary Agent
 * Plugin URI: https://oksia.in
 * Description: Fastest itinerary generation for travel agencies
 * Version: 1.0.0
 * Author: Ortus Knights
 * Text Domain: oksia
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OKSIA_VERSION', '1.0.0');
define('OKSIA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OKSIA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OKSIA_UPLOAD_DIR', WP_CONTENT_DIR . '/uploads/oksia-temp/');

// ============================================
// SIMPLE MENU - WORKING VERSION
// ============================================
add_action('admin_menu', 'oksia_simple_admin_menu');

function oksia_simple_admin_menu() {
    add_menu_page(
        'OKSIA',
        'OKSIA',
        'manage_options',
        'oksia',
        'oksia_main_page',
        'dashicons-airplane',
        25
    );

    add_submenu_page(
        'oksia',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'oksia',
        'oksia_main_page'
    );

    add_submenu_page(
        'oksia',
        'Agencies',
        'Agencies',
        'manage_options',
        'oksia-agencies',
        'oksia_agencies_page'
    );

    add_submenu_page(
        'oksia',
        'Quotes',
        'Quotes',
        'manage_options',
        'oksia-quotes',
        'oksia_quotes_page'
    );

    add_submenu_page(
        'oksia',
        'Settings',
        'Settings',
        'manage_options',
        'oksia-settings',
        'oksia_settings_page'
    );
}

// ============================================
// PAGE FUNCTIONS
// ============================================

function oksia_main_page() {
    ?>
    <div class="wrap">
        <h1>OKSIA - Ortus Knights Structured Itinerary Agent</h1>
        <p>Version: <?php echo OKSIA_VERSION; ?></p>
        <hr>
        <h2>Quick Links</h2>
        <ul>
            <li><code>[oksia_client_intake]</code> - Client Intake Form</li>
            <li><code>[oksia_agent_intake]</code> - Agent Intake Form</li>
            <li><code>[oksia_dashboard]</code> - Agency Dashboard</li>
            <li><code>[oksia_agency_settings]</code> - Agency Settings</li>
            <li><code>[oksia_agency_registration]</code> - Agency Registration</li>
        </ul>
    </div>
    <?php
}

function oksia_agencies_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'oksia_agencies';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        echo '<div class="wrap"><h1>Agencies</h1><div class="notice notice-error"><p>Database table not found. Please deactivate and reactivate the plugin.</p></div></div>';
        return;
    }

    $agencies = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    ?>
    <div class="wrap">
        <h1>Agencies</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Code</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
                <?php if (empty($agencies)): ?>
                    <tr><td colspan="5">No agencies found.</td></tr>
                <?php else: ?>
                    <?php foreach ($agencies as $agency): ?>
                        <tr>
                            <td><?php echo $agency->id; ?></td>
                            <td><?php echo esc_html($agency->name); ?></td>
                            <td><code><?php echo esc_html($agency->code); ?></code></td>
                            <td><span style="background:#d4edda;padding:3px 8px;border-radius:3px;"><?php echo esc_html($agency->status); ?></span></td>
                            <td><?php echo date('d/m/Y', strtotime($agency->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function oksia_quotes_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'oksia_quotes';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        echo '<div class="wrap"><h1>Quotes</h1><div class="notice notice-error"><p>Database table not found.</p></div></div>';
        return;
    }

    $quotes = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    ?>
    <div class="wrap">
        <h1>All Quotes</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr><th>ID</th><th>Quote #</th><th>Status</th><th>Version</th><th>Views</th><th>Created</th></tr>
            </thead>
            <tbody>
                <?php if (empty($quotes)): ?>
                    <tr><td colspan="6">No quotes found.</td></tr>
                <?php else: ?>
                    <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td><?php echo $quote->id; ?></td>
                            <td><code><?php echo esc_html($quote->quote_number); ?></code></td>
                            <td><?php echo esc_html($quote->status); ?></td>
                            <td>v<?php echo $quote->version; ?></td>
                            <td><?php echo $quote->view_count; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($quote->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function oksia_settings_page() {
    // Save settings
    if (isset($_POST['oksia_save_settings'])) {
        check_admin_referer('oksia_settings');
        update_option('oksia_base_currency', sanitize_text_field($_POST['base_currency']));
        update_option('oksia_openai_api_key', sanitize_text_field($_POST['openai_api_key']));
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }

    $base_currency = get_option('oksia_base_currency', 'INR');
    $openai_api_key = get_option('oksia_openai_api_key', '');
    ?>
    <div class="wrap">
        <h1>OKSIA Settings</h1>
        <form method="post">
            <?php wp_nonce_field('oksia_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="base_currency">Base Currency</label></th>
                    <td>
                        <select name="base_currency" id="base_currency">
                            <option value="USD" <?php selected($base_currency, 'USD'); ?>>USD ($)</option>
                            <option value="EUR" <?php selected($base_currency, 'EUR'); ?>>EUR (€)</option>
                            <option value="GBP" <?php selected($base_currency, 'GBP'); ?>>GBP (£)</option>
                            <option value="INR" <?php selected($base_currency, 'INR'); ?>>INR (₹)</option>
                            <option value="AUD" <?php selected($base_currency, 'AUD'); ?>>AUD (A$)</option>
                            <option value="CAD" <?php selected($base_currency, 'CAD'); ?>>CAD (C$)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="openai_api_key">OpenAI API Key</label></th>
                    <td>
                        <input type="password" name="openai_api_key" id="openai_api_key" value="<?php echo esc_attr($openai_api_key); ?>" class="regular-text">
                        <p class="description">Get your key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings', 'primary', 'oksia_save_settings'); ?>
        </form>
    </div>
    <?php
}

// ============================================
// ACTIVATION HOOK - Create demo agency
// ============================================
register_activation_hook(__FILE__, 'oksia_install');

function oksia_install() {
    // Create upload directory
    if (!file_exists(OKSIA_UPLOAD_DIR)) {
        wp_mkdir_p(OKSIA_UPLOAD_DIR);
    }

    // Set default options
    add_option('oksia_base_currency', 'INR');
    add_option('oksia_openai_model', 'gpt-4.1-mini');
}

// ============================================
// FRONTEND SHORTCODES (Working versions)
// ============================================

add_shortcode('oksia_client_intake', 'oksia_client_intake_form');
function oksia_client_intake_form() {
    return '<div style="padding:20px;background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <h2>Request a Travel Quote</h2>
        <form>
            <input type="text" placeholder="Your Name" style="width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:5px;">
            <input type="email" placeholder="Your Email" style="width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:5px;">
            <input type="text" placeholder="Destination" style="width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:5px;">
            <button type="submit" style="background:#3498db;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;">Submit</button>
        </form>
    </div>';
}

add_shortcode('oksia_agent_intake', 'oksia_agent_intake_form');
function oksia_agent_intake_form() {
    return '<div style="padding:20px;background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <h2>Agent Intake Form (Demo)</h2>
        <p>Create quote for client</p>
        <form>
            <input type="text" placeholder="Client Name" style="width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:5px;">
            <input type="email" placeholder="Email" style="width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:5px;">
            <input type="text" placeholder="Destination" style="width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:5px;">
            <button type="submit" style="background:#3498db;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;">Create Quote</button>
        </form>
    </div>';
}

add_shortcode('oksia_dashboard', 'oksia_agency_dashboard');
function oksia_agency_dashboard() {
    return '<div style="padding:20px;background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <h2>Agency Dashboard (Demo)</h2>
        <p>Welcome to your agency dashboard</p>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-top:20px;">
            <div style="background:#f8f9fa;padding:15px;text-align:center;border-radius:8px;"><div style="font-size:24px;font-weight:bold;">0</div><div>Total Quotes</div></div>
            <div style="background:#f8f9fa;padding:15px;text-align:center;border-radius:8px;"><div style="font-size:24px;font-weight:bold;">0</div><div>Draft</div></div>
            <div style="background:#f8f9fa;padding:15px;text-align:center;border-radius:8px;"><div style="font-size:24px;font-weight:bold;">0</div><div>Confirmed</div></div>
        </div>
    </div>';
}

add_shortcode('oksia_agency_settings', 'oksia_agency_settings_page');
function oksia_agency_settings_page() {
    return '<div style="padding:20px;background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <h2>Agency Settings (Demo)</h2>
        <form>
            <label style="display:block;margin-bottom:5px;">Currency</label>
            <select style="width:100%;padding:10px;margin-bottom:15px;border:1px solid #ddd;border-radius:5px;">
                <option>USD</option><option>EUR</option><option>INR</option>
            </select>
            <button type="submit" style="background:#3498db;color:white;padding:10px 20px;border:none;border-radius:5px;">Save Settings</button>
        </form>
    </div>';
}

add_shortcode('oksia_agency_registration', 'oksia_agency_registration_page');
function oksia_agency_registration_page() {
    return '<div style="padding:20px;background:white;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <h2>Register Your Agency</h2>
        <form>
            <input type="text" placeholder="Agency Name" style="width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:5px;">
            <input type="email" placeholder="Admin Email" style="width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:5px;">
            <input type="password" placeholder="Password" style="width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:5px;">
            <button type="submit" style="background:#3498db;color:white;padding:10px 20px;border:none;border-radius:5px;">Register</button>
        </form>
    </div>';
}

// ============================================
// DEACTIVATION
// ============================================
register_deactivation_hook(__FILE__, 'oksia_deactivate');
function oksia_deactivate() {
    flush_rewrite_rules();
}

add_action('admin_init', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'oksia_agencies';

    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    if ($count == 0) {
        $wpdb->insert($table, [
            'name' => 'Demo Agency',
            'code' => 'OKSIA1',
            'slug' => 'demo-agency',
            'status' => 'active',
            'created_by' => 1,
            'created_at' => current_time('mysql')
        ]);

        update_user_meta(1, 'oksia_agency_code', 'OKSIA1');

        echo '<div class="notice notice-success">Demo agency created!</div>';
    }
});
