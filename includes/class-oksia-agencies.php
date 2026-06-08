<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_Agencies {
    const POST_TYPE = 'oksia_agency';
    const OPTION_PRIMARY_AGENCY_ID = 'oksia_primary_agency_post_id';
    const USER_META_AGENCY_ID = 'oksia_agency_id';
    const USER_META_AGENCY_CODE = 'oksia_agency_code';
    const META_CODE = '_oksia_agency_code';
    const META_LEGAL_ENTITY = '_oksia_agency_legal_entity';
    const META_AGENCY_TYPE = '_oksia_agency_type';
    const META_IS_PRIMARY = '_oksia_agency_is_primary';
    const META_PRIMARY_COLOR = '_oksia_primary_color';
    const META_SECONDARY_COLOR = '_oksia_secondary_color';
    const META_ACCENT_COLOR = '_oksia_accent_color';
    const META_REGISTERED_AT = '_oksia_agency_registered_at';
    const META_TRIAL_DAYS = '_oksia_agency_trial_days';
    const META_TRIAL_EXPIRES_AT = '_oksia_agency_trial_expires_at';
    const META_SUBSCRIPTION_MODEL = '_oksia_agency_subscription_model';
    const OPTION_BACKFILL_DONE = 'oksia_agency_backfill_done';
    const CODE_PREFIX = 'ECOKSIA';
    const DEFAULT_TRIAL_DAYS = 14;
    const FALLBACK_PRIMARY_COLOR = '#000066';
    const FALLBACK_SECONDARY_COLOR = '#336699';
    const FALLBACK_ACCENT_COLOR = '#99FFFF';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'maybe_sync_primary_agency_from_options'), 20);
        add_action('add_meta_boxes_' . self::POST_TYPE, array($this, 'register_meta_boxes'));
        add_action('post_edit_form_tag', array($this, 'add_post_edit_form_enctype'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_agency_meta'));
        add_action('show_user_profile', array($this, 'render_user_agency_field'));
        add_action('edit_user_profile', array($this, 'render_user_agency_field'));
        add_action('personal_options_update', array($this, 'save_user_agency_field'));
        add_action('edit_user_profile_update', array($this, 'save_user_agency_field'));
        add_filter('login_form_middle', array($this, 'inject_agency_code_login_field'), 10, 2);
        add_filter('wp_authenticate_user', array($this, 'validate_agency_login'), 20, 2);
    }

    public static function get_front_end_role_slugs() {
        return array('oksia_agency', 'oksia_manager', 'oksia_employee');
    }

    public static function get_subscription_models() {
        return array(
            'economy' => array(
                'code' => 'E',
                'label' => __('Economy', 'oksia-smart-itinerary-agent'),
                'users' => 1,
                'range' => __('1 user', 'oksia-smart-itinerary-agent'),
            ),
            'premium' => array(
                'code' => 'P',
                'label' => __('Premium', 'oksia-smart-itinerary-agent'),
                'users' => 3,
                'range' => __('2-4 users', 'oksia-smart-itinerary-agent'),
            ),
            'business' => array(
                'code' => 'B',
                'label' => __('Business', 'oksia-smart-itinerary-agent'),
                'users' => 5,
                'range' => __('5+ users', 'oksia-smart-itinerary-agent'),
            ),
        );
    }

    public static function get_agency_type_options() {
        return array(
            'travel_agency' => __('Travel Agency', 'oksia-smart-itinerary-agent'),
            'tour_operator' => __('Tour Operator', 'oksia-smart-itinerary-agent'),
            'dmc' => __('DMC', 'oksia-smart-itinerary-agent'),
            'corporate_travel' => __('Corporate Travel', 'oksia-smart-itinerary-agent'),
            'online_travel_agent' => __('Online Travel Agent', 'oksia-smart-itinerary-agent'),
            'other' => __('Other', 'oksia-smart-itinerary-agent'),
        );
    }

    public static function get_company_type_options() {
        return array(
            '' => __('Select company type', 'oksia-smart-itinerary-agent'),
            'proprietorship' => __('Sole Proprietorship', 'oksia-smart-itinerary-agent'),
            'partnership' => __('Partnership Firm', 'oksia-smart-itinerary-agent'),
            'llp' => __('Limited Liability Partnership (LLP)', 'oksia-smart-itinerary-agent'),
            'private_limited' => __('Private Limited Company', 'oksia-smart-itinerary-agent'),
            'public_limited' => __('Public Limited Company', 'oksia-smart-itinerary-agent'),
            'opc' => __('One Person Company (OPC)', 'oksia-smart-itinerary-agent'),
            'huf' => __('HUF', 'oksia-smart-itinerary-agent'),
            'trust' => __('Trust', 'oksia-smart-itinerary-agent'),
            'society' => __('Society', 'oksia-smart-itinerary-agent'),
            'section_8' => __('Section 8 Company', 'oksia-smart-itinerary-agent'),
            'other' => __('Other', 'oksia-smart-itinerary-agent'),
        );
    }

    public static function handle_logo_upload(string $file_key, string $existing_url = ''): string {
    if (empty($_FILES[$file_key]['name'])) {
        return $existing_url;
    }

    if ($_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return $existing_url;
    }

    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if (!function_exists('wp_generate_attachment_metadata')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }

    $overrides = array(
        'test_form' => false,
        'mimes' => array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
            'svg'          => 'image/svg+xml',
        ),
    );

    $upload = wp_handle_upload($_FILES[$file_key], $overrides);
    error_log('OKSIA UPLOAD - key: ' . $file_key . ' | result: ' . wp_json_encode($upload));
    if (!empty($upload['error'])) {
        return $existing_url;
    }

    if (!empty($upload['url'])) {
        // Add to media library
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $upload['file']);

        if (!is_wp_error($attach_id)) {
            $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
            wp_update_attachment_metadata($attach_id, $attach_data);
        }

        return esc_url_raw($upload['url']);
    }

    return $existing_url;
}

    public function is_front_end_agency_user($user = null) {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$user instanceof WP_User || empty($user->roles) || !is_array($user->roles)) {
            return false;
        }

        return !empty(array_intersect((array) $user->roles, self::get_front_end_role_slugs()));
    }

    public function inject_agency_code_login_field($content, $args) {
        $agency_code = $this->sanitize_agency_code($_POST['oksia_agency_code'] ?? '');

        $field = '<p class="login-agency-code">';
        $field .= '<label for="oksia_agency_code">' . esc_html__('Agency Code', 'oksia-smart-itinerary-agent') . '</label>';
        $field .= '<input type="text" name="oksia_agency_code" id="oksia_agency_code" class="input" value="' . esc_attr($agency_code) . '" size="20" autocomplete="organization" />';
        $field .= '</p>';

        return $content . $field;
    }

    public function register_post_type() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name' => __('Agencies', 'oksia-smart-itinerary-agent'),
                    'singular_name' => __('Agency', 'oksia-smart-itinerary-agent'),
                    'add_new' => __('Add New Agency', 'oksia-smart-itinerary-agent'),
                    'add_new_item' => __('Add New Agency', 'oksia-smart-itinerary-agent'),
                    'edit_item' => __('Edit Agency', 'oksia-smart-itinerary-agent'),
                    'new_item' => __('New Agency', 'oksia-smart-itinerary-agent'),
                    'view_item' => __('View Agency', 'oksia-smart-itinerary-agent'),
                    'search_items' => __('Search Agencies', 'oksia-smart-itinerary-agent'),
                    'not_found' => __('No agencies found.', 'oksia-smart-itinerary-agent'),
                    'not_found_in_trash' => __('No agencies found in trash.', 'oksia-smart-itinerary-agent'),
                    'menu_name' => __('Agencies', 'oksia-smart-itinerary-agent'),
                ),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'menu_position' => 26,
                'menu_icon' => 'dashicons-building',
                'supports' => array('title'),
                'capability_type' => 'post',
                'map_meta_cap' => true,
            )
        );
    }

    public function register_meta_boxes() {
        add_meta_box(
            'oksia_agency_details',
            __('Agency Details', 'oksia-smart-itinerary-agent'),
            array($this, 'render_agency_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'oksia_agency_users',
            __('Agency Users', 'oksia-smart-itinerary-agent'),
            array($this, 'render_agency_users_meta_box'),
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'oksia_agency_subscription',
            __('Subscription Details', 'oksia-smart-itinerary-agent'),
            array($this, 'render_agency_subscription_meta_box'),
            self::POST_TYPE,
            'side',
            'high'
        );

    }

    public function add_post_edit_form_enctype() {
        echo ' enctype="multipart/form-data"';
    }

    public function render_agency_meta_box($post) {
        wp_nonce_field('oksia_save_agency_meta', 'oksia_agency_nonce');
        $code = $this->get_agency_code($post->ID);
        $agency_name = $this->get_agency_field_value($post->ID, 'post_title', 'oksia_agency_name', $post->post_title);
        $agency_type = $this->get_agency_field_value($post->ID, self::META_AGENCY_TYPE, 'oksia_agency_type');
        $company_type = $this->get_agency_field_value($post->ID, self::META_LEGAL_ENTITY, 'oksia_agency_legal_entity');
        $is_primary = '1' === (string) get_post_meta($post->ID, self::META_IS_PRIMARY, true);
        $authorize_name = $this->get_agency_field_value($post->ID, 'oksia_authorize_name', 'oksia_authorize_name');
        $agency_phone = $this->get_agency_field_value($post->ID, 'oksia_agency_phone', 'oksia_agency_phone');
        $agency_email = $this->get_agency_field_value($post->ID, 'oksia_agency_email', 'oksia_agency_email');
        $agency_website = $this->get_agency_field_value($post->ID, 'oksia_agency_website', 'oksia_agency_website');
        $agency_building = $this->get_agency_field_value($post->ID, 'oksia_agency_building', 'oksia_agency_building');
        $agency_landmark = $this->get_agency_field_value($post->ID, 'oksia_agency_landmark', 'oksia_agency_landmark');
        $agency_area = $this->get_agency_field_value($post->ID, 'oksia_agency_area', 'oksia_agency_area');
        $agency_location = $this->get_agency_field_value($post->ID, 'oksia_agency_location', 'oksia_agency_location');
        $agency_state = $this->get_agency_field_value($post->ID, 'oksia_agency_state', 'oksia_agency_state');
        $agency_pincode = $this->get_agency_field_value($post->ID, 'oksia_agency_pincode', 'oksia_agency_pincode');
        $iata_code = $this->get_agency_field_value($post->ID, 'oksia_iata_code', 'oksia_iata_code');
        $gst_number = $this->get_agency_field_value($post->ID, 'oksia_billing_gst', 'oksia_billing_gst');
        $gst_name = $this->get_agency_field_value($post->ID, 'oksia_billing_company', 'oksia_billing_company');
        $gst_email = $this->get_agency_field_value($post->ID, 'oksia_billing_email', 'oksia_billing_email');
        $agency_google = $this->get_agency_field_value($post->ID, 'oksia_agency_google', 'oksia_agency_google');
        $hear_about_us = $this->get_agency_field_value($post->ID, 'oksia_hear_about_us', 'oksia_hear_about_us');
        $agency_logo_url = $this->get_agency_field_value($post->ID, 'oksia_agency_logo_url', 'oksia_agency_logo_url');
        $primary_color = trim((string) get_post_meta($post->ID, self::META_PRIMARY_COLOR, true));
        $secondary_color = trim((string) get_post_meta($post->ID, self::META_SECONDARY_COLOR, true));
        $accent_color = trim((string) get_post_meta($post->ID, self::META_ACCENT_COLOR, true));
        if ('' === $primary_color) {
            $primary_color = self::FALLBACK_PRIMARY_COLOR;
        }
        if ('' === $secondary_color) {
            $secondary_color = self::FALLBACK_SECONDARY_COLOR;
        }
        if ('' === $accent_color) {
            $accent_color = self::FALLBACK_ACCENT_COLOR;
        }
        ?>
        <p class="description" style="margin-top:0;"><?php esc_html_e('Edit the same agency profile fields used at registration. Front-end agency users cannot change these values.', 'oksia-smart-itinerary-agent'); ?></p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="title"><?php esc_html_e('Agency Name', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="title" name="title" value="<?php echo esc_attr($agency_name); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_agency_type"><?php esc_html_e('Agency Type', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td>
                    <select id="oksia_agency_type" name="oksia_agency_type" class="regular-text">
                        <?php foreach (self::get_agency_type_options() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($agency_type !== '' ? $agency_type : 'travel_agency', $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <th scope="row"><label for="oksia_agency_website"><?php esc_html_e('Agency Website', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_website" name="oksia_agency_website" value="<?php echo esc_attr($agency_website); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="oksia_authorize_name"><?php esc_html_e('Name', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_authorize_name" name="oksia_authorize_name" value="<?php echo esc_attr($authorize_name); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_agency_phone"><?php esc_html_e('Contact', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_phone" name="oksia_agency_phone" value="<?php echo esc_attr($agency_phone); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_agency_email"><?php esc_html_e('Email', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="email" id="oksia_agency_email" name="oksia_agency_email" value="<?php echo esc_attr($agency_email); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="oksia_agency_building"><?php esc_html_e('Building', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_building" name="oksia_agency_building" value="<?php echo esc_attr($agency_building); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_agency_landmark"><?php esc_html_e('Landmark', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_landmark" name="oksia_agency_landmark" value="<?php echo esc_attr($agency_landmark); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_agency_area"><?php esc_html_e('Area', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_area" name="oksia_agency_area" value="<?php echo esc_attr($agency_area); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="oksia_agency_location"><?php esc_html_e('City', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_location" name="oksia_agency_location" value="<?php echo esc_attr($agency_location); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_agency_state"><?php esc_html_e('State', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_state" name="oksia_agency_state" value="<?php echo esc_attr($agency_state); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_agency_pincode"><?php esc_html_e('Pincode', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_pincode" name="oksia_agency_pincode" value="<?php echo esc_attr($agency_pincode); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="oksia_billing_company"><?php esc_html_e('GST Name', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_billing_company" name="oksia_billing_company" value="<?php echo esc_attr($gst_name); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_billing_gst"><?php esc_html_e('GST Number / PAN Number', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_billing_gst" name="oksia_billing_gst" value="<?php echo esc_attr($gst_number); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_agency_legal_entity"><?php esc_html_e('Company Type', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td>
                    <select id="oksia_agency_legal_entity" name="oksia_agency_legal_entity" class="regular-text">
                        <?php foreach (self::get_company_type_options() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($company_type, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="oksia_agency_logo_upload"><?php esc_html_e('Agency Logo', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td>
                    <input type="file" id="oksia_agency_logo_upload" name="oksia_agency_logo_upload" accept="image/*" class="regular-text" />
                    <?php if ($agency_logo_url !== '') : ?>
                        <div style="margin-top:8px;"><img src="<?php echo esc_url($agency_logo_url); ?>" alt="" style="max-width:120px;height:auto;" /></div>
                    <?php endif; ?>
                </td>
                <th scope="row"><label for="oksia_intake_tagline"><?php esc_html_e('Agency Tag Line', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_intake_tagline" name="oksia_intake_tagline" value="<?php echo esc_attr((string) get_post_meta($post->ID, 'oksia_intake_tagline', true)); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_iata_code"><?php esc_html_e('IATA/TIDS', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_iata_code" name="oksia_iata_code" value="<?php echo esc_attr($iata_code); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="oksia_agency_fb_page"><?php esc_html_e('FB Page', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_fb_page" name="oksia_agency_fb_page" value="<?php echo esc_attr((string) get_post_meta($post->ID, 'oksia_agency_fb_page', true)); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_agency_instagram"><?php esc_html_e('Instagram', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_instagram" name="oksia_agency_instagram" value="<?php echo esc_attr((string) get_post_meta($post->ID, 'oksia_agency_instagram', true)); ?>" class="regular-text" /></td>
                <th scope="row"><label for="oksia_agency_google"><?php esc_html_e('Google', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td><input type="text" id="oksia_agency_google" name="oksia_agency_google" value="<?php echo esc_attr($agency_google); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="oksia_hear_about_us"><?php esc_html_e('Where did you hear about us?', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td colspan="5"><input type="text" id="oksia_hear_about_us" name="oksia_hear_about_us" value="<?php echo esc_attr($hear_about_us); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="oksia_billing_email"><?php esc_html_e('GST Email Address', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td colspan="5"><input type="email" id="oksia_billing_email" name="oksia_billing_email" value="<?php echo esc_attr($gst_email); ?>" class="regular-text" /></td>
            </tr>
        </table>
        <p>
            <label>
                <input type="checkbox" name="oksia_agency_is_primary" value="1" <?php checked($is_primary); ?> />
                <?php esc_html_e('Primary agency for this installation', 'oksia-smart-itinerary-agent'); ?>
            </label>
        </p>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;">
            <p>
                <label for="oksia_agency_primary_color"><strong><?php esc_html_e('Primary Color', 'oksia-smart-itinerary-agent'); ?></strong></label>
                <input type="color" id="oksia_agency_primary_color" name="oksia_agency_primary_color" value="<?php echo esc_attr($primary_color); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_agency_secondary_color"><strong><?php esc_html_e('Secondary Color', 'oksia-smart-itinerary-agent'); ?></strong></label>
                <input type="color" id="oksia_agency_secondary_color" name="oksia_agency_secondary_color" value="<?php echo esc_attr($secondary_color); ?>" class="widefat" />
            </p>
            <p>
                <label for="oksia_agency_accent_color"><strong><?php esc_html_e('Accent Color', 'oksia-smart-itinerary-agent'); ?></strong></label>
                <input type="color" id="oksia_agency_accent_color" name="oksia_agency_accent_color" value="<?php echo esc_attr($accent_color); ?>" class="widefat" />
            </p>
        </div>
        <?php
    }

    public function render_agency_users_meta_box($post) {
        wp_nonce_field('oksia_save_agency_meta', 'oksia_agency_nonce');

        $current_main = absint(get_post_meta($post->ID, 'oksia_main_agency_user_id', true));
        $current_managers = array_map('absint', (array) get_post_meta($post->ID, 'oksia_agency_manager_user_ids', true));
        $current_staff = array_map('absint', (array) get_post_meta($post->ID, 'oksia_agency_staff_user_ids', true));
        $users = get_users(array(
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'user_email', 'roles'),
        ));
        ?>
        <p class="description" style="margin-top:0;"><?php esc_html_e('Assign the agency owner and the front-end user roles here. Administrators stay unrestricted.', 'oksia-smart-itinerary-agent'); ?></p>
        <p>
            <label for="oksia_main_agency_user_id"><strong><?php esc_html_e('Main Admin / Owner', 'oksia-smart-itinerary-agent'); ?></strong></label>
            <select id="oksia_main_agency_user_id" name="oksia_main_agency_user_id" class="widefat">
                <option value="0"><?php esc_html_e('Select user', 'oksia-smart-itinerary-agent'); ?></option>
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr((string) $user->ID); ?>" <?php selected($current_main, $user->ID); ?>>
                        <?php echo esc_html($this->format_user_label($user->ID)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="oksia_agency_manager_user_ids"><strong><?php esc_html_e('Managers', 'oksia-smart-itinerary-agent'); ?></strong></label>
            <select id="oksia_agency_manager_user_ids" name="oksia_agency_manager_user_ids[]" class="widefat" multiple size="5">
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr((string) $user->ID); ?>" <?php selected(in_array((int) $user->ID, $current_managers, true)); ?>>
                        <?php echo esc_html($this->format_user_label($user->ID)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="oksia_agency_staff_user_ids"><strong><?php esc_html_e('Staff', 'oksia-smart-itinerary-agent'); ?></strong></label>
            <select id="oksia_agency_staff_user_ids" name="oksia_agency_staff_user_ids[]" class="widefat" multiple size="5">
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr((string) $user->ID); ?>" <?php selected(in_array((int) $user->ID, $current_staff, true)); ?>>
                        <?php echo esc_html($this->format_user_label($user->ID)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    public function render_agency_overview_meta_box($post) {
        $agency_id = absint($post->ID);
        $trial = $this->get_agency_trial_info($agency_id);
        $tier = $this->get_agency_subscription_tier($agency_id);
        $user_ids = $this->get_agency_user_ids($agency_id);
        $quote_count = $this->get_agency_quote_count($agency_id);
        $legal_entity = trim((string) get_post_meta($agency_id, self::META_LEGAL_ENTITY, true));
        $registered_at = trim((string) get_post_meta($agency_id, self::META_REGISTERED_AT, true));
        $primary_color = trim((string) get_post_meta($agency_id, self::META_PRIMARY_COLOR, true));
        $secondary_color = trim((string) get_post_meta($agency_id, self::META_SECONDARY_COLOR, true));
        $accent_color = trim((string) get_post_meta($agency_id, self::META_ACCENT_COLOR, true));
        $phone = trim((string) get_post_meta($agency_id, 'oksia_agency_phone', true));
        $email = trim((string) get_post_meta($agency_id, 'oksia_agency_email', true));
        $website = trim((string) get_post_meta($agency_id, 'oksia_agency_website', true));
        $city = trim((string) get_post_meta($agency_id, 'oksia_agency_location', true));
        $state = trim((string) get_post_meta($agency_id, 'oksia_agency_state', true));
        $pincode = trim((string) get_post_meta($agency_id, 'oksia_agency_pincode', true));
        $gst = trim((string) get_post_meta($agency_id, 'oksia_billing_gst', true));
        $agency_type = trim((string) get_post_meta($agency_id, self::META_AGENCY_TYPE, true));
        $company_type = trim((string) get_post_meta($agency_id, self::META_LEGAL_ENTITY, true));
        $fb_page = trim((string) get_post_meta($agency_id, 'oksia_agency_fb_page', true));
        $instagram = trim((string) get_post_meta($agency_id, 'oksia_agency_instagram', true));
        $google = trim((string) get_post_meta($agency_id, 'oksia_agency_google', true));
        $logo_url = trim((string) get_post_meta($agency_id, 'oksia_agency_logo_url', true));
        $tagline = trim((string) get_post_meta($agency_id, 'oksia_intake_tagline', true));
        $gst_email = trim((string) get_post_meta($agency_id, 'oksia_billing_email', true));
        $trial_status_label = array(
            'active' => __('Active', 'oksia-smart-itinerary-agent'),
            'expiring' => __('Expiring Soon', 'oksia-smart-itinerary-agent'),
            'expired' => __('Expired', 'oksia-smart-itinerary-agent'),
            'unknown' => __('Unknown', 'oksia-smart-itinerary-agent'),
        );
        $trial_status = (string) ($trial['status'] ?? 'unknown');
        ?>
        <div class="oksia-agency-overview">
            <p><strong><?php esc_html_e('Agency Code:', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($this->get_agency_code($agency_id) ?: __('Pending', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('Subscribed Plan:', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($tier['label']); ?> <span class="description"><?php echo esc_html(sprintf('(%s)', $tier['range'])); ?></span></p>
            <p><strong><?php esc_html_e('Current Users:', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html(number_format_i18n(count($user_ids))); ?></p>
            <p><strong><?php esc_html_e('Total Quotes:', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html(number_format_i18n($quote_count)); ?></p>
            <p><strong><?php esc_html_e('Trial Status:', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($trial_status_label[$trial_status] ?? ucfirst($trial_status)); ?></p>
            <p><strong><?php esc_html_e('Trial Expires:', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html((string) ($trial['trial_expires_at'] ?? '')); ?></p>
            <p><strong><?php esc_html_e('Registered On:', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($registered_at !== '' ? mysql2date(get_option('date_format'), $registered_at) : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>

            <hr />

            <p><strong><?php esc_html_e('Agency Type', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($agency_type !== '' ? $agency_type : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('Company Type', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($company_type !== '' ? $company_type : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('FB Page', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($fb_page !== '' ? $fb_page : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('Instagram', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($instagram !== '' ? $instagram : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('Google', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($google !== '' ? $google : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('Agency Logo', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($logo_url !== '' ? $logo_url : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('Agency Tag Line', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($tagline !== '' ? $tagline : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>

            <p><strong><?php esc_html_e('Contact Info', 'oksia-smart-itinerary-agent'); ?></strong></p>
            <p style="margin-top:-6px;">
                <?php echo esc_html($phone !== '' ? $phone : __('Phone not set', 'oksia-smart-itinerary-agent')); ?><br>
                <?php echo esc_html($email !== '' ? $email : __('Email not set', 'oksia-smart-itinerary-agent')); ?><br>
                <?php echo esc_html($website !== '' ? $website : __('Website not set', 'oksia-smart-itinerary-agent')); ?>
            </p>

            <p><strong><?php esc_html_e('City', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($city !== '' ? $city : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('State', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($state !== '' ? $state : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('Pincode', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($pincode !== '' ? $pincode : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('GST', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($gst !== '' ? $gst : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
            <p><strong><?php esc_html_e('GST Email Address', 'oksia-smart-itinerary-agent'); ?></strong><br><?php echo esc_html($gst_email !== '' ? $gst_email : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>

            <hr />

            <p><strong><?php esc_html_e('Assigned Users', 'oksia-smart-itinerary-agent'); ?></strong></p>
            <?php if (!empty($user_ids)) : ?>
                <ul style="margin:0 0 0 18px;">
                    <?php foreach ($user_ids as $user_id) : ?>
                        <?php $user = get_userdata((int) $user_id); ?>
                        <li>
                            <?php echo esc_html($this->format_user_label($user_id)); ?>
                            <?php if ($user instanceof WP_User && !empty($user->roles)) : ?>
                                <span class="description"><?php echo esc_html(' - ' . implode(', ', $user->roles)); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="description"><?php esc_html_e('No users assigned yet.', 'oksia-smart-itinerary-agent'); ?></p>
            <?php endif; ?>

            <hr />

            <p><strong><?php esc_html_e('Brand Colors', 'oksia-smart-itinerary-agent'); ?></strong></p>
            <p style="margin-top:-6px;">
                <span class="description"><?php echo esc_html(sprintf('Primary: %s', $primary_color !== '' ? $primary_color : __('Not set', 'oksia-smart-itinerary-agent'))); ?></span><br>
                <span class="description"><?php echo esc_html(sprintf('Secondary: %s', $secondary_color !== '' ? $secondary_color : __('Not set', 'oksia-smart-itinerary-agent'))); ?></span><br>
                <span class="description"><?php echo esc_html(sprintf('Accent: %s', $accent_color !== '' ? $accent_color : __('Not set', 'oksia-smart-itinerary-agent'))); ?></span>
            </p>
        </div>
        <?php
    }

    public function render_agency_subscription_meta_box($post) {
        $agency_id = absint($post->ID);
        $tier = $this->get_agency_subscription_tier($agency_id);
        $user_count = count($this->get_agency_user_ids($agency_id));
        $saved_model = $this->get_agency_subscription_model_key($agency_id);
        $models = self::get_subscription_models();
        ?>
        <div class="oksia-agency-subscription">
            <p>
                <strong><?php esc_html_e('Current Plan:', 'oksia-smart-itinerary-agent'); ?></strong><br>
                <?php echo esc_html($tier['label']); ?> <span class="description"><?php echo esc_html(sprintf('(%s)', $tier['range'])); ?></span>
            </p>
            <p>
                <strong><?php esc_html_e('Current Users:', 'oksia-smart-itinerary-agent'); ?></strong><br>
                <?php echo esc_html(number_format_i18n($user_count)); ?>
            </p>
            <p>
                <label for="oksia_agency_subscription_model"><strong><?php esc_html_e('Subscription Model', 'oksia-smart-itinerary-agent'); ?></strong></label><br>
                <select name="oksia_agency_subscription_model" id="oksia_agency_subscription_model" style="width:100%;max-width:100%;">
                    <option value="automatic" <?php selected($saved_model, ''); ?>><?php esc_html_e('Automatic (based on users)', 'oksia-smart-itinerary-agent'); ?></option>
                    <?php foreach ($models as $model_key => $model) : ?>
                        <option value="<?php echo esc_attr($model_key); ?>" <?php selected($saved_model, $model_key); ?>>
                            <?php echo esc_html(sprintf('%s (%s)', $model['label'], $model['range'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p class="description" style="margin-bottom:0;">
                <?php esc_html_e('Choose a manual plan to override automatic user-based tiering.', 'oksia-smart-itinerary-agent'); ?>
            </p>
        </div>
        <?php
    }

    public function save_agency_meta($post_id) {
        if (!isset($_POST['oksia_agency_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['oksia_agency_nonce'])), 'oksia_save_agency_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        $code = $this->sanitize_agency_code($_POST['oksia_agency_code'] ?? '');
        $legal_entity = sanitize_text_field(wp_unslash($_POST['oksia_agency_legal_entity'] ?? ''));
        $agency_type = sanitize_key(wp_unslash($_POST['oksia_agency_type'] ?? ''));
        $agency_name = sanitize_text_field(wp_unslash($_POST['post_title'] ?? ''));
        $authorize_name = sanitize_text_field(wp_unslash($_POST['oksia_authorize_name'] ?? ''));
        $agency_phone = sanitize_text_field(wp_unslash($_POST['oksia_agency_phone'] ?? ''));
        $agency_email = sanitize_email(wp_unslash($_POST['oksia_agency_email'] ?? ''));
        $agency_website = esc_url_raw(wp_unslash($_POST['oksia_agency_website'] ?? ''));
        $agency_building = sanitize_text_field(wp_unslash($_POST['oksia_agency_building'] ?? ''));
        $agency_landmark = sanitize_text_field(wp_unslash($_POST['oksia_agency_landmark'] ?? ''));
        $agency_area = sanitize_text_field(wp_unslash($_POST['oksia_agency_area'] ?? ''));
        $agency_location = sanitize_text_field(wp_unslash($_POST['oksia_agency_location'] ?? ''));
        $agency_state = sanitize_text_field(wp_unslash($_POST['oksia_agency_state'] ?? ''));
        $agency_pincode = sanitize_text_field(wp_unslash($_POST['oksia_agency_pincode'] ?? ''));
        $agency_fb_page = sanitize_text_field(wp_unslash($_POST['oksia_agency_fb_page'] ?? ''));
        $agency_instagram = sanitize_text_field(wp_unslash($_POST['oksia_agency_instagram'] ?? ''));
        $agency_google = sanitize_text_field(wp_unslash($_POST['oksia_agency_google'] ?? ''));
        $hear_about_us = sanitize_text_field(wp_unslash($_POST['oksia_hear_about_us'] ?? ''));
        $iata_code = sanitize_text_field(wp_unslash($_POST['oksia_iata_code'] ?? ''));
        $gst_number = sanitize_text_field(wp_unslash($_POST['oksia_billing_gst'] ?? ''));
        $gst_name = sanitize_text_field(wp_unslash($_POST['oksia_billing_company'] ?? ''));
        $gst_email = sanitize_email(wp_unslash($_POST['oksia_billing_email'] ?? ''));
        $existing_logo_url = trim((string) get_post_meta($post_id, 'oksia_agency_logo_url', true));
        $agency_logo_url = esc_url_raw(wp_unslash($_POST['oksia_agency_logo_url'] ?? ''));
        if ('' === $agency_logo_url) {
        $agency_logo_url = $existing_logo_url;
        }
        $agency_logo_url = self::handle_logo_upload('oksia_agency_logo_upload', $agency_logo_url);
        $intake_tagline = sanitize_text_field(wp_unslash($_POST['oksia_intake_tagline'] ?? ''));
        $is_primary = !empty($_POST['oksia_agency_is_primary']) ? '1' : '0';
        $primary_color = sanitize_hex_color(wp_unslash($_POST['oksia_agency_primary_color'] ?? ''));
        $secondary_color = sanitize_hex_color(wp_unslash($_POST['oksia_agency_secondary_color'] ?? ''));
        $accent_color = sanitize_hex_color(wp_unslash($_POST['oksia_agency_accent_color'] ?? ''));
        $subscription_model = sanitize_key(wp_unslash($_POST['oksia_agency_subscription_model'] ?? 'automatic'));
        $main_user_id = absint($_POST['oksia_main_agency_user_id'] ?? 0);
        $manager_user_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($_POST['oksia_agency_manager_user_ids'] ?? array())))));
        $staff_user_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($_POST['oksia_agency_staff_user_ids'] ?? array())))));
        $previous_user_ids = $this->get_agency_user_ids($post_id);

        $manager_user_ids = array_values(array_diff($manager_user_ids, array($main_user_id)));
        $staff_user_ids = array_values(array_diff($staff_user_ids, array($main_user_id), $manager_user_ids));

        if ('' === $code) {
            $code = $this->get_agency_code($post_id);
        }

        if ('' === $code) {
            $code = $this->generate_next_agency_code();
        }

        $code = $this->ensure_unique_code($code, $post_id);
        update_post_meta($post_id, self::META_CODE, $code);
        update_post_meta($post_id, self::META_LEGAL_ENTITY, $legal_entity);
        update_post_meta($post_id, self::META_AGENCY_TYPE, $agency_type);
        update_post_meta($post_id, self::META_IS_PRIMARY, $is_primary);
        update_post_meta($post_id, 'oksia_authorize_name', $authorize_name);
        update_post_meta($post_id, 'oksia_agency_phone', $agency_phone);
        update_post_meta($post_id, 'oksia_agency_email', $agency_email);
        update_post_meta($post_id, 'oksia_agency_website', $agency_website);
        update_post_meta($post_id, 'oksia_agency_building', $agency_building);
        update_post_meta($post_id, 'oksia_agency_landmark', $agency_landmark);
        update_post_meta($post_id, 'oksia_agency_area', $agency_area);
        update_post_meta($post_id, 'oksia_agency_location', $agency_location);
        update_post_meta($post_id, 'oksia_agency_state', $agency_state);
        update_post_meta($post_id, 'oksia_agency_pincode', $agency_pincode);
        update_post_meta($post_id, 'oksia_agency_fb_page', $agency_fb_page);
        update_post_meta($post_id, 'oksia_agency_instagram', $agency_instagram);
        update_post_meta($post_id, 'oksia_agency_google', $agency_google);
        update_post_meta($post_id, 'oksia_hear_about_us', $hear_about_us);
        update_post_meta($post_id, 'oksia_iata_code', $iata_code);
        update_post_meta($post_id, 'oksia_billing_gst', $gst_number);
        update_post_meta($post_id, 'oksia_billing_company', $gst_name);
        update_post_meta($post_id, 'oksia_billing_email', $gst_email);
        update_post_meta($post_id, 'oksia_agency_logo_url', $agency_logo_url);
        update_post_meta($post_id, 'oksia_intake_tagline', $intake_tagline);
        if (!empty($primary_color)) {
            update_post_meta($post_id, self::META_PRIMARY_COLOR, $primary_color);
        } else {
            delete_post_meta($post_id, self::META_PRIMARY_COLOR);
        }
        if (!empty($secondary_color)) {
            update_post_meta($post_id, self::META_SECONDARY_COLOR, $secondary_color);
        } else {
            delete_post_meta($post_id, self::META_SECONDARY_COLOR);
        }
        if (!empty($accent_color)) {
            update_post_meta($post_id, self::META_ACCENT_COLOR, $accent_color);
        } else {
            delete_post_meta($post_id, self::META_ACCENT_COLOR);
        }

        if ('automatic' === $subscription_model || '' === $subscription_model) {
            delete_post_meta($post_id, self::META_SUBSCRIPTION_MODEL);
        } elseif (array_key_exists($subscription_model, self::get_subscription_models())) {
            update_post_meta($post_id, self::META_SUBSCRIPTION_MODEL, $subscription_model);
        } else {
            delete_post_meta($post_id, self::META_SUBSCRIPTION_MODEL);
        }

        update_post_meta($post_id, 'oksia_main_agency_user_id', $main_user_id);
        update_post_meta($post_id, 'oksia_agency_manager_user_ids', $manager_user_ids);
        update_post_meta($post_id, 'oksia_agency_staff_user_ids', $staff_user_ids);

        $option_sync_map = array(
            'oksia_authorize_name' => $authorize_name,
            'oksia_agency_phone' => $agency_phone,
            'oksia_agency_email' => $agency_email,
            'oksia_agency_website' => $agency_website,
            'oksia_agency_building' => $agency_building,
            'oksia_agency_landmark' => $agency_landmark,
            'oksia_agency_area' => $agency_area,
            'oksia_agency_location' => $agency_location,
            'oksia_agency_state' => $agency_state,
            'oksia_agency_pincode' => $agency_pincode,
            'oksia_agency_fb_page' => $agency_fb_page,
            'oksia_agency_instagram' => $agency_instagram,
            'oksia_agency_google' => $agency_google,
            'oksia_hear_about_us' => $hear_about_us,
            'oksia_iata_code' => $iata_code,
            'oksia_billing_gst' => $gst_number,
            'oksia_billing_company' => $gst_name,
            'oksia_billing_email' => $gst_email,
            'oksia_agency_logo_url' => $agency_logo_url,
            'oksia_intake_tagline' => $intake_tagline,
            'oksia_primary_color' => $primary_color,
            'oksia_secondary_color' => $secondary_color,
            'oksia_accent_color' => $accent_color,
            'oksia_main_agency_user_id' => $main_user_id,
            'oksia_agency_manager_user_ids' => $manager_user_ids,
            'oksia_agency_staff_user_ids' => $staff_user_ids,
            'oksia_agency_name' => $agency_name,
            'oksia_agency_legal_entity' => $legal_entity,
            'oksia_agency_type' => $agency_type,
        );

        foreach ($option_sync_map as $option_key => $option_value) {
            update_option($option_key, $option_value, false);
        }

        $this->sync_agency_user_assignments($post_id, $main_user_id, $manager_user_ids, $staff_user_ids, $previous_user_ids);

        if ('1' === $is_primary) {
            update_option(self::OPTION_PRIMARY_AGENCY_ID, (int) $post_id, false);
        } elseif ((int) get_option(self::OPTION_PRIMARY_AGENCY_ID, 0) === (int) $post_id) {
            delete_option(self::OPTION_PRIMARY_AGENCY_ID);
        }
    }

    public function maybe_seed_primary_agency() {
        $primary_id = absint(get_option(self::OPTION_PRIMARY_AGENCY_ID, 0));
        if ($primary_id && get_post($primary_id)) {
            return;
        }

        $existing = get_posts(array(
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'fields' => 'ids',
        ));

        if (!empty($existing)) {
            update_option(self::OPTION_PRIMARY_AGENCY_ID, (int) $existing[0], false);
            return;
        }

        $agency_name = trim((string) get_option('oksia_agency_name', 'OKSIA'));
        $post_id = wp_insert_post(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $agency_name,
        ), true);

        if (is_wp_error($post_id) || !$post_id) {
            return;
        }

        $code = self::CODE_PREFIX . '1';
        update_post_meta($post_id, self::META_CODE, $code);
        update_post_meta($post_id, self::META_LEGAL_ENTITY, 'Ekta Corporation');
        update_post_meta($post_id, self::META_IS_PRIMARY, '1');
        update_option(self::OPTION_PRIMARY_AGENCY_ID, (int) $post_id, false);
    }

    public function maybe_backfill_existing_records() {
        if ('1' === (string) get_option(self::OPTION_BACKFILL_DONE, '0')) {
            return;
        }

        $primary_id = absint(get_option(self::OPTION_PRIMARY_AGENCY_ID, 0));
        if (!$primary_id || !get_post($primary_id)) {
            return;
        }

        $quote_ids = get_posts(array(
            'post_type' => OKSIA_Post_Types::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_oksia_agency_id',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        ));

        foreach ($quote_ids as $quote_id) {
            update_post_meta($quote_id, '_oksia_agency_id', $primary_id);
            update_post_meta($quote_id, '_oksia_agency_code', $this->get_agency_code($primary_id));
        }

        $users = get_users(array(
            'fields' => array('ID'),
            'number' => -1,
        ));

        foreach ($users as $user) {
            $user_id = absint($user->ID ?? 0);
            if (!$user_id) {
                continue;
            }

            $user_data = get_userdata($user_id);
            if (!$user_data) {
                continue;
            }

            if ('' !== (string) get_user_meta($user_id, self::USER_META_AGENCY_ID, true)) {
                continue;
            }

            update_user_meta($user_id, self::USER_META_AGENCY_ID, $primary_id);
            update_user_meta($user_id, self::USER_META_AGENCY_CODE, $this->get_agency_code($primary_id));
        }

        update_option(self::OPTION_BACKFILL_DONE, '1', false);
    }

    public function maybe_sync_primary_agency_from_options() {
        $primary_id = absint(get_option(self::OPTION_PRIMARY_AGENCY_ID, 0));
        if (!$primary_id || !get_post($primary_id)) {
            return;
        }

        $option_map = array(
            'oksia_authorize_name' => 'oksia_authorize_name',
            'oksia_agency_phone' => 'oksia_agency_phone',
            'oksia_agency_email' => 'oksia_agency_email',
            'oksia_agency_website' => 'oksia_agency_website',
            'oksia_agency_fb_page' => 'oksia_agency_fb_page',
            'oksia_agency_instagram' => 'oksia_agency_instagram',
            'oksia_agency_google' => 'oksia_agency_google',
            'oksia_hear_about_us' => 'oksia_hear_about_us',
            'oksia_agency_building' => 'oksia_agency_building',
            'oksia_agency_landmark' => 'oksia_agency_landmark',
            'oksia_agency_area' => 'oksia_agency_area',
            'oksia_agency_location' => 'oksia_agency_location',
            'oksia_agency_state' => 'oksia_agency_state',
            'oksia_agency_pincode' => 'oksia_agency_pincode',
            'oksia_iata_code' => 'oksia_iata_code',
            'oksia_billing_gst' => 'oksia_billing_gst',
            'oksia_billing_company' => 'oksia_billing_company',
            'oksia_billing_email' => 'oksia_billing_email',
            'oksia_agency_logo_url' => 'oksia_agency_logo_url',
            'oksia_intake_tagline' => 'oksia_intake_tagline',
            'oksia_primary_color' => self::META_PRIMARY_COLOR,
            'oksia_secondary_color' => self::META_SECONDARY_COLOR,
            'oksia_accent_color' => self::META_ACCENT_COLOR,
        );

        foreach ($option_map as $option_key => $meta_key) {
            $value = get_option($option_key, '');
            if (is_array($value)) {
                $value = array_values(array_filter(array_map('absint', $value)));
                if (empty($value)) {
                    continue;
                }
            } else {
                $value = trim((string) $value);
                if ('' === $value) {
                    continue;
                }
            }

            if (in_array($option_key, array('oksia_primary_color', 'oksia_secondary_color', 'oksia_accent_color'), true)) {
                $value = $this->normalize_color_value($value, '');
                if ('' === $value) {
                    continue;
                }
            }

            update_post_meta($primary_id, $meta_key, $value);
        }

        $agency_name = trim((string) get_option('oksia_agency_name', ''));
        if ('' !== $agency_name) {
            wp_update_post(array(
                'ID' => $primary_id,
                'post_title' => $agency_name,
            ));
        }

        $agency_code = $this->sanitize_agency_code(get_option('oksia_agency_code', ''));
        if ('' !== $agency_code) {
            update_post_meta($primary_id, self::META_CODE, $agency_code);
        }

        $agency_type = sanitize_key((string) get_option('oksia_agency_type', ''));
        if ('' !== $agency_type) {
            update_post_meta($primary_id, self::META_AGENCY_TYPE, $agency_type);
        }

        $legal_entity = trim((string) get_option('oksia_agency_legal_entity', ''));
        if ('' !== $legal_entity) {
            update_post_meta($primary_id, self::META_LEGAL_ENTITY, $legal_entity);
        }

        $main_user_id = absint(get_option('oksia_main_agency_user_id', 0));
        $manager_user_ids = array_values(array_filter(array_map('absint', (array) get_option('oksia_agency_manager_user_ids', array()))));
        $staff_user_ids = array_values(array_filter(array_map('absint', (array) get_option('oksia_agency_staff_user_ids', array()))));
        if ($main_user_id > 0) {
            update_post_meta($primary_id, 'oksia_main_agency_user_id', $main_user_id);
        }
        update_post_meta($primary_id, 'oksia_agency_manager_user_ids', $manager_user_ids);
        update_post_meta($primary_id, 'oksia_agency_staff_user_ids', $staff_user_ids);
    }

    private function get_agency_field_value($agency_id, $meta_key, $option_key = '', $default = '') {
        $value = trim((string) get_post_meta($agency_id, $meta_key, true));
        if ('' === $value && '' !== $option_key) {
            $value = trim((string) get_option($option_key, ''));
        }
        if ('' === $value && '' !== $default) {
            $value = (string) $default;
        }

        return $value;
    }

    public function render_user_agency_field($user) {
        if (!is_user_logged_in()) {
            return;
        }

        $selected = absint(get_user_meta($user->ID, self::USER_META_AGENCY_ID, true));
        $agencies = $this->get_agency_options();
        ?>
        <h2><?php esc_html_e('Agency Assignment', 'oksia-smart-itinerary-agent'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="oksia_agency_id"><?php esc_html_e('Agency', 'oksia-smart-itinerary-agent'); ?></label></th>
                <td>
                    <select name="oksia_agency_id" id="oksia_agency_id">
                        <option value="0"><?php esc_html_e('Select agency', 'oksia-smart-itinerary-agent'); ?></option>
                        <?php foreach ($agencies as $agency_id => $label) : ?>
                            <option value="<?php echo esc_attr((string) $agency_id); ?>" <?php selected($selected, $agency_id); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Assign this user to one agency. Login will check agency code + username + password.', 'oksia-smart-itinerary-agent'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_agency_field($user_id) {
        if (!is_user_logged_in()) {
            return;
        }

        $agency_id = absint($_POST['oksia_agency_id'] ?? 0);
        if (!$agency_id) {
            delete_user_meta($user_id, self::USER_META_AGENCY_ID);
            delete_user_meta($user_id, self::USER_META_AGENCY_CODE);
            return;
        }

        $code = $this->get_agency_code($agency_id);
        if ('' === $code) {
            return;
        }

        update_user_meta($user_id, self::USER_META_AGENCY_ID, $agency_id);
        update_user_meta($user_id, self::USER_META_AGENCY_CODE, $code);
    }

    public function assign_user_to_agency($user_id, $agency_id, $role_slug = '') {
        $user_id = absint($user_id);
        $agency_id = absint($agency_id);
        if (!$user_id || !$agency_id) {
            return false;
        }

        $code = $this->get_agency_code($agency_id);
        if ('' === $code) {
            return false;
        }

        update_user_meta($user_id, self::USER_META_AGENCY_ID, $agency_id);
        update_user_meta($user_id, self::USER_META_AGENCY_CODE, $code);

        $role_slug = sanitize_key((string) $role_slug);
        if ('' !== $role_slug && in_array($role_slug, self::get_front_end_role_slugs(), true)) {
            $user = get_user_by('id', $user_id);
            if ($user instanceof WP_User) {
                $user->set_role($role_slug);
            }
        }

        return true;
    }

    public function sync_agency_user_assignments($agency_id, $main_user_id, array $manager_user_ids, array $staff_user_ids, array $previous_user_ids = array()) {
        $agency_id = absint($agency_id);
        if (!$agency_id) {
            return false;
        }

        $desired = array_values(array_unique(array_filter(array_merge(array(absint($main_user_id)), array_map('absint', $manager_user_ids), array_map('absint', $staff_user_ids)))));
        $current = !empty($previous_user_ids) ? array_values(array_unique(array_filter(array_map('absint', $previous_user_ids)))) : $this->get_agency_user_ids($agency_id);

        foreach ($current as $user_id) {
            if (in_array($user_id, $desired, true)) {
                continue;
            }

            delete_user_meta($user_id, self::USER_META_AGENCY_ID);
            delete_user_meta($user_id, self::USER_META_AGENCY_CODE);
        }

        if ($main_user_id > 0) {
            $this->assign_user_to_agency($main_user_id, $agency_id, 'oksia_agency');
        }

        foreach ($manager_user_ids as $user_id) {
            if ($user_id > 0) {
                $this->assign_user_to_agency($user_id, $agency_id, 'oksia_manager');
            }
        }

        foreach ($staff_user_ids as $user_id) {
            if ($user_id > 0) {
                $this->assign_user_to_agency($user_id, $agency_id, 'oksia_employee');
            }
        }

        return true;
    }

    public function set_agency_trial_period($agency_id, $days = self::DEFAULT_TRIAL_DAYS, $registered_at = '') {
        $agency_id = absint($agency_id);
        if (!$agency_id) {
            return false;
        }

        $days = max(1, absint($days));
        $registered_at = trim((string) $registered_at);
        if ('' === $registered_at) {
            $registered_at = current_time('mysql');
        }

        $registered_ts = strtotime($registered_at);
        if (!$registered_ts) {
            $registered_ts = current_time('timestamp');
            $registered_at = current_time('mysql');
        }

        $expires_ts = $registered_ts + ($days * DAY_IN_SECONDS);

        update_post_meta($agency_id, self::META_REGISTERED_AT, $registered_at);
        update_post_meta($agency_id, self::META_TRIAL_DAYS, $days);
        update_post_meta($agency_id, self::META_TRIAL_EXPIRES_AT, gmdate('Y-m-d H:i:s', $expires_ts));

        return true;
    }

    public function get_agency_trial_info($agency_id) {
        $agency_id = absint($agency_id);
        if (!$agency_id) {
            return array(
                'registered_at' => '',
                'trial_days' => self::DEFAULT_TRIAL_DAYS,
                'trial_expires_at' => '',
                'trial_expires_at_ts' => 0,
                'days_left' => null,
                'status' => 'unknown',
                'is_expired' => false,
            );
        }

        $registered_at = trim((string) get_post_meta($agency_id, self::META_REGISTERED_AT, true));
        if ('' === $registered_at) {
            $post = get_post($agency_id);
            if ($post instanceof WP_Post && !empty($post->post_date)) {
                $registered_at = get_date_from_gmt($post->post_date_gmt ?: $post->post_date, 'Y-m-d H:i:s');
            }
        }

        $trial_days = absint(get_post_meta($agency_id, self::META_TRIAL_DAYS, true));
        if ($trial_days < 1) {
            $trial_days = self::DEFAULT_TRIAL_DAYS;
        }

        $trial_expires_at = trim((string) get_post_meta($agency_id, self::META_TRIAL_EXPIRES_AT, true));
        $trial_expires_at_ts = $trial_expires_at ? strtotime($trial_expires_at) : 0;
        if (!$trial_expires_at_ts && '' !== $registered_at) {
            $registered_ts = strtotime($registered_at);
            if ($registered_ts) {
                $trial_expires_at_ts = $registered_ts + ($trial_days * DAY_IN_SECONDS);
                $trial_expires_at = gmdate('Y-m-d H:i:s', $trial_expires_at_ts);
            }
        }

        $now = current_time('timestamp');
        $days_left = null;
        $is_expired = false;
        $status = 'unknown';

        if ($trial_expires_at_ts > 0) {
            $diff = $trial_expires_at_ts - $now;
            $days_left = (int) floor($diff / DAY_IN_SECONDS);
            $is_expired = $diff < 0;
            if ($is_expired) {
                $status = 'expired';
            } elseif ($days_left <= 3) {
                $status = 'expiring';
            } else {
                $status = 'active';
            }
        }

        return array(
            'registered_at' => $registered_at,
            'trial_days' => $trial_days,
            'trial_expires_at' => $trial_expires_at,
            'trial_expires_at_ts' => $trial_expires_at_ts,
            'days_left' => $days_left,
            'status' => $status,
            'is_expired' => $is_expired,
        );
    }

    public function is_agency_trial_expired($agency_id) {
        $info = $this->get_agency_trial_info($agency_id);
        return !empty($info['is_expired']);
    }

    public function get_agency_user_ids($agency_id) {
        $agency_id = absint($agency_id);
        if (!$agency_id) {
            return array();
        }

        $user_ids = array(
            absint(get_post_meta($agency_id, 'oksia_main_agency_user_id', true)),
        );

        $manager_ids = (array) get_post_meta($agency_id, 'oksia_agency_manager_user_ids', true);
        $staff_ids = (array) get_post_meta($agency_id, 'oksia_agency_staff_user_ids', true);

        $user_ids = array_merge($user_ids, array_map('absint', $manager_ids), array_map('absint', $staff_ids));
        $user_ids = array_values(array_unique(array_filter($user_ids)));

        return $user_ids;
    }

    public function get_agency_subscription_tier($agency_id) {
        $agency_id = absint($agency_id);
        $models = self::get_subscription_models();
        $model_key = $this->get_agency_subscription_model_key($agency_id);
        if ('' !== $model_key && isset($models[$model_key])) {
            return $models[$model_key];
        }

        $user_count = count($this->get_agency_user_ids($agency_id));
        if ($user_count <= 1) {
            return $models['economy'];
        }

        if ($user_count <= 4) {
            return $models['premium'];
        }

        return $models['business'];
    }

    public function get_agency_subscription_model_key($agency_id) {
        $agency_id = absint($agency_id);
        if (!$agency_id) {
            return '';
        }

        $model_key = sanitize_key((string) get_post_meta($agency_id, self::META_SUBSCRIPTION_MODEL, true));
        return array_key_exists($model_key, self::get_subscription_models()) ? $model_key : '';
    }

    public function get_agency_quote_count($agency_id) {
        $agency_id = absint($agency_id);
        if (!$agency_id) {
            return 0;
        }

        $quote_ids = get_posts(array(
            'post_type' => OKSIA_Post_Types::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_oksia_agency_id',
                    'value' => $agency_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
        ));

        return count($quote_ids);
    }

    public function get_agency_dashboard_summary() {
        $agencies = get_posts(array(
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $quote_ids = get_posts(array(
            'post_type' => OKSIA_Post_Types::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'fields' => 'ids',
        ));

        $ongoing_trials = 0;
        $upcoming_renewals = 0;
        $renewal_items = array();

        foreach ($agencies as $agency_id) {
            $agency_id = absint($agency_id);
            if (!$agency_id) {
                continue;
            }

            $trial = $this->get_agency_trial_info($agency_id);
            if (in_array((string) ($trial['status'] ?? ''), array('active', 'expiring'), true)) {
                $ongoing_trials++;
            }

            if (null !== $trial['days_left'] && $trial['days_left'] >= 0 && $trial['days_left'] <= 7) {
                $upcoming_renewals++;
                $renewal_items[] = array(
                    'id' => $agency_id,
                    'name' => get_the_title($agency_id),
                    'code' => $this->get_agency_code($agency_id),
                    'renewal_date' => $trial['trial_expires_at'] !== '' ? mysql2date(get_option('date_format'), $trial['trial_expires_at']) : '',
                    'days_left' => max(0, (int) $trial['days_left']),
                );
            }
        }

        usort($renewal_items, function ($left, $right) {
            return strcmp((string) ($left['renewal_date'] ?? ''), (string) ($right['renewal_date'] ?? ''));
        });

        return array(
            'total_agencies' => count($agencies),
            'ongoing_trials' => $ongoing_trials,
            'upcoming_renewals' => $upcoming_renewals,
            'total_quotes' => count($quote_ids),
            'renewal_items' => $renewal_items,
        );
    }

    public function render_agency_list_page() {
        if (!is_user_logged_in()) {
            return;
        }

        $agencies = get_posts(array(
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Agency List', 'oksia-smart-itinerary-agent'); ?></h1>
            <p class="description"><?php esc_html_e('Review and manage every signed-up agency, including subscription tier, activation date, and quote activity.', 'oksia-smart-itinerary-agent'); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . self::POST_TYPE)); ?>"><?php esc_html_e('Add New Agency', 'oksia-smart-itinerary-agent'); ?></a>
            </p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Agency Code', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Name', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Owner', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Activated On', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Total Quotes Generated', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Subscription Tier', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Actions', 'oksia-smart-itinerary-agent'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agencies)) : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e('No agencies found yet.', 'oksia-smart-itinerary-agent'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($agencies as $agency) : ?>
                            <?php
                            $agency_id = (int) $agency->ID;
                            $code = $this->get_agency_code($agency_id);
                            $owner_id = absint(get_post_meta($agency_id, 'oksia_main_agency_user_id', true));
                            $owner_label = $owner_id > 0 ? $this->format_user_label($owner_id) : __('Unassigned', 'oksia-smart-itinerary-agent');
                            $trial = $this->get_agency_trial_info($agency_id);
                            $activated_on = $trial['registered_at'] !== '' ? mysql2date(get_option('date_format'), $trial['registered_at']) : mysql2date(get_option('date_format'), $agency->post_date);
                            $quote_count = $this->get_agency_quote_count($agency_id);
                            $tier = $this->get_agency_subscription_tier($agency_id);
                            $edit_link = admin_url('post.php?post=' . $agency_id . '&action=edit');
                            $trash_link = get_delete_post_link($agency_id, '', true);
                            ?>
                            <tr>
                                <td>
                                    <code><?php echo esc_html($code !== '' ? $code : __('Pending', 'oksia-smart-itinerary-agent')); ?></code>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($agency->post_title); ?></strong>
                                    <div class="description"><?php echo esc_html(sprintf(__('Agency ID: %d', 'oksia-smart-itinerary-agent'), $agency_id)); ?></div>
                                </td>
                                <td><?php echo esc_html($owner_label); ?></td>
                                <td><?php echo esc_html($activated_on); ?></td>
                                <td><?php echo esc_html(number_format_i18n($quote_count)); ?></td>
                                <td>
                                    <strong><?php echo esc_html($tier['label']); ?></strong>
                                    <div class="description"><?php echo esc_html(sprintf('%s · %s', $tier['code'], $tier['range'])); ?></div>
                                </td>
                                <td>
                                    <?php if ($edit_link) : ?>
                                        <a href="<?php echo esc_url($edit_link); ?>"><?php esc_html_e('Edit', 'oksia-smart-itinerary-agent'); ?></a>
                                    <?php endif; ?>
                                    <?php if ($trash_link) : ?>
                                        <?php if ($edit_link) : ?> | <?php endif; ?>
                                        <a href="<?php echo esc_url($trash_link); ?>" onclick="return confirm('<?php echo esc_js(__('Move this agency to the trash?', 'oksia-smart-itinerary-agent')); ?>');"><?php esc_html_e('Trash', 'oksia-smart-itinerary-agent'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function validate_agency_login($user, $password) {
        return $user;
    }

    public function get_agency_options() {
        $posts = get_posts(array(
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'orderby' => 'date',
            'order' => 'ASC',
        ));

        $options = array();
        foreach ($posts as $post) {
            $options[$post->ID] = $this->format_agency_label($post);
        }

        return $options;
    }

    public function get_agency_by_code($code) {
        $code = $this->sanitize_agency_code($code);
        if ('' === $code) {
            return null;
        }

        $posts = get_posts(array(
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'meta_key' => self::META_CODE,
            'meta_value' => $code,
        ));

        return !empty($posts) ? $posts[0] : null;
    }

    public function get_agency_code($agency_id) {
        $agency_id = absint($agency_id);
        if (!$agency_id) {
            return '';
        }

        $code = trim((string) get_post_meta($agency_id, self::META_CODE, true));
        if ('' !== $code) {
            return $this->sanitize_agency_code($code);
        }

        if ((int) get_option(self::OPTION_PRIMARY_AGENCY_ID, 0) === $agency_id) {
            return self::CODE_PREFIX . '1';
        }

        return '';
    }

    public function get_current_user_agency_id($user_id = 0) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();
        return absint(get_user_meta($user_id, self::USER_META_AGENCY_ID, true));
    }

    public function get_current_user_agency_code($user_id = 0) {
        $agency_id = $this->get_current_user_agency_id($user_id);
        if (!$agency_id) {
            return '';
        }

        return $this->get_agency_code($agency_id);
    }

    public function is_platform_admin($user = null) {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$user instanceof WP_User) {
            return false;
        }

        return is_user_logged_in();
    }

    public function format_agency_label($post) {
        $post = get_post($post);
        if (!$post) {
            return '';
        }

        $code = $this->get_agency_code($post->ID);
        return sprintf('%s%s', $post->post_title, $code ? ' (' . $code . ')' : '');
    }

    public function format_user_label($user_id) {
        $user_id = absint($user_id);
        if (!$user_id) {
            return '';
        }

        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return '';
        }

        $label = trim((string) $user->display_name);
        if ('' !== (string) $user->user_email) {
            $label .= ' (' . $user->user_email . ')';
        }

        return $label;
    }

    public function upsert_agency_from_registration(array $data) {
        $agency_name = trim((string) ($data['agency_name'] ?? ''));
        $agency_code = $this->sanitize_agency_code($data['agency_code'] ?? '');
        if ('' === $agency_code) {
            $agency_code = $this->generate_next_agency_code();
        }

        $legal_entity = trim((string) ($data['legal_entity'] ?? ''));
        if ('' === $legal_entity) {
            $legal_entity = $agency_name;
        }

        $existing = $this->get_agency_by_code($agency_code);
        $agency_id = $existing instanceof WP_Post ? (int) $existing->ID : 0;
        $is_new = false;

        if ($agency_id > 0) {
            wp_update_post(array(
                'ID' => $agency_id,
                'post_title' => '' !== $agency_name ? $agency_name : $existing->post_title,
            ));
        } else {
            $agency_id = wp_insert_post(array(
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => '' !== $agency_name ? $agency_name : $agency_code,
            ), true);
            $is_new = true;
        }

        if (is_wp_error($agency_id) || !$agency_id) {
            return is_wp_error($agency_id) ? $agency_id : new WP_Error('oksia_agency_create_failed', __('Could not save the agency signup.', 'oksia-smart-itinerary-agent'));
        }

        $agency_id = absint($agency_id);
        $registered_at = trim((string) get_post_meta($agency_id, self::META_REGISTERED_AT, true));
        if ('' === $registered_at) {
            $registered_at = current_time('mysql');
        }

        $defaults = array(
            'oksia_authorize_name' => '',
            'oksia_agency_phone' => '',
            'oksia_agency_email' => '',
            'oksia_agency_website' => '',
            'oksia_agency_fb_page' => '',
            'oksia_agency_instagram' => '',
            'oksia_agency_google' => '',
            'oksia_hear_about_us' => '',
            'oksia_agency_building' => '',
            'oksia_agency_landmark' => '',
            'oksia_agency_area' => '',
            'oksia_agency_location' => '',
            'oksia_agency_state' => '',
            'oksia_agency_pincode' => '',
            'oksia_iata_code' => '',
            'oksia_billing_gst' => '',
            'oksia_billing_company' => '',
            'oksia_billing_email' => '',
            'oksia_agency_logo_url' => '',
            'oksia_disclaimer_text' => '',
            'oksia_intake_tagline' => '',
            'oksia_primary_color' => '',
            'oksia_secondary_color' => '',
            'oksia_accent_color' => '',
            'oksia_main_agency_user_id' => 0,
            'oksia_agency_manager_user_ids' => array(),
            'oksia_agency_staff_user_ids' => array(),
            'trial_days' => self::DEFAULT_TRIAL_DAYS,
        );
        $meta_payload = array_merge($defaults, $data);

        update_post_meta($agency_id, self::META_CODE, $agency_code);
        update_post_meta($agency_id, self::META_LEGAL_ENTITY, $legal_entity);
        update_post_meta($agency_id, self::META_REGISTERED_AT, $registered_at);
        $trial_days = absint($meta_payload['trial_days'] ?? self::DEFAULT_TRIAL_DAYS);
        if ($trial_days < 1) {
            $trial_days = self::DEFAULT_TRIAL_DAYS;
        }
        $this->set_agency_trial_period($agency_id, $trial_days, $registered_at);

        $meta_map = array(
            'oksia_authorize_name' => 'oksia_authorize_name',
            'oksia_agency_phone' => 'oksia_agency_phone',
            'oksia_agency_email' => 'oksia_agency_email',
            'oksia_agency_website' => 'oksia_agency_website',
            'oksia_agency_fb_page' => 'oksia_agency_fb_page',
            'oksia_agency_instagram' => 'oksia_agency_instagram',
            'oksia_agency_google' => 'oksia_agency_google',
            'oksia_hear_about_us' => 'oksia_hear_about_us',
            'oksia_agency_building' => 'oksia_agency_building',
            'oksia_agency_landmark' => 'oksia_agency_landmark',
            'oksia_agency_area' => 'oksia_agency_area',
            'oksia_agency_location' => 'oksia_agency_location',
            'oksia_agency_state' => 'oksia_agency_state',
            'oksia_agency_pincode' => 'oksia_agency_pincode',
            'oksia_iata_code' => 'oksia_iata_code',
            'oksia_billing_gst' => 'oksia_billing_gst',
            'oksia_billing_company' => 'oksia_billing_company',
            'oksia_billing_email' => 'oksia_billing_email',
            'oksia_agency_logo_url' => 'oksia_agency_logo_url',
            'oksia_disclaimer_text' => 'oksia_disclaimer_text',
            'oksia_intake_tagline' => 'oksia_intake_tagline',
            'oksia_primary_color' => self::META_PRIMARY_COLOR,
            'oksia_secondary_color' => self::META_SECONDARY_COLOR,
            'oksia_accent_color' => self::META_ACCENT_COLOR,
        );

        foreach ($meta_map as $source_key => $target_key) {
            $value = $meta_payload[$source_key] ?? '';
            if (in_array($source_key, array('oksia_primary_color', 'oksia_secondary_color', 'oksia_accent_color'), true)) {
                $value = $this->normalize_color_value($value, '');
            }
            if ('' !== (string) $value) {
                update_post_meta($agency_id, $target_key, $value);
            } else {
                delete_post_meta($agency_id, $target_key);
            }
        }

        $main_user_id = absint($meta_payload['oksia_main_agency_user_id'] ?? 0);
        $manager_user_ids = array_map('absint', (array) ($meta_payload['oksia_agency_manager_user_ids'] ?? array()));
        $staff_user_ids = array_map('absint', (array) ($meta_payload['oksia_agency_staff_user_ids'] ?? array()));

        update_post_meta($agency_id, 'oksia_main_agency_user_id', $main_user_id);
        update_post_meta($agency_id, 'oksia_agency_manager_user_ids', array_values(array_filter($manager_user_ids)));
        update_post_meta($agency_id, 'oksia_agency_staff_user_ids', array_values(array_filter($staff_user_ids)));

        if ($main_user_id > 0) {
            $this->assign_user_to_agency($main_user_id, $agency_id, 'oksia_agency');
        }
        foreach ($manager_user_ids as $user_id) {
            if ($user_id > 0) {
                $this->assign_user_to_agency($user_id, $agency_id, 'oksia_manager');
            }
        }
        foreach ($staff_user_ids as $user_id) {
            if ($user_id > 0) {
                $this->assign_user_to_agency($user_id, $agency_id, 'oksia_employee');
            }
        }

        if ($is_new && !get_option(self::OPTION_PRIMARY_AGENCY_ID, 0)) {
            update_option(self::OPTION_PRIMARY_AGENCY_ID, $agency_id, false);
            update_post_meta($agency_id, self::META_IS_PRIMARY, '1');
        }

        return $agency_id;
    }

    public function get_agency_colors($agency_id = 0) {
        $agency_id = absint($agency_id);
        $colors = array(
            'primary' => self::FALLBACK_PRIMARY_COLOR,
            'secondary' => self::FALLBACK_SECONDARY_COLOR,
            'accent' => self::FALLBACK_ACCENT_COLOR,
        );

        $colors['primary'] = $this->normalize_color_value(get_option('oksia_primary_color', ''), $colors['primary']);
        $colors['secondary'] = $this->normalize_color_value(get_option('oksia_secondary_color', ''), $colors['secondary']);
        $colors['accent'] = $this->normalize_color_value(get_option('oksia_accent_color', ''), $colors['accent']);

        if ($agency_id > 0) {
            $colors['primary'] = $this->normalize_color_value(get_post_meta($agency_id, self::META_PRIMARY_COLOR, true), $colors['primary']);
            $colors['secondary'] = $this->normalize_color_value(get_post_meta($agency_id, self::META_SECONDARY_COLOR, true), $colors['secondary']);
            $colors['accent'] = $this->normalize_color_value(get_post_meta($agency_id, self::META_ACCENT_COLOR, true), $colors['accent']);
        }

        return $colors;
    }

    public function get_current_user_agency_colors($user_id = 0) {
        $agency_id = $this->get_current_user_agency_id($user_id);
        return $this->get_agency_colors($agency_id);
    }

    public function sanitize_agency_code($value) {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/[^A-Z0-9]+/', '', $value);
        return is_string($value) ? $value : '';
    }

    public function generate_next_agency_code() {
        $posts = get_posts(array(
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'fields' => 'ids',
        ));

        $highest = 1;
        foreach ($posts as $agency_id) {
            $code = $this->sanitize_agency_code(get_post_meta($agency_id, self::META_CODE, true));
            if (preg_match('/^' . preg_quote(self::CODE_PREFIX, '/') . '(\d+)$/', $code, $matches)) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return self::CODE_PREFIX . strval($highest + 1);
    }

    private function normalize_color_value($value, $fallback) {
        $value = sanitize_hex_color((string) $value);
        if (empty($value)) {
            return $fallback;
        }

        return $value;
    }

    private function ensure_unique_code($code, $exclude_post_id = 0) {
        $code = $this->sanitize_agency_code($code);
        if ('' === $code) {
            $code = $this->generate_next_agency_code();
        }

        $existing = $this->get_agency_by_code($code);
        if (!$existing || (int) $existing->ID === (int) $exclude_post_id) {
            return $code;
        }

        $suffix = 2;
        do {
            $candidate = self::CODE_PREFIX . $suffix;
            $existing = $this->get_agency_by_code($candidate);
            $suffix++;
        } while ($existing && (int) $existing->ID !== (int) $exclude_post_id);

        return $candidate;
    }
}
