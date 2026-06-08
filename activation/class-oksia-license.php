<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_License {
    const OPTION_KEY = 'oksia_license_key';
    const OPTION_STATUS = 'oksia_license_status';
    const OPTION_MODE = 'oksia_license_mode';
    const OPTION_ACTIVATED_AT = 'oksia_license_activated_at';
    const OPTION_SERVER_URL = 'oksia_license_server_url';
    const OPTION_VALIDATED_AT = 'oksia_license_validated_at';
    const OPTION_VALIDATION_MESSAGE = 'oksia_license_validation_message';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function is_dev_license_key($license_key) {
        $license_key = trim((string) $license_key);
        if ('' === $license_key) {
            return false;
        }

        return (bool) preg_match('/021087$/', $license_key);
    }

    public static function get_license_key() {
        return trim((string) get_option(self::OPTION_KEY, ''));
    }

    public static function get_license_status() {
        return trim((string) get_option(self::OPTION_STATUS, 'inactive'));
    }

    public static function is_active() {
        $license_key = self::get_license_key();
        $status = self::get_license_status();

        if ('active' !== $status || '' === $license_key) {
            return false;
        }

        if (self::is_dev_license_key($license_key)) {
            return true;
        }

        return 'verified' === trim((string) get_option(self::OPTION_MODE, 'manual'));
    }

    public static function get_license_server_url() {
        return trim((string) get_option(self::OPTION_SERVER_URL, ''));
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_post_oksia_save_license', array($this, 'handle_save'));
        add_action('admin_notices', array($this, 'maybe_show_admin_notice'));
    }

    public function register_menu() {
        add_menu_page(
            __('OK License', 'oksia-smart-itinerary-agent'),
            __('OK License', 'oksia-smart-itinerary-agent'),
            'manage_options',
            'oksia-license',
            array($this, 'render_page'),
            'dashicons-shield-alt',
            58
        );
    }

    public function maybe_show_admin_notice() {
        if (!current_user_can('manage_options') || self::is_active()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && 'toplevel_page_oksia-license' === $screen->id) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('OK - Smart Itinerary Agent is not activated yet. Open OK License and enter a valid development key ending in 021087.', 'oksia-smart-itinerary-agent');
        echo '</p></div>';
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage license settings.', 'oksia-smart-itinerary-agent'));
        }

        check_admin_referer('oksia_license_save');

        $license_key = sanitize_text_field(wp_unslash($_POST['oksia_license_key'] ?? ''));
        $server_url = esc_url_raw(trim((string) wp_unslash($_POST['oksia_license_server_url'] ?? '')));
        $status = 'inactive';
        $mode = 'manual';
        $message = __('License not validated.', 'oksia-smart-itinerary-agent');

        if ('' !== $license_key && self::is_dev_license_key($license_key)) {
            $status = 'active';
            $mode = 'development';
            update_option(self::OPTION_ACTIVATED_AT, current_time('mysql'), false);
            $message = __('Development key accepted.', 'oksia-smart-itinerary-agent');
        } elseif ('' !== $license_key) {
            $remote = $this->verify_remote_license($license_key);
            if (is_wp_error($remote)) {
                $message = $remote->get_error_message();
            } elseif (!empty($remote['active'])) {
                $status = 'active';
                $mode = 'verified';
                update_option(self::OPTION_VALIDATED_AT, current_time('mysql'), false);
                $message = __('License verified.', 'oksia-smart-itinerary-agent');
            } else {
                $message = __('License was not accepted by the license server.', 'oksia-smart-itinerary-agent');
            }
        }

        update_option(self::OPTION_KEY, $license_key, false);
        update_option(self::OPTION_SERVER_URL, $server_url, false);
        update_option(self::OPTION_STATUS, $status, false);
        update_option(self::OPTION_MODE, $mode, false);
        update_option(self::OPTION_VALIDATION_MESSAGE, $message, false);

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'oksia-license',
                    'oksia_license_saved' => '1',
                    'oksia_license_status' => $status,
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $license_key = self::get_license_key();
        $status = self::get_license_status();
        $mode = trim((string) get_option(self::OPTION_MODE, 'manual'));
        $activated_at = trim((string) get_option(self::OPTION_ACTIVATED_AT, ''));
        $validated_at = trim((string) get_option(self::OPTION_VALIDATED_AT, ''));
        $validation_message = trim((string) get_option(self::OPTION_VALIDATION_MESSAGE, ''));
        $server_url = self::get_license_server_url();
        $saved = isset($_GET['oksia_license_saved']) ? sanitize_text_field(wp_unslash($_GET['oksia_license_saved'])) : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('OK License', 'oksia-smart-itinerary-agent'); ?></h1>
            <?php if ('1' === $saved) : ?>
                <div class="notice notice-success inline"><p><?php esc_html_e('License settings saved.', 'oksia-smart-itinerary-agent'); ?></p></div>
            <?php endif; ?>

            <div class="card" style="max-width: 760px;">
                <h2><?php esc_html_e('Activation', 'oksia-smart-itinerary-agent'); ?></h2>
                <p>
                    <?php esc_html_e('In development, any key ending with 021087 activates the plugin.', 'oksia-smart-itinerary-agent'); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Status:', 'oksia-smart-itinerary-agent'); ?></strong>
                    <?php echo esc_html(ucfirst($status)); ?>
                    <?php if ('' !== $mode) : ?>
                        <br />
                        <strong><?php esc_html_e('Mode:', 'oksia-smart-itinerary-agent'); ?></strong>
                        <?php echo esc_html(ucfirst($mode)); ?>
                    <?php endif; ?>
                    <?php if ('' !== $activated_at) : ?>
                        <br />
                        <strong><?php esc_html_e('Activated At:', 'oksia-smart-itinerary-agent'); ?></strong>
                        <?php echo esc_html($activated_at); ?>
                    <?php endif; ?>
                    <?php if ('' !== $validated_at) : ?>
                        <br />
                        <strong><?php esc_html_e('Validated At:', 'oksia-smart-itinerary-agent'); ?></strong>
                        <?php echo esc_html($validated_at); ?>
                    <?php endif; ?>
                    <?php if ('' !== $validation_message) : ?>
                        <br />
                        <strong><?php esc_html_e('Message:', 'oksia-smart-itinerary-agent'); ?></strong>
                        <?php echo esc_html($validation_message); ?>
                    <?php endif; ?>
                </p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width: 760px; margin-top: 24px;">
                <?php wp_nonce_field('oksia_license_save'); ?>
                <input type="hidden" name="action" value="oksia_save_license" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="oksia_license_key"><?php esc_html_e('License Key', 'oksia-smart-itinerary-agent'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="oksia_license_key" name="oksia_license_key" value="<?php echo esc_attr($license_key); ?>" placeholder="XXXX-XXXX-021087" />
                            <p class="description"><?php esc_html_e('Any key ending with 021087 is accepted in development.', 'oksia-smart-itinerary-agent'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oksia_license_server_url"><?php esc_html_e('License Server URL', 'oksia-smart-itinerary-agent'); ?></label></th>
                        <td>
                            <input type="url" class="regular-text" id="oksia_license_server_url" name="oksia_license_server_url" value="<?php echo esc_attr($server_url); ?>" placeholder="https://your-license-server.example/validate" />
                            <p class="description"><?php esc_html_e('Used for production key validation when the key does not end in 021087.', 'oksia-smart-itinerary-agent'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save License', 'oksia-smart-itinerary-agent')); ?>
            </form>
        </div>
        <?php
    }

    private function verify_remote_license($license_key) {
        $server_url = self::get_license_server_url();
        if ('' === $server_url) {
            return new WP_Error('oksia_license_missing_server', __('Enter a license server URL to validate production keys.', 'oksia-smart-itinerary-agent'));
        }

        $response = wp_remote_post(
            esc_url_raw($server_url),
            array(
                'timeout' => 20,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(
                    array(
                        'license_key' => $license_key,
                        'site_url' => home_url('/'),
                        'plugin' => 'OK - Smart Itinerary Agent',
                        'slug' => 'oksia-smart-itinerary-agent',
                    )
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if (200 !== $code || !is_array($body)) {
            return new WP_Error('oksia_license_bad_response', __('License server returned an invalid response.', 'oksia-smart-itinerary-agent'));
        }

        return $body;
    }
}
