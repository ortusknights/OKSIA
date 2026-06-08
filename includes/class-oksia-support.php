<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_Support {
    const NONCE_ACTION = 'oksia_support_submit';
    const NONCE_FIELD = 'oksia_support_nonce';
    const CAPTCHA_TRANSIENT_PREFIX = 'oksia_support_captcha_';
    const TICKET_STATUS_KEY = 'oksia_ticket_status';
    const TICKET_ID_KEY = 'oksia_ticket_id';
    const TICKET_CATEGORY_KEY = 'oksia_ticket_category';
    const TICKET_AGENCY_ID_KEY = 'oksia_ticket_agency_id';
    const TICKET_AGENCY_NAME_KEY = 'oksia_ticket_agency_name';
    const TICKET_AGENCY_CODE_KEY = 'oksia_ticket_agency_code';
    const TICKET_AGENCY_EMAIL_KEY = 'oksia_ticket_agency_email';
    const TICKET_OWNER_EMAIL_KEY = 'oksia_ticket_owner_email';
    const TICKET_TITLE_KEY = 'oksia_ticket_title';
    const TICKET_BRIEF_KEY = 'oksia_ticket_brief';
    const TICKET_ATTACHMENT_ID_KEY = 'oksia_ticket_attachment_id';
    const TICKET_SUBMITTER_USER_ID_KEY = 'oksia_ticket_submitter_user_id';
    const TICKET_POST_TYPE = 'oksia_ticket';

    private $agencies;

    public function __construct() {
        $this->agencies = class_exists('OKSIA_Agencies') ? OKSIA_Agencies::instance() : null;
        add_action('admin_menu', array($this, 'register_tickets_menu'));
        add_action('admin_post_oksia_submit_support_ticket', array($this, 'handle_support_submission'));
        add_action('admin_post_oksia_process_ticket_actions', array($this, 'process_ticket_bulk_actions'));
        add_shortcode('oksia_support', array($this, 'render_support_shortcode'));
    }

    public static function get_categories() {
        return array(
            'billing' => __('Billing', 'oksia-smart-itinerary-agent'),
            'ams_tabs' => __('Agency Master Settings', 'oksia-smart-itinerary-agent'),
            'agent_intake' => __('Agent Intake', 'oksia-smart-itinerary-agent'),
            'client_intake' => __('Client Intake', 'oksia-smart-itinerary-agent'),
            'renewal_cancellation' => __('Renewal / Cancellation', 'oksia-smart-itinerary-agent'),
            'upgrade_downgrade' => __('Upgrade / Downgrade', 'oksia-smart-itinerary-agent'),
            'core_field_changes' => __('Core field changes', 'oksia-smart-itinerary-agent'),
            'other' => __('Other', 'oksia-smart-itinerary-agent'),
        );
    }

    public function register_tickets_menu() {
        add_submenu_page(
            'oksia',
            __('Tickets', 'oksia-smart-itinerary-agent'),
            __('Tickets', 'oksia-smart-itinerary-agent'),
            'manage_options',
            'oksia-tickets',
            array($this, 'render_tickets_page'),
            4
        );
    }

    public function render_tickets_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $status_filter = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $meta_query = array();
        if ('' !== $status_filter && in_array($status_filter, array('open', 'resolved', 'closed'), true)) {
            $meta_query[] = array(
                'key' => self::TICKET_STATUS_KEY,
                'value' => $status_filter,
                'compare' => '=',
            );
        }

        $ticket_args = array(
            'post_type' => self::TICKET_POST_TYPE,
            'posts_per_page' => 20,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'orderby' => 'date',
            'order' => 'DESC',
        );
        if (!empty($meta_query)) {
            $ticket_args['meta_query'] = $meta_query;
        }
        if ('' !== $search) {
            $ticket_args['s'] = $search;
        }

        $tickets = get_posts($ticket_args);
        $page_url = admin_url('admin.php?page=oksia-tickets');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Tickets', 'oksia-smart-itinerary-agent'); ?></h1>
            <p class="description"><?php esc_html_e('Recent support tickets received from agencies.', 'oksia-smart-itinerary-agent'); ?></p>

            <form method="get" action="">
                <input type="hidden" name="page" value="oksia-tickets">
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:16px 0;">
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search tickets', 'oksia-smart-itinerary-agent'); ?>" />
                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'oksia-smart-itinerary-agent'); ?></option>
                        <option value="open" <?php selected($status_filter, 'open'); ?>><?php esc_html_e('Open', 'oksia-smart-itinerary-agent'); ?></option>
                        <option value="resolved" <?php selected($status_filter, 'resolved'); ?>><?php esc_html_e('Resolved', 'oksia-smart-itinerary-agent'); ?></option>
                        <option value="closed" <?php selected($status_filter, 'closed'); ?>><?php esc_html_e('Closed', 'oksia-smart-itinerary-agent'); ?></option>
                    </select>
                    <button type="submit" class="button"><?php esc_html_e('Filter', 'oksia-smart-itinerary-agent'); ?></button>
                </div>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="action" value="oksia_process_ticket_actions">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($page_url); ?>">

                <div style="display:flex;gap:8px;align-items:center;margin:0 0 12px;">
                    <select name="bulk_action">
                        <option value=""><?php esc_html_e('Bulk actions', 'oksia-smart-itinerary-agent'); ?></option>
                        <option value="open"><?php esc_html_e('Mark Open', 'oksia-smart-itinerary-agent'); ?></option>
                        <option value="resolved"><?php esc_html_e('Resolved', 'oksia-smart-itinerary-agent'); ?></option>
                        <option value="closed"><?php esc_html_e('Closed', 'oksia-smart-itinerary-agent'); ?></option>
                    </select>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'oksia-smart-itinerary-agent'); ?></button>
                </div>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" id="oksia-ticket-select-all"></th>
                            <th><?php esc_html_e('Ticket ID', 'oksia-smart-itinerary-agent'); ?></th>
                            <th><?php esc_html_e('Agency Name', 'oksia-smart-itinerary-agent'); ?></th>
                            <th><?php esc_html_e('Agency Code', 'oksia-smart-itinerary-agent'); ?></th>
                            <th><?php esc_html_e('Category', 'oksia-smart-itinerary-agent'); ?></th>
                            <th><?php esc_html_e('Title', 'oksia-smart-itinerary-agent'); ?></th>
                            <th><?php esc_html_e('Status', 'oksia-smart-itinerary-agent'); ?></th>
                            <th><?php esc_html_e('Date', 'oksia-smart-itinerary-agent'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tickets)) : ?>
                            <?php foreach ($tickets as $ticket) : ?>
                                <?php
                                $ticket_id = (string) get_post_meta($ticket->ID, self::TICKET_ID_KEY, true);
                                $ticket_status = (string) get_post_meta($ticket->ID, self::TICKET_STATUS_KEY, true);
                                $ticket_agency_name = (string) get_post_meta($ticket->ID, self::TICKET_AGENCY_NAME_KEY, true);
                                $ticket_agency_code = (string) get_post_meta($ticket->ID, self::TICKET_AGENCY_CODE_KEY, true);
                                $ticket_category = (string) get_post_meta($ticket->ID, self::TICKET_CATEGORY_KEY, true);
                                ?>
                                <tr>
                                    <th class="check-column"><input type="checkbox" name="ticket_ids[]" value="<?php echo esc_attr((string) $ticket->ID); ?>"></th>
                                    <td><strong><?php echo esc_html($ticket_id !== '' ? $ticket_id : ('#' . $ticket->ID)); ?></strong></td>
                                    <td><?php echo esc_html($ticket_agency_name !== '' ? $ticket_agency_name : __('Not set', 'oksia-smart-itinerary-agent')); ?></td>
                                    <td><?php echo esc_html($ticket_agency_code !== '' ? $ticket_agency_code : __('Not set', 'oksia-smart-itinerary-agent')); ?></td>
                                    <td><?php echo esc_html(self::get_categories()[$ticket_category] ?? $ticket_category); ?></td>
                                    <td><?php echo esc_html(get_the_title($ticket)); ?></td>
                                    <td><strong><?php echo esc_html(ucfirst($ticket_status !== '' ? $ticket_status : 'open')); ?></strong></td>
                                    <td><?php echo esc_html(get_the_date(get_option('date_format') . ' ' . get_option('time_format'), $ticket)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="8"><?php esc_html_e('No tickets received yet.', 'oksia-smart-itinerary-agent'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <script>
            (function () {
                var selectAll = document.getElementById('oksia-ticket-select-all');
                if (!selectAll) return;
                selectAll.addEventListener('change', function () {
                    document.querySelectorAll('input[name="ticket_ids[]"]').forEach(function (checkbox) {
                        checkbox.checked = selectAll.checked;
                    });
                });
            })();
        </script>
        <?php
    }

    public function handle_support_submission() {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(wp_get_referer() ?: home_url('/')));
            exit;
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $agency_id = $this->agencies ? absint($this->agencies->get_current_user_agency_id()) : 0;
        if (!$agency_id) {
            wp_safe_redirect(add_query_arg('oksia_support_error', 'agency', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $category = sanitize_key(wp_unslash($_POST['oksia_support_category'] ?? ''));
        $categories = self::get_categories();
        if (!isset($categories[$category])) {
            $category = 'other';
        }

        $title = sanitize_text_field(wp_unslash($_POST['oksia_support_title'] ?? ''));
        $brief = sanitize_textarea_field(wp_unslash($_POST['oksia_support_brief'] ?? ''));
        $captcha_token = sanitize_text_field(wp_unslash($_POST['oksia_support_captcha_token'] ?? ''));
        $captcha_answer = sanitize_text_field(wp_unslash($_POST['oksia_support_captcha_answer'] ?? ''));

        if ('' === $title || '' === $brief) {
            wp_safe_redirect(add_query_arg('oksia_support_error', 'required', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $expected_answer = (string) get_transient(self::CAPTCHA_TRANSIENT_PREFIX . $captcha_token);
        delete_transient(self::CAPTCHA_TRANSIENT_PREFIX . $captcha_token);
        if ('' === $expected_answer || (string) $expected_answer !== (string) $captcha_answer) {
            wp_safe_redirect(add_query_arg('oksia_support_error', 'captcha', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $attachment_id = 0;
        if (!empty($_FILES['oksia_support_screenshot']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $attachment_id = media_handle_upload('oksia_support_screenshot', 0);
            if (is_wp_error($attachment_id)) {
                $attachment_id = 0;
            }
        }

        $ticket_post_id = wp_insert_post(array(
            'post_type' => self::TICKET_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $brief,
        ), true);

        if (is_wp_error($ticket_post_id) || !$ticket_post_id) {
            wp_safe_redirect(add_query_arg('oksia_support_error', 'save', wp_get_referer() ?: home_url('/')));
            exit;
        }

        $ticket_id = 'TK-' . $ticket_post_id;
        $agency_name = get_the_title($agency_id);
        $agency_code = $this->agencies ? (string) $this->agencies->get_agency_code($agency_id) : '';
        $agency_email = trim((string) get_post_meta($agency_id, 'oksia_agency_email', true));
        $main_user_id = absint(get_post_meta($agency_id, 'oksia_main_agency_user_id', true));
        $owner_email = '';
        if ($main_user_id > 0) {
            $owner_user = get_user_by('id', $main_user_id);
            if ($owner_user instanceof WP_User) {
                $owner_email = sanitize_email((string) $owner_user->user_email);
            }
        }
        if ('' === $owner_email) {
            $owner_email = $agency_email;
        }

        update_post_meta($ticket_post_id, self::TICKET_ID_KEY, $ticket_id);
        update_post_meta($ticket_post_id, self::TICKET_STATUS_KEY, 'open');
        update_post_meta($ticket_post_id, self::TICKET_CATEGORY_KEY, $category);
        update_post_meta($ticket_post_id, self::TICKET_AGENCY_ID_KEY, $agency_id);
        update_post_meta($ticket_post_id, self::TICKET_AGENCY_NAME_KEY, $agency_name);
        update_post_meta($ticket_post_id, self::TICKET_AGENCY_CODE_KEY, $agency_code);
        update_post_meta($ticket_post_id, self::TICKET_AGENCY_EMAIL_KEY, $agency_email);
        update_post_meta($ticket_post_id, self::TICKET_OWNER_EMAIL_KEY, $owner_email);
        update_post_meta($ticket_post_id, self::TICKET_TITLE_KEY, $title);
        update_post_meta($ticket_post_id, self::TICKET_BRIEF_KEY, $brief);
        update_post_meta($ticket_post_id, self::TICKET_ATTACHMENT_ID_KEY, absint($attachment_id));
        update_post_meta($ticket_post_id, self::TICKET_SUBMITTER_USER_ID_KEY, get_current_user_id());

        $subject = sprintf(
            'Support : %s (%s) need help with %s - %s',
            $agency_name !== '' ? $agency_name : __('Agency', 'oksia-smart-itinerary-agent'),
            $agency_code !== '' ? $agency_code : __('No Code', 'oksia-smart-itinerary-agent'),
            $categories[$category],
            $ticket_id
        );

        $admin_email = sanitize_email((string) get_option('oksia_billing_email', get_option('admin_email')));
        if ('' === $admin_email) {
            $admin_email = sanitize_email((string) get_option('admin_email'));
        }

        $attachment_link = $attachment_id ? wp_get_attachment_url($attachment_id) : '';
        $body = array(
            'Ticket ID : ' . $ticket_id,
            'Agency    : ' . $agency_name,
            'Code      : ' . $agency_code,
            'Category  : ' . $categories[$category],
            'Title     : ' . $title,
            'Brief     : ' . $brief,
            'Status    : Open',
        );
        if ('' !== $attachment_link) {
            $body[] = 'Screenshot : ' . $attachment_link;
        }
        $body = implode("\r\n", $body);

        wp_mail($admin_email, $subject, $body);

        if ('' !== $owner_email) {
            $owner_body = "Your support ticket has been raised successfully.\r\n\r\n";
            $owner_body .= 'Ticket ID : ' . $ticket_id . "\r\n";
            $owner_body .= 'Category  : ' . $categories[$category] . "\r\n";
            $owner_body .= "We will revert within maximum 3 working days.\r\n";
            wp_mail($owner_email, 'Support ticket received - ' . $ticket_id, $owner_body);
        }

        wp_safe_redirect(add_query_arg('oksia_support_submitted', '1', wp_get_referer() ?: home_url('/')));
        exit;
    }

    public function process_ticket_bulk_actions() {
        if (!current_user_can('manage_options')) {
            wp_safe_redirect(admin_url('admin.php?page=oksia-tickets'));
            exit;
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $bulk_action = sanitize_key(wp_unslash($_POST['bulk_action'] ?? ''));
        $ticket_ids = array_map('absint', (array) ($_POST['ticket_ids'] ?? array()));
        $ticket_ids = array_values(array_filter($ticket_ids));
        if (!in_array($bulk_action, array('open', 'resolved', 'closed'), true) || empty($ticket_ids)) {
            wp_safe_redirect(admin_url('admin.php?page=oksia-tickets'));
            exit;
        }

        foreach ($ticket_ids as $ticket_id) {
            if (get_post_type($ticket_id) === self::TICKET_POST_TYPE) {
                update_post_meta($ticket_id, self::TICKET_STATUS_KEY, $bulk_action);
            }
        }

        wp_safe_redirect(add_query_arg('oksia_ticket_updated', '1', admin_url('admin.php?page=oksia-tickets')));
        exit;
    }

    public function render_support_shortcode() {
        $current_user = wp_get_current_user();
        $agency_id = $this->agencies ? absint($this->agencies->get_current_user_agency_id()) : 0;
        $agency_name = $agency_id ? get_the_title($agency_id) : '';
        $agency_code = ($this->agencies && $agency_id) ? (string) $this->agencies->get_agency_code($agency_id) : '';
        $categories = self::get_categories();
        $submitted = isset($_GET['oksia_support_submitted']) ? sanitize_text_field(wp_unslash($_GET['oksia_support_submitted'])) : '';
        $error = isset($_GET['oksia_support_error']) ? sanitize_text_field(wp_unslash($_GET['oksia_support_error'])) : '';

        ob_start();
        ?>
        <style>
            .oksia-support-wrap {
                max-width: 980px;
                margin: 0 auto;
                padding: 24px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            }
            .oksia-support-card {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            }
            .oksia-support-grid {
                display: grid;
                grid-template-columns: 1.2fr 1fr;
                gap: 20px;
            }
            .oksia-support-field {
                display: flex;
                flex-direction: column;
                gap: 6px;
                margin-bottom: 16px;
            }
            .oksia-support-field label {
                font-weight: 600;
                color: #0f172a;
            }
            .oksia-support-field input,
            .oksia-support-field select,
            .oksia-support-field textarea {
                width: 100%;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 12px;
                box-sizing: border-box;
                font-size: 14px;
            }
            .oksia-support-field textarea {
                min-height: 160px;
                resize: vertical;
            }
            .oksia-support-meta {
                display: grid;
                gap: 12px;
                align-content: start;
            }
            .oksia-support-note {
                margin: 0 0 16px;
                color: #475569;
            }
            .oksia-support-success,
            .oksia-support-error {
                margin-bottom: 16px;
                padding: 12px 14px;
                border-radius: 10px;
                font-weight: 600;
            }
            .oksia-support-success {
                background: #ecfdf5;
                color: #166534;
                border: 1px solid #bbf7d0;
            }
            .oksia-support-error {
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fecaca;
            }
            .oksia-support-actions {
                display: flex;
                gap: 12px;
                align-items: center;
                margin-top: 18px;
            }
            .oksia-support-btn {
                border: none;
                background: linear-gradient(135deg, #1d4ed8 0%, #111827 100%);
                color: #fff;
                border-radius: 10px;
                padding: 12px 18px;
                font-weight: 700;
                cursor: pointer;
            }
            .oksia-support-inline {
                background: #f8fafc;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 14px;
            }
            @media (max-width: 900px) {
                .oksia-support-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <div class="oksia-support-wrap">
            <div class="oksia-support-card">
                <h2><?php esc_html_e('Support', 'oksia-smart-itinerary-agent'); ?></h2>
                <p class="oksia-support-note"><?php esc_html_e('Raise an issue with a screenshot and we will respond within 3 working days.', 'oksia-smart-itinerary-agent'); ?></p>

                <?php if ('1' === $submitted) : ?>
                    <div class="oksia-support-success"><?php esc_html_e('Your ticket has been raised successfully.', 'oksia-smart-itinerary-agent'); ?></div>
                <?php endif; ?>

                <?php if ('' !== $error) : ?>
                    <div class="oksia-support-error">
                        <?php
                        echo esc_html(
                            'captcha' === $error ? __('Captcha verification failed. Please try again.', 'oksia-smart-itinerary-agent') : __('Please complete all required fields.', 'oksia-smart-itinerary-agent')
                        );
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (!is_user_logged_in() || !$agency_id) : ?>
                    <div class="oksia-support-inline">
                        <?php esc_html_e('Please log in with your agency account to raise a support ticket.', 'oksia-smart-itinerary-agent'); ?>
                    </div>
                <?php else : ?>
                    <?php
                    $captcha_a = wp_rand(2, 9);
                    $captcha_b = wp_rand(1, 9);
                    $captcha_token = wp_generate_password(24, false, false);
                    set_transient(self::CAPTCHA_TRANSIENT_PREFIX . $captcha_token, (string) ($captcha_a + $captcha_b), 15 * MINUTE_IN_SECONDS);
                    ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                        <input type="hidden" name="action" value="oksia_submit_support_ticket">
                        <input type="hidden" name="oksia_support_captcha_token" value="<?php echo esc_attr($captcha_token); ?>">
                        <input type="hidden" name="oksia_support_agency_id" value="<?php echo esc_attr((string) $agency_id); ?>">

                        <div class="oksia-support-grid">
                            <div>
                                <div class="oksia-support-field">
                                    <label><?php esc_html_e('Agency Name', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" value="<?php echo esc_attr($agency_name); ?>" readonly>
                                </div>
                                <div class="oksia-support-field">
                                    <label><?php esc_html_e('Agency Code', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" value="<?php echo esc_attr($agency_code); ?>" readonly>
                                </div>
                                <div class="oksia-support-field">
                                    <label for="oksia_support_category"><?php esc_html_e('Category', 'oksia-smart-itinerary-agent'); ?></label>
                                    <select id="oksia_support_category" name="oksia_support_category" required>
                                        <option value=""><?php esc_html_e('Select category', 'oksia-smart-itinerary-agent'); ?></option>
                                        <?php foreach ($categories as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="oksia-support-field">
                                    <label for="oksia_support_title"><?php esc_html_e('Title', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" id="oksia_support_title" name="oksia_support_title" placeholder="<?php esc_attr_e('Write a short title', 'oksia-smart-itinerary-agent'); ?>" required>
                                </div>
                                <div class="oksia-support-field">
                                    <label for="oksia_support_brief"><?php esc_html_e('Brief', 'oksia-smart-itinerary-agent'); ?></label>
                                    <textarea id="oksia_support_brief" name="oksia_support_brief" placeholder="<?php esc_attr_e('Describe the issue briefly', 'oksia-smart-itinerary-agent'); ?>" required></textarea>
                                </div>
                            </div>
                            <div class="oksia-support-meta">
                                <div class="oksia-support-field">
                                    <label for="oksia_support_screenshot"><?php esc_html_e('Screenshot', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="file" id="oksia_support_screenshot" name="oksia_support_screenshot" accept="image/*">
                                </div>
                                <div class="oksia-support-field">
                                    <label for="oksia_support_captcha_answer"><?php echo esc_html(sprintf(__('Captcha: %1$d + %2$d = ?', 'oksia-smart-itinerary-agent'), $captcha_a, $captcha_b)); ?></label>
                                    <input type="text" id="oksia_support_captcha_answer" name="oksia_support_captcha_answer" required>
                                </div>
                                <div class="oksia-support-actions">
                                    <button type="submit" class="oksia-support-btn"><?php esc_html_e('Send Ticket', 'oksia-smart-itinerary-agent'); ?></button>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
