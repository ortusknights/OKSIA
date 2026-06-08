<?php
/**
 * Plugin Name: OK - Smart Itinerary Agent
 * Description: AI-assisted itinerary builder for travel agents with review workflow, branding, and PDF-ready brochure output.
 * Version: 1.0.0
 * Author: OK
 * Text Domain: oksia-smart-itinerary-agent
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OKSIA_VERSION', '1.0.0');
define('OKSIA_FILE', __FILE__);
define('OKSIA_PATH', plugin_dir_path(__FILE__));
define('OKSIA_URL', plugin_dir_url(__FILE__));

require_once OKSIA_PATH . 'includes/class-oksia-plugin.php';


OKSIA_Smart_Itinerary_Agent_Plugin::instance();

