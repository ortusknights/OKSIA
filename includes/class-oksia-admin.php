<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_Admin {
    private $ai_service;
    private $frontend;
    private $agencies;
    private $workspace;

    public function __construct($ai_service, $frontend = null, $agencies = null, $workspace = null) {
        $this->ai_service = $ai_service;
        $this->frontend = $frontend;
        $this->agencies = $agencies;
        $this->workspace = $workspace;

        add_action('admin_menu', array($this, 'register_oksia_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'register_feature_settings'));
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('save_post_' . OKSIA_Post_Types::POST_TYPE, array($this, 'save_meta_boxes'));
        add_filter('redirect_post_location', array($this, 'maybe_redirect_to_generated_pdf'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('manage_' . OKSIA_Post_Types::POST_TYPE . '_posts_columns', array($this, 'set_admin_columns'));
        add_action('manage_' . OKSIA_Post_Types::POST_TYPE . '_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);
        add_filter('post_row_actions', array($this, 'filter_row_actions'), 10, 2);
        add_filter('bulk_actions-edit-' . OKSIA_Post_Types::POST_TYPE, array($this, 'filter_bulk_actions'));
        add_filter('pre_trash_post', array($this, 'guard_trash_post'), 10, 2);
        add_filter('pre_delete_post', array($this, 'guard_delete_post'), 10, 2);
        add_filter('option_page_capability_oksia_settings', array($this, 'get_master_settings_capability'));
        add_filter('option_page_capability_oksia_feature_settings', array($this, 'get_master_settings_capability'));
        add_action('updated_option', array($this, 'maybe_sync_primary_agency_from_options'), 10, 3);
    }

    public function register_oksia_menu() {
        add_menu_page(
            __('OKSIA', 'oksia-smart-itinerary-agent'),
            __('OKSIA', 'oksia-smart-itinerary-agent'),
            'read',
            'oksia',
            array($this, 'render_oksia_dashboard_page'),
            'dashicons-clipboard',
            1.5
        );

        add_submenu_page(
            'oksia',
            __('OKSIA-WP-DASHBOARD', 'oksia-smart-itinerary-agent'),
            __('Dashboard', 'oksia-smart-itinerary-agent'),
            'read',
            'oksia-wp-dashboard',
            array($this, 'render_oksia_dashboard_page')
        );

        add_submenu_page(
            'oksia',
            __('Agency List', 'oksia-smart-itinerary-agent'),
            __('Agency List', 'oksia-smart-itinerary-agent'),
            'read',
            'oksia-agency-list',
            array($this->agencies ?: OKSIA_Agencies::instance(), 'render_agency_list_page')
        );

        add_submenu_page(
            'oksia',
            __('Settings', 'oksia-smart-itinerary-agent'),
            __('Settings', 'oksia-smart-itinerary-agent'),
            'read',
            'oksia-settings',
            array($this, 'render_oksia_settings_page')
        );

        remove_submenu_page('oksia', 'oksia');
    }

    public function get_master_settings_capability() {
        return 'oksia_manage_settings';
    }

    public function register_feature_settings() {
        register_setting('oksia_feature_settings', 'oksia_feature_flags', array(
            'sanitize_callback' => array($this, 'sanitize_feature_flags'),
        ));
        register_setting('oksia_feature_settings', 'oksia_plan_feature_matrix', array(
            'sanitize_callback' => array($this, 'sanitize_plan_feature_matrix'),
        ));
        register_setting('oksia_feature_settings', 'oksia_supported_currencies', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_feature_settings', 'oksia_world_time_list', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_feature_settings', 'oksia_openai_api_key', array('sanitize_callback' => array($this, 'sanitize_secret')));
        register_setting('oksia_feature_settings', 'oksia_openai_model', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('oksia_feature_settings', 'oksia_intake_tagline', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('oksia_feature_settings', 'oksia_razorpay_key_id', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('oksia_feature_settings', 'oksia_razorpay_key_secret', array('sanitize_callback' => array($this, 'sanitize_secret')));
        register_setting('oksia_feature_settings', 'oksia_razorpay_webhook_secret', array('sanitize_callback' => array($this, 'sanitize_secret')));
        register_setting('oksia_feature_settings', 'oksia_razorpay_mode', array('sanitize_callback' => array($this, 'sanitize_razorpay_mode')));
    }

    public function sanitize_feature_flags($value) {
        $defaults = $this->get_feature_flag_defaults();
        $incoming = is_array($value) ? $value : array();
        $sanitized = array();

        foreach ($defaults as $key => $default_value) {
            $sanitized[$key] = !empty($incoming[$key]) ? 1 : 0;
        }

        return $sanitized;
    }

    public function sanitize_plan_feature_matrix($value) {
        $defaults = $this->get_plan_feature_matrix_defaults();
        $incoming = is_array($value) ? $value : array();
        $sanitized = array();

        foreach ($defaults as $plan_key => $feature_defaults) {
            $plan_incoming = isset($incoming[$plan_key]) && is_array($incoming[$plan_key]) ? $incoming[$plan_key] : array();
            $sanitized[$plan_key] = array();
            foreach ($feature_defaults as $feature_key => $default_value) {
                $sanitized[$plan_key][$feature_key] = !empty($plan_incoming[$feature_key]) ? 1 : 0;
            }
        }

        return $sanitized;
    }

    private function get_feature_flag_defaults() {
        return array(
            'dashboard' => 1,
            'agency_list' => 1,
            'settings' => 1,
            'agency_registration' => 1,
            'agent_intake' => 1,
            'client_intake' => 1,
            'unlimited_quotes' => 1,
            'currency_rates' => 1,
            'world_clock' => 1,
            'age_calculator' => 1,
            'pdf_output' => 1,
            'agency_color' => 1,
            'single_mode' => 1,
            'multi_mode' => 1,
            'quote_share_link' => 1,
            'client_intake_feature' => 1,
            'markup' => 1,
            'inr_conversion' => 1,
        );
    }

    private function get_plan_feature_matrix_defaults() {
        $features = array_keys(array(
            'unlimited_quotes' => 1,
            'currency_rates' => 1,
            'world_clock' => 1,
            'age_calculator' => 1,
            'pdf_output' => 1,
            'agency_color' => 1,
            'single_mode' => 1,
            'multi_mode' => 1,
            'quote_share_link' => 1,
            'client_intake_feature' => 1,
            'markup' => 1,
            'inr_conversion' => 1,
        ));

        $matrix = array();
        foreach (array('economy', 'premium', 'business') as $plan_key) {
            $matrix[$plan_key] = array_fill_keys($features, 1);
        }

        return $matrix;
    }

    public function render_oksia_settings_page() {
        if (!current_user_can('read')) {
            return;
        }

        $flags = wp_parse_args((array) get_option('oksia_feature_flags', array()), $this->get_feature_flag_defaults());
        $enabled_count = count(array_filter($flags));
        $backend_features = array(
            'dashboard' => array(
                'title' => __('Dashboard', 'oksia-smart-itinerary-agent'),
                'description' => __('Show the OKSIA backend dashboard in the admin sidebar.', 'oksia-smart-itinerary-agent'),
            ),
            'agency_list' => array(
                'title' => __('Agency List', 'oksia-smart-itinerary-agent'),
                'description' => __('Show the list of agencies and their trial/subscription data.', 'oksia-smart-itinerary-agent'),
            ),
            'settings' => array(
                'title' => __('Settings', 'oksia-smart-itinerary-agent'),
                'description' => __('Show this feature controls page in the OKSIA menu.', 'oksia-smart-itinerary-agent'),
            ),
        );
        $portal_features = array(
            'agency_registration' => array(
                'title' => __('Agency Registration', 'oksia-smart-itinerary-agent'),
                'description' => __('Keep the public agency signup flow available.', 'oksia-smart-itinerary-agent'),
            ),
            'agent_intake' => array(
                'title' => __('Agent Intake', 'oksia-smart-itinerary-agent'),
                'description' => __('Keep the agent submission workspace available for the front-end portal.', 'oksia-smart-itinerary-agent'),
            ),
            'client_intake' => array(
                'title' => __('Client Intake', 'oksia-smart-itinerary-agent'),
                'description' => __('Keep the client-facing intake form available to share and embed.', 'oksia-smart-itinerary-agent'),
            ),
        );
        $agency_features = array(
            'unlimited_quotes' => array(
                'title' => __('Unlimited Quotes', 'oksia-smart-itinerary-agent'),
                'description' => __('Allow agencies to create as many quotes as the plan permits.', 'oksia-smart-itinerary-agent'),
            ),
            'currency_rates' => array(
                'title' => __('Currency Rates', 'oksia-smart-itinerary-agent'),
                'description' => __('Show live currency conversion help in the workspace and quote flow.', 'oksia-smart-itinerary-agent'),
            ),
            'world_clock' => array(
                'title' => __('World Clock', 'oksia-smart-itinerary-agent'),
                'description' => __('Show time helpers for destination planning.', 'oksia-smart-itinerary-agent'),
            ),
            'age_calculator' => array(
                'title' => __('Age Calculator', 'oksia-smart-itinerary-agent'),
                'description' => __('Show the age helper used for child pricing and travel details.', 'oksia-smart-itinerary-agent'),
            ),
            'pdf_output' => array(
                'title' => __('PDF Output', 'oksia-smart-itinerary-agent'),
                'description' => __('Allow PDF quote export and download.', 'oksia-smart-itinerary-agent'),
            ),
            'agency_color' => array(
                'title' => __('Agency Color', 'oksia-smart-itinerary-agent'),
                'description' => __('Allow agency brand colors to drive the quote theme.', 'oksia-smart-itinerary-agent'),
            ),
            'single_mode' => array(
                'title' => __('Single Mode', 'oksia-smart-itinerary-agent'),
                'description' => __('Enable single-vendor quote calculations.', 'oksia-smart-itinerary-agent'),
            ),
            'multi_mode' => array(
                'title' => __('Multi Mode', 'oksia-smart-itinerary-agent'),
                'description' => __('Enable multi-vendor quote calculations.', 'oksia-smart-itinerary-agent'),
            ),
            'quote_share_link' => array(
                'title' => __('Quote Share Link', 'oksia-smart-itinerary-agent'),
                'description' => __('Allow generating and sharing the client quote link.', 'oksia-smart-itinerary-agent'),
            ),
            'client_intake_feature' => array(
                'title' => __('Client Intake', 'oksia-smart-itinerary-agent'),
                'description' => __('Allow the client intake page and embedded form to stay active.', 'oksia-smart-itinerary-agent'),
            ),
            'markup' => array(
                'title' => __('Markup', 'oksia-smart-itinerary-agent'),
                'description' => __('Allow markup controls in the quote pricing flow.', 'oksia-smart-itinerary-agent'),
            ),
            'inr_conversion' => array(
                'title' => __('INR Conversion', 'oksia-smart-itinerary-agent'),
                'description' => __('Show INR reference conversions for foreign-currency quotes.', 'oksia-smart-itinerary-agent'),
            ),
        );
        $plan_matrix = wp_parse_args((array) get_option('oksia_plan_feature_matrix', array()), $this->get_plan_feature_matrix_defaults());
        ?>
        <div class="wrap oksia-settings-page">
            <div class="oksia-settings-hero" style="background:linear-gradient(135deg,#173f68 0%,#1f7a8c 100%);color:#fff;padding:24px;border-radius:16px;margin:16px 0 24px;">
                <h1 style="color:#fff;margin-top:0;"><?php esc_html_e('OKSIA Settings', 'oksia-smart-itinerary-agent'); ?></h1>
                <p style="max-width:860px;margin-bottom:0;"><?php esc_html_e('Use this screen to turn features on or off for the OKSIA backend and front-end portal. Agency master settings and subscription controls will be added here later.', 'oksia-smart-itinerary-agent'); ?></p>
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:0 0 24px;">
                <div class="card"><h2 style="margin:0 0 8px;"><?php echo esc_html(number_format_i18n($enabled_count)); ?></h2><p style="margin:0;"><?php esc_html_e('Enabled feature toggles', 'oksia-smart-itinerary-agent'); ?></p></div>
                <div class="card"><h2 style="margin:0 0 8px;"><?php echo esc_html(number_format_i18n(count($backend_features))); ?></h2><p style="margin:0;"><?php esc_html_e('Backend controls', 'oksia-smart-itinerary-agent'); ?></p></div>
                <div class="card"><h2 style="margin:0 0 8px;"><?php echo esc_html(number_format_i18n(count($portal_features))); ?></h2><p style="margin:0;"><?php esc_html_e('Portal controls', 'oksia-smart-itinerary-agent'); ?></p></div>
                <div class="card"><h2 style="margin:0 0 8px;">Soon</h2><p style="margin:0;"><?php esc_html_e('Subscription rules and pricing', 'oksia-smart-itinerary-agent'); ?></p></div>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
                <?php settings_fields('oksia_feature_settings'); ?>

                <div class="postbox" style="padding:0 16px 8px;margin-bottom:16px;">
                    <h2 style="padding:14px 0 6px;margin:0;"><?php esc_html_e('Backend Navigation', 'oksia-smart-itinerary-agent'); ?></h2>
                    <p class="description"><?php esc_html_e('Control which items appear under the OKSIA admin menu.', 'oksia-smart-itinerary-agent'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php foreach ($backend_features as $flag_key => $meta) : ?>
                            <?php $this->render_feature_toggle_row($flag_key, $meta, $flags); ?>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div class="postbox" style="padding:0 16px 8px;margin-bottom:16px;">
                    <h2 style="padding:14px 0 6px;margin:0;"><?php esc_html_e('Front-End Portal', 'oksia-smart-itinerary-agent'); ?></h2>
                    <p class="description"><?php esc_html_e('Control the public and agency-facing screens used for quoting and onboarding.', 'oksia-smart-itinerary-agent'); ?></p>
                    <table class="form-table" role="presentation">
                        <?php foreach ($portal_features as $flag_key => $meta) : ?>
                            <?php $this->render_feature_toggle_row($flag_key, $meta, $flags); ?>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div class="postbox" style="padding:0 16px 8px;margin-bottom:16px;">
                    <h2 style="padding:14px 0 6px;margin:0;"><?php esc_html_e('Agency Feature Switches', 'oksia-smart-itinerary-agent'); ?></h2>
                    <p class="description"><?php esc_html_e('Turn agency-facing product features on or off per subscription plan. Time and Currency stay platform-only.', 'oksia-smart-itinerary-agent'); ?></p>
                    <table class="widefat fixed striped" role="presentation" style="margin-top:12px;">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Feature', 'oksia-smart-itinerary-agent'); ?></th>
                                <th scope="col" style="width:18%;"><?php esc_html_e('Economy', 'oksia-smart-itinerary-agent'); ?></th>
                                <th scope="col" style="width:18%;"><?php esc_html_e('Premium', 'oksia-smart-itinerary-agent'); ?></th>
                                <th scope="col" style="width:18%;"><?php esc_html_e('Business', 'oksia-smart-itinerary-agent'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agency_features as $flag_key => $meta) : ?>
                                <?php $this->render_plan_matrix_row($flag_key, $meta, $plan_matrix); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="postbox" style="padding:0 16px 8px;margin-bottom:16px;">
                    <h2 style="padding:14px 0 6px;margin:0;"><?php esc_html_e('Platform Controls', 'oksia-smart-itinerary-agent'); ?></h2>
                    <p class="description"><?php esc_html_e('These values are controlled only by OKSIA and are not editable by agencies.', 'oksia-smart-itinerary-agent'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Currencies List', 'oksia-smart-itinerary-agent'); ?></th>
                            <td>
                                <textarea name="oksia_supported_currencies" rows="5" class="large-text"><?php echo esc_textarea((string) get_option('oksia_supported_currencies', "INR\nUSD\nEUR\nAED\nTHB")); ?></textarea>
                                <p class="description"><?php esc_html_e('Use one currency code per line.', 'oksia-smart-itinerary-agent'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('World Time List', 'oksia-smart-itinerary-agent'); ?></th>
                            <td>
                                <textarea name="oksia_world_time_list" rows="5" class="large-text"><?php echo esc_textarea((string) get_option('oksia_world_time_list', "Dubai\nThailand\nNewyork\nToronto\nHanoi\nBali")); ?></textarea>
                                <p class="description"><?php esc_html_e('Use one location per line.', 'oksia-smart-itinerary-agent'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="postbox" style="padding:0 16px 8px;margin-bottom:16px;">
                    <h2 style="padding:14px 0 6px;margin:0;"><?php esc_html_e('AI Draft Generation', 'oksia-smart-itinerary-agent'); ?></h2>
                    <p class="description"><?php esc_html_e('Configure the AI model and prompt defaults used for itinerary generation.', 'oksia-smart-itinerary-agent'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('OpenAI API Key', 'oksia-smart-itinerary-agent'); ?></th>
                            <td>
                                <input type="password" name="oksia_openai_api_key" value="<?php echo esc_attr((string) get_option('oksia_openai_api_key', '')); ?>" class="regular-text" autocomplete="off" />
                                <p class="description"><?php esc_html_e('Used for itinerary draft generation and AI-assisted quote content.', 'oksia-smart-itinerary-agent'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('OpenAI Model', 'oksia-smart-itinerary-agent'); ?></th>
                            <td>
                                <input type="text" name="oksia_openai_model" value="<?php echo esc_attr((string) get_option('oksia_openai_model', 'gpt-4.1-mini')); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Example: gpt-4.1-mini', 'oksia-smart-itinerary-agent'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Agency Intake Tagline', 'oksia-smart-itinerary-agent'); ?></th>
                            <td>
                                <input type="text" name="oksia_intake_tagline" value="<?php echo esc_attr((string) get_option('oksia_intake_tagline', 'Tell us about your dream trip')); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Small helper text shown on the agency intake flow.', 'oksia-smart-itinerary-agent'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="postbox" style="padding:0 16px 8px;margin-bottom:16px;">
                    <h2 style="padding:14px 0 6px;margin:0;"><?php esc_html_e('Razorpay Billing', 'oksia-smart-itinerary-agent'); ?></h2>
                    <p class="description"><?php esc_html_e('Set the Razorpay credentials used for OKSIA billing and subscription sync.', 'oksia-smart-itinerary-agent'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Razorpay Key ID', 'oksia-smart-itinerary-agent'); ?></th>
                            <td>
                                <input type="text" name="oksia_razorpay_key_id" value="<?php echo esc_attr((string) get_option('oksia_razorpay_key_id', '')); ?>" class="regular-text" autocomplete="off" />
                                <p class="description"><?php esc_html_e('Public Razorpay key used to initialize checkout or links.', 'oksia-smart-itinerary-agent'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Razorpay Key Secret', 'oksia-smart-itinerary-agent'); ?></th>
                            <td>
                                <input type="password" name="oksia_razorpay_key_secret" value="<?php echo esc_attr((string) get_option('oksia_razorpay_key_secret', '')); ?>" class="regular-text" autocomplete="off" />
                                <p class="description"><?php esc_html_e('Private secret used for server-side Razorpay requests.', 'oksia-smart-itinerary-agent'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Webhook Secret', 'oksia-smart-itinerary-agent'); ?></th>
                            <td>
                                <input type="password" name="oksia_razorpay_webhook_secret" value="<?php echo esc_attr((string) get_option('oksia_razorpay_webhook_secret', '')); ?>" class="regular-text" autocomplete="off" />
                                <p class="description"><?php esc_html_e('Used to verify incoming Razorpay webhook signatures.', 'oksia-smart-itinerary-agent'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Mode', 'oksia-smart-itinerary-agent'); ?></th>
                            <td>
                                <?php $razorpay_mode = sanitize_key((string) get_option('oksia_razorpay_mode', 'test')); ?>
                                <select name="oksia_razorpay_mode">
                                    <option value="test" <?php selected($razorpay_mode, 'test'); ?>><?php esc_html_e('Test', 'oksia-smart-itinerary-agent'); ?></option>
                                    <option value="live" <?php selected($razorpay_mode, 'live'); ?>><?php esc_html_e('Live', 'oksia-smart-itinerary-agent'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Use Test while integrating, switch to Live only when ready for production.', 'oksia-smart-itinerary-agent'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="notice notice-info inline" style="margin:0 0 16px;">
                    <p><?php esc_html_e('Agency master fields, pricing rules, and subscription enforcement will be added later in this settings area.', 'oksia-smart-itinerary-agent'); ?></p>
                </div>

                <?php submit_button(__('Save OKSIA Settings', 'oksia-smart-itinerary-agent')); ?>
            </form>
        </div>
        <?php
    }

    public function render_oksia_dashboard_page() {
        if (!current_user_can('read')) {
            return;
        }

        $agencies = class_exists('OKSIA_Agencies') ? OKSIA_Agencies::instance() : null;
        $agency_summary = $agencies ? $agencies->get_agency_dashboard_summary() : array();
        $agency_count = absint($agency_summary['total_agencies'] ?? 0);
        $active_trial_count = absint($agency_summary['ongoing_trials'] ?? 0);
        $upcoming_renewal_count = absint($agency_summary['upcoming_renewals'] ?? 0);
        $total_quotes = absint($agency_summary['total_quotes'] ?? 0);
        $renewal_items = (array) ($agency_summary['renewal_items'] ?? array());
        $agency_list_url = admin_url('admin.php?page=oksia-agency-list');
        $settings_url = admin_url('admin.php?page=oksia-settings');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('OKSIA-WP-DASHBOARD', 'oksia-smart-itinerary-agent'); ?></h1>
            <p><?php esc_html_e('Backend operations dashboard for subscription tracking and agency management.', 'oksia-smart-itinerary-agent'); ?></p>
            <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:24px 0;">
                <div class="card"><h2><?php echo esc_html(number_format_i18n($agency_count)); ?></h2><p><?php esc_html_e('Total Agencies Subscribed', 'oksia-smart-itinerary-agent'); ?></p></div>
                <div class="card"><h2><?php echo esc_html(number_format_i18n($active_trial_count)); ?></h2><p><?php esc_html_e('Total Ongoing Trials', 'oksia-smart-itinerary-agent'); ?></p></div>
                <div class="card"><h2><?php echo esc_html(number_format_i18n($upcoming_renewal_count)); ?></h2><p><?php esc_html_e('Upcoming Renewal', 'oksia-smart-itinerary-agent'); ?></p></div>
                <div class="card"><h2><?php echo esc_html(number_format_i18n($total_quotes)); ?></h2><p><?php esc_html_e('Total Quotes', 'oksia-smart-itinerary-agent'); ?></p></div>
            </div>
            <p>
                <a class="button button-primary" href="<?php echo esc_url($agency_list_url); ?>"><?php esc_html_e('Open Agency List', 'oksia-smart-itinerary-agent'); ?></a>
                <a class="button" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Open Settings', 'oksia-smart-itinerary-agent'); ?></a>
            </p>
            <?php if (!empty($renewal_items)) : ?>
                <div class="card" style="margin-top:24px;">
                    <h2><?php esc_html_e('Upcoming Renewals', 'oksia-smart-itinerary-agent'); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Agency', 'oksia-smart-itinerary-agent'); ?></th>
                                <th><?php esc_html_e('Renewal Date', 'oksia-smart-itinerary-agent'); ?></th>
                                <th><?php esc_html_e('Days Left', 'oksia-smart-itinerary-agent'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($renewal_items as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) ($item['name'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($item['renewal_date'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($item['days_left'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function register_settings() {
        $text_options = array(
            'oksia_agency_name',
            'oksia_agency_phone',
            'oksia_agency_website',
            'oksia_agency_location',
            'oksia_iata_code',
            'oksia_billing_company',
            'oksia_billing_gst',
            'oksia_billing_state',
            'oksia_company_address',
            'oksia_agency_building',
            'oksia_agency_landmark',
            'oksia_agency_area',
        );

        foreach ($text_options as $option_name) {
            register_setting('oksia_settings', $option_name, array('sanitize_callback' => 'sanitize_text_field'));
        }

        register_setting('oksia_settings', 'oksia_agency_email', array('sanitize_callback' => 'sanitize_email'));
        register_setting('oksia_settings', 'oksia_authorize_name', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('oksia_settings', 'oksia_agency_code', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('oksia_settings', 'oksia_agency_pincode', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('oksia_settings', 'oksia_billing_email', array('sanitize_callback' => 'sanitize_email'));
        register_setting('oksia_settings', 'oksia_agency_logo_url', array('sanitize_callback' => 'esc_url_raw'));
        register_setting('oksia_settings', 'oksia_main_agency_user_id', array('sanitize_callback' => 'absint'));
        register_setting('oksia_settings', 'oksia_agency_manager_user_ids', array('sanitize_callback' => array($this, 'sanitize_user_id_array')));
        register_setting('oksia_settings', 'oksia_agency_staff_user_ids', array('sanitize_callback' => array($this, 'sanitize_user_id_array')));
        register_setting('oksia_settings', 'oksia_primary_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting('oksia_settings', 'oksia_secondary_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting('oksia_settings', 'oksia_accent_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting('oksia_settings', 'oksia_footer_text', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_disclaimer_text', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_default_inclusions', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_default_exclusions', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_default_child_policy', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_default_booking_policy', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_default_cancellation_policy', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_default_refund_policy', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_domestic_inclusions', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_domestic_exclusions', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_domestic_child_policy', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_domestic_booking_policy', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_domestic_cancellation_policy', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_domestic_refund_policy', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_domestic_destinations', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_international_destinations', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_hotel_categories', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_occupancies', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_meal_plans', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_meal_transfer_types', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_pickup_points', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_drop_points', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_transfer_modes', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_sightseeing_vehicles', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_settings', 'oksia_vehicle_types', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_feature_settings', 'oksia_supported_currencies', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_feature_settings', 'oksia_world_time_list', array('sanitize_callback' => 'sanitize_textarea_field'));
        register_setting('oksia_feature_settings', 'oksia_openai_api_key', array('sanitize_callback' => array($this, 'sanitize_secret')));
        register_setting('oksia_feature_settings', 'oksia_openai_model', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('oksia_feature_settings', 'oksia_intake_tagline', array('sanitize_callback' => 'sanitize_text_field'));
    }

    public function sanitize_secret($value) {
        return trim((string) $value);
    }

    public function sanitize_razorpay_mode($value) {
        $value = sanitize_key((string) $value);
        return in_array($value, array('test', 'live'), true) ? $value : 'test';
    }

    public static function get_razorpay_config() {
        $mode = sanitize_key((string) get_option('oksia_razorpay_mode', 'test'));
        if (!in_array($mode, array('test', 'live'), true)) {
            $mode = 'test';
        }

        return array(
            'key_id' => trim((string) get_option('oksia_razorpay_key_id', '')),
            'key_secret' => trim((string) get_option('oksia_razorpay_key_secret', '')),
            'webhook_secret' => trim((string) get_option('oksia_razorpay_webhook_secret', '')),
            'mode' => $mode,
            'is_live' => ('live' === $mode),
        );
    }

    public function maybe_sync_primary_agency_from_options($option, $old_value, $value) {
        if (!in_array((string) $option, array(
            'oksia_agency_name',
            'oksia_authorize_name',
            'oksia_agency_phone',
            'oksia_agency_email',
            'oksia_agency_website',
            'oksia_agency_fb_page',
            'oksia_agency_instagram',
            'oksia_agency_google',
            'oksia_hear_about_us',
            'oksia_agency_building',
            'oksia_agency_landmark',
            'oksia_agency_area',
            'oksia_agency_location',
            'oksia_agency_state',
            'oksia_agency_pincode',
            'oksia_iata_code',
            'oksia_billing_gst',
            'oksia_billing_company',
            'oksia_billing_email',
            'oksia_agency_logo_url',
            'oksia_intake_tagline',
            'oksia_primary_color',
            'oksia_secondary_color',
            'oksia_accent_color',
            'oksia_main_agency_user_id',
            'oksia_agency_manager_user_ids',
            'oksia_agency_staff_user_ids',
            'oksia_agency_code',
            'oksia_agency_type',
            'oksia_agency_legal_entity',
        ), true)) {
            return;
        }

        if ($this->agencies && method_exists($this->agencies, 'maybe_sync_primary_agency_from_options')) {
            $this->agencies->maybe_sync_primary_agency_from_options();
        }
    }

    public function sanitize_user_id_array($value) {
        $ids = array();

        if (is_array($value)) {
            $ids = $value;
        } elseif (is_string($value) && '' !== trim($value)) {
            $ids = preg_split('/[\s,]+/', $value);
        }

        $ids = array_filter(array_map('absint', (array) $ids));
        $ids = array_values(array_unique($ids));

        return $ids;
    }

    public static function get_quote_version_number($post_id) {
        $version = absint(get_post_meta($post_id, '_oksia_quote_version_number', true));
        return max(1, $version);
    }

    public static function get_quote_version_label($post_id) {
        return 'v' . self::get_quote_version_number($post_id);
    }

    public static function get_quote_last_updated_by_name($post_id) {
        $name = trim((string) get_post_meta($post_id, '_oksia_quote_last_updated_by_name', true));
        if ('' !== $name) {
            return $name;
        }

        $actor_id = absint(get_post_meta($post_id, '_oksia_quote_last_updated_by', true));
        if (!$actor_id) {
            $actor_id = absint(get_post_field('post_author', $post_id));
        }

        if (!$actor_id) {
            return __('System', 'oksia-smart-itinerary-agent');
        }

        $user = get_userdata($actor_id);
        if ($user) {
            $display_name = trim((string) $user->display_name);
            if ('' !== $display_name) {
                return $display_name;
            }

            $login = trim((string) $user->user_login);
            if ('' !== $login) {
                return $login;
            }
        }

        return __('System', 'oksia-smart-itinerary-agent');
    }

    public static function get_quote_share_token($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return '';
        }

        $token = trim((string) get_post_meta($post_id, '_oksia_quote_share_token', true));
        if ('' === $token) {
            $token = wp_generate_password(20, false, false);
            update_post_meta($post_id, '_oksia_quote_share_token', $token);
        }

        return $token;
    }

    public static function get_quote_share_url($post_id, $version_label = '') {
        $post_id = absint($post_id);
        if (!$post_id) {
            return '';
        }

        if (OKSIA_Post_Types::POST_TYPE === get_post_type($post_id) && 'publish' !== get_post_status($post_id)) {
            wp_update_post(
                array(
                    'ID' => $post_id,
                    'post_status' => 'publish',
                )
            );
        }

        self::sync_quote_share_meta($post_id, get_current_user_id(), false);

        $permalink = get_permalink($post_id);
        if (!$permalink) {
            return '';
        }

        $version_label = trim((string) $version_label);
        if ('' === $version_label) {
            $version_label = self::get_quote_version_label($post_id);
        }

        $args = array(
            'oksia_quote_key' => self::get_quote_share_token($post_id),
        );

        if ('' !== $version_label) {
            $args['oksia_quote_version'] = $version_label;
        }

        $stage = self::get_quote_stage($post_id);
        if (in_array($stage, array('confirmed', 'cancelled'), true)) {
            $args['oksia_quote_stage'] = $stage;
        }

        return self::normalize_generated_url(add_query_arg($args, $permalink));
    }

    public static function get_quote_view_url($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return '';
        }

        if (OKSIA_Post_Types::POST_TYPE === get_post_type($post_id) && 'publish' !== get_post_status($post_id)) {
            wp_update_post(
                array(
                    'ID' => $post_id,
                    'post_status' => 'publish',
                )
            );
        }

        return self::normalize_generated_url(get_permalink($post_id));
    }

    private static function normalize_generated_url($url) {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        return rtrim($url, " \t\n\r\0\x0B?&");
    }

    public static function get_quote_share_history($post_id) {
        $history = get_post_meta($post_id, '_oksia_quote_share_history', true);
        if (!is_array($history)) {
            return array();
        }

        $clean_history = array();
        foreach (array_values($history) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $url = esc_url_raw(trim((string) ($entry['url'] ?? '')));
            if ('' === $url) {
                continue;
            }

            $clean_history[] = array(
                'version' => sanitize_text_field((string) ($entry['version'] ?? '')),
                'url' => $url,
                'token' => sanitize_text_field((string) ($entry['token'] ?? '')),
                'stage' => self::normalize_quote_stage($entry['stage'] ?? ''),
                'updated_at' => sanitize_text_field((string) ($entry['updated_at'] ?? '')),
                'updated_by' => absint($entry['updated_by'] ?? 0),
                'updated_by_name' => sanitize_text_field((string) ($entry['updated_by_name'] ?? '')),
                'expires_at' => sanitize_text_field((string) ($entry['expires_at'] ?? '')),
                'obsolete_at' => sanitize_text_field((string) ($entry['obsolete_at'] ?? '')),
            );
        }

        return $clean_history;
    }

    private static function get_quote_share_retention_dates($post_id, $stage) {
        $stage = self::normalize_quote_stage($stage);
        $trip = (array) get_post_meta($post_id, '_oksia_trip_overview', true);
        $now_ts = current_time('timestamp');
        $trip_end_ts = 0;

        if (!empty($trip['end_date'])) {
            $trip_end_ts = strtotime((string) $trip['end_date'] . ' 23:59:59');
        }

        if (!$trip_end_ts) {
            $trip_end_ts = $now_ts;
        }

        $expires_ts = in_array($stage, array('confirmed', 'cancelled'), true) ? ($now_ts + (30 * DAY_IN_SECONDS)) : $trip_end_ts;
        $obsolete_ts = $now_ts + (90 * DAY_IN_SECONDS);

        return array(
            'expires_at' => wp_date('Y-m-d H:i:s', $expires_ts),
            'obsolete_at' => wp_date('Y-m-d H:i:s', $obsolete_ts),
        );
    }

    public static function sync_quote_share_meta($post_id, $actor_id = 0, $force_refresh = false) {
        $post_id = absint($post_id);
        if (!$post_id || OKSIA_Post_Types::POST_TYPE !== get_post_type($post_id)) {
            return array();
        }

        $stage = self::get_quote_stage($post_id);
        $version_label = self::get_quote_version_label($post_id);
        $existing_url = trim((string) get_post_meta($post_id, '_oksia_quote_share_url', true));
        $history = self::get_quote_share_history($post_id);
        $actor = self::get_quote_version_actor_identity($post_id, $actor_id);
        $retention = self::get_quote_share_retention_dates($post_id, $stage);
        $now_mysql = current_time('mysql');
        $now_ts = current_time('timestamp');

        $latest_entry = !empty($history) ? end($history) : array();
        $latest_token = is_array($latest_entry) && !empty($latest_entry['token']) ? (string) $latest_entry['token'] : '';
        $existing_token = trim((string) get_post_meta($post_id, '_oksia_quote_share_token', true));
        if (!$force_refresh && '' === $latest_token && '' !== $existing_token) {
            $latest_token = $existing_token;
        }
        if ($force_refresh || '' === $latest_token) {
            $latest_token = wp_generate_password(20, false, false);
        }

        $share_url = self::normalize_generated_url(add_query_arg(
            array(
                'oksia_quote_key' => $latest_token,
                'oksia_quote_version' => $version_label,
                'oksia_quote_stage' => $stage,
            ),
            get_permalink($post_id)
        ));

        $entry = array(
            'version' => $version_label,
            'url' => $share_url,
            'token' => $latest_token,
            'stage' => $stage,
            'updated_at' => $now_mysql,
            'updated_by' => $actor['id'],
            'updated_by_name' => $actor['name'],
            'expires_at' => $retention['expires_at'],
            'obsolete_at' => $retention['obsolete_at'],
        );

        $filtered_history = array();
        foreach ($history as $history_entry) {
            $obsolete_at = !empty($history_entry['obsolete_at']) ? strtotime((string) $history_entry['obsolete_at']) : 0;
            if ($obsolete_at && $obsolete_at < $now_ts) {
                continue;
            }

            $history_stage = self::normalize_quote_stage($history_entry['stage'] ?? '');
            $expires_at = !empty($history_entry['expires_at']) ? strtotime((string) $history_entry['expires_at']) : 0;
            if (in_array($history_stage, array('confirmed', 'cancelled'), true) && $expires_at && $expires_at < $now_ts) {
                continue;
            }

            $filtered_history[] = $history_entry;
        }

        if (in_array($stage, array('confirmed', 'cancelled'), true)) {
            $filtered_history = array($entry);
        } else {
            $replaced = false;
            foreach ($filtered_history as $index => $history_entry) {
                if (($history_entry['version'] ?? '') === $version_label) {
                    $filtered_history[$index] = $entry;
                    $replaced = true;
                    break;
                }
            }

            if (!$replaced) {
                $filtered_history[] = $entry;
            }
        }

        update_post_meta($post_id, '_oksia_quote_share_token', $latest_token);
        update_post_meta($post_id, '_oksia_quote_share_url', $share_url);
        update_post_meta($post_id, '_oksia_quote_share_history', array_values($filtered_history));
        update_post_meta($post_id, '_oksia_quote_share_updated_at', $now_mysql);
        update_post_meta($post_id, '_oksia_quote_share_expires_at', $retention['expires_at']);
        update_post_meta($post_id, '_oksia_quote_share_obsolete_at', $retention['obsolete_at']);

        return $entry;
    }

    public static function is_valid_quote_share_key($post_id, $token = '') {
        $post_id = absint($post_id);
        $token = trim((string) $token);
        if (!$post_id || '' === $token) {
            return false;
        }

        if (empty(self::get_quote_share_history($post_id))) {
            self::sync_quote_share_meta($post_id, 0, false);
        }

        $now_ts = current_time('timestamp');
        $current_token = trim((string) get_post_meta($post_id, '_oksia_quote_share_token', true));
        if ('' !== $current_token && hash_equals($current_token, $token)) {
            $obsolete_at = strtotime((string) get_post_meta($post_id, '_oksia_quote_share_obsolete_at', true));
            if ($obsolete_at && $obsolete_at < $now_ts) {
                return false;
            }

            $stage = self::get_quote_stage($post_id);
            if (in_array($stage, array('confirmed', 'cancelled'), true)) {
                $expires_at = strtotime((string) get_post_meta($post_id, '_oksia_quote_share_expires_at', true));
                if ($expires_at && $expires_at < $now_ts) {
                    return false;
                }
            }

            return true;
        }

        foreach (self::get_quote_share_history($post_id) as $entry) {
            if (trim((string) ($entry['token'] ?? '')) !== $token) {
                continue;
            }

            $obsolete_at = !empty($entry['obsolete_at']) ? strtotime((string) $entry['obsolete_at']) : 0;
            if ($obsolete_at && $obsolete_at < $now_ts) {
                return false;
            }

            $stage = self::normalize_quote_stage($entry['stage'] ?? '');
            $expires_at = !empty($entry['expires_at']) ? strtotime((string) $entry['expires_at']) : 0;
            if (in_array($stage, array('confirmed', 'cancelled'), true) && $expires_at && $expires_at < $now_ts) {
                return false;
            }

            return true;
        }

        return false;
    }

    private static function get_quote_version_actor_identity($post_id, $actor_id = 0) {
        $actor_id = absint($actor_id);
        if (!$actor_id) {
            $actor_id = get_current_user_id();
        }
        if (!$actor_id) {
            $actor_id = absint(get_post_field('post_author', $post_id));
        }

        $actor_name = '';
        if ($actor_id) {
            $user = get_userdata($actor_id);
            if ($user) {
                $actor_name = trim((string) $user->display_name);
                if ('' === $actor_name) {
                    $actor_name = trim((string) $user->user_login);
                }
            }
        }

        if ('' === $actor_name) {
            $actor_name = __('System', 'oksia-smart-itinerary-agent');
        }

        return array(
            'id' => $actor_id,
            'name' => $actor_name,
        );
    }

    private static function build_quote_version_payload($post_id) {
        return array(
            'post_title' => get_the_title($post_id),
            'trip' => (array) get_post_meta($post_id, '_oksia_trip_overview', true),
            'quote' => (array) get_post_meta($post_id, '_oksia_quote_details', true),
            'hotel_plan' => (array) get_post_meta($post_id, '_oksia_hotel_plan', true),
            'operational_notes' => (array) get_post_meta($post_id, '_oksia_operational_notes', true),
            'documents' => (array) get_post_meta($post_id, '_oksia_documents', true),
            'source_brief' => (string) get_post_meta($post_id, '_oksia_source_brief', true),
            'days' => (array) get_post_meta($post_id, '_oksia_days', true),
            'quote_stage' => self::get_quote_stage($post_id),
            'quote_finalized' => (string) get_post_meta($post_id, '_oksia_quote_finalized', true),
            'quote_finalized_at' => (string) get_post_meta($post_id, '_oksia_quote_finalized_at', true),
            'quote_finalized_by' => absint(get_post_meta($post_id, '_oksia_quote_finalized_by', true)),
            'confirmation_note' => (string) get_post_meta($post_id, '_oksia_quote_confirmation_note', true),
            'confirmed_by_name' => (string) get_post_meta($post_id, '_oksia_quote_confirmed_by_name', true),
            'dmc_name' => (string) get_post_meta($post_id, '_oksia_dmc_name', true),
            'dmc_number' => (string) get_post_meta($post_id, '_oksia_dmc_number', true),
            'driver_name' => (string) get_post_meta($post_id, '_oksia_driver_name', true),
            'dmc_quote_id' => (string) get_post_meta($post_id, '_oksia_dmc_quote_id', true),
            'cancel_reason' => (string) get_post_meta($post_id, '_oksia_quote_cancel_reason', true),
        );
    }

    public static function sync_quote_version_meta($post_id, $actor_id = 0) {
        $post_id = absint($post_id);
        if (!$post_id || OKSIA_Post_Types::POST_TYPE !== get_post_type($post_id)) {
            return 0;
        }

        $payload = self::build_quote_version_payload($post_id);
        $hash = sha1(wp_json_encode($payload));
        $stored_hash = trim((string) get_post_meta($post_id, '_oksia_quote_state_hash', true));
        $current_version = absint(get_post_meta($post_id, '_oksia_quote_version_number', true));
        $refresh_share = false;
        if ($current_version < 1) {
            $current_version = 1;
            $refresh_share = true;
        }

        if ('' === $stored_hash) {
            update_post_meta($post_id, '_oksia_quote_version_number', $current_version);
            update_post_meta($post_id, '_oksia_quote_state_hash', $hash);
            $actor = self::get_quote_version_actor_identity($post_id, $actor_id);
            update_post_meta($post_id, '_oksia_quote_last_updated_by', $actor['id']);
            update_post_meta($post_id, '_oksia_quote_last_updated_by_name', $actor['name']);
            update_post_meta($post_id, '_oksia_quote_version_updated_at', current_time('mysql'));
            $refresh_share = true;
            return $current_version;
        }

        if ($stored_hash !== $hash) {
            $current_version++;
            $actor = self::get_quote_version_actor_identity($post_id, $actor_id);
            update_post_meta($post_id, '_oksia_quote_version_number', $current_version);
            update_post_meta($post_id, '_oksia_quote_state_hash', $hash);
            update_post_meta($post_id, '_oksia_quote_last_updated_by', $actor['id']);
            update_post_meta($post_id, '_oksia_quote_last_updated_by_name', $actor['name']);
            update_post_meta($post_id, '_oksia_quote_version_updated_at', current_time('mysql'));
            $refresh_share = true;
        } elseif ('' === trim((string) get_post_meta($post_id, '_oksia_quote_last_updated_by_name', true))) {
            $actor = self::get_quote_version_actor_identity($post_id, $actor_id);
            update_post_meta($post_id, '_oksia_quote_last_updated_by', $actor['id']);
            update_post_meta($post_id, '_oksia_quote_last_updated_by_name', $actor['name']);
            update_post_meta($post_id, '_oksia_quote_version_updated_at', current_time('mysql'));
        }

        if ($refresh_share || '' === trim((string) get_post_meta($post_id, '_oksia_quote_share_url', true))) {
            self::sync_quote_share_meta($post_id, $actor_id, $refresh_share);
        }

        return $current_version;
    }

    private function can_manage_finalized_quotes() {
        return current_user_can('manage_options') || current_user_can('edit_users');
    }

    public static function get_quote_stage_labels() {
        return array(
            'draft' => __('Drafts', 'oksia-smart-itinerary-agent'),
            'send' => __('Send', 'oksia-smart-itinerary-agent'),
            'confirmed' => __('Confirmed', 'oksia-smart-itinerary-agent'),
            'cancelled' => __('Cancelled', 'oksia-smart-itinerary-agent'),
        );
    }

    public static function get_quote_stage_label($stage) {
        $labels = self::get_quote_stage_labels();
        $stage = self::normalize_quote_stage($stage);
        return $labels[$stage] ?? $labels['draft'];
    }

    public static function normalize_quote_stage($stage) {
        $stage = sanitize_key((string) $stage);
        $allowed = array('draft', 'send', 'confirmed', 'cancelled');
        return in_array($stage, $allowed, true) ? $stage : 'draft';
    }

    public static function get_quote_stage($post_id) {
        $stage = trim((string) get_post_meta($post_id, '_oksia_quote_stage', true));
        if ('' === $stage) {
            $stage = '1' === (string) get_post_meta($post_id, '_oksia_quote_finalized', true) ? 'send' : 'draft';
        }

        return self::normalize_quote_stage($stage);
    }

    private function set_quote_stage($post_id, $stage) {
        update_post_meta($post_id, '_oksia_quote_stage', self::normalize_quote_stage($stage));
    }

    private function is_quote_finalized($post_id) {
        return '1' === (string) get_post_meta($post_id, '_oksia_quote_finalized', true);
    }

    private function get_quote_revision_count($post_id) {
        return absint(get_post_meta($post_id, '_oksia_quote_revision_count', true));
    }

    private function build_quote_subject($post_id) {
        $trip = $this->get_trip_data($post_id);
        $quote_id = trim((string) get_post_meta($post_id, '_oksia_quote_id', true));
        $duration = '';
        $nights = absint($trip['total_nights'] ?? 0);
        if ($nights > 0) {
            $duration = $nights . 'N/' . ($nights + 1) . 'D';
        }

        $destination = trim((string) ($trip['destination'] ?? ''));
        $client_name = trim(($trip['salutation'] ? $trip['salutation'] . ' ' : '') . ($trip['client_name'] ?? ''));

        $subject = trim(sprintf(
            __('Quote for %1$s %2$s for %3$s - %4$s', 'oksia-smart-itinerary-agent'),
            $duration,
            $destination,
            $client_name,
            $quote_id
        ));

        $subject = preg_replace('/\s+/', ' ', $subject);
        return trim($subject);
    }

    private function build_quote_email_body($post_id) {
        $trip = $this->get_trip_data($post_id);
        $quote = $this->get_quote_data($post_id);
        $quote_id = trim((string) get_post_meta($post_id, '_oksia_quote_id', true));
        $client_name = trim(($trip['salutation'] ? $trip['salutation'] . ' ' : '') . ($trip['client_name'] ?? ''));
        $destination = trim((string) ($trip['destination'] ?? ''));
        $nights = absint($trip['total_nights'] ?? 0);
        $duration = $nights > 0 ? $nights . 'N/' . ($nights + 1) . 'D' : '';
        $currency = trim((string) ($quote['currency'] ?? 'INR'));
        $stage = self::get_quote_stage($post_id);
        $status_word = 'confirmed' === $stage ? 'confirmed' : 'finalized';

        $body  = "Hello,\r\n\r\n";
        $body .= 'A quote has been ' . $status_word . " and the PDF is attached.\r\n\r\n";
        $body .= "Quote ID   : " . $quote_id . "\r\n";
        $body .= "Client     : " . $client_name . "\r\n";
        $body .= "Destination: " . $destination . "\r\n";
        $body .= "Duration   : " . $duration . "\r\n";
        $body .= "Currency   : " . $currency . "\r\n\r\n";
        $body .= "Please review the attached PDF for the finalized quote.\r\n\r\n";
        $body .= "Regards,\r\nOK Team\r\n";

        return $body;
    }

    private function build_pdf_attachment($post_id) {
        if (!$this->frontend || !method_exists($this->frontend, 'generate_pdf_file')) {
            return new WP_Error('oksia_pdf_unavailable', __('PDF export is unavailable in this request.', 'oksia-smart-itinerary-agent'));
        }

        return $this->frontend->generate_pdf_file($post_id);
    }

    private function send_quote_email($to, $subject, $body, $attachment) {
        $to = sanitize_email((string) $to);
        if ('' === $to) {
            return false;
        }

        $attachments = array();
        if ($attachment && file_exists($attachment)) {
            $attachments[] = $attachment;
        }

        return wp_mail($to, $subject, $body, array(), $attachments);
    }

    private function meal_transfers_allowed_plans() {
        return array('Breakfast & Dinner', 'Breakfast/Lunch/Dinner', 'Breakfast/Lunch/HiTea/Dinner');
    }

    private function meal_transfers_is_applicable($trip_type, $meal_plan) {
        return 'International' === trim((string) $trip_type) && in_array(trim((string) $meal_plan), $this->meal_transfers_allowed_plans(), true);
    }

    private function normalize_meal_transfers_value($trip_type, $meal_plan, $meal_transfers) {
        if (!$this->meal_transfers_is_applicable($trip_type, $meal_plan)) {
            return '';
        }

        $meal_transfers = trim((string) $meal_transfers);
        if ('' === $meal_transfers || !in_array($meal_transfers, array('Included', 'Excluded'), true)) {
            return 'Excluded';
        }

        return $meal_transfers;
    }

    public function enqueue_assets() {
        $screen = get_current_screen();
        if (!$screen || OKSIA_Post_Types::POST_TYPE !== $screen->post_type) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('oksia-admin', OKSIA_URL . 'assets/css/admin.css', array(), OKSIA_VERSION);
        wp_enqueue_script('oksia-admin', OKSIA_URL . 'assets/js/admin.js', array('jquery'), OKSIA_VERSION, true);
        wp_localize_script(
            'oksia-admin',
            'okAdminData',
            array(
                'destinations' => array(
                    'Domestic' => $this->get_setting_options('oksia_domestic_destinations', array()),
                    'International' => $this->get_setting_options('oksia_international_destinations', array()),
                ),
              'exchangeApiBase' => 'https://convertz.app/api/currency',
            )
        );
    }

    public function register_meta_boxes() {
        add_meta_box('oksia_destination', __('Destination', 'oksia-smart-itinerary-agent'), array($this, 'render_destination_box'), OKSIA_Post_Types::POST_TYPE, 'normal', 'high');
        add_meta_box('oksia_hotels_meals', __('Hotels & Meals', 'oksia-smart-itinerary-agent'), array($this, 'render_hotels_meals_box'), OKSIA_Post_Types::POST_TYPE, 'normal', 'high');
        add_meta_box('oksia_transfers', __('Transfers', 'oksia-smart-itinerary-agent'), array($this, 'render_transfers_box'), OKSIA_Post_Types::POST_TYPE, 'normal', 'high');
        add_meta_box('oksia_stay_plan', __('Stay Plan', 'oksia-smart-itinerary-agent'), array($this, 'render_stay_plan_box'), OKSIA_Post_Types::POST_TYPE, 'normal', 'high');
        add_meta_box('oksia_rates', __('Rates', 'oksia-smart-itinerary-agent'), array($this, 'render_rates_box'), OKSIA_Post_Types::POST_TYPE, 'normal', 'high');
        add_meta_box('oksia_source_documents', __('Source Documents', 'oksia-smart-itinerary-agent'), array($this, 'render_documents_box'), OKSIA_Post_Types::POST_TYPE, 'normal', 'default');
        add_meta_box('oksia_policies', __('Policies & Notes', 'oksia-smart-itinerary-agent'), array($this, 'render_policies_box'), OKSIA_Post_Types::POST_TYPE, 'normal', 'default');
        add_meta_box('oksia_day_plan', __('Day-wise Itinerary', 'oksia-smart-itinerary-agent'), array($this, 'render_days_box'), OKSIA_Post_Types::POST_TYPE, 'normal', 'default');
        add_meta_box('oksia_ai_review', __('Itinerary Draft Workspace', 'oksia-smart-itinerary-agent'), array($this, 'render_ai_review_box'), OKSIA_Post_Types::POST_TYPE, 'side', 'default');
    }

    public function render_destination_box($post) {
        wp_nonce_field('oksia_save_itinerary', 'oksia_nonce');
        $trip = $this->get_trip_data($post->ID);
        $today = wp_date('Y-m-d', current_time('timestamp'));
        $destination_options = $this->get_setting_options(
            'Domestic' === $trip['trip_type'] ? 'oksia_domestic_destinations' : 'oksia_international_destinations',
            array()
        );
        ?>
        <div class="oksia-grid oksia-grid--two oksia-grid--destination-top">
            <p>
                <label for="oksia_salutation"><?php esc_html_e('Salutation', 'oksia-smart-itinerary-agent'); ?></label>
                <select id="oksia_salutation" name="oksia_trip[salutation]" class="widefat">
                    <option value="Mr" <?php selected($trip['salutation'], 'Mr'); ?>><?php esc_html_e('Mr', 'oksia-smart-itinerary-agent'); ?></option>
                    <option value="Ms" <?php selected($trip['salutation'], 'Ms'); ?>><?php esc_html_e('Ms', 'oksia-smart-itinerary-agent'); ?></option>
                </select>
            </p>
            <p>
                <label for="oksia_client_name"><?php esc_html_e('Client Name', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="text" id="oksia_client_name" name="oksia_trip[client_name]" value="<?php echo esc_attr($trip['client_name']); ?>" class="widefat" />
            </p>
        </div>
        <div class="oksia-grid oksia-grid--five oksia-grid--destination-main">
            <p>
                <label for="oksia_trip_type"><?php esc_html_e('Trip Type', 'oksia-smart-itinerary-agent'); ?></label>
                <select id="oksia_trip_type" name="oksia_trip[trip_type]" class="widefat">
                    <option value="Domestic" <?php selected($trip['trip_type'], 'Domestic'); ?>><?php esc_html_e('Domestic', 'oksia-smart-itinerary-agent'); ?></option>
                    <option value="International" <?php selected($trip['trip_type'], 'International'); ?>><?php esc_html_e('International', 'oksia-smart-itinerary-agent'); ?></option>
                </select>
            </p>
            <p>
                <label for="oksia_destination_field"><?php esc_html_e('Destination', 'oksia-smart-itinerary-agent'); ?></label>
                <select id="oksia_destination_field" name="oksia_trip[destination]" class="widefat">
                    <option value=""><?php echo esc_html(empty($destination_options) ? __('Add destinations in Settings', 'oksia-smart-itinerary-agent') : __('Select Destination', 'oksia-smart-itinerary-agent')); ?></option>
                    <?php foreach ($destination_options as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($trip['destination'], $value); ?>><?php echo esc_html($value); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="oksia_start_date"><?php esc_html_e('Check-in', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="date" id="oksia_start_date" name="oksia_trip[start_date]" value="<?php echo esc_attr($trip['start_date']); ?>" min="<?php echo esc_attr($today); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_end_date"><?php esc_html_e('Check-out', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="date" id="oksia_end_date" name="oksia_trip[end_date]" value="<?php echo esc_attr($trip['end_date']); ?>" min="<?php echo esc_attr($trip['start_date'] ? $trip['start_date'] : $today); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_total_nights"><?php esc_html_e('Total Nights', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="number" id="oksia_total_nights" name="oksia_trip[total_nights]" value="<?php echo esc_attr((string) $trip['total_nights']); ?>" class="widefat" readonly />
            </p>
        </div>
        <div class="oksia-grid oksia-grid--four oksia-grid--destination-pax">
            <p>
                <label for="oksia_adults"><?php esc_html_e('Adults', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="number" min="0" id="oksia_adults" name="oksia_trip[adults]" value="<?php echo esc_attr((string) $trip['adults']); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_adult_with_bed"><?php esc_html_e('Adult/Child With Bed', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="number" min="0" id="oksia_adult_with_bed" name="oksia_trip[adult_with_bed]" value="<?php echo esc_attr((string) $trip['adult_with_bed']); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_child_without_bed"><?php esc_html_e('Child Without Bed', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="number" min="0" id="oksia_child_without_bed" name="oksia_trip[child_without_bed]" value="<?php echo esc_attr((string) $trip['child_without_bed']); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_total_travelers"><?php esc_html_e('Total Travelers', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="number" id="oksia_total_travelers" name="oksia_trip[travelers]" value="<?php echo esc_attr((string) $trip['travelers']); ?>" class="widefat" readonly />
            </p>
        </div>
        <p>
            <label for="oksia_source_brief"><?php esc_html_e('Trip Brief For AI', 'oksia-smart-itinerary-agent'); ?></label>
            <textarea id="oksia_source_brief" name="oksia_source_brief" rows="6" class="widefat" placeholder="<?php esc_attr_e('Paste the complete trip brief, activity flow, hotel sequence, and operational notes. AI will convert this into a day-wise itinerary.', 'oksia-smart-itinerary-agent'); ?>"><?php echo esc_textarea((string) get_post_meta($post->ID, '_oksia_source_brief', true)); ?></textarea>
        </p>
        <?php
    }

    public function render_hotels_meals_box($post) {
        $quote = $this->get_quote_data($post->ID);
        $meal_transfers_value = $this->normalize_meal_transfers_value($this->get_trip_data($post->ID)['trip_type'] ?? 'Domestic', $quote['meal_plan'], $quote['meal_transfers']);
        ?>
        <div class="oksia-grid oksia-grid--five">
            <?php $this->render_select_field('Hotel Category', 'oksia_quote[hotel_category]', 'oksia_hotel_category', $quote['hotel_category'], $this->get_setting_options('oksia_hotel_categories', array('3 Star', '4 Star', '5 Star', '3/4 Split', '3/5 Split', '4/5 Split'))); ?>
            <?php $this->render_select_field('Occupancy', 'oksia_quote[occupancy]', 'oksia_occupancy', $quote['occupancy'], $this->get_setting_options('oksia_occupancies', array('Single', 'Double', 'Triple', 'Quad'))); ?>
            <p>
                <label for="oksia_rooms"><?php esc_html_e('No. of Rooms', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="number" min="0" id="oksia_rooms" name="oksia_quote[rooms]" value="<?php echo esc_attr((string) $quote['rooms']); ?>" class="widefat" />
            </p>
            <?php $this->render_select_field('Meal Plan', 'oksia_quote[meal_plan]', 'oksia_meal_plan', $quote['meal_plan'], $this->get_setting_options('oksia_meal_plans', array('No Meals', 'Breakfast', 'Breakfast & Dinner', 'Breakfast/Lunch/Dinner', 'Breakfast/Lunch/HiTea/Dinner'))); ?>
            <p class="oksia-conditional-field" data-show-trip-type="International">
                <label for="oksia_meal_transfers"><?php esc_html_e('Meal Transfers', 'oksia-smart-itinerary-agent'); ?></label>
                <select id="oksia_meal_transfers" name="oksia_quote[meal_transfers]" class="widefat">
                    <option value=""><?php esc_html_e('Select Meal Transfers', 'oksia-smart-itinerary-agent'); ?></option>
                    <?php foreach ($this->get_setting_options('oksia_meal_transfer_types', array('Included', 'Excluded')) as $value) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($meal_transfers_value, $value); ?>><?php echo esc_html($value); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
        </div>
        <?php
    }

    public function render_transfers_box($post) {
        $quote = $this->get_quote_data($post->ID);
        ?>
        <div class="oksia-grid oksia-grid--three">
            <?php $this->render_select_field('Pick Up From', 'oksia_quote[pickup_from]', 'oksia_pickup_from', $quote['pickup_from'], $this->get_setting_options('oksia_pickup_points', array())); ?>
            <?php $this->render_select_field('First Transfer', 'oksia_quote[first_transfer]', 'oksia_first_transfer', $quote['first_transfer'], $this->get_setting_options('oksia_transfer_modes', array('Private', 'SIC - Sharing in Coach'))); ?>
            <?php $this->render_select_field('Sightseeing Vehicle', 'oksia_quote[sightseeing_vehicle]', 'oksia_sightseeing_vehicle', $quote['sightseeing_vehicle'], $this->get_setting_options('oksia_sightseeing_vehicles', array('Private', 'SIC - Sharing in Coach'))); ?>
        </div>
        <div class="oksia-grid oksia-grid--three">
            <?php $this->render_select_field('Drop To', 'oksia_quote[drop_to]', 'oksia_drop_to', $quote['drop_to'], $this->get_setting_options('oksia_drop_points', array())); ?>
            <?php $this->render_select_field('Last Transfer', 'oksia_quote[last_transfer]', 'oksia_last_transfer', $quote['last_transfer'], $this->get_setting_options('oksia_transfer_modes', array('Private', 'SIC - Sharing in Coach'))); ?>
            <?php $this->render_select_field('Vehicle Type', 'oksia_quote[vehicle_type]', 'oksia_vehicle_type', $quote['vehicle_type'], $this->get_setting_options('oksia_vehicle_types', array('Tempo Traveller', 'Innova', 'Sedan', 'SUV', 'Coach', 'Minibus'))); ?>
        </div>
        <p>
            <label for="oksia_transfer_note"><?php esc_html_e('Transfer Note', 'oksia-smart-itinerary-agent'); ?></label>
            <input type="text" id="oksia_transfer_note" name="oksia_quote[transfer_note]" value="<?php echo esc_attr($quote['transfer_note']); ?>" class="widefat" />
        </p>
        <?php
    }

    public function render_stay_plan_box($post) {
        $hotel_plan = get_post_meta($post->ID, '_oksia_hotel_plan', true);
        $hotel_plan = is_array($hotel_plan) ? array_values($hotel_plan) : array();
        $saved_cities = $this->get_reusable_values('oksia_saved_cities');
        $saved_hotels = $this->get_reusable_values('oksia_saved_hotels');
        ?>
        <p class="description"><?php esc_html_e('Add the city, hotel, and night count exactly as your team uses them in quotations.', 'oksia-smart-itinerary-agent'); ?></p>
        <datalist id="oksia-city-options">
            <?php foreach ($saved_cities as $value) : ?>
                <option value="<?php echo esc_attr($value); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <datalist id="oksia-hotel-options">
            <?php foreach ($saved_hotels as $value) : ?>
                <option value="<?php echo esc_attr($value); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <div id="oksia-hotel-plan">
            <?php if (empty($hotel_plan)) : ?>
                <?php $this->render_hotel_plan_row(0, array()); ?>
            <?php else : ?>
                <?php foreach ($hotel_plan as $index => $stay) : ?>
                    <?php $this->render_hotel_plan_row($index, $stay); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p><button type="button" class="button" id="oksia-add-hotel-plan"><?php esc_html_e('Add Stay Stop', 'oksia-smart-itinerary-agent'); ?></button></p>
        <script type="text/html" id="tmpl-oksia-hotel-plan-row"><?php $this->render_hotel_plan_row('__INDEX__', array(), true); ?></script>
        <?php
    }

    public function render_rates_box($post) {
        $quote = $this->get_quote_data($post->ID);
        ?>
        <div class="oksia-grid oksia-grid--five">
            <?php $this->render_select_field('Quote Currency', 'oksia_quote[currency]', 'oksia_currency', $quote['currency'], $this->get_setting_options('oksia_supported_currencies', array('INR', 'USD', 'EUR', 'AED', 'THB'))); ?>
            <p>
                <label for="oksia_exchange_rate"><?php esc_html_e('Exchange Rate (to INR)', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="text" id="oksia_exchange_rate" name="oksia_quote[exchange_rate]" value="<?php echo esc_attr($quote['exchange_rate']); ?>" class="widefat" readonly />
            </p>
            <p>
                <label for="oksia_transaction_cost"><?php esc_html_e('Transaction Cost (INR)', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="number" step="0.01" id="oksia_transaction_cost" name="oksia_quote[transaction_cost]" value="<?php echo esc_attr((string) $quote['transaction_cost']); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_additional_cost"><?php esc_html_e('Additional Cost (INR)', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="number" step="0.01" id="oksia_additional_cost" name="oksia_quote[additional_cost]" value="<?php echo esc_attr((string) $quote['additional_cost']); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_effective_rate"><?php esc_html_e('Effective Rate', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="text" id="oksia_effective_rate" name="oksia_quote[effective_rate]" value="<?php echo esc_attr($quote['effective_rate']); ?>" class="widefat" readonly />
            </p>
        </div>
        <div class="oksia-grid oksia-grid--three">
            <p>
                <label for="oksia_adult_rate"><?php esc_html_e('Adult Rate', 'oksia-smart-itinerary-agent'); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html($quote['currency']); ?></span>)</label>
                <input type="number" step="0.01" id="oksia_adult_rate" name="oksia_quote[adult_rate]" value="<?php echo esc_attr($quote['adult_rate']); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_with_bed_rate"><?php esc_html_e('Adult/Child With Bed Rate', 'oksia-smart-itinerary-agent'); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html($quote['currency']); ?></span>)</label>
                <input type="number" step="0.01" id="oksia_with_bed_rate" name="oksia_quote[with_bed_rate]" value="<?php echo esc_attr($quote['with_bed_rate']); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_child_rate"><?php esc_html_e('Child Without Bed Rate', 'oksia-smart-itinerary-agent'); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html($quote['currency']); ?></span>)</label>
                <input type="number" step="0.01" id="oksia_child_rate" name="oksia_quote[child_rate]" value="<?php echo esc_attr($quote['child_rate']); ?>" class="widefat" />
            </p>
        </div>
        <div class="oksia-grid oksia-grid--three oksia-rate-markup-row">
            <p>
                <label for="oksia_adult_markup"><?php esc_html_e('Adult Markup', 'oksia-smart-itinerary-agent'); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html($quote['currency']); ?></span>)</label>
                <input type="number" step="0.01" id="oksia_adult_markup" name="oksia_quote[adult_markup]" value="<?php echo esc_attr($quote['adult_markup']); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_with_bed_markup"><?php esc_html_e('Extra / With Bed Markup', 'oksia-smart-itinerary-agent'); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html($quote['currency']); ?></span>)</label>
                <input type="number" step="0.01" id="oksia_with_bed_markup" name="oksia_quote[with_bed_markup]" value="<?php echo esc_attr($quote['with_bed_markup']); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_child_markup"><?php esc_html_e('Child Markup', 'oksia-smart-itinerary-agent'); ?> (<span class="oksia-selected-currency-label"><?php echo esc_html($quote['currency']); ?></span>)</label>
                <input type="number" step="0.01" id="oksia_child_markup" name="oksia_quote[child_markup]" value="<?php echo esc_attr($quote['child_markup']); ?>" class="widefat" />
            </p>
        </div>
        <div class="oksia-grid oksia-grid--three">
            <p>
                <label for="oksia_adult_rate_quote"><?php esc_html_e('Adult INR Reference Rate', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="text" id="oksia_adult_rate_quote" name="oksia_quote[adult_rate_quote]" value="<?php echo esc_attr($quote['adult_rate_quote']); ?>" class="widefat" readonly />
            </p>
            <p>
                <label for="oksia_with_bed_rate_quote"><?php esc_html_e('With Bed INR Reference Rate', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="text" id="oksia_with_bed_rate_quote" name="oksia_quote[with_bed_rate_quote]" value="<?php echo esc_attr($quote['with_bed_rate_quote']); ?>" class="widefat" readonly />
            </p>
            <p>
                <label for="oksia_child_rate_quote"><?php esc_html_e('Child INR Reference Rate', 'oksia-smart-itinerary-agent'); ?></label>
                <input type="text" id="oksia_child_rate_quote" name="oksia_quote[child_rate_quote]" value="<?php echo esc_attr($quote['child_rate_quote']); ?>" class="widefat" readonly />
            </p>
        </div>
        <p class="description"><?php esc_html_e('Currency conversion is indicative only. Final payable rates will be calculated on the day of payment, not on the day of quotation.', 'oksia-smart-itinerary-agent'); ?></p>
        <?php
    }

    public function render_documents_box($post) {
        $documents = get_post_meta($post->ID, '_oksia_documents', true);
        $documents = is_array($documents) ? array_values($documents) : array();
        ?>
        <div id="oksia-documents">
            <?php if (empty($documents)) : ?>
                <?php $this->render_document_row(0, array()); ?>
            <?php else : ?>
                <?php foreach ($documents as $index => $document) : ?>
                    <?php $this->render_document_row($index, $document); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p><button type="button" class="button" id="oksia-add-document"><?php esc_html_e('Add Document', 'oksia-smart-itinerary-agent'); ?></button></p>
        <script type="text/html" id="tmpl-oksia-document-row"><?php $this->render_document_row('__INDEX__', array(), true); ?></script>
        <?php
    }

    public function render_policies_box($post) {
        $operational = get_post_meta($post->ID, '_oksia_operational_notes', true);
        $operational = is_array($operational) ? $operational : array();
        $defaults = $this->get_operational_defaults();
        ?>
        <p><label for="oksia_summary"><?php esc_html_e('Client-facing Summary', 'oksia-smart-itinerary-agent'); ?></label><textarea id="oksia_summary" name="oksia_operational[summary]" rows="4" class="widefat"><?php echo esc_textarea($operational['summary'] ?? ''); ?></textarea></p>
        <p><label for="oksia_inclusions"><?php esc_html_e('Inclusions', 'oksia-smart-itinerary-agent'); ?></label><textarea id="oksia_inclusions" name="oksia_operational[inclusions]" rows="4" class="widefat"><?php echo esc_textarea($operational['inclusions'] ?? $defaults['inclusions']); ?></textarea></p>
        <p><label for="oksia_exclusions"><?php esc_html_e('Exclusions', 'oksia-smart-itinerary-agent'); ?></label><textarea id="oksia_exclusions" name="oksia_operational[exclusions]" rows="4" class="widefat"><?php echo esc_textarea($operational['exclusions'] ?? $defaults['exclusions']); ?></textarea></p>
        <p><label for="oksia_important_notes"><?php esc_html_e('Important Notes', 'oksia-smart-itinerary-agent'); ?></label><textarea id="oksia_important_notes" name="oksia_operational[important_notes]" rows="4" class="widefat"><?php echo esc_textarea($operational['important_notes'] ?? ''); ?></textarea></p>
        <?php
    }

    public function render_days_box($post) {
        $days = get_post_meta($post->ID, '_oksia_days', true);
        $days = is_array($days) ? array_values($days) : array();
        $saved_sightseeing = $this->get_reusable_values('oksia_saved_sightseeing');
        ?>
        <p class="description"><?php esc_html_e('AI can generate this day-wise plan from your trip brief. You can then attach a media-library image to the relevant day.', 'oksia-smart-itinerary-agent'); ?></p>
        <datalist id="oksia-sightseeing-options">
            <?php foreach ($saved_sightseeing as $value) : ?>
                <option value="<?php echo esc_attr($value); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <div id="oksia-days">
            <?php if (empty($days)) : ?>
                <?php $this->render_day_row(0, array()); ?>
            <?php else : ?>
                <?php foreach ($days as $index => $day) : ?>
                    <?php $this->render_day_row($index, $day); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p><button type="button" class="button" id="oksia-add-day"><?php esc_html_e('Add Day', 'oksia-smart-itinerary-agent'); ?></button></p>
        <script type="text/html" id="tmpl-oksia-day-row"><?php $this->render_day_row('__INDEX__', array(), true); ?></script>
        <?php
    }

    public function render_ai_review_box($post) {
        $ai_status = get_post_meta($post->ID, '_oksia_ai_status', true);
        $ai_status = $ai_status ? $ai_status : __('Not generated yet', 'oksia-smart-itinerary-agent');
        $is_finalized = $this->is_quote_finalized($post->ID);
        $revision_count = $this->get_quote_revision_count($post->ID);
        $quote_stage = self::get_quote_stage($post->ID);
        $stage_labels = self::get_quote_stage_labels();
        $version_label = self::get_quote_version_label($post->ID);
        $last_updated_by = self::get_quote_last_updated_by_name($post->ID);
        $share_url = self::get_quote_share_url($post->ID);
        ?>
        <p><strong><?php esc_html_e('AI Status:', 'oksia-smart-itinerary-agent'); ?></strong> <?php echo esc_html($ai_status); ?></p>
        <p><strong><?php esc_html_e('Quote Stage:', 'oksia-smart-itinerary-agent'); ?></strong> <?php echo esc_html($stage_labels[$quote_stage] ?? self::get_quote_stage_label($quote_stage)); ?></p>
        <p><strong><?php esc_html_e('Version:', 'oksia-smart-itinerary-agent'); ?></strong> <?php echo esc_html($version_label); ?></p>
        <p><strong><?php esc_html_e('Last Updated By:', 'oksia-smart-itinerary-agent'); ?></strong> <?php echo esc_html($last_updated_by); ?></p>
        <?php if ('' !== $share_url) : ?>
            <p><strong><?php esc_html_e('Share URL:', 'oksia-smart-itinerary-agent'); ?></strong><br><code style="word-break: break-all;"><?php echo esc_html($share_url); ?></code></p>
        <?php endif; ?>
        <?php if ($revision_count > 0) : ?>
            <p><strong><?php esc_html_e('Revisions:', 'oksia-smart-itinerary-agent'); ?></strong> <?php echo esc_html((string) $revision_count); ?></p>
        <?php endif; ?>
        <p><?php esc_html_e('Generate and review the editable day-wise itinerary for this quote.', 'oksia-smart-itinerary-agent'); ?></p>
        <p>
            <label for="oksia_quote_stage"><?php esc_html_e('Quote Status', 'oksia-smart-itinerary-agent'); ?></label>
            <select id="oksia_quote_stage" name="oksia_quote_stage" class="widefat">
                <?php foreach ($stage_labels as $stage_key => $stage_label) : ?>
                    <option value="<?php echo esc_attr($stage_key); ?>" <?php selected($quote_stage, $stage_key); ?>><?php echo esc_html($stage_label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description"><?php esc_html_e('Use these stages to sort quote drafts, sent quotes, confirmations, and cancellations in the workspace.', 'oksia-smart-itinerary-agent'); ?></p>
        <p><button type="submit" name="oksia_quote_state_action" value="download_draft" class="button"><?php esc_html_e('Generate Draft PDF', 'oksia-smart-itinerary-agent'); ?></button></p>
        <?php if ($is_finalized && $this->can_manage_finalized_quotes()) : ?>
            <p><button type="submit" name="oksia_quote_state_action" value="reopen" class="button"><?php esc_html_e('Reopen Draft', 'oksia-smart-itinerary-agent'); ?></button></p>
        <?php elseif (! $is_finalized) : ?>
            <p><button type="submit" name="oksia_quote_state_action" value="finalize" class="button button-primary"><?php esc_html_e('Finalize Quote', 'oksia-smart-itinerary-agent'); ?></button></p>
        <?php else : ?>
            <p class="description"><?php esc_html_e('This quote is locked. Ask a manager or admin to reopen it for edits.', 'oksia-smart-itinerary-agent'); ?></p>
        <?php endif; ?>
        <?php if (! $is_finalized) : ?>
            <p><button type="submit" name="oksia_after_save_action" value="generate" class="button button-primary"><?php esc_html_e('Save + Generate AI Draft', 'oksia-smart-itinerary-agent'); ?></button></p>
        <?php endif; ?>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['oksia_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['oksia_nonce'])), 'oksia_save_itinerary')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $quote_action = sanitize_text_field(wp_unslash($_POST['oksia_quote_state_action'] ?? ''));
        $is_finalized = $this->is_quote_finalized($post_id);
        if ($is_finalized && 'download_draft' === $quote_action) {
            $this->process_quote_state_action($post_id, $quote_action);
            return;
        }
        if ($is_finalized && 'reopen' !== $quote_action) {
            return;
        }

        $trip = isset($_POST['oksia_trip']) ? (array) wp_unslash($_POST['oksia_trip']) : array();
        $trip_clean = array(
            'salutation' => sanitize_text_field($trip['salutation'] ?? 'Mr'),
            'client_name' => sanitize_text_field($trip['client_name'] ?? ''),
            'trip_type' => sanitize_text_field($trip['trip_type'] ?? 'Domestic'),
            'destination' => sanitize_text_field($trip['destination'] ?? ''),
            'start_date' => sanitize_text_field($trip['start_date'] ?? ''),
            'end_date' => sanitize_text_field($trip['end_date'] ?? ''),
            'total_nights' => $this->calculate_nights($trip['start_date'] ?? '', $trip['end_date'] ?? ''),
            'adults' => absint($trip['adults'] ?? 0),
            'adult_with_bed' => absint($trip['adult_with_bed'] ?? 0),
            'child_without_bed' => absint($trip['child_without_bed'] ?? 0),
            'travelers' => 0,
        );
        $trip_clean['travelers'] = $trip_clean['adults'] + $trip_clean['adult_with_bed'] + $trip_clean['child_without_bed'];
        update_post_meta($post_id, '_oksia_trip_overview', $trip_clean);
        update_post_meta($post_id, '_oksia_source_brief', sanitize_textarea_field(wp_unslash($_POST['oksia_source_brief'] ?? '')));
        if (!get_post_meta($post_id, '_oksia_quote_id', true)) {
            update_post_meta($post_id, '_oksia_quote_id', $this->generate_next_quote_id());
        }

        $quote = isset($_POST['oksia_quote']) ? (array) wp_unslash($_POST['oksia_quote']) : array();
        $meal_plan = sanitize_text_field($quote['meal_plan'] ?? '');
        $quote_clean = array(
            'hotel_category' => sanitize_text_field($quote['hotel_category'] ?? ''),
            'occupancy' => sanitize_text_field($quote['occupancy'] ?? ''),
            'rooms' => absint($quote['rooms'] ?? 0),
            'meal_plan' => $meal_plan,
            'meal_transfers' => $this->normalize_meal_transfers_value($trip_clean['trip_type'], $meal_plan, $quote['meal_transfers'] ?? ''),
            'pickup_from' => sanitize_text_field($quote['pickup_from'] ?? ''),
            'drop_to' => sanitize_text_field($quote['drop_to'] ?? ''),
            'first_transfer' => sanitize_text_field($quote['first_transfer'] ?? ''),
            'last_transfer' => sanitize_text_field($quote['last_transfer'] ?? ''),
            'sightseeing_vehicle' => sanitize_text_field($quote['sightseeing_vehicle'] ?? ''),
            'vehicle_type' => sanitize_text_field($quote['vehicle_type'] ?? ''),
            'transfer_note' => sanitize_text_field($quote['transfer_note'] ?? ''),
            'currency' => sanitize_text_field($quote['currency'] ?? 'INR'),
            'exchange_rate' => sanitize_text_field($quote['exchange_rate'] ?? ''),
            'transaction_cost' => $this->sanitize_decimal($quote['transaction_cost'] ?? '1.9', 1.9),
            'additional_cost' => $this->sanitize_decimal($quote['additional_cost'] ?? '0', 0),
            'effective_rate' => sanitize_text_field($quote['effective_rate'] ?? ''),
            'adult_rate' => $this->sanitize_decimal($quote['adult_rate'] ?? '0', 0),
            'with_bed_rate' => $this->sanitize_decimal($quote['with_bed_rate'] ?? '0', 0),
            'child_rate' => $this->sanitize_decimal($quote['child_rate'] ?? '0', 0),
            'adult_markup' => $this->sanitize_decimal($quote['adult_markup'] ?? '0', 0),
            'with_bed_markup' => $this->sanitize_decimal($quote['with_bed_markup'] ?? '0', 0),
            'child_markup' => $this->sanitize_decimal($quote['child_markup'] ?? '0', 0),
            'adult_rate_quote' => sanitize_text_field($quote['adult_rate_quote'] ?? ''),
            'with_bed_rate_quote' => sanitize_text_field($quote['with_bed_rate_quote'] ?? ''),
            'child_rate_quote' => sanitize_text_field($quote['child_rate_quote'] ?? ''),
        );
        update_post_meta($post_id, '_oksia_quote_details', $quote_clean);

        $hotel_plan = isset($_POST['oksia_hotel_plan']) ? (array) wp_unslash($_POST['oksia_hotel_plan']) : array();
        $clean_hotel_plan = $this->sanitize_hotel_plan($hotel_plan);
        update_post_meta($post_id, '_oksia_hotel_plan', $clean_hotel_plan);
        $this->update_reusable_values('oksia_saved_cities', wp_list_pluck($clean_hotel_plan, 'city'));
        $this->update_reusable_values('oksia_saved_hotels', wp_list_pluck($clean_hotel_plan, 'hotel'));

        $operational = isset($_POST['oksia_operational']) ? (array) wp_unslash($_POST['oksia_operational']) : array();
        update_post_meta(
            $post_id,
            '_oksia_operational_notes',
            array(
                'summary' => sanitize_textarea_field($operational['summary'] ?? ''),
                'inclusions' => sanitize_textarea_field($operational['inclusions'] ?? ''),
                'exclusions' => sanitize_textarea_field($operational['exclusions'] ?? ''),
                'important_notes' => sanitize_textarea_field($operational['important_notes'] ?? ''),
            )
        );

        $documents = isset($_POST['oksia_documents']) ? (array) wp_unslash($_POST['oksia_documents']) : array();
        update_post_meta($post_id, '_oksia_documents', $this->sanitize_documents($documents));

        $days = isset($_POST['oksia_days']) ? (array) wp_unslash($_POST['oksia_days']) : array();
        $clean_days = $this->sanitize_days($days);
        update_post_meta($post_id, '_oksia_days', $clean_days);
        $this->update_reusable_values('oksia_saved_sightseeing', wp_list_pluck($clean_days, 'location'));

        $submitted_stage = isset($_POST['oksia_quote_stage']) ? self::normalize_quote_stage(sanitize_text_field(wp_unslash($_POST['oksia_quote_stage']))) : '';
        if (in_array($submitted_stage, array('draft', 'send'), true)) {
            $this->set_quote_stage($post_id, $submitted_stage);
        }

        if (!$is_finalized && !get_post_meta($post_id, '_oksia_quote_stage', true)) {
            $this->set_quote_stage($post_id, 'draft');
        }

        if ($quote_action) {
            $this->process_quote_state_action($post_id, $quote_action);
            return;
        }

        $after_save_action = sanitize_text_field(wp_unslash($_POST['oksia_after_save_action'] ?? ''));
        if ('generate' === $after_save_action) {
            $this->maybe_process_after_save_action($post_id);
            return;
        }

        self::sync_quote_version_meta($post_id, get_current_user_id());
    }

    public function set_admin_columns($columns) {
        $columns['oksia_destination'] = __('Destination', 'oksia-smart-itinerary-agent');
        $columns['oksia_dates'] = __('Dates', 'oksia-smart-itinerary-agent');
        return $columns;
    }

    public function render_admin_columns($column, $post_id) {
        $trip = $this->get_trip_data($post_id);
        if ('oksia_destination' === $column) {
            echo esc_html($trip['destination']);
        }
        if ('oksia_dates' === $column) {
            echo esc_html(trim($trip['start_date'] . ' - ' . $trip['end_date'], ' -'));
        }
    }

    public function filter_row_actions($actions, $post) {
        if (!$post || OKSIA_Post_Types::POST_TYPE !== $post->post_type || $this->can_delete_quotes()) {
            return $actions;
        }

        unset($actions['trash'], $actions['delete'], $actions['inline hide-if-no-js']);
        return $actions;
    }

    public function filter_bulk_actions($actions) {
        if ($this->can_delete_quotes()) {
            return $actions;
        }

        unset($actions['trash'], $actions['delete']);
        return $actions;
    }

    public function guard_trash_post($pre_trash, $post, $previous_status = null) {
        if (!$post || OKSIA_Post_Types::POST_TYPE !== $post->post_type || $this->can_delete_quotes()) {
            return $pre_trash;
        }

        return false;
    }

    public function guard_delete_post($pre_delete, $post, $force_delete = null) {
        if (!$post || OKSIA_Post_Types::POST_TYPE !== $post->post_type || $this->can_delete_quotes()) {
            return $pre_delete;
        }

        return false;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options') && !current_user_can($this->get_master_settings_capability())) {
            return;
        }

        $users = get_users(array(
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'user_email'),
        ));
        $allow_multi_assignments = count($users) > 1;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Agency Master Settings', 'oksia-smart-itinerary-agent'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('oksia_settings'); ?>
                <?php
                    $agency_details_fields = array(
                        array('label' => 'Authorize Name', 'option' => 'oksia_authorize_name', 'default' => ''),
                        array('label' => 'Contact', 'option' => 'oksia_agency_phone', 'default' => '+91-8320-696-872'),
                        array('label' => 'Email', 'option' => 'oksia_agency_email', 'default' => '', 'type' => 'email'),
                        array('label' => 'Agency name', 'option' => 'oksia_agency_name', 'default' => 'OK'),
                        array('label' => 'Agency Code', 'option' => 'oksia_agency_code', 'default' => 'ECOKSIA1'),
                        array('label' => 'Agency Website', 'option' => 'oksia_agency_website', 'default' => '', 'type' => 'text'),
                        array('label' => 'Agency Address', 'option' => 'oksia_company_address', 'default' => '6th Floor, A-604, Rainbow Exotica, Vatva Lambha Road, Behind Aviraj Pinnacle, Narol, Ahmedabad, Gujarat - 382405 IN', 'span' => 3, 'type' => 'textarea'),
                        array('label' => 'Location', 'option' => 'oksia_agency_location', 'default' => 'Ahmedabad, GJ, India'),
                        array('label' => 'Pincode', 'option' => 'oksia_agency_pincode', 'default' => ''),
                        array('label' => 'IATA/TIDS', 'option' => 'oksia_iata_code', 'default' => '96169710'),
                        array('label' => 'GST Number', 'option' => 'oksia_billing_gst', 'default' => '24ATNPB9314Q1Z8'),
                        array('label' => 'GST Name', 'option' => 'oksia_billing_company', 'default' => 'EKTA CORPORATION'),
                        array('label' => 'GST Email Address', 'option' => 'oksia_billing_email', 'default' => '', 'type' => 'email'),
                        array('label' => 'Main Admin', 'option' => 'oksia_main_agency_user_id', 'default' => 0, 'type' => 'user_select', 'span' => 1),
                    );
                    if ( $allow_multi_assignments ) {
                        $agency_details_fields[] = array('label' => 'Manager', 'option' => 'oksia_agency_manager_user_ids', 'default' => array(), 'type' => 'user_multi_select', 'span' => 1, 'help' => 'Select one or more managers for this agency.');
                        $agency_details_fields[] = array('label' => 'Staff', 'option' => 'oksia_agency_staff_user_ids', 'default' => array(), 'type' => 'user_multi_select', 'span' => 1, 'help' => 'Select one or more staff members for this agency.');
                    } else {
                        $agency_details_fields[] = array('label' => 'Manager', 'option' => 'oksia_agency_manager_user_ids', 'default' => array(), 'type' => 'note', 'span' => 1, 'help' => 'Manager selection appears when more than one user exists.');
                        $agency_details_fields[] = array('label' => 'Staff', 'option' => 'oksia_agency_staff_user_ids', 'default' => array(), 'type' => 'note', 'span' => 1, 'help' => 'Staff selection appears when more than one user exists.');
                    }
                    $agency_details_fields[] = array('label' => 'Primary Color', 'option' => 'oksia_primary_color', 'default' => '#000066', 'type' => 'color');
                    $agency_details_fields[] = array('label' => 'Secondary', 'option' => 'oksia_secondary_color', 'default' => '#336699', 'type' => 'color');
                    $agency_details_fields[] = array('label' => 'Accent', 'option' => 'oksia_accent_color', 'default' => '#99FFFF', 'type' => 'color');
                    $agency_details_fields[] = array('label' => 'Disclaimer Text', 'option' => 'oksia_disclaimer_text', 'default' => 'This is quotation only, no bookings are hold or confirmed. Prices are valid for 24hrs.', 'type' => 'textarea', 'span' => 3);
                    $this->render_settings_section('Agency Details', $agency_details_fields);
                    ?>

                    <?php $this->render_settings_section('Master Dropdown List', array(
                        array('label' => 'Domestic', 'option' => 'oksia_domestic_destinations', 'default' => '', 'type' => 'textarea', 'span' => 2, 'help' => 'Use one value per line.'),
                        array('label' => 'International', 'option' => 'oksia_international_destinations', 'default' => '', 'type' => 'textarea', 'span' => 2, 'help' => 'Use one value per line.'),
                        array('label' => 'Hotel Categories', 'option' => 'oksia_hotel_categories', 'default' => "3 Star\n4 Star\n5 Star\n3/4 Split\n3/5 Split\n4/5 Split", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Occupancies', 'option' => 'oksia_occupancies', 'default' => "Single\nDouble\nTriple\nQuad", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Meal Plans', 'option' => 'oksia_meal_plans', 'default' => "No Meals\nBreakfast\nBreakfast & Dinner\nBreakfast/Lunch/Dinner\nBreakfast/Lunch/HiTea/Dinner", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Meal Transfer', 'option' => 'oksia_meal_transfer_types', 'default' => "Included\nExcluded", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Pickup Points', 'option' => 'oksia_pickup_points', 'default' => '', 'type' => 'textarea'),
                        array('label' => 'Drop Points', 'option' => 'oksia_drop_points', 'default' => '', 'type' => 'textarea'),
                        array('label' => 'Transfer Modes', 'option' => 'oksia_transfer_modes', 'default' => "Private\nSIC - Sharing in Coach", 'type' => 'textarea'),
                        array('label' => 'Sightseeing Vehicles', 'option' => 'oksia_sightseeing_vehicles', 'default' => "Private\nSIC - Sharing in Coach", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Vehicle Types', 'option' => 'oksia_vehicle_types', 'default' => "Tempo Traveller\nInnova\nSedan\nSUV\nCoach\nMinibus", 'type' => 'textarea', 'span' => 2),
                    )); ?>

                    <?php $this->render_settings_section('Master International Policies', array(
                        array('label' => 'Inclusion', 'option' => 'oksia_default_inclusions', 'default' => "Accommodation in selected hotels with base room category\nSelected meals as per itinerary\nSightseeing as specified in the itinerary", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Exlusions', 'option' => 'oksia_default_exclusions', 'default' => "Sightseeing other than specified in the itinerary is chargeable\nPersonal expenses and extra meals are not included\nAny incidental expenses not specified are excluded", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Child Policy', 'option' => 'oksia_default_child_policy', 'default' => "Below 5 years: Complimentary\nUp to 7 years: Chargeable without bed\nAbove 7 years: Chargeable with bed\nAbove 10 years: Extra adult charge", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Cancellation', 'option' => 'oksia_default_cancellation_policy', 'default' => "0 to 10 days before check-in: 100% charge\n11 to 20 days before check-in: 75% charge\n21 to 30 days before check-in: 35% charge", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Booking', 'option' => 'oksia_default_booking_policy', 'default' => "Booking confirmation after 50% advance payment\nVouchers will be issued once services are reconfirmed\n100% payment required before travel", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Refund', 'option' => 'oksia_default_refund_policy', 'default' => '', 'type' => 'textarea', 'span' => 2),
                    )); ?>

                    <?php $this->render_settings_section('Master Domestic Policies', array(
                        array('label' => 'Inclusion', 'option' => 'oksia_domestic_inclusions', 'default' => "Accommodation in selected hotels with base room category\nSelected meals as per itinerary\nSightseeing as specified in the itinerary", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Exlusions', 'option' => 'oksia_domestic_exclusions', 'default' => "Sightseeing other than specified in the itinerary is chargeable\nPersonal expenses and extra meals are not included\nAny incidental expenses not specified are excluded", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Child Policy', 'option' => 'oksia_domestic_child_policy', 'default' => "Below 5 years: Complimentary\nUp to 7 years: Chargeable without bed\nAbove 7 years: Chargeable with bed\nAbove 10 years: Extra adult charge", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Cancellation', 'option' => 'oksia_domestic_cancellation_policy', 'default' => "0 to 10 days before check-in: 100% charge\n11 to 20 days before check-in: 75% charge\n21 to 30 days before check-in: 35% charge", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Booking', 'option' => 'oksia_domestic_booking_policy', 'default' => "Booking confirmation after 50% advance payment\nVouchers will be issued once services are reconfirmed\n100% payment required before travel", 'type' => 'textarea', 'span' => 2),
                        array('label' => 'Refund', 'option' => 'oksia_domestic_refund_policy', 'default' => '', 'type' => 'textarea', 'span' => 2),
                    )); ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function render_settings_section($title, $fields) {
        echo '<section class="oksia-settings-section">';
        echo '<div class="oksia-settings-section__head"><h3>' . esc_html__($title, 'oksia-smart-itinerary-agent') . '</h3></div>';
        echo '<div class="oksia-settings-grid">';
        foreach ((array) $fields as $field) {
            if (!is_array($field) || empty($field['label']) || empty($field['option'])) {
                continue;
            }
            $this->render_settings_field($field);
        }
        echo '</div>';
        echo '</section>';
    }

    private function render_settings_field($field) {
        $label = (string) ($field['label'] ?? '');
        $option_name = (string) ($field['option'] ?? '');
        $default = $field['default'] ?? '';
        $type = (string) ($field['type'] ?? 'text');
        $span = max(1, min(3, absint($field['span'] ?? 1)));
        $help = (string) ($field['help'] ?? '');
        $value = get_option($option_name, $default);
        if ('color' === $type) {
            $value = sanitize_hex_color((string) $value);
            if (empty($value)) {
                $value = sanitize_hex_color((string) $default) ?: '#000066';
            }
        }

        echo '<div class="oksia-settings-field oksia-settings-field--span-' . esc_attr((string) $span) . '">';
        echo '<label for="' . esc_attr($option_name) . '">' . esc_html__($label, 'oksia-smart-itinerary-agent') . '</label>';

        if ('textarea' === $type) {
            echo '<textarea id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" rows="5">' . esc_textarea((string) $value) . '</textarea>';
        } elseif ('user_select' === $type) {
            $users = get_users(array(
                'orderby' => 'display_name',
                'order' => 'ASC',
                'fields' => array('ID', 'display_name', 'user_email'),
            ));
            $selected_user_id = absint($value);
            echo '<select id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '">';
            echo '<option value="0">' . esc_html__('Use current admin fallback', 'oksia-smart-itinerary-agent') . '</option>';
            foreach ($users as $user) {
                $label_text = trim($user->display_name);
                if (!empty($user->user_email)) {
                    $label_text .= ' (' . $user->user_email . ')';
                }
                echo '<option value="' . esc_attr((string) $user->ID) . '" ' . selected($selected_user_id, $user->ID, false) . '>' . esc_html($label_text) . '</option>';
            }
            echo '</select>';
        } elseif ('user_multi_select' === $type) {
            $users = get_users(array(
                'orderby' => 'display_name',
                'order' => 'ASC',
                'fields' => array('ID', 'display_name', 'user_email'),
            ));
            $selected_ids = array_map('absint', (array) $value);
            echo '<select id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '[]" multiple size="6">';
            foreach ($users as $user) {
                $label_text = trim($user->display_name);
                if (!empty($user->user_email)) {
                    $label_text .= ' (' . $user->user_email . ')';
                }
                echo '<option value="' . esc_attr((string) $user->ID) . '" ' . selected(in_array((int) $user->ID, $selected_ids, true), true, false) . '>' . esc_html($label_text) . '</option>';
            }
            echo '</select>';
        } elseif ('note' === $type) {
            echo '<div class="description" style="margin-top:6px;">' . esc_html($help) . '</div>';
        } elseif ('color' === $type) {
            echo '<input type="color" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr((string) $value) . '" />';
        } else {
            echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr((string) $value) . '" />';
        }

        if ('' !== $help) {
            echo '<p class="description">' . esc_html($help) . '</p>';
        }

        echo '</div>';
    }

    private function render_feature_toggle_row($flag_key, $meta, $flags) {
        $flag_key = (string) $flag_key;
        $title = (string) ($meta['title'] ?? $flag_key);
        $description = (string) ($meta['description'] ?? '');
        $enabled = !empty($flags[$flag_key]);
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($title); ?></th>
            <td>
                <label class="oksia-toggle">
                    <input type="checkbox" name="oksia_feature_flags[<?php echo esc_attr($flag_key); ?>]" value="1" <?php checked($enabled); ?> />
                    <span class="oksia-toggle__track" aria-hidden="true"></span>
                    <span class="oksia-toggle__text"><?php echo esc_html($enabled ? __('On', 'oksia-smart-itinerary-agent') : __('Off', 'oksia-smart-itinerary-agent')); ?></span>
                </label>
                <?php if ('' !== $description) : ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function render_plan_matrix_row($feature_key, $meta, $plan_matrix) {
        $feature_key = (string) $feature_key;
        $title = (string) ($meta['title'] ?? $feature_key);
        $description = (string) ($meta['description'] ?? '');
        $economy = !empty($plan_matrix['economy'][$feature_key]);
        $premium = !empty($plan_matrix['premium'][$feature_key]);
        $business = !empty($plan_matrix['business'][$feature_key]);
        ?>
        <tr>
            <th scope="row">
                <?php echo esc_html($title); ?>
                <?php if ('' !== $description) : ?>
                    <p class="description" style="margin:4px 0 0;"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </th>
            <td>
                <label class="oksia-toggle" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="oksia_plan_feature_matrix[economy][<?php echo esc_attr($feature_key); ?>]" value="1" <?php checked($economy); ?> />
                    <span class="oksia-toggle__track" aria-hidden="true"></span>
                    <span class="oksia-toggle__text"><?php echo esc_html($economy ? __('On', 'oksia-smart-itinerary-agent') : __('Off', 'oksia-smart-itinerary-agent')); ?></span>
                </label>
            </td>
            <td>
                <label class="oksia-toggle" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="oksia_plan_feature_matrix[premium][<?php echo esc_attr($feature_key); ?>]" value="1" <?php checked($premium); ?> />
                    <span class="oksia-toggle__track" aria-hidden="true"></span>
                    <span class="oksia-toggle__text"><?php echo esc_html($premium ? __('On', 'oksia-smart-itinerary-agent') : __('Off', 'oksia-smart-itinerary-agent')); ?></span>
                </label>
            </td>
            <td>
                <label class="oksia-toggle" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="oksia_plan_feature_matrix[business][<?php echo esc_attr($feature_key); ?>]" value="1" <?php checked($business); ?> />
                    <span class="oksia-toggle__track" aria-hidden="true"></span>
                    <span class="oksia-toggle__text"><?php echo esc_html($business ? __('On', 'oksia-smart-itinerary-agent') : __('Off', 'oksia-smart-itinerary-agent')); ?></span>
                </label>
            </td>
        </tr>
        <?php
    }

    private function render_settings_row($label, $option_name, $default = '', $type = 'text') {
        $value = get_option($option_name, $default);
        echo '<tr><th scope="row"><label for="' . esc_attr($option_name) . '">' . esc_html__($label, 'oksia-smart-itinerary-agent') . '</label></th><td><input type="' . esc_attr($type) . '" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr((string) $value) . '" class="large-text" /></td></tr>';
    }

    private function render_settings_area_row($label, $option_name, $default = '') {
        $value = get_option($option_name, $default);
        echo '<tr><th scope="row"><label for="' . esc_attr($option_name) . '">' . esc_html__($label, 'oksia-smart-itinerary-agent') . '</label></th><td><textarea id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" rows="4" class="large-text">' . esc_textarea((string) $value) . '</textarea><p class="description">' . esc_html__('Use one value per line.', 'oksia-smart-itinerary-agent') . '</p></td></tr>';
    }

    private function render_user_select_row($label, $option_name, $description = '', $default = 0) {
        $selected_user_id = absint(get_option($option_name, $default));
        $users = get_users(array(
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'user_email'),
        ));

        echo '<tr><th scope="row"><label for="' . esc_attr($option_name) . '">' . esc_html__($label, 'oksia-smart-itinerary-agent') . '</label></th><td>';
        echo '<select id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" class="regular-text">';
        echo '<option value="0">' . esc_html__('Use current admin fallback', 'oksia-smart-itinerary-agent') . '</option>';
        foreach ($users as $user) {
            $label_text = trim($user->display_name);
            if (!empty($user->user_email)) {
                $label_text .= ' (' . $user->user_email . ')';
            }
            echo '<option value="' . esc_attr((string) $user->ID) . '" ' . selected($selected_user_id, $user->ID, false) . '>' . esc_html($label_text) . '</option>';
        }
        echo '</select>';
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        echo '</td></tr>';
    }

    private function can_delete_quotes() {
        return current_user_can('delete_oksia_itineraries');
    }

    private function render_select_field($label, $name, $id, $selected_value, $options) {
        echo '<p><label for="' . esc_attr($id) . '">' . esc_html__($label, 'oksia-smart-itinerary-agent') . '</label><select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="widefat"><option value="">' . esc_html__('Select', 'oksia-smart-itinerary-agent') . '</option>';
        foreach ($options as $value) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($selected_value, $value, false) . '>' . esc_html($value) . '</option>';
        }
        echo '</select></p>';
    }

    private function render_document_row($index, $document, $is_template = false) {
        $attachment_id = isset($document['attachment_id']) ? absint($document['attachment_id']) : 0;
        ?>
        <div class="oksia-document-card">
            <input type="hidden" class="oksia-attachment-id" name="oksia_documents[<?php echo esc_attr($index); ?>][attachment_id]" value="<?php echo esc_attr((string) $attachment_id); ?>" />
            <p><label><?php esc_html_e('Document Title', 'oksia-smart-itinerary-agent'); ?></label><input type="text" name="oksia_documents[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($document['title'] ?? ''); ?>" class="widefat" /></p>
            <?php $this->render_select_field('Document Type', 'oksia_documents[' . $index . '][type]', 'oksia_document_type_' . $index, $document['type'] ?? '', array('Flight Ticket', 'Hotel Voucher', 'Transfer', 'Activity', 'Visa / Insurance', 'Manual Note')); ?>
            <p><label><?php esc_html_e('Upload / Link', 'oksia-smart-itinerary-agent'); ?></label><span class="oksia-upload-row"><button type="button" class="button oksia-upload-document"><?php esc_html_e('Choose File', 'oksia-smart-itinerary-agent'); ?></button><input type="url" class="widefat oksia-document-url" name="oksia_documents[<?php echo esc_attr($index); ?>][url]" value="<?php echo esc_attr($document['url'] ?? ''); ?>" /></span></p>
            <p><label><?php esc_html_e('Notes / Key Details', 'oksia-smart-itinerary-agent'); ?></label><textarea name="oksia_documents[<?php echo esc_attr($index); ?>][notes]" rows="3" class="widefat"><?php echo esc_textarea($document['notes'] ?? ''); ?></textarea></p>
            <p><button type="button" class="button-link-delete oksia-remove-row"><?php esc_html_e('Remove document', 'oksia-smart-itinerary-agent'); ?></button></p>
        </div>
        <?php
    }

    private function render_hotel_plan_row($index, $stay, $is_template = false) {
        ?>
        <div class="oksia-hotel-plan-row">
            <p><label><?php esc_html_e('City', 'oksia-smart-itinerary-agent'); ?></label><input type="text" list="oksia-city-options" name="oksia_hotel_plan[<?php echo esc_attr($index); ?>][city]" value="<?php echo esc_attr($stay['city'] ?? ''); ?>" class="widefat oksia-city-input" /></p>
            <p><label><?php esc_html_e('Hotel Name', 'oksia-smart-itinerary-agent'); ?></label><input type="text" list="oksia-hotel-options" name="oksia_hotel_plan[<?php echo esc_attr($index); ?>][hotel]" value="<?php echo esc_attr($stay['hotel'] ?? ''); ?>" class="widefat oksia-hotel-input" /></p>
            <p><label><?php esc_html_e('Night', 'oksia-smart-itinerary-agent'); ?></label><input type="number" min="0" name="oksia_hotel_plan[<?php echo esc_attr($index); ?>][nights]" value="<?php echo esc_attr((string) ($stay['nights'] ?? '')); ?>" class="widefat" /></p>
            <p class="oksia-row-action"><button type="button" class="button-link-delete oksia-remove-row"><?php esc_html_e('Remove stop', 'oksia-smart-itinerary-agent'); ?></button></p>
        </div>
        <?php
    }

    private function render_day_row($index, $day, $is_template = false) {
        $image_id = isset($day['image_id']) ? absint($day['image_id']) : 0;
        $image_url = isset($day['image_url']) ? $day['image_url'] : '';
        ?>
        <div class="oksia-day-card">
            <input type="hidden" class="oksia-day-image-id" name="oksia_days[<?php echo esc_attr($index); ?>][image_id]" value="<?php echo esc_attr((string) $image_id); ?>" />
            <input type="hidden" class="oksia-day-image-url" name="oksia_days[<?php echo esc_attr($index); ?>][image_url]" value="<?php echo esc_attr($image_url); ?>" />
            <div class="oksia-grid oksia-grid--two">
                <p><label><?php esc_html_e('Day Title', 'oksia-smart-itinerary-agent'); ?></label><input type="text" name="oksia_days[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($day['title'] ?? ''); ?>" class="widefat" /></p>
                <p><label><?php esc_html_e('Location / Activity', 'oksia-smart-itinerary-agent'); ?></label><input type="text" list="oksia-sightseeing-options" name="oksia_days[<?php echo esc_attr($index); ?>][location]" value="<?php echo esc_attr($day['location'] ?? ''); ?>" class="widefat" /></p>
            </div>
            <p><label><?php esc_html_e('Description', 'oksia-smart-itinerary-agent'); ?></label><textarea name="oksia_days[<?php echo esc_attr($index); ?>][description]" rows="4" class="widefat"><?php echo esc_textarea($day['description'] ?? ''); ?></textarea></p>
            <p><label><?php esc_html_e('Logistics', 'oksia-smart-itinerary-agent'); ?></label><textarea name="oksia_days[<?php echo esc_attr($index); ?>][logistics]" rows="3" class="widefat"><?php echo esc_textarea($day['logistics'] ?? ''); ?></textarea></p>
            <p class="oksia-upload-row"><button type="button" class="button oksia-upload-day-image"><?php esc_html_e('Choose Day Image', 'oksia-smart-itinerary-agent'); ?></button><span class="oksia-day-image-preview"><?php echo esc_html($image_url ? basename($image_url) : __('No image selected', 'oksia-smart-itinerary-agent')); ?></span></p>
            <p><button type="button" class="button-link-delete oksia-remove-row"><?php esc_html_e('Remove day', 'oksia-smart-itinerary-agent'); ?></button></p>
        </div>
        <?php
    }

    private function get_trip_data($post_id) {
        $defaults = array(
            'salutation' => 'Mr',
            'client_name' => '',
            'trip_type' => 'Domestic',
            'destination' => '',
            'start_date' => '',
            'end_date' => '',
            'total_nights' => 0,
            'adults' => 0,
            'adult_with_bed' => 0,
            'child_without_bed' => 0,
            'travelers' => 0,
        );
        return wp_parse_args((array) get_post_meta($post_id, '_oksia_trip_overview', true), $defaults);
    }

    private function get_quote_data($post_id) {
        $defaults = array(
            'hotel_category' => '',
            'occupancy' => '',
            'rooms' => 0,
            'meal_plan' => '',
            'meal_transfers' => '',
            'pickup_from' => '',
            'drop_to' => '',
            'first_transfer' => '',
            'last_transfer' => '',
            'sightseeing_vehicle' => '',
            'vehicle_type' => '',
            'transfer_note' => '',
            'currency' => 'INR',
            'exchange_rate' => '',
            'transaction_cost' => 1.9,
            'additional_cost' => 0,
            'effective_rate' => '',
            'adult_rate' => '',
            'with_bed_rate' => '',
            'child_rate' => '',
            'adult_markup' => '',
            'with_bed_markup' => '',
            'child_markup' => '',
            'adult_rate_quote' => '',
            'with_bed_rate_quote' => '',
            'child_rate_quote' => '',
        );
        return wp_parse_args((array) get_post_meta($post_id, '_oksia_quote_details', true), $defaults);
    }

    private function get_quote_id($post_id) {
        $quote_id = get_post_meta($post_id, '_oksia_quote_id', true);
        return $quote_id ? $quote_id : $this->generate_next_quote_id();
    }

    private function generate_next_quote_id() {
        global $wpdb;

        $date_part = wp_date('ymd', current_time('timestamp'));
        $prefix = 'OK' . $date_part;
        $like = $wpdb->esc_like($prefix) . '%';
        $meta_key = '_oksia_quote_id';

        $existing_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
                $meta_key,
                $like
            )
        );

        $max_sequence = 0;
        foreach ((array) $existing_ids as $existing_id) {
            if (0 === strpos((string) $existing_id, $prefix)) {
                $suffix = (int) substr((string) $existing_id, strlen($prefix));
                if ($suffix > $max_sequence) {
                    $max_sequence = $suffix;
                }
            }
        }

        return $prefix . str_pad((string) ($max_sequence + 1), 2, '0', STR_PAD_LEFT);
    }

    private function get_operational_defaults() {
        return array(
            'inclusions' => (string) get_option('oksia_default_inclusions', ''),
            'exclusions' => (string) get_option('oksia_default_exclusions', ''),
            'child_policy' => (string) get_option('oksia_default_child_policy', ''),
            'booking_policy' => (string) get_option('oksia_default_booking_policy', ''),
            'cancellation_policy' => (string) get_option('oksia_default_cancellation_policy', ''),
        );
    }

    private function get_setting_options($option_name, $fallback) {
        $value = (string) get_option($option_name, '');
        $items = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value)));
        return !empty($items) ? array_values($items) : $fallback;
    }

    private function get_reusable_values($option_name) {
        $values = get_option($option_name, array());
        return is_array($values) ? array_values(array_filter(array_map('sanitize_text_field', $values))) : array();
    }

    private function update_reusable_values($option_name, $incoming_values) {
        $existing = $this->get_reusable_values($option_name);
        $merged = array_unique(array_filter(array_merge($existing, array_map('sanitize_text_field', (array) $incoming_values))));
        sort($merged, SORT_NATURAL | SORT_FLAG_CASE);
        update_option($option_name, array_values($merged), false);
    }

    private function calculate_nights($start_date, $end_date) {
        if (!$start_date || !$end_date) {
            return 0;
        }
        try {
            $start = new DateTimeImmutable($start_date);
            $end = new DateTimeImmutable($end_date);
            $diff = $start->diff($end);
            return max(0, (int) $diff->days);
        } catch (Exception $exception) {
            return 0;
        }
    }

    private function sanitize_decimal($value, $default) {
        $value = is_scalar($value) ? (string) $value : '';
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        return '' === $value ? (string) $default : $value;
    }

    private function sanitize_documents($documents) {
        $clean = array();
        foreach ($documents as $document) {
            $row = array(
                'attachment_id' => absint($document['attachment_id'] ?? 0),
                'title' => sanitize_text_field($document['title'] ?? ''),
                'type' => sanitize_text_field($document['type'] ?? ''),
                'url' => esc_url_raw($document['url'] ?? ''),
                'notes' => sanitize_textarea_field($document['notes'] ?? ''),
            );
            if (!$row['attachment_id'] && '' === $row['title'] && '' === $row['url'] && '' === $row['notes']) {
                continue;
            }
            $clean[] = $row;
        }
        return $clean;
    }

    private function sanitize_hotel_plan($hotel_plan) {
        $clean = array();
        foreach ($hotel_plan as $stay) {
            $row = array(
                'city' => sanitize_text_field($stay['city'] ?? ''),
                'hotel' => sanitize_text_field($stay['hotel'] ?? ''),
                'nights' => absint($stay['nights'] ?? 0),
            );
            if ('' === $row['city'] && '' === $row['hotel'] && 0 === $row['nights']) {
                continue;
            }
            $clean[] = $row;
        }
        return $clean;
    }

    private function sanitize_days($days) {
        $clean = array();
        foreach ($days as $day) {
            $row = array(
                'title' => sanitize_text_field($day['title'] ?? ''),
                'location' => sanitize_text_field($day['location'] ?? ''),
                'description' => sanitize_textarea_field($day['description'] ?? ''),
                'logistics' => sanitize_textarea_field($day['logistics'] ?? ''),
                'image_id' => absint($day['image_id'] ?? 0),
                'image_url' => esc_url_raw($day['image_url'] ?? ''),
            );
            if ('' === $row['title'] && '' === $row['description'] && '' === $row['logistics']) {
                continue;
            }
            $clean[] = $row;
        }
        return $clean;
    }

    private function process_generate_ai_draft($post_id) {
        $draft = $this->ai_service->generate_itinerary_draft($post_id);
        if (is_wp_error($draft)) {
            update_post_meta($post_id, '_oksia_ai_status', sprintf(__('AI error: %s', 'oksia-smart-itinerary-agent'), $draft->get_error_message()));
            return;
        }

        $existing = (array) get_post_meta($post_id, '_oksia_operational_notes', true);
        $operational = array(
            'summary' => sanitize_textarea_field($draft['summary'] ?? ''),
            'inclusions' => sanitize_textarea_field(implode("\n", $draft['inclusions'] ?? array())),
            'exclusions' => sanitize_textarea_field(implode("\n", $draft['exclusions'] ?? array())),
            'important_notes' => sanitize_textarea_field($draft['important_notes'] ?? ''),
            'child_policy' => $existing['child_policy'] ?? '',
            'booking_policy' => $existing['booking_policy'] ?? '',
            'cancellation_policy' => $existing['cancellation_policy'] ?? '',
        );
        update_post_meta($post_id, '_oksia_operational_notes', $operational);
        $clean_days = $this->sanitize_days($draft['days'] ?? array());
        update_post_meta($post_id, '_oksia_days', $clean_days);
        $this->update_reusable_values('oksia_saved_sightseeing', wp_list_pluck($clean_days, 'location'));
        $this->set_quote_stage($post_id, 'send');
        if (class_exists('OKSIA_Quote_Templates')) {
            OKSIA_Quote_Templates::ensure_default_template_key($post_id);
        }
        update_post_meta($post_id, '_oksia_quote_status', __('Quote sent for review.', 'oksia-smart-itinerary-agent'));
        update_post_meta($post_id, '_oksia_ai_status', __('Draft generated. Review the day-wise itinerary and attach images where needed.', 'oksia-smart-itinerary-agent'));
        self::sync_quote_version_meta($post_id, get_current_user_id());
    }

    private function ensure_daywise_itinerary($post_id, &$generated = false) {
        $generated = false;
        $days = get_post_meta($post_id, '_oksia_days', true);
        if (is_array($days) && !empty($days)) {
            return true;
        }

        if (!$this->ai_service || !method_exists($this->ai_service, 'generate_itinerary_draft')) {
            return false;
        }

        $draft = $this->ai_service->generate_itinerary_draft($post_id);
        if (is_wp_error($draft)) {
            update_post_meta($post_id, '_oksia_ai_status', sprintf(__('AI error: %s', 'oksia-smart-itinerary-agent'), $draft->get_error_message()));
            return false;
        }

        $existing = (array) get_post_meta($post_id, '_oksia_operational_notes', true);
        $operational = array(
            'summary' => sanitize_textarea_field($draft['summary'] ?? ''),
            'inclusions' => sanitize_textarea_field(implode("
", $draft['inclusions'] ?? array())),
            'exclusions' => sanitize_textarea_field(implode("
", $draft['exclusions'] ?? array())),
            'important_notes' => sanitize_textarea_field($draft['important_notes'] ?? ''),
            'child_policy' => $existing['child_policy'] ?? '',
            'booking_policy' => $existing['booking_policy'] ?? '',
            'cancellation_policy' => $existing['cancellation_policy'] ?? '',
        );
        update_post_meta($post_id, '_oksia_operational_notes', $operational);
        $clean_days = $this->sanitize_days($draft['days'] ?? array());
        update_post_meta($post_id, '_oksia_days', $clean_days);
        $this->update_reusable_values('oksia_saved_sightseeing', wp_list_pluck($clean_days, 'location'));
        $this->set_quote_stage($post_id, 'send');
        update_post_meta($post_id, '_oksia_quote_status', __('Quote sent for review.', 'oksia-smart-itinerary-agent'));
        update_post_meta($post_id, '_oksia_ai_status', __('Draft generated. Review the day-wise itinerary and attach images where needed.', 'oksia-smart-itinerary-agent'));
        $generated = true;

        return true;
    }

    private function process_quote_state_action($post_id, $action) {
        if ('download_draft' === $action) {
            $quote_stage = self::get_quote_stage($post_id);
            // Allow PDF download for finalized quotes (send or confirmed status)
            if (!in_array($quote_stage, array('send', 'confirmed'), true)) {
                return new WP_Error(
                    'oksia_pdf_requires_finalization',
                    __('PDF generation is available only after a quote is finalized and sent, or confirmed.', 'oksia-smart-itinerary-agent')
                );
            }

            $generated_days = false;
            $this->ensure_daywise_itinerary($post_id, $generated_days);
            if ($generated_days) {
                self::sync_quote_version_meta($post_id, get_current_user_id());
            }
            $pdf = $this->build_pdf_attachment($post_id);
            if (is_wp_error($pdf)) {
                wp_die(esc_html($pdf->get_error_message()));
            }

            while (ob_get_level()) {
                ob_end_clean();
            }
            nocache_headers();
            status_header(200);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $pdf['filename'] . '"');
            header('Content-Length: ' . filesize($pdf['file']));
            readfile($pdf['file']);
            @unlink($pdf['file']);
            exit;
        }

        $quote_stage = self::get_quote_stage($post_id);
        if ('cancelled' === $quote_stage && in_array($action, array('finalize', 'confirmed', 'reopen'), true)) {
            return new WP_Error('oksia_quote_locked', __('Cancelled quotes cannot be reopened or confirmed again.', 'oksia-smart-itinerary-agent'));
        }

        if ('reopen' === $action) {
            if (!$this->can_manage_finalized_quotes()) {
                wp_die(esc_html__('Only managers and admins can reopen a finalized quote.', 'oksia-smart-itinerary-agent'));
            }

            $revisions = $this->get_quote_revision_count($post_id);
            update_post_meta($post_id, '_oksia_quote_finalized', '0');
            $this->set_quote_stage($post_id, 'draft');
            update_post_meta($post_id, '_oksia_quote_status', __('Draft reopened for edits.', 'oksia-smart-itinerary-agent'));
            update_post_meta($post_id, '_oksia_quote_revision_count', $revisions + 1);
            update_post_meta($post_id, '_oksia_ai_status', __('Quote reopened. Make changes and finalize again when ready.', 'oksia-smart-itinerary-agent'));
            self::sync_quote_version_meta($post_id, get_current_user_id());
            return true;
        }

        if ('finalize' === $action) {
            return $this->finalize_quote($post_id, 'send');
        }

        if ('confirmed' === $action) {
            return $this->confirm_quote($post_id);
        }

        if ('cancelled' === $action) {
            return $this->cancel_quote($post_id);
        }

        return false;
    }

    private function finalize_quote($post_id, $final_stage = 'confirmed') {
        $this->ensure_daywise_itinerary($post_id);
        if (class_exists('OKSIA_Quote_Templates')) {
            OKSIA_Quote_Templates::ensure_default_template_key($post_id);
        }
        $pdf = $this->build_pdf_attachment($post_id);
        if (is_wp_error($pdf)) {
            update_post_meta($post_id, '_oksia_quote_status', sprintf(__('Finalization failed: %s', 'oksia-smart-itinerary-agent'), $pdf->get_error_message()));
            return false;
        }
        $revised = $this->get_quote_revision_count($post_id) > 0;
        $subject = $this->build_quote_subject($post_id);
        if ($revised) {
            $subject .= ' - Revised';
        }

        $body = $this->build_quote_email_body($post_id);
        $admin_email = sanitize_email((string) get_option('oksia_billing_email', get_option('admin_email')));
        if ('' === $admin_email) {
            $admin_email = sanitize_email((string) get_option('admin_email'));
        }
        $customer_email = sanitize_email((string) get_post_meta($post_id, '_oksia_client_email', true));

        $admin_sent = $this->send_quote_email($admin_email, $subject, $body, $pdf['file']);
        $customer_sent = true;
        if ('' !== $customer_email) {
            $customer_sent = $this->send_quote_email($customer_email, $subject, $body, $pdf['file']);
        }

        @unlink($pdf['file']);

        if (!$admin_sent) {
            update_post_meta($post_id, '_oksia_quote_status', __('Finalization failed while sending the admin email.', 'oksia-smart-itinerary-agent'));
            update_post_meta($post_id, '_oksia_ai_status', __('Quote finalized file generated, but admin email could not be sent.', 'oksia-smart-itinerary-agent'));
            return false;
        }

        update_post_meta($post_id, '_oksia_quote_finalized', '1');
        $this->set_quote_stage($post_id, $final_stage);
        update_post_meta($post_id, '_oksia_quote_finalized_at', current_time('mysql'));
        update_post_meta($post_id, '_oksia_quote_finalized_by', get_current_user_id());
        if ('send' === $final_stage) {
            update_post_meta($post_id, '_oksia_quote_status', $customer_sent ? __('Quote finalized and emailed to admin and customer.', 'oksia-smart-itinerary-agent') : __('Quote finalized and emailed to admin.', 'oksia-smart-itinerary-agent'));
            update_post_meta($post_id, '_oksia_ai_status', $customer_sent ? __('Quote finalized and locked.', 'oksia-smart-itinerary-agent') : __('Quote finalized and locked. Customer email was not available.', 'oksia-smart-itinerary-agent'));
        } else {
            update_post_meta($post_id, '_oksia_quote_status', $customer_sent ? __('Quote confirmed and emailed to admin and customer.', 'oksia-smart-itinerary-agent') : __('Quote confirmed and emailed to admin.', 'oksia-smart-itinerary-agent'));
            update_post_meta($post_id, '_oksia_ai_status', $customer_sent ? __('Quote confirmed and locked.', 'oksia-smart-itinerary-agent') : __('Quote confirmed and locked. Customer email was not available.', 'oksia-smart-itinerary-agent'));
        }
        self::sync_quote_version_meta($post_id, get_current_user_id());
        return true;
    }

    private function confirm_quote($post_id) {
        if ('cancelled' === self::get_quote_stage($post_id)) {
            return new WP_Error('oksia_quote_locked', __('Cancelled quotes cannot be confirmed again.', 'oksia-smart-itinerary-agent'));
        }

        $travel_pnr_raw = $_POST['oksia_travel_pnr'] ?? '';
        $hotel_pnr_raw = $_POST['oksia_hotel_pnr'] ?? ($_POST['oksia_confirmation_note'] ?? '');
        $travel_pnr = sanitize_text_field(wp_unslash($travel_pnr_raw));
        $hotel_pnr = sanitize_text_field(wp_unslash($hotel_pnr_raw));
        $handler_type = sanitize_text_field(wp_unslash($_POST['oksia_handler_type'] ?? ''));
        $handler_name = sanitize_text_field(wp_unslash($_POST['oksia_handler_name'] ?? ''));
        $template_key = sanitize_text_field(wp_unslash($_POST['oksia_quote_template_style'] ?? ''));
        $template_key = class_exists('OKSIA_Quote_Templates') ? OKSIA_Quote_Templates::normalize_template_key($template_key) : 'default';
        $confirmed_by = sanitize_text_field(wp_unslash($_POST['oksia_confirmed_by'] ?? ''));

        if ('' === $confirmed_by) {
            $user = wp_get_current_user();
            $confirmed_by = $user instanceof WP_User ? trim((string) $user->display_name) : '';
            if ('' === $confirmed_by && $user instanceof WP_User) {
                $confirmed_by = trim((string) $user->user_login);
            }
        }

        update_post_meta($post_id, '_oksia_travel_pnr', $travel_pnr);
        update_post_meta($post_id, '_oksia_hotel_pnr', $hotel_pnr);
        update_post_meta($post_id, '_oksia_handler_type', $handler_type);
        update_post_meta($post_id, '_oksia_handler_name', $handler_name);
        update_post_meta($post_id, '_oksia_quote_template_style', $template_key);
        update_post_meta($post_id, '_oksia_quote_confirmation_note', '' !== $hotel_pnr ? $hotel_pnr : $travel_pnr);
        update_post_meta($post_id, '_oksia_quote_confirmed_by_name', $confirmed_by);
        update_post_meta($post_id, '_oksia_dmc_name', $handler_name);
        update_post_meta($post_id, '_oksia_dmc_number', '');
        update_post_meta($post_id, '_oksia_driver_name', '');
        update_post_meta($post_id, '_oksia_dmc_quote_id', '');
        if (class_exists('OKSIA_Quote_Templates')) {
            OKSIA_Quote_Templates::ensure_default_template_key($post_id);
        }

        return $this->finalize_quote($post_id, 'confirmed');
    }

    private function cancel_quote($post_id) {
        if ('cancelled' === self::get_quote_stage($post_id)) {
            return new WP_Error('oksia_quote_already_cancelled', __('This quote has already been cancelled and cannot be changed again.', 'oksia-smart-itinerary-agent'));
        }

        $reason = sanitize_text_field(wp_unslash($_POST['oksia_cancel_reason'] ?? ''));
        $allowed = array('Budget Issues', 'Ghost Client', 'Confirmed Outside', 'Other');
        if ('' === $reason || !in_array($reason, $allowed, true)) {
            return new WP_Error(
                'oksia_missing_cancel_reason',
                __('Please select a cancellation reason before cancelling this quote.', 'oksia-smart-itinerary-agent'),
                array('fields' => array('cancel_reason'))
            );
        }

        update_post_meta($post_id, '_oksia_quote_cancel_reason', $reason);
        update_post_meta($post_id, '_oksia_quote_finalized', '1');
        $this->set_quote_stage($post_id, 'cancelled');
        update_post_meta($post_id, '_oksia_quote_finalized_at', current_time('mysql'));
        update_post_meta($post_id, '_oksia_quote_finalized_by', get_current_user_id());
        update_post_meta($post_id, '_oksia_quote_status', sprintf(__('Quote cancelled: %s', 'oksia-smart-itinerary-agent'), $reason));
        update_post_meta($post_id, '_oksia_ai_status', __('Quote cancelled and permanently locked.', 'oksia-smart-itinerary-agent'));
        self::sync_quote_version_meta($post_id, get_current_user_id());
        return true;
    }

    public function handle_quote_state_action($post_id, $action) {
        return $this->process_quote_state_action($post_id, $action);
    }

    private function maybe_process_after_save_action($post_id) {
        if (empty($_POST['oksia_after_save_action'])) {
            return;
        }
        $action = sanitize_text_field(wp_unslash($_POST['oksia_after_save_action']));
        if ('generate' === $action) {
            $this->process_generate_ai_draft($post_id);
        }
    }

    public function maybe_redirect_to_generated_pdf($location, $post_id) {
        if (OKSIA_Post_Types::POST_TYPE !== get_post_type($post_id)) {
            return $location;
        }

        $action = sanitize_text_field(wp_unslash($_POST['oksia_after_save_action'] ?? ''));
        if ('generate' !== $action) {
            return $location;
        }

        $view_url = self::get_quote_view_url($post_id);
        if ('' === $view_url) {
            return $location;
        }

        return $view_url;
    }
}



