<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_Workspace {
    const OPTION_LOGIN_PAGE_ID = 'oksia_login_page_id';
    const OPTION_DASHBOARD_PAGE_ID = 'oksia_dashboard_page_id';
    const OPTION_AGENT_INTAKE_PAGE_ID = 'oksia_agent_intake_page_id';
    const OPTION_CLIENT_INTAKE_PAGE_ID = 'oksia_client_intake_page_id';
    const OPTION_SETTINGS_PAGE_ID = 'oksia_settings_page_id';
    const OPTION_REGISTRATION_PAGE_ID = 'oksia_registration_page_id';
    const OPTION_SUPPORT_PAGE_ID = 'oksia_support_page_id';
    const OPTION_CURRENCY_SNAPSHOT = 'oksia_currency_snapshot';
    const OPTION_REGISTRATION_LOCKED = 'oksia_agency_registration_locked';
    const OPTION_REGISTRATION_LOCKED_AT = 'oksia_agency_registration_locked_at';
    const CRON_CURRENCY_REFRESH_HOOK = 'oksia_currency_refresh_daily';
    const OPTION_PAGES_SEEDED = 'oksia_workspace_pages_seeded';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate() {
        self::seed_pages(true);
        self::schedule_currency_refresh();
    }

    public static function deactivate() {
        self::unschedule_currency_refresh();
    }

    public static function seed_pages($force = false) {
        if (!$force && '1' === (string) get_option(self::OPTION_PAGES_SEEDED, '0')) {
            return;
        }

        $pages = array(
            self::OPTION_LOGIN_PAGE_ID => array(
                'title' => __('OK Login', 'oksia-smart-itinerary-agent'),
                'slug' => 'ok-login',
                'content' => '[oksia_login]',
            ),
            self::OPTION_DASHBOARD_PAGE_ID => array(
                'title' => __('OK Dashboard', 'oksia-smart-itinerary-agent'),
                'slug' => 'oksia-dashboard',
                'content' => '[oksia_dashboard]',
            ),
            self::OPTION_AGENT_INTAKE_PAGE_ID => array(
                'title' => __('Agent Intake', 'oksia-smart-itinerary-agent'),
                'slug' => 'agent-intake',
                'content' => '[oksia_agent_intake_form]',
            ),
            self::OPTION_CLIENT_INTAKE_PAGE_ID => array(
                'title' => __('Client Intake', 'oksia-smart-itinerary-agent'),
                'slug' => 'client-intake',
                'content' => '[oksia_intake_form]',
            ),
            self::OPTION_SETTINGS_PAGE_ID => array(
                'title' => __('Agency Master Settings', 'oksia-smart-itinerary-agent'),
                'slug' => 'agency-settings',
                'content' => '[oksia_agency_settings]',
            ),
            self::OPTION_REGISTRATION_PAGE_ID => array(
                'title' => __('Agency Registration', 'oksia-smart-itinerary-agent'),
                'slug' => 'agency-registration',
                'content' => '[oksia_agency_registration]',
            ),
            self::OPTION_SUPPORT_PAGE_ID => array(
                'title' => __('Support', 'oksia-smart-itinerary-agent'),
                'slug' => 'support',
                'content' => '[oksia_support]',
            ),
        );

        foreach ($pages as $option_key => $page) {
            $page_id = absint(get_option($option_key, 0));
            $existing = $page_id ? get_post($page_id) : null;
            if (!$existing || 'page' !== $existing->post_type) {
                $found = get_page_by_path($page['slug']);
                if ($found instanceof WP_Post) {
                    $page_id = (int) $found->ID;
                } else {
                    $page_id = wp_insert_post(
                        array(
                            'post_type' => 'page',
                            'post_status' => 'publish',
                            'post_title' => $page['title'],
                            'post_name' => $page['slug'],
                            'post_content' => $page['content'],
                        ),
                        true
                    );
                }
            } else {
                $update_args = array('ID' => (int) $existing->ID);
                $needs_update = false;

                if (false === strpos((string) $existing->post_content, $page['content'])) {
                    $update_args['post_content'] = $page['content'];
                    $needs_update = true;
                }

                if ((string) $existing->post_title !== (string) $page['title']) {
                    $update_args['post_title'] = $page['title'];
                    $needs_update = true;
                }

                if ((string) $existing->post_name !== (string) $page['slug']) {
                    $update_args['post_name'] = $page['slug'];
                    $needs_update = true;
                }

                if ($needs_update) {
                    wp_update_post($update_args);
                }
            }

            if (!is_wp_error($page_id) && $page_id > 0) {
                update_option($option_key, $page_id, false);
            }
        }

        update_option(self::OPTION_PAGES_SEEDED, '1', false);
    }

    public function __construct() {
        add_action('init', array($this, 'maybe_seed_pages'));
        add_action('init', array($this, 'maybe_schedule_currency_refresh'));
        add_action('init', array($this, 'maybe_seed_currency_snapshot'));
        add_action('init', array($this, 'maybe_handle_dashboard_actions'));
        add_filter('cron_schedules', array($this, 'register_currency_cron_schedules'));
        add_action('admin_post_oksia_save_agency_registration', array($this, 'handle_agency_registration_submission'));
        add_action('admin_post_nopriv_oksia_save_agency_registration', array($this, 'handle_agency_registration_submission'));
        add_action('admin_post_oksia_save_agency_settings', array($this, 'handle_agency_settings_submission'));
        add_action('admin_post_nopriv_oksia_save_agency_settings', array($this, 'redirect_to_login_for_agency_registration'));
        add_action(self::CRON_CURRENCY_REFRESH_HOOK, array($this, 'refresh_currency_snapshot'));
        add_shortcode('oksia_login', array($this, 'render_login_shortcode'));
        add_shortcode('oksia_dashboard', array($this, 'render_dashboard_shortcode'));
        add_shortcode('oksia_agency_settings', array($this, 'render_agency_settings_shortcode'));
        add_shortcode('oksia_temp_master_settings', array($this, 'render_temp_master_settings_shortcode'));
        add_shortcode('oksia_agency_registration', array($this, 'render_agency_registration_shortcode'));
        add_shortcode('oksia_support', array($this, 'render_support_shortcode'));
        add_filter('login_redirect', array($this, 'filter_login_redirect'), 20, 3);
        add_filter('logout_redirect', array($this, 'filter_logout_redirect'), 10, 3);
        add_filter('the_content', array($this, 'render_workspace_page_content'), 999);
        add_action('admin_init', array($this, 'maybe_block_wp_admin'));
        add_filter('show_admin_bar', array($this, 'maybe_hide_admin_bar'));
    }

    public function maybe_seed_pages() {
        self::seed_pages(true);
    }

    public function maybe_schedule_currency_refresh() {
        self::schedule_currency_refresh();
    }

    public static function schedule_currency_refresh() {
        wp_clear_scheduled_hook('oksia_currency_refresh_daily');

        if (!wp_next_scheduled(self::CRON_CURRENCY_REFRESH_HOOK)) {
            wp_schedule_event(self::next_currency_refresh_timestamp(), 'daily', self::CRON_CURRENCY_REFRESH_HOOK);
        }
    }

    public static function unschedule_currency_refresh() {
        wp_clear_scheduled_hook('oksia_currency_refresh_daily');

        $timestamp = wp_next_scheduled(self::CRON_CURRENCY_REFRESH_HOOK);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_CURRENCY_REFRESH_HOOK);
            $timestamp = wp_next_scheduled(self::CRON_CURRENCY_REFRESH_HOOK);
        }
    }

    private static function next_currency_refresh_timestamp() {
        return current_time('timestamp', true) + (5 * MINUTE_IN_SECONDS);
    }

    public function register_currency_cron_schedules($schedules) {
        return $schedules;
    }

    public function maybe_hide_admin_bar($show) {
        return $show;
    }

    public function render_workspace_page_content($content) {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return $content;
        }

        $page_id = get_queried_object_id();
        if (!$page_id) {
            return $content;
        }

        $workspace_shortcodes = array(
            absint(get_option(self::OPTION_LOGIN_PAGE_ID, 0)) => '[oksia_login]',
            absint(get_option(self::OPTION_DASHBOARD_PAGE_ID, 0)) => '[oksia_dashboard]',
            absint(get_option(self::OPTION_AGENT_INTAKE_PAGE_ID, 0)) => '[oksia_agent_intake_form]',
            absint(get_option(self::OPTION_CLIENT_INTAKE_PAGE_ID, 0)) => '[oksia_intake_form]',
            absint(get_option(self::OPTION_SETTINGS_PAGE_ID, 0)) => '[oksia_agency_settings]',
            absint(get_option(self::OPTION_REGISTRATION_PAGE_ID, 0)) => '[oksia_agency_registration]',
            absint(get_option(self::OPTION_SUPPORT_PAGE_ID, 0)) => '[oksia_support]',
        );

        $page_slug = get_post_field('post_name', $page_id);
        if ('temp-master-settings' === $page_slug) {
            return do_shortcode('[oksia_temp_master_settings]');
        }

        if (!isset($workspace_shortcodes[$page_id])) {
            return $content;
        }

        return do_shortcode($workspace_shortcodes[$page_id]);
    }

    public function maybe_block_wp_admin() {
        return;
    }

    public function filter_login_redirect($redirect_to, $requested, $user) {
        if (is_wp_error($user) || !($user instanceof WP_User)) {
            return $redirect_to;
        }

        if (class_exists('OKSIA_Agencies') && OKSIA_Agencies::instance()->is_front_end_agency_user($user)) {
            return $this->get_dashboard_url();
        }

        if (!empty($requested)) {
            return $requested;
        }

        return $this->get_dashboard_url();
    }

    public function filter_logout_redirect($redirect_to, $requested, $user) {
        return wp_login_url();
    }

    public function get_login_url() {
        $page_id = absint(get_option(self::OPTION_LOGIN_PAGE_ID, 0));
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        return home_url('/ok-login/');
    }

    public function get_dashboard_url() {
        $page_id = absint(get_option(self::OPTION_DASHBOARD_PAGE_ID, 0));
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        return home_url('/oksia-dashboard/');
    }

    public function get_agent_intake_url($quote_id = '') {
        $page_id = absint(get_option(self::OPTION_AGENT_INTAKE_PAGE_ID, 0));
        $base_url = $page_id ? get_permalink($page_id) : home_url('/agent-intake/');
        if (!$base_url) {
            $base_url = home_url('/agent-intake/');
        }

        if ('' !== $quote_id) {
            return add_query_arg(array(
                'oksia_agent_mode' => 'open',
                'oksia_quote_id' => $quote_id,
            ), $base_url);
        }

        return add_query_arg('oksia_agent_mode', 'new', $base_url);
    }

    public function get_settings_url() {
        $page_id = absint(get_option(self::OPTION_SETTINGS_PAGE_ID, 0));
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        return home_url('/agency-settings/');
    }

    public function get_registration_url() {
        $page_id = absint(get_option(self::OPTION_REGISTRATION_PAGE_ID, 0));
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        return home_url('/agency-registration/');
    }

    public function get_support_url() {
        $page_id = absint(get_option(self::OPTION_SUPPORT_PAGE_ID, 0));
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        return home_url('/support/');
    }

    public function render_login_shortcode() {
        if (is_user_logged_in()) {
            return $this->render_logged_in_shortcuts();
        }

        $redirect = $this->get_dashboard_url();
        ob_start();
        ?>
        <div class="oksia-workspace-shell">
            <div class="oksia-workspace-card">
                <h2><?php esc_html_e('OK Login', 'oksia-smart-itinerary-agent'); ?></h2>
                <p><?php esc_html_e('Sign in with your WordPress username and password.', 'oksia-smart-itinerary-agent'); ?></p>
                <?php
                wp_login_form(array(
                    'redirect' => $redirect,
                    'remember' => true,
                ));
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_dashboard_shortcode() {
        if (!is_user_logged_in()) {
            return $this->render_login_shortcode();
        }

        return $this->render_dashboard_layout();
    }

    private function render_dashboard_layout() {
        $user = wp_get_current_user();
        $stats = $this->get_quote_stats();
        $stage_counts = $this->get_quote_stage_counts();
        $trip_counts = $this->get_trip_type_counts();
        $world_times = $this->get_dashboard_world_times();
        $currency_rows = $this->get_dashboard_currency_rates();
        $destination_options = $this->get_dashboard_destination_options();
        $dashboard_url = $this->get_dashboard_url();
        $now_timestamp = current_time('timestamp');
        $today_day = (int) wp_date('j', $now_timestamp);
        $today_month = (int) wp_date('n', $now_timestamp);
        $today_year = (int) wp_date('Y', $now_timestamp);

        $stage_filter = sanitize_text_field(wp_unslash($_GET['oksia_quote_stage'] ?? ''));
        $trip_filter = sanitize_text_field(wp_unslash($_GET['oksia_trip_type'] ?? ''));
        $destination_filter = sanitize_text_field(wp_unslash($_GET['oksia_destination'] ?? ''));
        $search = sanitize_text_field(wp_unslash($_GET['oksia_quote_search'] ?? ''));
        $current_quotes_page = max(1, absint($_GET['oksia_quotes_page'] ?? 1));
        $quotes_payload = $this->get_recent_quotes(10, $stage_filter, $destination_filter, $search, $trip_filter, $current_quotes_page);
        $quotes = $quotes_payload['quotes'];
        $quotes_pagination = $quotes_payload['pagination'];
        $notice = sanitize_text_field(wp_unslash($_GET['oksia_dashboard_notice'] ?? ''));
        $open_quote_view_id = absint($_GET['oksia_open_quote_view'] ?? 0);
        $open_quote_view_url = '';
        if ($open_quote_view_id > 0 && class_exists('OKSIA_Admin') && method_exists('OKSIA_Admin', 'get_quote_view_url')) {
            $open_quote_view_url = OKSIA_Admin::get_quote_view_url($open_quote_view_id);
        }
        $action_options = array(
            '' => __('Select', 'oksia-smart-itinerary-agent'),
            'edit' => __('Edit', 'oksia-smart-itinerary-agent'),
            'send' => __('Send', 'oksia-smart-itinerary-agent'),
            'confirmed' => __('Confirm', 'oksia-smart-itinerary-agent'),
            'cancelled' => __('Cancel', 'oksia-smart-itinerary-agent'),
            'view' => __('View', 'oksia-smart-itinerary-agent'),
        );

        ob_start();
        ?>
        <div class="oksia-dashboard-shell" id="oksia-dashboard-top">
            <div class="oksia-dashboard-grid">
                <div class="oksia-dashboard-maincol">
                    <div class="oksia-workspace-section__head oksia-dashboard-main-head">
                        <h2><?php esc_html_e('Summary', 'oksia-smart-itinerary-agent'); ?></h2>
                    </div>
                    <div class="oksia-workspace-card">
                        <div class="oksia-dashboard-trip-row">
                            <a class="oksia-dashboard-metric-card oksia-dashboard-trip-card" href="<?php echo esc_url(add_query_arg('oksia_trip_type', 'Domestic', $dashboard_url)); ?>" data-dashboard-filter="1">
                                <span><?php esc_html_e('Domestic', 'oksia-smart-itinerary-agent'); ?></span>
                                <strong><?php echo esc_html(number_format_i18n((int) ($trip_counts['Domestic'] ?? 0))); ?></strong>
                            </a>
                            <a class="oksia-dashboard-metric-card oksia-dashboard-trip-card" href="<?php echo esc_url(add_query_arg('oksia_trip_type', 'International', $dashboard_url)); ?>" data-dashboard-filter="1">
                                <span><?php esc_html_e('International', 'oksia-smart-itinerary-agent'); ?></span>
                                <strong><?php echo esc_html(number_format_i18n((int) ($trip_counts['International'] ?? 0))); ?></strong>
                            </a>
                        </div>
                        <div class="oksia-dashboard-stage-row">
                            <?php
                            $stage_links = array(
                                'draft' => __('Draft', 'oksia-smart-itinerary-agent'),
                                'send' => __('Send', 'oksia-smart-itinerary-agent'),
                                'confirmed' => __('Confirmed', 'oksia-smart-itinerary-agent'),
                                'cancelled' => __('Cancelled', 'oksia-smart-itinerary-agent'),
                            );
                            foreach ($stage_links as $slug => $label) :
                            ?>
                                <a class="oksia-dashboard-metric-card oksia-dashboard-stage-card" href="<?php echo esc_url(add_query_arg('oksia_quote_stage', $slug, $dashboard_url)); ?>" data-dashboard-filter="1">
                                    <span><?php echo esc_html($label); ?></span>
                                    <strong><?php echo esc_html(number_format_i18n((int) ($stage_counts[$slug] ?? 0))); ?></strong>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <a class="oksia-dashboard-totalbar" href="<?php echo esc_url($dashboard_url); ?>" aria-label="<?php esc_attr_e('Show all quotes', 'oksia-smart-itinerary-agent'); ?>" data-dashboard-filter="1">
                            <span><?php esc_html_e('Total quotes till date:', 'oksia-smart-itinerary-agent'); ?></span>
                            <strong><?php echo esc_html(number_format_i18n((int) ($stats['total'] ?? 0))); ?></strong>
                        </a>
                    </div>

                    <div class="oksia-workspace-card">
                        <div class="oksia-workspace-section__head">
                            <h4><?php esc_html_e('Live World Time', 'oksia-smart-itinerary-agent'); ?></h4>
                        </div>
                        <div class="oksia-dashboard-timezone-grid">
                            <?php foreach ($world_times as $zone) : ?>
                                <div class="oksia-workspace-stat oksia-dashboard-timezone">
                                    <strong><?php echo esc_html($zone['label']); ?></strong>
                                    <span><?php echo esc_html($zone['time']); ?></span>
                                    <small><?php echo esc_html($zone['meridiem']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php echo $this->render_currency_rates_card($currency_rows); ?>
                    <div class="oksia-workspace-card oksia-dashboard-age-card">
                        <div class="oksia-workspace-section__head">
                            <h4><?php esc_html_e('Age Calculator', 'oksia-smart-itinerary-agent'); ?></h4>
                        </div>
                        <div class="oksia-dashboard-age">
                            <div class="oksia-dashboard-age-group">
                                <span><?php esc_html_e('From', 'oksia-smart-itinerary-agent'); ?></span>
                                <div class="oksia-dashboard-age-inputs">
                                    <select data-age-part="from-day">
                                        <option value=""><?php esc_html_e('Date', 'oksia-smart-itinerary-agent'); ?></option>
                                        <?php for ($day = 1; $day <= 31; $day++) : ?>
                                            <option value="<?php echo esc_attr($day); ?>"><?php echo esc_html($day); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select data-age-part="from-month">
                                        <option value=""><?php esc_html_e('Month', 'oksia-smart-itinerary-agent'); ?></option>
                                        <?php for ($month = 1; $month <= 12; $month++) : ?>
                                            <option value="<?php echo esc_attr($month); ?>"><?php echo esc_html($month); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select data-age-part="from-year">
                                        <option value=""><?php esc_html_e('Year', 'oksia-smart-itinerary-agent'); ?></option>
                                        <?php for ($year = 1900; $year <= 2100; $year++) : ?>
                                            <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="oksia-dashboard-age-group">
                                <span><?php esc_html_e('To', 'oksia-smart-itinerary-agent'); ?></span>
                                <div class="oksia-dashboard-age-inputs">
                                    <select data-age-part="to-day">
                                        <?php for ($day = 1; $day <= 31; $day++) : ?>
                                            <option value="<?php echo esc_attr($day); ?>" <?php selected($today_day, $day); ?>><?php echo esc_html($day); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select data-age-part="to-month">
                                        <?php for ($month = 1; $month <= 12; $month++) : ?>
                                            <option value="<?php echo esc_attr($month); ?>" <?php selected($today_month, $month); ?>><?php echo esc_html($month); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <select data-age-part="to-year">
                                        <?php for ($year = 1900; $year <= 2100; $year++) : ?>
                                            <option value="<?php echo esc_attr($year); ?>" <?php selected($today_year, $year); ?>><?php echo esc_html($year); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="oksia-dashboard-age-result" data-age-result><?php esc_html_e('10 years, 5 Months, 9 Days', 'oksia-smart-itinerary-agent'); ?></div>
                            </div>
                        </div>
                </div>

                <div class="oksia-dashboard-sidecol">
                    <div class="oksia-workspace-section__head oksia-dashboard-quotes-head oksia-dashboard-main-head">
                        <h2><?php esc_html_e('All Quotes', 'oksia-smart-itinerary-agent'); ?></h2>
                    </div>
                    <div class="oksia-workspace-card oksia-dashboard-quotes-card">
                        <form class="oksia-dashboard-table-form" method="get">
                            <div class="oksia-dashboard-table-form__controls">
                                <input type="search" name="oksia_quote_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by ID or Name', 'oksia-smart-itinerary-agent'); ?>" data-oksia-live-search>
                                <div class="oksia-dashboard-table-form__actions">
                                    <select name="oksia_dashboard_action" aria-label="<?php esc_attr_e('Select action', 'oksia-smart-itinerary-agent'); ?>">
                                        <?php foreach ($action_options as $action_slug => $action_label) : ?>
                                            <option value="<?php echo esc_attr($action_slug); ?>">
                                                <?php echo esc_html($action_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'oksia-smart-itinerary-agent'); ?></button>
                                </div>
                            </div>
                            <?php if ('' !== $stage_filter) : ?>
                                <input type="hidden" name="oksia_quote_stage" value="<?php echo esc_attr($stage_filter); ?>">
                            <?php endif; ?>
                            <?php if ('' !== $trip_filter) : ?>
                                <input type="hidden" name="oksia_trip_type" value="<?php echo esc_attr($trip_filter); ?>">
                            <?php endif; ?>
                            <?php if ('' !== $destination_filter) : ?>
                                <input type="hidden" name="oksia_destination" value="<?php echo esc_attr($destination_filter); ?>">
                            <?php endif; ?>
                            <?php if ('' !== $notice) : ?>
                                <div class="oksia-dashboard-notice oksia-dashboard-notice--inline">
                                    <strong><?php esc_html_e('Dashboard update', 'oksia-smart-itinerary-agent'); ?></strong>
                                    <span><?php echo esc_html($notice); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php echo $this->render_dashboard_quotes_table($quotes, $dashboard_url, $action_options, $quotes_pagination, array(
                                'stage' => $stage_filter,
                                'destination' => $destination_filter,
                                'search' => $search,
                                'trip_type' => $trip_filter,
                            )); ?>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        <script>
        (function () {
            var dashboardPath = window.location.pathname;
            var dashboardSelector = '#oksia-dashboard-top';
            var currencyCardSelector = '#oksia-currency-card';
            var currencyRefreshUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var currencyRefreshNonce = '<?php echo esc_js(wp_create_nonce('oksia_currency_refresh')); ?>';
            var currencyRefreshBusy = false;
            var generatedQuoteUrl = <?php echo wp_json_encode($open_quote_view_url); ?>;

            function restoreDashboardUrl() {
                if (window.location.pathname !== dashboardPath || window.location.search) {
                    window.history.replaceState({}, '', dashboardPath);
                }
            }

            function openGeneratedQuote() {
                if (!generatedQuoteUrl) {
                    return;
                }

                window.setTimeout(function () {
                    window.open(generatedQuoteUrl, '_blank', 'noopener');
                }, 250);
            }

            function swapDashboard(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var nextRoot = doc.querySelector(dashboardSelector);
                var currentRoot = document.querySelector(dashboardSelector);

                if (!nextRoot || !currentRoot) {
                    return false;
                }

                currentRoot.outerHTML = nextRoot.outerHTML;
                restoreDashboardUrl();

                initAgeCalculator();
                initInlineNotice();
                return true;
            }

            function loadDashboard(url) {
                return window.fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (response) {
                    return response.text();
                })
                .then(function (html) {
                    if (!swapDashboard(html)) {
                        window.location.href = url;
                    }
                })
                .catch(function () {
                    window.location.href = url;
                });
            }


            function setCurrencyRefreshState(isBusy) {
                var button = document.querySelector('[data-currency-refresh="1"]');
                if (!button) {
                    return;
                }

                button.disabled = !!isBusy;
                button.classList.toggle('is-loading', !!isBusy);
                button.setAttribute('aria-busy', isBusy ? 'true' : 'false');
            }

            function swapCurrencyCard(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var nextCard = doc.querySelector(currencyCardSelector);
                var currentCard = document.querySelector(currencyCardSelector);

                if (!nextCard || !currentCard) {
                    return false;
                }

                currentCard.outerHTML = nextCard.outerHTML;
                return true;
            }

            function refreshCurrencySection() {
                if (currencyRefreshBusy) {
                    return Promise.resolve(false);
                }

                currencyRefreshBusy = true;
                setCurrencyRefreshState(true);

                var formData = new FormData();
                formData.append('action', 'oksia_refresh_currency_section');
                formData.append('nonce', currencyRefreshNonce);

                return window.fetch(currencyRefreshUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.html) {
                        return false;
                    }

                    return swapCurrencyCard(payload.data.html);
                })
                .catch(function () {
                    return false;
                })
                .then(function () {
                    currencyRefreshBusy = false;
                    setCurrencyRefreshState(false);
                });
            }
            function initAgeCalculator() {
                var root = document.querySelector('.oksia-dashboard-age');
                if (!root) {
                    return;
                }

                var result = root.querySelector('[data-age-result]');
                var fields = {
                    fromDay: root.querySelector('[data-age-part="from-day"]'),
                    fromMonth: root.querySelector('[data-age-part="from-month"]'),
                    fromYear: root.querySelector('[data-age-part="from-year"]'),
                    toDay: root.querySelector('[data-age-part="to-day"]'),
                    toMonth: root.querySelector('[data-age-part="to-month"]'),
                    toYear: root.querySelector('[data-age-part="to-year"]')
                };

                if (!result || !fields.fromDay || !fields.fromMonth || !fields.fromYear || !fields.toDay || !fields.toMonth || !fields.toYear) {
                    return;
                }

                function readDate(prefix) {
                    var day = parseInt(fields[prefix + 'Day'].value, 10);
                    var month = parseInt(fields[prefix + 'Month'].value, 10);
                    var year = parseInt(fields[prefix + 'Year'].value, 10);
                    if (!day || !month || !year) {
                        return null;
                    }

                    var date = new Date(year, month - 1, day);
                    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
                        return null;
                    }

                    return date;
                }

                function daysInMonth(date) {
                    return new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
                }

                function formatAge(fromDate, toDate) {
                    var years = toDate.getFullYear() - fromDate.getFullYear();
                    var months = toDate.getMonth() - fromDate.getMonth();
                    var days = toDate.getDate() - fromDate.getDate();

                    if (days < 0) {
                        months -= 1;
                        days += daysInMonth(new Date(toDate.getFullYear(), toDate.getMonth(), 1));
                    }

                    if (months < 0) {
                        years -= 1;
                        months += 12;
                    }

                    return years + ' Years, ' + months + ' Months, ' + days + ' Days';
                }

                function updateAge() {
                    var fromDate = readDate('from');
                    var toDate = readDate('to');
                    if (!fromDate || !toDate) {
                        result.textContent = '<?php echo esc_js(__('Enter both dates to calculate age.', 'oksia-smart-itinerary-agent')); ?>';
                        return;
                    }

                    if (toDate < fromDate) {
                        result.textContent = '<?php echo esc_js(__('To date must be after From date.', 'oksia-smart-itinerary-agent')); ?>';
                        return;
                    }

                    result.textContent = formatAge(fromDate, toDate);
                }

                if (!root.dataset.ageBound) {
                    Object.keys(fields).forEach(function (key) {
                        fields[key].addEventListener('input', updateAge);
                        fields[key].addEventListener('change', updateAge);
                    });
                    root.dataset.ageBound = '1';
                }

                updateAge();
            }

            function initInlineNotice() {
                var notice = document.querySelector('.oksia-dashboard-quotes-card .oksia-dashboard-notice--inline');
                if (!notice || notice.dataset.noticeBound) {
                    return;
                }

                notice.dataset.noticeBound = '1';
                notice.classList.add('oksia-dashboard-notice--visible');
                window.setTimeout(function () {
                    notice.classList.add('oksia-dashboard-notice--hiding');
                    notice.classList.remove('oksia-dashboard-notice--visible');
                    window.setTimeout(function () {
                        if (notice && notice.parentNode) {
                            notice.parentNode.removeChild(notice);
                        }
                    }, 350);
                }, 4200);
            }

            document.addEventListener('click', function (event) {
                var currencyTrigger = event.target.closest('[data-currency-refresh="1"]');
                if (currencyTrigger) {
                    event.preventDefault();
                    refreshCurrencySection();
                    return;
                }

                var trigger = event.target.closest('[data-dashboard-filter="1"]');
                if (!trigger) {
                    return;
                }

                var href = trigger.getAttribute('href');
                if (!href) {
                    return;
                }

                event.preventDefault();
                loadDashboard(href);
            });

            document.addEventListener('submit', function (event) {
                var filtersForm = event.target.closest('.oksia-dashboard-table-form');
                if (!filtersForm) {
                    return;
                }

                event.preventDefault();
                var formData = new FormData(filtersForm);
                var url = new URL(window.location.href);
                url.search = '';

                formData.forEach(function (value, key) {
                    if (value !== '') {
                        url.searchParams.set(key, value);
                    }
                });

                loadDashboard(url.toString());
            });

            document.addEventListener('input', function (event) {
                var liveSearch = event.target.closest('[data-oksia-live-search]');
                if (!liveSearch) {
                    return;
                }

                window.clearTimeout(window.oksiaDashboardSearchTimer);
                window.oksiaDashboardSearchTimer = window.setTimeout(function () {
                    var form = liveSearch.closest('.oksia-dashboard-table-form');
                    if (form) {
                        form.requestSubmit ? form.requestSubmit() : form.submit();
                    }
                }, 350);
            });

            document.addEventListener('click', function (event) {
                var link = event.target.closest('.oksia-dashboard-action-menu a');
                if (!link) {
                    return;
                }

                event.preventDefault();

                var row = link.closest('.oksia-workspace-table__row');
                var details = link.closest('.oksia-dashboard-action');
                if (details) {
                    details.open = false;
                }

                if (!row) {
                    return;
                }

                var button = row.querySelector('.oksia-dashboard-row-action-btn');
                if (!button) {
                    return;
                }

                var kind = link.getAttribute('data-action-button-kind') || '';
                var label = link.getAttribute('data-action-button-label') || link.textContent.trim();
                var title = link.getAttribute('data-action-title') || label;
                var target = link.getAttribute('data-action-target') || '';

                button.hidden = false;
                button.removeAttribute('aria-hidden');
                button.textContent = label;
                button.href = link.href;
                button.title = title;
                button.className = 'oksia-dashboard-row-action-btn';
                if (kind) {
                    button.classList.add('oksia-dashboard-row-action-btn--' + kind);
                }

                if ('_blank' === target) {
                    button.target = '_blank';
                    button.rel = 'noopener';
                } else {
                    button.removeAttribute('target');
                    button.removeAttribute('rel');
                }
            }, true);

            initAgeCalculator();
            initInlineNotice();
            openGeneratedQuote();
        }());
        </script>
        <?php
        return ob_get_clean();
    }

    private function render_logged_in_shortcuts($show_dashboard = false) {
        $user = wp_get_current_user();
        $dashboard_url = esc_url($this->get_dashboard_url());
        $login_url = esc_url($this->get_login_url());
        $agent_intake_url = esc_url(get_permalink(absint(get_option(self::OPTION_AGENT_INTAKE_PAGE_ID, 0))));
        $client_intake_url = esc_url(get_permalink(absint(get_option(self::OPTION_CLIENT_INTAKE_PAGE_ID, 0))));
        $settings_url = esc_url($this->get_settings_url());
        $registration_url = esc_url($this->get_registration_url());
        $admin_url = esc_url(admin_url());
        $can_manage = current_user_can('manage_options');

        ob_start();
        ?>
        <div class="oksia-workspace-shell">
            <div class="oksia-workspace-card">
                <div class="oksia-workspace-actions">
                    <a class="button button-primary" href="<?php echo $agent_intake_url; ?>"><?php esc_html_e('Open Agent Intake', 'oksia-smart-itinerary-agent'); ?></a>
                    <a class="button" href="<?php echo $client_intake_url; ?>"><?php esc_html_e('Open Client Intake', 'oksia-smart-itinerary-agent'); ?></a>
                    <?php if ($can_manage) : ?>
                        <a class="button" href="<?php echo $settings_url; ?>"><?php esc_html_e('Open Agency Settings', 'oksia-smart-itinerary-agent'); ?></a>
                        <a class="button" href="<?php echo $registration_url; ?>"><?php esc_html_e('Open Agency Registration', 'oksia-smart-itinerary-agent'); ?></a>
                    <?php endif; ?>
                    <?php if ($can_manage) : ?>
                        <a class="button" href="<?php echo $admin_url; ?>"><?php esc_html_e('Open wp-admin', 'oksia-smart-itinerary-agent'); ?></a>
                    <?php endif; ?>
                    <a class="button" href="<?php echo esc_url(wp_logout_url($login_url)); ?>"><?php esc_html_e('Log Out', 'oksia-smart-itinerary-agent'); ?></a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_dashboard_quotes_table($quotes, $dashboard_url, $action_options, $pagination = array(), $filters = array()) {
        if (empty($quotes)) {
            $empty_markup = '<div class="oksia-dashboard-quote-list"><div class="oksia-workspace-table__empty"><p class="oksia-dashboard-empty">' . esc_html__('No quotes match your current filters.', 'oksia-smart-itinerary-agent') . '</p></div></div>';
            if (!empty($pagination['total_pages']) && (int) $pagination['total_pages'] > 1) {
                $empty_markup .= $this->render_dashboard_pagination($dashboard_url, $pagination, $filters);
            }

            return $empty_markup;
        }

        ob_start();
        ?>
        <div class="oksia-dashboard-quote-list">
            <div class="oksia-dashboard-quote-list__head">
                <div class="oksia-dashboard-quote-list__cell oksia-dashboard-quote-list__cell--select"></div>
                <div class="oksia-dashboard-quote-list__cell"><?php esc_html_e('Quote ID', 'oksia-smart-itinerary-agent'); ?></div>
                <div class="oksia-dashboard-quote-list__cell"><?php esc_html_e('Name', 'oksia-smart-itinerary-agent'); ?></div>
                <div class="oksia-dashboard-quote-list__cell"><?php esc_html_e('Version', 'oksia-smart-itinerary-agent'); ?></div>
                <div class="oksia-dashboard-quote-list__cell"><?php esc_html_e('Last Updated By', 'oksia-smart-itinerary-agent'); ?></div>
                <div class="oksia-dashboard-quote-list__cell"><?php esc_html_e('Status', 'oksia-smart-itinerary-agent'); ?></div>
            </div>
            <?php foreach ($quotes as $quote) : ?>
                <?php
                $post_id = absint($quote['id']);
                $quote_id = trim((string) ($quote['quote_id'] ?? ''));
                $client = trim((string) ($quote['client'] ?? ''));
                $trip_type = $this->clean_dashboard_text($quote['trip_type'] ?? '');
                $destination = $this->clean_dashboard_text($quote['destination'] ?? '');
                $stage = sanitize_key((string) ($quote['stage'] ?? ''));
                $status_label = trim((string) ($quote['status'] ?? ''));
                $version_label = trim((string) ($quote['version'] ?? 'v1'));
                $updated_by = trim((string) ($quote['updated_by'] ?? ''));
                $status_class = $this->get_dashboard_status_class($stage);
                $trip_meta = trim(implode(' - ', array_filter(array($trip_type, $destination))));
                $display_quote = trim($quote_id . ' | ' . $trip_meta, ' |-');
                ?>
                <div class="oksia-dashboard-quote-list__row">
                    <div class="oksia-dashboard-quote-list__cell oksia-dashboard-quote-list__cell--select">
                        <input type="radio" name="quote_id" value="<?php echo esc_attr((string) $post_id); ?>" aria-label="<?php echo esc_attr(sprintf(__('Select quote %s', 'oksia-smart-itinerary-agent'), $quote_id)); ?>">
                    </div>
                    <div class="oksia-dashboard-quote-list__cell oksia-dashboard-quote-list__cell--quote">
                        <span class="oksia-dashboard-quote-list__quote-main"><?php echo esc_html($quote_id); ?></span>
                        <span class="oksia-dashboard-quote-list__quote-meta"><?php echo esc_html($trip_meta); ?></span>
                    </div>
                    <div class="oksia-dashboard-quote-list__cell">
                        <?php echo esc_html($client); ?>
                    </div>
                    <div class="oksia-dashboard-quote-list__cell oksia-dashboard-quote-list__cell--version">
                        <span class="oksia-dashboard-quote-list__version-pill"><?php echo esc_html($version_label); ?></span>
                    </div>
                    <div class="oksia-dashboard-quote-list__cell oksia-dashboard-quote-list__cell--updated-by">
                        <?php echo esc_html($updated_by); ?>
                    </div>
                    <div class="oksia-dashboard-quote-list__cell">
                        <span class="oksia-dashboard-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php echo $this->render_dashboard_pagination($dashboard_url, $pagination, $filters); ?>
        <?php
        return ob_get_clean();
    }

    private function render_dashboard_pagination($dashboard_url, $pagination, $filters = array()) {
        $total_pages = max(1, absint($pagination['total_pages'] ?? 1));
        $current_page = max(1, absint($pagination['current_page'] ?? 1));

        if ($total_pages <= 1) {
            return '';
        }

        $base_args = array();
        if (!empty($filters['stage'])) {
            $base_args['oksia_quote_stage'] = sanitize_text_field($filters['stage']);
        }
        if (!empty($filters['destination'])) {
            $base_args['oksia_destination'] = sanitize_text_field($filters['destination']);
        }
        if (!empty($filters['search'])) {
            $base_args['oksia_quote_search'] = sanitize_text_field($filters['search']);
        }
        if (!empty($filters['trip_type'])) {
            $base_args['oksia_trip_type'] = sanitize_text_field($filters['trip_type']);
        }

        $prev_url = '';
        $next_url = '';
        if ($current_page > 1) {
            $base_args['oksia_quotes_page'] = $current_page - 1;
            $prev_url = add_query_arg($base_args, $dashboard_url);
        }
        if ($current_page < $total_pages) {
            $base_args['oksia_quotes_page'] = $current_page + 1;
            $next_url = add_query_arg($base_args, $dashboard_url);
        }

        ob_start();
        ?>
        <div class="oksia-dashboard-pagination" aria-label="<?php esc_attr_e('Quote pagination', 'oksia-smart-itinerary-agent'); ?>">
            <div class="oksia-dashboard-pagination__info"><?php echo esc_html(sprintf(__('Page %1$s of %2$s', 'oksia-smart-itinerary-agent'), number_format_i18n($current_page), number_format_i18n($total_pages))); ?></div>
            <div class="oksia-dashboard-pagination__actions">
                <a class="oksia-dashboard-pagination__link <?php echo $prev_url ? '' : 'is-disabled'; ?>" <?php echo $prev_url ? 'href="' . esc_url($prev_url) . '" data-dashboard-filter="1"' : 'aria-disabled="true" tabindex="-1"'; ?>>
                    <span aria-hidden="true">&lsaquo;</span>
                    <span><?php esc_html_e('Previous', 'oksia-smart-itinerary-agent'); ?></span>
                </a>
                <a class="oksia-dashboard-pagination__link <?php echo $next_url ? '' : 'is-disabled'; ?>" <?php echo $next_url ? 'href="' . esc_url($next_url) . '" data-dashboard-filter="1"' : 'aria-disabled="true" tabindex="-1"'; ?>>
                    <span><?php esc_html_e('Next', 'oksia-smart-itinerary-agent'); ?></span>
                    <span aria-hidden="true">&rsaquo;</span>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_dashboard_primary_action($post_id, $quote_id, $stage, $dashboard_url) {
        $actions = array(
            'draft' => array('label' => __('Edit', 'oksia-smart-itinerary-agent'), 'type' => 'edit'),
            'send' => array('label' => __('Confirm', 'oksia-smart-itinerary-agent'), 'type' => 'confirmed'),
            'confirmed' => array('label' => __('View', 'oksia-smart-itinerary-agent'), 'type' => 'view'),
            'cancelled' => array('label' => __('Locked', 'oksia-smart-itinerary-agent'), 'type' => 'locked'),
        );

        $action = $actions[$stage] ?? $actions['draft'];

        return array(
            'label' => $action['label'],
            'url' => $this->get_dashboard_action_url($post_id, $quote_id, $action['type'], $dashboard_url),
            'title' => $action['label'],
            'kind' => $action['type'],
        );
    }

    private function get_dashboard_action_url($post_id, $quote_id, $action, $dashboard_url) {
        $post_id = absint($post_id);
        $quote_id = trim((string) $quote_id);

        if ('edit' === $action) {
            return $quote_id ? $this->get_agent_intake_url($quote_id) : $this->get_dashboard_url();
        }

        if ('view' === $action) {
            $stage = $this->get_quote_stage($post_id);
            if ('cancelled' === $stage) {
                return $dashboard_url;
            }
            $quote_url = $this->get_quote_share_url($post_id);
            return $quote_url ? $quote_url : add_query_arg('oksia_view_pdf', $post_id, $dashboard_url);
        }

        if ('locked' === $action) {
            return $dashboard_url;
        }

        if (!in_array($action, array('send', 'confirmed', 'cancelled'), true)) {
            return $dashboard_url;
        }

        if (in_array($action, array('confirmed', 'cancelled'), true)) {
            $quote_url = $this->get_quote_share_url($post_id);
            if ($quote_url) {
                return add_query_arg('oksia_quote_workflow', $action, $quote_url);
            }
        }

        return add_query_arg(
            array(
                'oksia_dashboard_action' => $action,
                'quote_id' => $post_id,
                '_wpnonce' => wp_create_nonce('oksia_dashboard_action_' . $post_id),
            ),
            $dashboard_url
        );
    }

    private function get_dashboard_status_class($stage) {
        $map = array(
            'draft' => 'oksia-dashboard-status--draft',
            'send' => 'oksia-dashboard-status--send',
            'confirmed' => 'oksia-dashboard-status--confirmed',
            'cancelled' => 'oksia-dashboard-status--cancelled',
        );

        return $map[$stage] ?? 'oksia-dashboard-status--draft';
    }

    private function clean_dashboard_text($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        $value = preg_replace('/[^\x20-\x7E]/', ' ', $value);
        $value = str_replace(array(' -  ', '  - '), ' - ', $value);
        $value = preg_replace('/\s*-\s*/', ' - ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value, " \t\n\r\0\x0B-");
    }

    private function get_quote_stage($post_id) {
        if (class_exists('OKSIA_Admin') && method_exists('OKSIA_Admin', 'get_quote_stage')) {
            return OKSIA_Admin::get_quote_stage($post_id);
        }

        $stage = trim((string) get_post_meta($post_id, '_oksia_quote_stage', true));
        if ('' === $stage) {
            $stage = '1' === (string) get_post_meta($post_id, '_oksia_quote_finalized', true) ? 'send' : 'draft';
        }

        return in_array($stage, array('draft', 'send', 'confirmed', 'cancelled'), true) ? $stage : 'draft';
    }

    private function get_quote_share_url($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return '';
        }

        if (class_exists('OKSIA_Admin') && method_exists('OKSIA_Admin', 'get_quote_share_url')) {
            $share_url = OKSIA_Admin::get_quote_share_url($post_id);
            if ($share_url) {
                return $share_url;
            }
        }

        $quote_url = get_permalink($post_id);
        return $quote_url ? rtrim(trim((string) $quote_url), " \t\n\r\0\x0B?&") : '';
    }

    public function maybe_handle_dashboard_actions() {
        if (!is_user_logged_in() || empty($_GET['oksia_dashboard_action']) || empty($_GET['quote_id'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_GET['oksia_dashboard_action']));
        $post_id = absint($_GET['quote_id']);
        if (!$post_id || OKSIA_Post_Types::POST_TYPE !== get_post_type($post_id)) {
            return;
        }

        if (!$this->can_access_quote($post_id)) {
            return;
        }

        $current_stage = $this->get_quote_stage($post_id);
        if ('cancelled' === $current_stage) {
            return;
        }

        if ('edit' === $action) {
            $quote_code = trim((string) get_post_meta($post_id, '_oksia_quote_id', true));
            wp_safe_redirect($quote_code ? $this->get_agent_intake_url($quote_code) : $this->get_dashboard_url());
            exit;
        }

        if ('view' === $action) {
            $quote_url = $this->get_quote_share_url($post_id);
            if (!$quote_url) {
                $quote_url = add_query_arg('oksia_view_pdf', $post_id, $this->get_dashboard_url());
            }
            wp_safe_redirect($quote_url);
            exit;
        }

        if (in_array($action, array('confirmed', 'cancelled'), true)) {
            $quote_url = $this->get_quote_share_url($post_id);
            if (!$quote_url) {
                $quote_url = $this->get_dashboard_url();
            }
            wp_safe_redirect(add_query_arg(
                array(
                    'oksia_quote_workflow' => $action,
                ),
                $quote_url
            ));
            exit;
        }

        $labels = array(
            'send' => __('send', 'oksia-smart-itinerary-agent'),
        );

        if (!isset($labels[$action])) {
            return;
        }

        update_post_meta($post_id, '_oksia_quote_stage', $action);
        update_post_meta($post_id, '_oksia_quote_finalized', '1');
        update_post_meta($post_id, '_oksia_quote_status', sprintf(__('Quote marked as %s from the dashboard.', 'oksia-smart-itinerary-agent'), $labels[$action]));
        update_post_meta($post_id, '_oksia_ai_status', sprintf(__('Dashboard stage updated to %s.', 'oksia-smart-itinerary-agent'), $labels[$action]));
        update_post_meta($post_id, '_oksia_quote_finalized_at', current_time('mysql'));
        update_post_meta($post_id, '_oksia_quote_finalized_by', get_current_user_id());

        wp_safe_redirect(add_query_arg('oksia_dashboard_notice', sprintf(__('Quote marked as %s.', 'oksia-smart-itinerary-agent'), $labels[$action]), $this->get_dashboard_url()));
        exit;
    }

    private function get_trip_type_counts() {
        $counts = array(
            'Domestic' => 0,
            'International' => 0,
        );

        $query = new WP_Query(
            array_filter(array(
                'post_type' => OKSIA_Post_Types::POST_TYPE,
                'posts_per_page' => -1,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'fields' => 'ids',
                'meta_query' => $this->get_agency_meta_query(),
            ))
        );

        if (!$query->have_posts()) {
            return $counts;
        }

        while ($query->have_posts()) {
            $query->the_post();
            $trip = (array) get_post_meta(get_the_ID(), '_oksia_trip_overview', true);
            $trip_type = trim((string) ($trip['trip_type'] ?? ''));
            if (isset($counts[$trip_type])) {
                $counts[$trip_type]++;
            }
        }

        wp_reset_postdata();
        return $counts;
    }

    private function get_dashboard_fun_facts() {
        return array(
            __('Travel planning feels faster when the dashboard is split into clear workflow stages.', 'oksia-smart-itinerary-agent'),
            __('Most quote revisions happen in the first pass, before hotel and transfer details are locked.', 'oksia-smart-itinerary-agent'),
            __('A calm workspace usually wins over a crowded admin page when teams handle daily bookings.', 'oksia-smart-itinerary-agent'),
            __('Quick filters save more time than scrolling when the quote list grows.', 'oksia-smart-itinerary-agent'),
            __('A good dashboard should make the next action obvious at a glance.', 'oksia-smart-itinerary-agent'),
        );
    }

    private function get_dashboard_world_times() {
        $zones = array(
            array('label' => 'Dubai', 'timezone' => 'Asia/Dubai'),
            array('label' => 'Thailand', 'timezone' => 'Asia/Bangkok'),
            array('label' => 'Newyork', 'timezone' => 'America/New_York'),
            array('label' => 'Toronto', 'timezone' => 'America/Toronto'),
            array('label' => 'Hanoi', 'timezone' => 'Asia/Bangkok'),
            array('label' => 'Bali', 'timezone' => 'Asia/Makassar'),
        );

        $rows = array();
        $base = new DateTimeImmutable('now', wp_timezone());
        foreach ($zones as $zone) {
            try {
                $time = $base->setTimezone(new DateTimeZone($zone['timezone']));
            } catch (Exception $e) {
                $time = $base;
            }

            $rows[] = array(
                'label' => $zone['label'],
                'time' => $time->format('h:i'),
                'meridiem' => $time->format('A'),
            );
        }

        return $rows;
    }

    private function get_dashboard_currency_rates() {
        $codes = array('USD', 'THB', 'AED', 'SGD', 'AUD', 'EUR');
        $snapshot = $this->get_currency_snapshot_state();
        $current_snapshot = (array) ($snapshot['current'] ?? array());

        $rows = array();
        foreach ($codes as $code) {
            $today_value = (string) ($current_snapshot[$code]['value'] ?? $current_snapshot[$code] ?? '');
            $value = '' !== $today_value ? number_format_i18n((float) $today_value, 2) : __('N/A', 'oksia-smart-itinerary-agent');
            $trend_class = (string) ($current_snapshot[$code]['trend_class'] ?? 'oksia-dashboard-currency--flat');
            $trend_direction = (string) ($current_snapshot[$code]['trend_direction'] ?? 'flat');
            $delta_value = (string) ($current_snapshot[$code]['delta_value'] ?? '');
            $delta_text = '' !== $delta_value ? $this->format_currency_delta($delta_value) : '-';

            $rows[] = array(
                'code' => $code,
                'value' => $value,
                'trend_class' => $trend_class,
                'trend_direction' => $trend_direction,
                'delta_text' => $delta_text,
            );
        }

        return $rows;
    }

    private function render_currency_rates_card($currency_rows) {
        ob_start();
        ?>
        <div class="oksia-workspace-card" id="oksia-currency-card" data-currency-card="1">
            <div class="oksia-workspace-section__head oksia-dashboard-currency-head">
                <h4><?php esc_html_e('Live Currency Rates in INR', 'oksia-smart-itinerary-agent'); ?></h4>
                <button type="button" class="oksia-dashboard-mini-refresh" data-currency-refresh="1" aria-label="<?php esc_attr_e('Refresh currency rates', 'oksia-smart-itinerary-agent'); ?>" title="<?php esc_attr_e('Refresh currency rates', 'oksia-smart-itinerary-agent'); ?>">&#8635;</button>
            </div>
            <div class="oksia-dashboard-currency-grid">
                <?php foreach ($currency_rows as $currency_row) : ?>
                    <div class="oksia-workspace-stat oksia-dashboard-currency <?php echo esc_attr($currency_row['trend_class'] ?? 'oksia-dashboard-currency--flat'); ?>">
                        <strong><?php echo esc_html($currency_row['code']); ?></strong>
                        <span class="oksia-dashboard-currency__stack">
                            <span class="oksia-dashboard-currency__row">
                                <em class="oksia-dashboard-currency__arrow"><?php
                                    $trend_direction = (string) ($currency_row['trend_direction'] ?? 'flat');
                                    echo 'up' === $trend_direction ? '&#9650;' : ('down' === $trend_direction ? '&#9660;' : '&mdash;');
                                ?></em>
                                <strong class="oksia-dashboard-currency__value"><?php echo esc_html($currency_row['value']); ?></strong>
                            </span>
                            <small class="oksia-dashboard-currency__delta"><?php echo esc_html((string) ($currency_row['delta_text'] ?? '')); ?></small>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_currency_section_refresh() {
        check_ajax_referer('oksia_currency_refresh', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to refresh rates.', 'oksia-smart-itinerary-agent')), 403);
        }

        $this->refresh_currency_snapshot();
        wp_send_json_success(array(
            'html' => $this->render_currency_rates_card($this->get_dashboard_currency_rates()),
        ));
    }

    public function refresh_currency_snapshot() {
        $codes = array('USD', 'THB', 'AED', 'SGD', 'AUD', 'EUR');
        $existing = $this->get_currency_snapshot_state();
        $current_snapshot = array();
        $latest_payload = $this->fetch_convertz_latest_snapshot(true);
        $inr_history = $this->fetch_convertz_history_snapshot('USD', 'INR', 7, true);

        foreach ($codes as $code) {
            $today_rate = '';
            if (is_array($latest_payload) && !empty($latest_payload)) {
                $today_rate = (string) $this->convertz_rate_from_snapshot($latest_payload, $code);
            }
            if ('' === $today_rate) {
                $today_rate = (string) $this->fetch_currency_rate($code, 'latest', true);
            }
            $previous_rate = (string) ($existing['current'][$code]['value'] ?? $existing['previous'][$code]['value'] ?? '');
            $yesterday_rate = $this->get_convertz_yesterday_cross_rate(
                $inr_history,
                $this->fetch_convertz_history_snapshot('USD', $code, 7, true)
            );

            if ('' === $today_rate && '' !== $previous_rate) {
                $today_rate = $previous_rate;
            }

            $trend_direction = 'flat';
            $trend_class = 'oksia-dashboard-currency--flat';
            $value = $today_rate;
            $delta_value = '';

            if ('' !== $today_rate && '' !== $yesterday_rate) {
                $today_float = (float) $today_rate;
                $yesterday_float = (float) $yesterday_rate;
                $delta = round($today_float - $yesterday_float, 2);
                $delta_value = number_format($delta, 2, '.', '');
                if ($delta > 0) {
                    $trend_direction = 'up';
                    $trend_class = 'oksia-dashboard-currency--up';
                } elseif ($delta < 0) {
                    $trend_direction = 'down';
                    $trend_class = 'oksia-dashboard-currency--down';
                }
            } elseif ('' !== $today_rate && '' !== $previous_rate) {
                $today_float = (float) $today_rate;
                $previous_float = (float) $previous_rate;
                $delta = round($today_float - $previous_float, 2);
                $delta_value = number_format($delta, 2, '.', '');
                if ($delta > 0) {
                    $trend_direction = 'up';
                    $trend_class = 'oksia-dashboard-currency--up';
                } elseif ($delta < 0) {
                    $trend_direction = 'down';
                    $trend_class = 'oksia-dashboard-currency--down';
                }
            }

            $current_snapshot[$code] = array(
                'value' => $value,
                'trend_direction' => $trend_direction,
                'trend_class' => $trend_class,
                'updated_at' => current_time('mysql', true),
                'today_rate' => $today_rate,
                'previous_rate' => $previous_rate,
                'yesterday_rate' => $yesterday_rate,
                'delta_value' => $delta_value,
            );
        }

        update_option(
            self::OPTION_CURRENCY_SNAPSHOT,
            array(
                'current' => $current_snapshot,
                'previous' => (array) ($existing['current'] ?? array()),
                'updated_at' => current_time('mysql', true),
                'source' => 'convertz',
            ),
            false
        );
    }

    public function maybe_seed_currency_snapshot() {
        $force_refresh = get_option('oksia_currency_snapshot_forced_reset', '0');
        if ('1' === (string) $force_refresh) {
            delete_option('oksia_currency_snapshot_forced_reset');
            $this->refresh_currency_snapshot();
            return;
        }

        $snapshot = $this->get_currency_snapshot_state();
        $current = (array) ($snapshot['current'] ?? array());
        $previous = (array) ($snapshot['previous'] ?? array());
        $updated_at = trim((string) ($snapshot['updated_at'] ?? ''));

        if (empty($current) && empty($previous) && '' === $updated_at) {
            $this->refresh_currency_snapshot();
            return;
        }

        if ('' === $updated_at) {
            return;
        }

        $updated_ts = strtotime($updated_at . ' UTC');
        if (!$updated_ts) {
            return;
        }

        $stale_after = (int) apply_filters('oksia_currency_snapshot_stale_after', DAY_IN_SECONDS, $snapshot);
        $stale_after = max(HOUR_IN_SECONDS, $stale_after);
        if ((current_time('timestamp', true) - $updated_ts) < $stale_after) {
            return;
        }

        if (get_transient('oksia_currency_snapshot_refresh_lock')) {
            return;
        }

        set_transient('oksia_currency_snapshot_refresh_lock', '1', 5 * MINUTE_IN_SECONDS);
        $this->refresh_currency_snapshot();
        delete_transient('oksia_currency_snapshot_refresh_lock');
    }

    private function get_currency_snapshot_state() {
        $snapshot = (array) get_option(self::OPTION_CURRENCY_SNAPSHOT, array());
        if (isset($snapshot['current']) || isset($snapshot['previous'])) {
            return array(
                'current' => (array) ($snapshot['current'] ?? array()),
                'previous' => (array) ($snapshot['previous'] ?? array()),
                'updated_at' => (string) ($snapshot['updated_at'] ?? ''),
                'source' => (string) ($snapshot['source'] ?? ''),
            );
        }

        return array(
            'current' => $snapshot,
            'previous' => array(),
            'updated_at' => '',
            'source' => '',
        );
    }

    private function fetch_currency_rate($base_currency, $date = 'latest', $force_refresh = false) {
        $base_currency = strtoupper(preg_replace('/[^A-Z]/', '', (string) $base_currency));
        if ('' === $base_currency) {
            return '';
        }

        $cache_key = 'oksia_convertz_currency_snapshot';
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
        } else {
            $cached = false;
        }
        if (false !== $cached) {
            return $this->convertz_rate_from_snapshot($cached, $base_currency);
        }

        $url = 'https://convertz.app/api/currency';

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 8,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            )
        );

        if (is_wp_error($response)) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        if ('' === $body) {
            return '';
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return '';
        }

        set_transient($cache_key, $payload, HOUR_IN_SECONDS);
        return $this->convertz_rate_from_snapshot($payload, $base_currency);
    }

    private function fetch_convertz_latest_snapshot($force_refresh = false) {
        $cache_key = 'oksia_convertz_latest_snapshot';
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
        } else {
            $cached = false;
        }

        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://convertz.app/api/currency',
            array(
                'timeout' => 8,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            )
        );

        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return array();
        }

        set_transient($cache_key, $payload, HOUR_IN_SECONDS);
        return $payload;
    }

    private function fetch_convertz_history_snapshot($from_currency, $to_currency, $days = 7, $force_refresh = false) {
        $from_currency = strtoupper(preg_replace('/[^A-Z]/', '', (string) $from_currency));
        $to_currency = strtoupper(preg_replace('/[^A-Z]/', '', (string) $to_currency));
        $days = max(7, min(90, absint($days)));
        if ('' === $from_currency || '' === $to_currency) {
            return array();
        }

        $cache_key = 'oksia_convertz_history_' . strtolower($from_currency . '_' . $to_currency . '_' . $days);
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
        } else {
            $cached = false;
        }

        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        $url = sprintf(
            'https://convertz.app/api/currency/%s-to-%s/history?days=%d',
            rawurlencode($from_currency),
            rawurlencode($to_currency),
            $days
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 8,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            )
        );

        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        if ('' === $body) {
            return array();
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return array();
        }

        set_transient($cache_key, $payload, HOUR_IN_SECONDS);
        return $payload;
    }

    private function get_convertz_yesterday_cross_rate($inr_history_payload, $base_history_payload) {
        $inr_rates = $this->extract_convertz_rate_series($inr_history_payload);
        $base_rates = $this->extract_convertz_rate_series($base_history_payload);
        if (empty($inr_rates) || empty($base_rates)) {
            return '';
        }

        $common_dates = array_values(array_intersect(array_keys($inr_rates), array_keys($base_rates)));
        if (count($common_dates) < 2) {
            return '';
        }

        sort($common_dates, SORT_STRING);
        $yesterday_date = $common_dates[count($common_dates) - 2];
        $inr_rate = (float) ($inr_rates[$yesterday_date] ?? 0);
        $base_rate = (float) ($base_rates[$yesterday_date] ?? 0);
        if ($inr_rate <= 0 || $base_rate <= 0) {
            return '';
        }

        return (string) ($inr_rate / $base_rate);
    }

    private function extract_convertz_rate_series($payload) {
        $rates = (array) ($payload['rates'] ?? array());
        if (empty($rates)) {
            return array();
        }

        $series = array();
        $is_associative = array_keys($rates) !== range(0, count($rates) - 1);

        if ($is_associative) {
            foreach ($rates as $date => $rate) {
                $date = trim((string) $date);
                if ('' === $date) {
                    continue;
                }
                $series[$date] = (float) $rate;
            }
            ksort($series, SORT_STRING);
            return $series;
        }

        foreach ($rates as $entry) {
            if (is_array($entry)) {
                $date = trim((string) ($entry['date'] ?? $entry[0] ?? ''));
                $rate = $entry['rate'] ?? $entry[1] ?? '';
                if ('' === $date || '' === $rate) {
                    continue;
                }
                $series[$date] = (float) $rate;
                continue;
            }

            if (is_object($entry)) {
                $date = trim((string) ($entry->date ?? ''));
                $rate = $entry->rate ?? '';
                if ('' === $date || '' === $rate) {
                    continue;
                }
                $series[$date] = (float) $rate;
            }
        }

        ksort($series, SORT_STRING);
        return $series;
    }

    private function format_currency_delta($delta_value) {
        if ('' === (string) $delta_value) {
            return '';
        }

        $delta = (float) $delta_value;
        if (0.0 === $delta) {
            return '-';
        }

        $sign = $delta > 0 ? '+' : '-';
        return $sign . number_format(abs($delta), 2, '.', '');
    }

    private function convertz_rate_from_snapshot($payload, $base_currency) {
        $base_currency = strtoupper(preg_replace('/[^A-Z]/', '', (string) $base_currency));
        if ('' === $base_currency) {
            return '';
        }

        $rates = (array) ($payload['rates'] ?? array());
        if ('INR' === $base_currency) {
            return '1';
        }

        $usd_to_inr = (float) ($rates['INR'] ?? 0);
        $usd_to_base = (float) ($rates[$base_currency] ?? 0);
        if ($usd_to_inr <= 0 || $usd_to_base <= 0) {
            return '';
        }

        return (string) ($usd_to_inr / $usd_to_base);
    }

    private function get_dashboard_destination_options() {
        $query = new WP_Query(
            array_filter(array(
                'post_type' => OKSIA_Post_Types::POST_TYPE,
                'posts_per_page' => -1,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'fields' => 'ids',
                'meta_query' => $this->get_agency_meta_query(),
            ))
        );

        $destinations = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $trip = (array) get_post_meta(get_the_ID(), '_oksia_trip_overview', true);
                $destination = trim((string) ($trip['destination'] ?? ''));
                if ('' !== $destination) {
                    $destinations[$destination] = $destination;
                }
            }
            wp_reset_postdata();
        }

        if (empty($destinations)) {
            $destinations = array(
                'Himachal' => 'Himachal',
                'Kashmir' => 'Kashmir',
                'Dubai' => 'Dubai',
                'Thailand' => 'Thailand',
            );
        }

        ksort($destinations);
        return array_values($destinations);
    }

    private function normalize_dashboard_stage($stage) {
        $allowed = array('draft', 'send', 'confirmed', 'cancelled');
        return in_array($stage, $allowed, true) ? $stage : '';
    }

    private function get_current_agency_id() {
        if (!class_exists('OKSIA_Agencies')) {
            return 0;
        }

        return OKSIA_Agencies::instance()->get_current_user_agency_id();
    }

    private function get_agency_meta_query() {
        $agency_id = $this->get_current_agency_id();
        if (!$agency_id || current_user_can('manage_options')) {
            return array();
        }

        return array(
            array(
                'key' => '_oksia_agency_id',
                'value' => $agency_id,
                'compare' => '=',
            ),
        );
    }

    private function can_access_quote($post_id) {
        if (current_user_can('manage_options')) {
            return true;
        }

        $agency_id = $this->get_current_agency_id();
        if (!$agency_id) {
            return true;
        }

        $quote_agency_id = absint(get_post_meta($post_id, '_oksia_agency_id', true));
        return $quote_agency_id && $quote_agency_id === $agency_id;
    }

    public function render_agency_settings_shortcode() {
        ob_start();
        include plugin_dir_path(__FILE__) . '../templates/agency-settings-fullwidth.php';
        return ob_get_clean();
    }

    public function render_temp_master_settings_shortcode() {
        ob_start();
        include plugin_dir_path(__FILE__) . '../templates/temp-master-settings.php';
        return ob_get_clean();
    }
    public function render_agency_registration_shortcode() {
        $locked = $this->is_agency_registration_locked() || $this->get_current_agency_id() > 0;
        $saved = isset($_GET['oksia_registration_saved']) ? sanitize_text_field(wp_unslash($_GET['oksia_registration_saved'])) : '';
        $users = get_users(array(
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'user_email'),
        ));
        $allow_multi_assignments = count($users) > 1;
        $registration_agency_id = $this->get_current_agency_id();
        ob_start();
        ?>
        <style>
            body {
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                -webkit-font-smoothing: antialiased;
            }

        .oksia-workspace-shell {
            max-width: 1200px;
            margin: 0 auto;
            margin-top: 24px;
            padding: 30px;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }

            .oksia-registration-top-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 16px 24px;
            }

            .oksia-settings-field {
                display: flex;
                flex-direction: column;
            }

            .oksia-settings-field--span-1 {
                grid-column: span 1;
            }

            .oksia-settings-field--span-3 {
                grid-column: span 3;
            }

            .oksia-settings-field label {
                font-size: 13px;
                font-weight: 600;
                color: #334155;
                margin-bottom: 6px;
            }

            .oksia-settings-field input[type="text"],
            .oksia-settings-field input[type="email"],
            .oksia-settings-field select,
            .oksia-settings-field input[type="file"] {
                width: 100%;
                height: 38px;
                padding: 6px 12px;
                font-size: 14px;
                border: 1px solid #cbd5e1;
                border-radius: 4px;
                background-color: #ffffff;
                box-sizing: border-box;
                transition: border-color 0.15s ease-in-out;
            }

            .oksia-settings-field input[type="file"] {
                padding: 6px 8px;
                line-height: 1.5;
            }

            .oksia-settings-field input:disabled,
            .oksia-settings-field select:disabled {
                background-color: #ffffff;
                color: #64748b;
                border-color: #e2e8f0;
                cursor: not-allowed;
            }

            .oksia-settings-form p {
                display: flex;
                gap: 12px;
                margin-top: 28px;
                margin-bottom: 0;
            }

            .oksia-settings-form .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                height: 40px;
                padding: 0 20px;
                font-size: 14px;
                font-weight: 500;
                color: #ffffff;
                background-color: #0284c7;
                border: none;
                border-radius: 4px;
                text-decoration: none;
                cursor: pointer;
                transition: background-color 0.2s ease;
            }

            .oksia-settings-form .button:hover {
                background-color: #0369a1;
            }

            @media (max-width: 768px) {
                body { padding: 15px; }
                .oksia-workspace-shell { margin-top: 12px; padding: 15px; }
                .oksia-registration-top-grid { grid-template-columns: 1fr; gap: 16px; }
                .oksia-settings-field--span-3 { grid-column: span 1; }
                .oksia-settings-form p { flex-direction: column; gap: 10px; }
                .oksia-settings-form .button { width: 100%; }
            }
        </style>
        <div class="oksia-workspace-shell">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="oksia-settings-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="oksia_save_agency_registration" />
                <?php wp_nonce_field('oksia_save_agency_registration', 'oksia_agency_registration_nonce'); ?>
                <?php
                $agency_registration_top_fields = array(
                    array('label' => 'GST Name', 'option' => 'oksia_billing_company', 'default' => 'EKTA CORPORATION', 'disabled' => $locked, 'required' => true),
                    array('label' => 'GST Number / PAN Number', 'option' => 'oksia_billing_gst', 'default' => '24ATNPB9314Q1Z8', 'disabled' => $locked, 'required' => true),
                    array('label' => 'Company Type', 'option' => 'oksia_agency_legal_entity', 'default' => '', 'type' => 'select', 'choices' => OKSIA_Agencies::get_company_type_options(), 'disabled' => $locked, 'required' => true),
                    array('label' => 'Agency Name', 'option' => 'oksia_agency_name', 'default' => 'OK', 'disabled' => $locked, 'required' => true),
                    array('label' => 'Agency Type', 'option' => 'oksia_agency_type', 'default' => 'travel_agency', 'type' => 'select', 'choices' => OKSIA_Agencies::get_agency_type_options(), 'disabled' => $locked, 'required' => true),
                    array('label' => 'Agency Website', 'option' => 'oksia_agency_website', 'default' => '', 'type' => 'text', 'disabled' => $locked, 'required' => true),
                    array('label' => 'Name', 'option' => 'oksia_authorize_name', 'default' => '', 'disabled' => $locked, 'required' => true),
                    array('label' => 'Contact', 'option' => 'oksia_agency_phone', 'default' => '+91-8320-696-872', 'disabled' => $locked, 'required' => true),
                    array('label' => 'Email', 'option' => 'oksia_agency_email', 'default' => '', 'type' => 'email', 'disabled' => $locked, 'required' => true),
                    array('label' => 'Building', 'option' => 'oksia_agency_building', 'default' => $this->get_legacy_address_part(0), 'disabled' => $locked, 'required' => true),
                    array('label' => 'Landmark', 'option' => 'oksia_agency_landmark', 'default' => $this->get_legacy_address_part(1), 'disabled' => $locked, 'required' => true),
                    array('label' => 'Area', 'option' => 'oksia_agency_area', 'default' => $this->get_legacy_address_part(2), 'disabled' => $locked, 'required' => true),
                    array('label' => 'City', 'option' => 'oksia_agency_location', 'default' => 'Ahmedabad', 'disabled' => $locked, 'required' => true),
                    array('label' => 'State', 'option' => 'oksia_agency_state', 'default' => 'Gujarat', 'disabled' => $locked, 'required' => true),
                    array('label' => 'Pincode', 'option' => 'oksia_agency_pincode', 'default' => '', 'disabled' => $locked, 'required' => true),
                    array('label' => 'Agency Logo', 'option' => 'oksia_agency_logo_url', 'default' => '', 'type' => 'file', 'disabled' => $locked, 'required' => true),
                    array('label' => 'Agency Tag Line', 'option' => 'oksia_intake_tagline', 'default' => 'Tell us about your dream trip', 'type' => 'text', 'disabled' => $locked),
                    array('label' => 'IATA/TIDS', 'option' => 'oksia_iata_code', 'default' => '96169710', 'disabled' => $locked),
                    array('label' => 'FB Page', 'option' => 'oksia_agency_fb_page', 'default' => '', 'type' => 'text', 'disabled' => $locked),
                    array('label' => 'Instagram', 'option' => 'oksia_agency_instagram', 'default' => '', 'type' => 'text', 'disabled' => $locked),
                    array('label' => 'Google', 'option' => 'oksia_agency_google', 'default' => '', 'type' => 'text', 'disabled' => $locked),
                    array('label' => 'Where did you hear about us?', 'option' => 'oksia_hear_about_us', 'default' => '', 'type' => 'text', 'span' => 3, 'disabled' => $locked, 'required' => true),
                    array('label' => 'GST Email Address', 'option' => 'oksia_billing_email', 'default' => '', 'type' => 'email', 'span' => 3, 'disabled' => $locked, 'required' => true),
                );
                $registration_submit_label = $locked ? __('Registration Locked', 'oksia-smart-itinerary-agent') : __('Save Agency Registration', 'oksia-smart-itinerary-agent');
                ?>
                <div class="oksia-registration-top-grid">
                    <div class="oksia-settings-field oksia-settings-field--span-3" style="margin-bottom:4px;">
                        <span style="font-size:13px;font-weight:600;color:#dc2626;">* Fields marked with an asterisk are mandatory.</span>
                    </div>
                    <?php foreach ($agency_registration_top_fields as $field) : ?>
                        <?php $this->render_settings_field($field); ?>
                    <?php endforeach; ?>
                </div>
        <p>
            <button type="submit" class="button button-primary"><?php esc_html_e('Submit Registration', 'oksia-smart-itinerary-agent'); ?></button>
        </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_agency_registration_submission() {
        if (!isset($_POST['oksia_agency_registration_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['oksia_agency_registration_nonce'])), 'oksia_save_agency_registration')) {
            wp_safe_redirect(add_query_arg('oksia_registration_error', 'invalid_nonce', $this->get_registration_url()));
            exit;
        }

        $primary_color = sanitize_hex_color(wp_unslash($_POST['oksia_primary_color'] ?? ''));
        $secondary_color = sanitize_hex_color(wp_unslash($_POST['oksia_secondary_color'] ?? ''));
        $accent_color = sanitize_hex_color(wp_unslash($_POST['oksia_accent_color'] ?? ''));
        $agency_name = sanitize_text_field(wp_unslash($_POST['oksia_agency_name'] ?? ''));
        $authorize_name = sanitize_text_field(wp_unslash($_POST['oksia_authorize_name'] ?? ''));
        $agency_email = sanitize_email(wp_unslash($_POST['oksia_agency_email'] ?? ''));
        $agency_phone = sanitize_text_field(wp_unslash($_POST['oksia_agency_phone'] ?? ''));
        $agency_code = sanitize_text_field(wp_unslash($_POST['oksia_agency_code'] ?? ''));
        $agency_type = sanitize_key(wp_unslash($_POST['oksia_agency_type'] ?? 'travel_agency'));
        $company_type = sanitize_text_field(wp_unslash($_POST['oksia_agency_legal_entity'] ?? ''));
        $agency_website = $this->normalize_website_value(wp_unslash($_POST['oksia_agency_website'] ?? ''));
        $agency_fb_page = sanitize_text_field(wp_unslash($_POST['oksia_agency_fb_page'] ?? ''));
        $agency_instagram = sanitize_text_field(wp_unslash($_POST['oksia_agency_instagram'] ?? ''));
        $agency_google = sanitize_text_field(wp_unslash($_POST['oksia_agency_google'] ?? ''));
        $hear_about_us = sanitize_text_field(wp_unslash($_POST['oksia_hear_about_us'] ?? ''));
        $agency_location = sanitize_text_field(wp_unslash($_POST['oksia_agency_location'] ?? ''));
        $agency_building = sanitize_text_field(wp_unslash($_POST['oksia_agency_building'] ?? ''));
        $agency_landmark = sanitize_text_field(wp_unslash($_POST['oksia_agency_landmark'] ?? ''));
        $agency_area = sanitize_text_field(wp_unslash($_POST['oksia_agency_area'] ?? ''));
        $agency_pincode = sanitize_text_field(wp_unslash($_POST['oksia_agency_pincode'] ?? ''));
        $agency_state = sanitize_text_field(wp_unslash($_POST['oksia_agency_state'] ?? ''));
        $iata_code = sanitize_text_field(wp_unslash($_POST['oksia_iata_code'] ?? ''));
        $gst_number = sanitize_text_field(wp_unslash($_POST['oksia_billing_gst'] ?? ''));
        $gst_name = sanitize_text_field(wp_unslash($_POST['oksia_billing_company'] ?? ''));
        $gst_email = sanitize_email(wp_unslash($_POST['oksia_billing_email'] ?? ''));
        $agency_logo_url = esc_url_raw(wp_unslash($_POST['oksia_agency_logo_url'] ?? ''));
        $agency_logo_url = class_exists('OKSIA_Agencies') ? OKSIA_Agencies::handle_logo_upload('oksia_agency_logo_upload', $agency_logo_url) : $agency_logo_url;
        $intake_tagline = sanitize_text_field(wp_unslash($_POST['oksia_intake_tagline'] ?? ''));
        $main_agency_user_id = $this->resolve_user_id_from_lookup(wp_unslash($_POST['oksia_main_agency_user_id_lookup'] ?? ''));
        $manager_user_ids = $this->resolve_user_ids_from_lookup(wp_unslash($_POST['oksia_agency_manager_user_ids_lookup'] ?? ''));
        $staff_user_ids = $this->resolve_user_ids_from_lookup(wp_unslash($_POST['oksia_agency_staff_user_ids_lookup'] ?? ''));

        if (0 === $main_agency_user_id && is_user_logged_in()) {
            $main_agency_user_id = get_current_user_id();
        }

        $trial_days = class_exists('OKSIA_Agencies') ? OKSIA_Agencies::DEFAULT_TRIAL_DAYS : 15;
        $registration_payload = array(
            'agency_name' => $agency_name,
            'legal_entity' => $company_type,
            'agency_type' => $agency_type,
            'agency_code' => $agency_code,
            'authorize_name' => $authorize_name,
            'agency_phone' => $agency_phone,
            'agency_email' => $agency_email,
            'agency_website' => $agency_website,
            'agency_fb_page' => $agency_fb_page,
            'agency_instagram' => $agency_instagram,
            'agency_google' => $agency_google,
            'hear_about_us' => $hear_about_us,
            'agency_building' => $agency_building,
            'agency_landmark' => $agency_landmark,
            'agency_area' => $agency_area,
            'agency_location' => $agency_location,
            'agency_state' => $agency_state,
            'agency_pincode' => $agency_pincode,
            'iata_code' => $iata_code,
            'billing_gst' => $gst_number,
            'billing_company' => $gst_name,
            'billing_email' => $gst_email,
            'agency_logo_url' => $agency_logo_url,
            'intake_tagline' => $intake_tagline,
            'primary_color' => $primary_color,
            'secondary_color' => $secondary_color,
            'accent_color' => $accent_color,
            'main_agency_user_id' => $main_agency_user_id,
            'agency_manager_user_ids' => $manager_user_ids,
            'agency_staff_user_ids' => $staff_user_ids,
            'trial_days' => $trial_days,
        );

        if (!class_exists('OKSIA_Agencies')) {
            wp_safe_redirect(add_query_arg('oksia_registration_error', 'agency_module_missing', $this->get_registration_url()));
            exit;
        }

        $agencies = OKSIA_Agencies::instance();
        $agency_id = $agencies->upsert_agency_from_registration($registration_payload);
        if (is_wp_error($agency_id)) {
            wp_safe_redirect(add_query_arg('oksia_registration_error', sanitize_key($agency_id->get_error_code()), $this->get_registration_url()));
            exit;
        }

        $primary_agency_id = absint(get_option(OKSIA_Agencies::OPTION_PRIMARY_AGENCY_ID, 0));
        if ($primary_agency_id > 0 && $primary_agency_id === (int) $agency_id) {
            if ('' !== $agency_name) {
                update_option('oksia_agency_name', $agency_name, false);
            }
            update_option('oksia_authorize_name', $authorize_name, false);
            update_option('oksia_agency_email', $agency_email, false);
            update_option('oksia_agency_phone', $agency_phone, false);
            update_option('oksia_agency_code', $agency_code, false);
            update_option('oksia_agency_type', $agency_type, false);
            update_option('oksia_agency_legal_entity', $company_type, false);
            update_option('oksia_agency_website', $agency_website, false);
            update_option('oksia_agency_fb_page', $agency_fb_page, false);
            update_option('oksia_agency_instagram', $agency_instagram, false);
            update_option('oksia_agency_google', $agency_google, false);
            update_option('oksia_hear_about_us', $hear_about_us, false);
            update_option('oksia_agency_building', $agency_building, false);
            update_option('oksia_agency_landmark', $agency_landmark, false);
            update_option('oksia_agency_area', $agency_area, false);
            update_option('oksia_agency_location', $agency_location, false);
            update_option('oksia_agency_state', $agency_state, false);
            update_option('oksia_agency_pincode', $agency_pincode, false);
            update_option('oksia_iata_code', $iata_code, false);
            update_option('oksia_billing_gst', $gst_number, false);
            update_option('oksia_billing_company', $gst_name, false);
            update_option('oksia_billing_email', $gst_email, false);
            update_option('oksia_agency_logo_url', $agency_logo_url, false);
            update_option('oksia_intake_tagline', $intake_tagline, false);
            update_option('oksia_main_agency_user_id', $main_agency_user_id, false);
            update_option('oksia_agency_manager_user_ids', $manager_user_ids, false);
            update_option('oksia_agency_staff_user_ids', $staff_user_ids, false);

            if ('' !== $primary_color) {
                update_option('oksia_primary_color', $primary_color, false);
            }
            if ('' !== $secondary_color) {
                update_option('oksia_secondary_color', $secondary_color, false);
            }
            if ('' !== $accent_color) {
                update_option('oksia_accent_color', $accent_color, false);
            }
        }

        delete_option(self::OPTION_REGISTRATION_LOCKED);
        delete_option(self::OPTION_REGISTRATION_LOCKED_AT);

        wp_safe_redirect(add_query_arg('oksia_registration_saved', '1', $this->get_registration_url()));
        exit;
    }

    public function handle_agency_settings_submission() {
        if (!is_user_logged_in()) {
            $this->redirect_to_login_for_agency_registration();
        }

        if (!isset($_POST['oksia_agency_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['oksia_agency_settings_nonce'])), 'oksia_save_agency_settings')) {
            wp_safe_redirect(add_query_arg('oksia_settings_error', 'invalid_nonce', $this->get_settings_url()));
            exit;
        }

        if (!class_exists('OKSIA_Agencies')) {
            wp_safe_redirect(add_query_arg('oksia_settings_error', 'agency_module_missing', $this->get_settings_url()));
            exit;
        }

        $agencies = OKSIA_Agencies::instance();
        $agency_id = $this->get_current_agency_id();
        if (!$agency_id && current_user_can('manage_options')) {
            $agency_id = absint(get_option(OKSIA_Agencies::OPTION_PRIMARY_AGENCY_ID, 0));
        }
        if (!$agency_id) {
            wp_safe_redirect(add_query_arg('oksia_settings_error', 'agency_missing', $this->get_settings_url()));
            exit;
        }

        $agency_code = trim((string) get_post_meta($agency_id, OKSIA_Agencies::META_CODE, true));
        if ('' === $agency_code) {
            $agency_code = $agencies->generate_next_agency_code();
        }

        $current_agency_name = trim((string) get_post_field('post_title', $agency_id, 'raw'));
        $current_legal_entity = trim((string) get_post_meta($agency_id, OKSIA_Agencies::META_LEGAL_ENTITY, true));
        $current_agency_type = trim((string) get_post_meta($agency_id, OKSIA_Agencies::META_AGENCY_TYPE, true));
        $current_authorize_name = trim((string) get_post_meta($agency_id, 'oksia_authorize_name', true));
        $current_agency_phone = trim((string) get_post_meta($agency_id, 'oksia_agency_phone', true));
        $current_agency_email = trim((string) get_post_meta($agency_id, 'oksia_agency_email', true));
        $current_agency_website = trim((string) get_post_meta($agency_id, 'oksia_agency_website', true));
        $current_agency_fb_page = sanitize_text_field(wp_unslash($_POST['oksia_agency_fb_page'] ?? get_post_meta($agency_id, 'oksia_agency_fb_page', true)));
        $current_agency_instagram = sanitize_text_field(wp_unslash($_POST['oksia_agency_instagram'] ?? get_post_meta($agency_id, 'oksia_agency_instagram', true)));
        $current_agency_google = sanitize_text_field(wp_unslash($_POST['oksia_agency_google'] ?? get_post_meta($agency_id, 'oksia_agency_google', true)));
        $current_agency_building = trim((string) get_post_meta($agency_id, 'oksia_agency_building', true));
        $current_agency_landmark = trim((string) get_post_meta($agency_id, 'oksia_agency_landmark', true));
        $current_agency_area = trim((string) get_post_meta($agency_id, 'oksia_agency_area', true));
        $current_agency_location = trim((string) get_post_meta($agency_id, 'oksia_agency_location', true));
        $current_agency_state = trim((string) get_post_meta($agency_id, 'oksia_agency_state', true));
        $current_agency_pincode = trim((string) get_post_meta($agency_id, 'oksia_agency_pincode', true));
        $current_iata_code = sanitize_text_field(wp_unslash($_POST['oksia_iata_code'] ?? get_post_meta($agency_id, 'oksia_iata_code', true)));
        $current_gst_number = trim((string) get_post_meta($agency_id, 'oksia_billing_gst', true));
        $current_gst_name = trim((string) get_post_meta($agency_id, 'oksia_billing_company', true));
        $current_gst_email = trim((string) get_post_meta($agency_id, 'oksia_billing_email', true));
        $current_agency_logo_url = esc_url_raw(wp_unslash($_POST['oksia_agency_logo_url'] ?? get_post_meta($agency_id, 'oksia_agency_logo_url', true)));
        if (class_exists('OKSIA_Agencies')) {
        $current_agency_logo_url = OKSIA_Agencies::handle_logo_upload('oksia_agency_logo_url_upload', $current_agency_logo_url);
        if ('' === $current_agency_logo_url) {
        $current_agency_logo_url = OKSIA_Agencies::handle_logo_upload('oksia_agency_logo_upload', '');
        }
        $current_intake_tagline = sanitize_text_field(wp_unslash($_POST['oksia_intake_tagline'] ?? get_post_meta($agency_id, 'oksia_intake_tagline', true)));
        $current_hotel_categories_domestic = sanitize_textarea_field(wp_unslash($_POST['oksia_hotel_categories_domestic'] ?? get_option('oksia_hotel_categories_domestic', '')));
        $current_hotel_categories_international = sanitize_textarea_field(wp_unslash($_POST['oksia_hotel_categories_international'] ?? get_option('oksia_hotel_categories_international', '')));
        $current_occupancies_domestic = sanitize_textarea_field(wp_unslash($_POST['oksia_occupancies_domestic'] ?? get_option('oksia_occupancies_domestic', '')));
        $current_occupancies_international = sanitize_textarea_field(wp_unslash($_POST['oksia_occupancies_international'] ?? get_option('oksia_occupancies_international', '')));
        $current_meal_plans_domestic = sanitize_textarea_field(wp_unslash($_POST['oksia_meal_plans_domestic'] ?? get_option('oksia_meal_plans_domestic', '')));
        $current_meal_plans_international = sanitize_textarea_field(wp_unslash($_POST['oksia_meal_plans_international'] ?? get_option('oksia_meal_plans_international', '')));
        $current_meal_transfer_types_domestic = sanitize_textarea_field(wp_unslash($_POST['oksia_meal_transfer_types_domestic'] ?? get_option('oksia_meal_transfer_types_domestic', '')));
        $current_meal_transfer_types_international = sanitize_textarea_field(wp_unslash($_POST['oksia_meal_transfer_types_international'] ?? get_option('oksia_meal_transfer_types_international', '')));
        $current_pickup_points_domestic = sanitize_textarea_field(wp_unslash($_POST['oksia_pickup_points_domestic'] ?? get_option('oksia_pickup_points_domestic', '')));
        $current_pickup_points_international = sanitize_textarea_field(wp_unslash($_POST['oksia_pickup_points_international'] ?? get_option('oksia_pickup_points_international', '')));
        $current_drop_points_domestic = sanitize_textarea_field(wp_unslash($_POST['oksia_drop_points_domestic'] ?? get_option('oksia_drop_points_domestic', '')));
        $current_drop_points_international = sanitize_textarea_field(wp_unslash($_POST['oksia_drop_points_international'] ?? get_option('oksia_drop_points_international', '')));
        $current_transfer_modes_domestic = sanitize_textarea_field(wp_unslash($_POST['oksia_transfer_modes_domestic'] ?? get_option('oksia_transfer_modes_domestic', '')));
        $current_transfer_modes_international = sanitize_textarea_field(wp_unslash($_POST['oksia_transfer_modes_international'] ?? get_option('oksia_transfer_modes_international', '')));
        $current_sightseeing_vehicles_domestic = sanitize_textarea_field(wp_unslash($_POST['oksia_sightseeing_vehicles_domestic'] ?? get_option('oksia_sightseeing_vehicles_domestic', '')));
        $current_sightseeing_vehicles_international = sanitize_textarea_field(wp_unslash($_POST['oksia_sightseeing_vehicles_international'] ?? get_option('oksia_sightseeing_vehicles_international', '')));
        $current_vehicle_types_domestic = sanitize_textarea_field(wp_unslash($_POST['oksia_vehicle_types_domestic'] ?? get_option('oksia_vehicle_types_domestic', '')));
        $current_vehicle_types_international = sanitize_textarea_field(wp_unslash($_POST['oksia_vehicle_types_international'] ?? get_option('oksia_vehicle_types_international', '')));

        $payload = array(
            'agency_name' => $current_agency_name,
            'legal_entity' => $current_legal_entity,
            'agency_type' => $current_agency_type,
            'agency_code' => $agency_code,
            'authorize_name' => $current_authorize_name,
            'agency_phone' => $current_agency_phone,
            'agency_email' => $current_agency_email,
            'agency_website' => $current_agency_website,
            'agency_fb_page' => $current_agency_fb_page,
            'agency_instagram' => $current_agency_instagram,
            'agency_google' => $current_agency_google,
            'agency_building' => $current_agency_building,
            'agency_landmark' => $current_agency_landmark,
            'agency_area' => $current_agency_area,
            'agency_location' => $current_agency_location,
            'agency_state' => $current_agency_state,
            'agency_pincode' => $current_agency_pincode,
            'iata_code' => $current_iata_code,
            'billing_gst' => $current_gst_number,
            'billing_company' => $current_gst_name,
            'billing_email' => $current_gst_email,
            'agency_logo_url' => $current_agency_logo_url,
            'intake_tagline' => $current_intake_tagline,
            'main_agency_user_id' => $this->resolve_user_id_from_lookup(wp_unslash($_POST['oksia_main_agency_user_id_lookup'] ?? '')),
            'agency_manager_user_ids' => $this->resolve_user_ids_from_lookup(wp_unslash($_POST['oksia_agency_manager_user_ids_lookup'] ?? '')),
            'agency_staff_user_ids' => $this->resolve_user_ids_from_lookup(wp_unslash($_POST['oksia_agency_staff_user_ids_lookup'] ?? '')),
            'primary_color' => sanitize_hex_color(wp_unslash($_POST['oksia_primary_color'] ?? '')),
            'secondary_color' => sanitize_hex_color(wp_unslash($_POST['oksia_secondary_color'] ?? '')),
            'accent_color' => sanitize_hex_color(wp_unslash($_POST['oksia_accent_color'] ?? '')),
            'trial_days' => absint(get_post_meta($agency_id, OKSIA_Agencies::META_TRIAL_DAYS, true)) ?: OKSIA_Agencies::DEFAULT_TRIAL_DAYS,
        );

        $saved_agency_id = $agencies->upsert_agency_from_registration($payload);
        if (is_wp_error($saved_agency_id)) {
            wp_safe_redirect(add_query_arg('oksia_settings_error', sanitize_key($saved_agency_id->get_error_code()), $this->get_settings_url()));
            exit;
        }

        $option_fields = array(
            'oksia_hotel_categories_domestic' => $current_hotel_categories_domestic,
            'oksia_hotel_categories_international' => $current_hotel_categories_international,
            'oksia_occupancies_domestic' => $current_occupancies_domestic,
            'oksia_occupancies_international' => $current_occupancies_international,
            'oksia_meal_plans_domestic' => $current_meal_plans_domestic,
            'oksia_meal_plans_international' => $current_meal_plans_international,
            'oksia_meal_transfer_types_domestic' => $current_meal_transfer_types_domestic,
            'oksia_meal_transfer_types_international' => $current_meal_transfer_types_international,
            'oksia_domestic_destinations',
            'oksia_international_destinations',
            'oksia_pickup_points_domestic' => $current_pickup_points_domestic,
            'oksia_pickup_points_international' => $current_pickup_points_international,
            'oksia_drop_points_domestic' => $current_drop_points_domestic,
            'oksia_drop_points_international' => $current_drop_points_international,
            'oksia_transfer_modes_domestic' => $current_transfer_modes_domestic,
            'oksia_transfer_modes_international' => $current_transfer_modes_international,
            'oksia_sightseeing_vehicles_domestic' => $current_sightseeing_vehicles_domestic,
            'oksia_sightseeing_vehicles_international' => $current_sightseeing_vehicles_international,
            'oksia_vehicle_types_domestic' => $current_vehicle_types_domestic,
            'oksia_vehicle_types_international' => $current_vehicle_types_international,
            'oksia_default_inclusions',
            'oksia_default_exclusions',
            'oksia_default_child_policy',
            'oksia_default_cancellation_policy',
            'oksia_default_booking_policy',
            'oksia_default_refund_policy',
            'oksia_domestic_inclusions',
            'oksia_domestic_exclusions',
            'oksia_domestic_child_policy',
            'oksia_domestic_cancellation_policy',
            'oksia_domestic_booking_policy',
            'oksia_domestic_refund_policy',
            'oksia_disclaimer_text',
        );

        foreach ($option_fields as $option_name => $option_value)
            if (is_int($option_name)) {
                $option_name = $option_value;
                $option_value = sanitize_textarea_field(wp_unslash($_POST[$option_name] ?? ''));
            }
            update_option($option_name, $option_value, false);
        }

        wp_safe_redirect(add_query_arg('oksia_settings_saved', '1', $this->get_settings_url()));
        exit;
    }

    public function redirect_to_login_for_agency_registration() {
        wp_safe_redirect(wp_login_url($this->get_registration_url()));
        exit;
    }

    private function is_agency_registration_locked() {
        return '1' === (string) get_option(self::OPTION_REGISTRATION_LOCKED, '0');
    }

    private function sanitize_user_id_array($value) {
        $items = array();
        foreach ((array) $value as $candidate) {
            $candidate = absint($candidate);
            if ($candidate > 0) {
                $items[] = $candidate;
            }
        }
        return array_values(array_unique($items));
    }

    private function resolve_user_id_from_lookup($lookup) {
        $lookup = trim(sanitize_text_field((string) $lookup));
        if ('' === $lookup) {
            return 0;
        }

        foreach (get_users(array('fields' => array('ID', 'display_name', 'user_email'))) as $user) {
            $display = trim((string) $user->display_name);
            $email = trim((string) $user->user_email);
            $combo = $display . ('' !== $email ? ' (' . $email . ')' : '');
            if (0 === strcasecmp($lookup, $display) || 0 === strcasecmp($lookup, $email) || 0 === strcasecmp($lookup, $combo)) {
                return (int) $user->ID;
            }
        }

        return 0;
    }

    private function resolve_user_ids_from_lookup($lookup) {
        $text = trim(sanitize_text_field((string) $lookup));
        if ('' === $text) {
            return array();
        }

        $parts = array_filter(array_map('trim', preg_split('/[,;\n]+/', $text)));
        $ids = array();
        foreach ($parts as $part) {
            $id = $this->resolve_user_id_from_lookup($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function normalize_website_value($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return esc_url_raw($value);
        }

        return esc_url_raw('https://' . ltrim($value, '/'));
    }

    private function get_legacy_address_part($index) {
        $index = absint($index);
        $legacy = (string) get_option('oksia_company_address', '');
        if ('' === trim($legacy)) {
            return '';
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $legacy))));
        return isset($parts[$index]) ? $parts[$index] : '';
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

    private function render_settings_section($title, $fields, $layout = array()) {
        echo '<div class="oksia-settings-grid">';
        foreach ((array) $fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            if (($field['type'] ?? '') !== 'spacer' && (empty($field['label']) || empty($field['option']))) {
                continue;
            }
            $this->render_settings_field($field);
        }
        echo '</div>';
    }

    private function render_settings_field($field) {
        $label = (string) ($field['label'] ?? '');
        $option_name = (string) ($field['option'] ?? '');
        $default = $field['default'] ?? '';
        $type = (string) ($field['type'] ?? 'text');
        $span = max(1, min(3, absint($field['span'] ?? 1)));
        $help = (string) ($field['help'] ?? '');
        $disabled = !empty($field['disabled']);
        $required = !empty($field['required']);
        $source = (string) ($field['source'] ?? 'option');
        $post_id = absint($field['post_id'] ?? 0);

        if ('spacer' === $type) {
            echo '<div class="oksia-settings-field oksia-settings-field--span-' . esc_attr((string) $span) . ' oksia-settings-field--spacer" aria-hidden="true"></div>';
            return;
        }

        if ('meta' === $source && $post_id > 0) {
            if ('title' === $option_name) {
                $value = get_post_field('post_title', $post_id, 'raw');
            } else {
                $value = get_post_meta($post_id, $option_name, true);
            }
            if ('' === $value || null === $value) {
                $value = $default;
            }
        } else {
            $value = get_option($option_name, $default);
        }
        if ('color' === $type) {
            $value = sanitize_hex_color((string) $value);
            if (empty($value)) {
                $value = sanitize_hex_color((string) $default) ?: '#000066';
            }
        }

        echo '<div class="oksia-settings-field oksia-settings-field--span-' . esc_attr((string) $span) . '">';
        echo '<label for="' . esc_attr($option_name) . '">' . esc_html__($label, 'oksia-smart-itinerary-agent');
        if ($required) {
            echo ' <span style="color:#dc2626;" aria-hidden="true">*</span>';
        }
        echo '</label>';

        $required_attr = $required ? ' required="required"' : '';

        if ('textarea' === $type) {
            echo '<textarea id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" rows="5"' . ($disabled ? ' disabled="disabled"' : '') . $required_attr . '>' . esc_textarea((string) $value) . '</textarea>';
        } elseif ('user_select' === $type) {
            $users = get_users(array(
                'orderby' => 'display_name',
                'order' => 'ASC',
                'fields' => array('ID', 'display_name', 'user_email'),
            ));
            $selected_user_id = absint($value);
            echo '<select id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '"' . ($disabled ? ' disabled="disabled"' : '') . $required_attr . '>';
            echo '<option value="0">' . esc_html__('Use current admin fallback', 'oksia-smart-itinerary-agent') . '</option>';
            foreach ($users as $user) {
                $label_text = trim($user->display_name);
                if (!empty($user->user_email)) {
                    $label_text .= ' (' . $user->user_email . ')';
                }
                echo '<option value="' . esc_attr((string) $user->ID) . '" ' . selected($selected_user_id, $user->ID, false) . '>' . esc_html($label_text) . '</option>';
            }
            echo '</select>';
        } elseif ('select' === $type) {
            $choices = (array) ($field['choices'] ?? array());
            $selected_value = (string) $value;
            echo '<select id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '"' . ($disabled ? ' disabled="disabled"' : '') . $required_attr . '>';
            foreach ($choices as $choice_value => $choice_label) {
                echo '<option value="' . esc_attr((string) $choice_value) . '" ' . selected($selected_value, (string) $choice_value, false) . '>' . esc_html((string) $choice_label) . '</option>';
            }
            echo '</select>';
        } elseif ('file' === $type) {
            $current_value = is_string($value) ? $value : '';
            echo '<input type="hidden" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr($current_value) . '" />';
            echo '<input type="file" id="' . esc_attr($option_name) . '_upload" name="' . esc_attr($option_name) . '_upload" accept="image/*"' . ($disabled ? ' disabled="disabled"' : '') . $required_attr . ' />';
            if ('' !== $current_value) {
                echo '<div class="oksia-file-preview" style="margin-top:8px;">';
                echo '<img src="' . esc_url($current_value) . '" alt="" style="max-width:120px;height:auto;display:block;" />';
                echo '</div>';
            }
        } elseif ('user_multi_select' === $type) {
            $users = get_users(array(
                'orderby' => 'display_name',
                'order' => 'ASC',
                'fields' => array('ID', 'display_name', 'user_email'),
            ));
            $selected_ids = array_map('absint', (array) $value);
            echo '<div class="oksia-user-multi-list" id="' . esc_attr($option_name) . '" role="group" aria-label="' . esc_attr($label) . '">';
            foreach ($users as $user) {
                $label_text = trim($user->display_name);
                if (!empty($user->user_email)) {
                    $label_text .= ' (' . $user->user_email . ')';
                }
                $checked = in_array((int) $user->ID, $selected_ids, true) ? ' checked="checked"' : '';
                echo '<label class="oksia-user-multi-item">';
                echo '<input type="checkbox" name="' . esc_attr($option_name) . '[]" value="' . esc_attr((string) $user->ID) . '"' . $checked . ($disabled ? ' disabled="disabled"' : '') . $required_attr . ' />';
                echo '<span>' . esc_html($label_text) . '</span>';
                echo '</label>';
            }
            echo '</div>';
        } elseif ('user_suggest_single' === $type) {
            $users = get_users(array(
                'orderby' => 'display_name',
                'order' => 'ASC',
                'fields' => array('ID', 'display_name', 'user_email'),
            ));
            $selected_user_id = absint($value);
            $selected_user = $selected_user_id ? get_user_by('id', $selected_user_id) : false;
            $selected_label = '';
            if ($selected_user instanceof WP_User) {
                $selected_label = trim((string) $selected_user->display_name);
                if (!empty($selected_user->user_email)) {
                    $selected_label .= ' (' . $selected_user->user_email . ')';
                }
            }
            $list_id = $option_name . '_users';
            echo '<input type="text" id="' . esc_attr($option_name) . '_lookup" name="' . esc_attr($option_name) . '_lookup" value="' . esc_attr($selected_label) . '" list="' . esc_attr($list_id) . '" placeholder="' . esc_attr__('Type user name or email', 'oksia-smart-itinerary-agent') . '"' . ($disabled ? ' disabled="disabled"' : '') . $required_attr . ' />';
            echo '<datalist id="' . esc_attr($list_id) . '">';
            foreach ($users as $user) {
                $label_text = trim((string) $user->display_name);
                if (!empty($user->user_email)) {
                    $label_text .= ' (' . $user->user_email . ')';
                }
                echo '<option value="' . esc_attr($label_text) . '"></option>';
            }
            echo '</datalist>';
        } elseif ('user_suggest_multi' === $type) {
            $users = get_users(array(
                'orderby' => 'display_name',
                'order' => 'ASC',
                'fields' => array('ID', 'display_name', 'user_email'),
            ));
            $selected_ids = array_map('absint', (array) $value);
            $selected_labels = array();
            foreach ($selected_ids as $selected_id) {
                $selected_user = get_user_by('id', $selected_id);
                if (!$selected_user instanceof WP_User) {
                    continue;
                }
                $label_text = trim((string) $selected_user->display_name);
                if (!empty($selected_user->user_email)) {
                    $label_text .= ' (' . $selected_user->user_email . ')';
                }
                $selected_labels[] = $label_text;
            }
            $list_id = $option_name . '_users';
            echo '<input type="text" id="' . esc_attr($option_name) . '_lookup" name="' . esc_attr($option_name) . '_lookup" value="' . esc_attr(implode(', ', $selected_labels)) . '" list="' . esc_attr($list_id) . '" placeholder="' . esc_attr__('Type names, separate with comma', 'oksia-smart-itinerary-agent') . '"' . ($disabled ? ' disabled="disabled"' : '') . $required_attr . ' />';
            echo '<datalist id="' . esc_attr($list_id) . '">';
            foreach ($users as $user) {
                $label_text = trim((string) $user->display_name);
                if (!empty($user->user_email)) {
                    $label_text .= ' (' . $user->user_email . ')';
                }
                echo '<option value="' . esc_attr($label_text) . '"></option>';
            }
            echo '</datalist>';
        } elseif ('color' === $type) {
            echo '<input type="color" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr((string) $value) . '"' . ($disabled ? ' disabled="disabled"' : '') . $required_attr . ' />';
        } else {
            echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($option_name) . '" name="' . esc_attr($option_name) . '" value="' . esc_attr((string) $value) . '"' . ($disabled ? ' disabled="disabled"' : '') . $required_attr . ' />';
        }

        if ('' !== $help) {
            echo '<p class="description">' . esc_html($help) . '</p>';
        }

        echo '</div>';
    }

    private function normalize_stage($stage) {
        $allowed = array('draft', 'send', 'confirmed', 'cancelled');
        return in_array($stage, $allowed, true) ? $stage : '';
    }

    public function get_quote_stage_counts() {
        $counts = array(
            'draft' => 0,
            'send' => 0,
            'confirmed' => 0,
            'cancelled' => 0,
        );

        $query = new WP_Query(
            array_filter(array(
                'post_type' => OKSIA_Post_Types::POST_TYPE,
                'posts_per_page' => -1,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'fields' => 'ids',
                'meta_query' => $this->get_agency_meta_query(),
            ))
        );

        if (!$query->have_posts()) {
            return $counts;
        }

        while ($query->have_posts()) {
            $query->the_post();
            $stage = OKSIA_Admin::get_quote_stage(get_the_ID());
            if (isset($counts[$stage])) {
                $counts[$stage]++;
            }
        }

        wp_reset_postdata();
        return $counts;
    }

    private function get_primary_role_slug($user) {
        if (!$user || empty($user->roles) || !is_array($user->roles)) {
            return 'staff';
        }

        $primary_role = (string) reset($user->roles);
        return '' !== $primary_role ? $primary_role : 'staff';
    }

    private function get_role_badge_copy($role_slug, $can_manage) {
        $map = array(
            'administrator' => array(
                'title' => __('Platform Admin Mode', 'oksia-smart-itinerary-agent'),
                'subtitle' => __('Full backend control', 'oksia-smart-itinerary-agent'),
                'description' => __('Manage agencies, users, quotes, settings, and system tools from wp-admin.', 'oksia-smart-itinerary-agent'),
            ),
            'oksia_agency' => array(
                'title' => __('Agency Owner Mode', 'oksia-smart-itinerary-agent'),
                'subtitle' => __('Master settings access', 'oksia-smart-itinerary-agent'),
                'description' => __('Manage agency master settings, users, role assignments, and dashboard items.', 'oksia-smart-itinerary-agent'),
            ),
            'oksia_manager' => array(
                'title' => __('Agency Manager Mode', 'oksia-smart-itinerary-agent'),
                'subtitle' => __('Team + reopen access', 'oksia-smart-itinerary-agent'),
                'description' => __('Add users below the agency, manage daily workflow, and reopen closed quotes when needed.', 'oksia-smart-itinerary-agent'),
            ),
            'oksia_employee' => array(
                'title' => __('Agency Employee Mode', 'oksia-smart-itinerary-agent'),
                'subtitle' => __('Quote work only', 'oksia-smart-itinerary-agent'),
                'description' => __('Share client intake, create new agency intake entries, and edit quotes until they are closed.', 'oksia-smart-itinerary-agent'),
            ),
            'default' => array(
                'title' => __('Workspace Ready', 'oksia-smart-itinerary-agent'),
                'subtitle' => __('Portal access', 'oksia-smart-itinerary-agent'),
                'description' => __('Use the custom dashboard to move between quote intake, review, and travel documents.', 'oksia-smart-itinerary-agent'),
            ),
        );

        if ($can_manage && isset($map['administrator'])) {
            return $map['administrator'];
        }

        if (isset($map[$role_slug])) {
            return $map[$role_slug];
        }

        return $map['default'];
    }

    private function get_quick_cards($role_slug, $can_manage, $agent_intake_url, $client_intake_url, $admin_url) {
        $cards = array(
            array(
                'title' => __('Agent Intake', 'oksia-smart-itinerary-agent'),
                'description' => __('Create or edit itineraries for agent-led quote workflow.', 'oksia-smart-itinerary-agent'),
                'url' => $agent_intake_url,
            ),
            array(
                'title' => __('Client Intake', 'oksia-smart-itinerary-agent'),
                'description' => __('Capture guest details and prepare fresh quote requests.', 'oksia-smart-itinerary-agent'),
                'url' => $client_intake_url,
            ),
        );

        if (in_array($role_slug, array('oksia_agency', 'oksia_manager'), true) || $can_manage) {
            $cards[] = array(
                'title' => __('Finalize Queue', 'oksia-smart-itinerary-agent'),
                'description' => __('Review drafts, lock finalized quotes, and send customer mails.', 'oksia-smart-itinerary-agent'),
                'url' => $agent_intake_url,
            );
        }

        if ($can_manage) {
            $cards[] = array(
                'title' => __('WordPress Admin', 'oksia-smart-itinerary-agent'),
                'description' => __('Open wp-admin for site settings, users, and system tools.', 'oksia-smart-itinerary-agent'),
                'url' => $admin_url,
            );
        } else {
            $cards[] = array(
                'title' => __('Public Site', 'oksia-smart-itinerary-agent'),
                'description' => __('Return to the brochure, quote pages, and public intake.', 'oksia-smart-itinerary-agent'),
                'url' => home_url('/'),
            );
        }

        return $cards;
    }

    public function get_quote_stats() {
        $recent_query = new WP_Query(
            array_filter(array(
                'post_type' => OKSIA_Post_Types::POST_TYPE,
                'posts_per_page' => 100,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'fields' => 'ids',
                'date_query' => array(
                    array(
                        'after' => '7 days ago',
                    ),
                ),
                'meta_query' => $this->get_agency_meta_query(),
            ))
        );

        $counts = $this->get_quote_stage_counts();

        return array(
            'total' => array_sum($counts),
            'draft' => (int) ($counts['draft'] ?? 0),
            'send' => (int) ($counts['send'] ?? 0),
            'confirmed' => (int) ($counts['confirmed'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
            'finalized' => (int) (($counts['send'] ?? 0) + ($counts['confirmed'] ?? 0)),
            'recent' => (int) $recent_query->found_posts,
        );
    }

    public function get_recent_quotes($limit = 10, $stage = '', $destination = '', $search = '', $trip_type = '', $page = 1) {
        $query_args = array(
            'post_type' => OKSIA_Post_Types::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $stage = $this->normalize_stage($stage);
        $destination = trim((string) $destination);
        $search = trim((string) $search);
        $trip_type = trim((string) $trip_type);
        if ('' !== $stage) {
            $query_args['meta_query'] = array(
                array(
                    'key' => '_oksia_quote_stage',
                    'value' => $stage,
                    'compare' => '=',
                ),
            );
        }

        $agency_meta_query = $this->get_agency_meta_query();
        if (!empty($agency_meta_query)) {
            $query_args['meta_query'] = isset($query_args['meta_query']) ? array_merge($query_args['meta_query'], $agency_meta_query) : $agency_meta_query;
        }

        $query = new WP_Query($query_args);

        if (!$query->have_posts()) {
            return array(
                'quotes' => array(),
                'pagination' => array(
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total_items' => 0,
                    'per_page' => max(1, absint($limit)),
                ),
            );
        }

        $rows = array();
        while ($query->have_posts()) {
            $query->the_post();
            $trip = (array) get_post_meta(get_the_ID(), '_oksia_trip_overview', true);
            $trip_destination = trim((string) ($trip['destination'] ?? ''));
            $trip_type_value = trim((string) ($trip['trip_type'] ?? ''));
            if ('' !== $destination && $trip_destination !== $destination) {
                continue;
            }
            if ('' !== $trip_type && strcasecmp($trip_type_value, $trip_type) !== 0) {
                continue;
            }
            $quote_id = trim((string) get_post_meta(get_the_ID(), '_oksia_quote_id', true));
            $quote_stage = OKSIA_Admin::get_quote_stage(get_the_ID());
            $stage_labels = OKSIA_Admin::get_quote_stage_labels();
            $client = trim((string) (($trip['salutation'] ?? '') . ' ' . ($trip['client_name'] ?? '')));
            if ('' !== $search) {
                $haystack = strtolower(trim(implode(' ', array($quote_id, get_the_title(), $client, $trip_destination, $trip_type_value))));
                if (false === strpos($haystack, strtolower($search))) {
                    continue;
                }
            }
            $rows[] = array(
                'id' => get_the_ID(),
                'link' => get_permalink(),
                'quote_id' => '' !== $quote_id ? $quote_id : get_the_title(),
                'client' => $client,
                'trip_type' => $trip_type_value,
                'destination' => trim((string) ($trip['destination'] ?? '')),
                'status' => $stage_labels[$quote_stage] ?? __('Send', 'oksia-smart-itinerary-agent'),
                'stage' => $quote_stage,
                'updated' => get_the_modified_date(),
                'version' => class_exists('OKSIA_Admin') ? OKSIA_Admin::get_quote_version_label(get_the_ID()) : 'v1',
                'updated_by' => class_exists('OKSIA_Admin') ? OKSIA_Admin::get_quote_last_updated_by_name(get_the_ID()) : '',
            );
        }
        wp_reset_postdata();
        $per_page = max(1, absint($limit));
        $current_page = max(1, absint($page));
        $total_items = count($rows);
        $total_pages = max(1, (int) ceil($total_items / $per_page));
        $current_page = min($current_page, $total_pages);
        $offset = ($current_page - 1) * $per_page;
        $paged_rows = array_slice($rows, $offset, $per_page);

        return array(
            'quotes' => $paged_rows,
            'pagination' => array(
                'current_page' => $current_page,
                'total_pages' => $total_pages,
                'total_items' => $total_items,
                'per_page' => $per_page,
            ),
        );
    }

    public function get_user_role_label($user) {
        return '';
    }
}
