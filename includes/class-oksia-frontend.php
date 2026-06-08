<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_Frontend {
    public function __construct() {
        add_shortcode('oksia_itinerary', array($this, 'render_shortcode'));
        add_shortcode('oksia_itineraries', array($this, 'render_listing_shortcode'));
        add_filter('the_content', array($this, 'append_brochure_view'));
        add_action('pre_get_posts', array($this, 'allow_quote_share_query'), 1);
        add_action('template_redirect', array($this, 'maybe_validate_quote_share_access'), 1);
        add_action('template_redirect', array($this, 'maybe_handle_frontend_quote_action'));
        add_action('template_redirect', array($this, 'maybe_download_pdf'));
        add_action('template_redirect', array($this, 'maybe_render_standalone_quote_view'), 99);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function allow_quote_share_query($query) {
        if (is_admin() || !$query instanceof WP_Query || !$query->is_main_query()) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['oksia_quote_key'] ?? ''));
        if ('' === $token) {
            return;
        }

        $post_type = (string) $query->get('post_type');
        if ('' === $post_type && isset($_GET['post_type'])) {
            $post_type = sanitize_key(wp_unslash($_GET['post_type']));
        }
        if (OKSIA_Post_Types::POST_TYPE !== $post_type) {
            return;
        }

        $post_id = absint($query->get('p'));
        if (!$post_id && isset($_GET['p'])) {
            $post_id = absint($_GET['p']);
        }
        if (!$post_id) {
            return;
        }

        $query->set('post_type', OKSIA_Post_Types::POST_TYPE);
        $query->set('p', $post_id);
        $query->set('post_status', array('publish', 'draft', 'pending', 'private'));
    }

    public function enqueue_assets() {
        wp_enqueue_style('oksia-frontend', OKSIA_URL . 'assets/css/admin.css', array(), OKSIA_VERSION);
    }

    public function maybe_validate_quote_share_access() {
        if (!is_singular(OKSIA_Post_Types::POST_TYPE)) {
            return;
        }

        $post_id = absint(get_queried_object_id());
        if (!$post_id) {
            return;
        }

        if (is_user_logged_in() && current_user_can('edit_post', $post_id)) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['oksia_quote_key'] ?? ''));
        if ('' === $token) {
            return;
        }

        if (!class_exists('OKSIA_Admin') || !OKSIA_Admin::is_valid_quote_share_key($post_id, $token)) {
            wp_die(
                esc_html__('This quote link has expired or is unavailable.', 'oksia-smart-itinerary-agent'),
                esc_html__('Quote link expired', 'oksia-smart-itinerary-agent'),
                array('response' => 403)
            );
        }
    }

    private function normalize_day_title($title, $day_number) {
        $title = trim((string) $title);
        $title = preg_replace('/^\s*Day\s*\d+\s*[:\-â€“]\s*/i', '', $title);
        $title = preg_replace('/^\s*Day\s*\d+\s*/i', '', $title);
        if ('' === $title) {
            return sprintf(__('Day %s', 'oksia-smart-itinerary-agent'), $day_number);
        }

        return $title;
    }

private function get_brand_logo_src($agency_id = 0) {
    $agency_id = absint($agency_id);
    $logo = '';
    if ($agency_id > 0) {
        $logo = trim((string) get_post_meta($agency_id, 'oksia_agency_logo_url', true));
        if ('' === $logo) {
            $primary_id = absint(get_option('oksia_primary_agency_post_id', 0));
            if ($primary_id && $primary_id !== $agency_id) {
                $logo = trim((string) get_post_meta($primary_id, 'oksia_agency_logo_url', true));
            }
        }
    }
    if ('' === $logo) {
        $logo = trim((string) get_option('oksia_agency_logo_url', ''));
    }
    $resolved_logo = '';

    if ('' !== $logo) {
        $attachment_id = is_numeric($logo) ? absint($logo) : attachment_url_to_postid($logo);
        if ($attachment_id) {
            $attachment_url = wp_get_attachment_url($attachment_id);
            if ($attachment_url) {
                $resolved_logo = $attachment_url;
            }
        }

        if ('' === $resolved_logo && filter_var($logo, FILTER_VALIDATE_URL)) {
            $resolved_logo = $logo;
        } elseif ('' === $resolved_logo) {
            $uploads = wp_upload_dir();
            if (!empty($uploads['basedir']) && !empty($uploads['baseurl'])) {
                $normalized_base = wp_normalize_path($uploads['basedir']);
                $normalized_logo = wp_normalize_path($logo);
                if (0 === strpos($normalized_logo, $normalized_base)) {
                    $relative = ltrim(substr($normalized_logo, strlen($normalized_base)), '/');
                    $resolved_logo = trailingslashit($uploads['baseurl']) . str_replace('\\', '/', $relative);
                }
            }
        }
    }

    return $resolved_logo;
}

/**
 * Convert logo URL to base64 data URL for PDF rendering
 */
private function get_logo_as_data_url($logo_url) {
    if (!$logo_url) {
        return '';
    }

    try {
        // Method 1: Try media library first
        $attachment_id = attachment_url_to_postid($logo_url);
        if ($attachment_id) {
            $file = get_attached_file($attachment_id);
            if ($file && file_exists($file)) {
                $mime = 'image/png';
                if (function_exists('mime_content_type')) {
                    $mime = mime_content_type($file);
                } else {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $mime_map = array(
                        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png', 'gif' => 'image/gif',
                        'svg' => 'image/svg+xml', 'webp' => 'image/webp',
                    );
                    $mime = $mime_map[$ext] ?? 'image/png';
                }
                $data = file_get_contents($file);
                if ($data !== false) {
                    return 'data:' . $mime . ';base64,' . base64_encode($data);
                }
            }
        }

        // Method 2: Convert URL to local file path directly
        $uploads = wp_upload_dir();
        $upload_base_url = trailingslashit($uploads['baseurl']);
        $upload_base_dir = trailingslashit($uploads['basedir']);

        if ('' !== $upload_base_url && 0 === strpos($logo_url, $upload_base_url)) {
            $relative = substr($logo_url, strlen($upload_base_url));
            $file = $upload_base_dir . $relative;
            $file = wp_normalize_path($file);

            if (file_exists($file)) {
                $mime = 'image/png';
                if (function_exists('mime_content_type')) {
                    $mime = mime_content_type($file);
                } else {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $mime_map = array(
                        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png', 'gif' => 'image/gif',
                        'svg' => 'image/svg+xml', 'webp' => 'image/webp',
                    );
                    $mime = $mime_map[$ext] ?? 'image/png';
                }
                $data = file_get_contents($file);
                if ($data !== false) {
                    return 'data:' . $mime . ';base64,' . base64_encode($data);
                }
            }
        }

        // Method 3: Try home URL to path conversion
        $home_url = trailingslashit(home_url());
        $abspath = trailingslashit(ABSPATH);
        if (0 === strpos($logo_url, $home_url)) {
            $relative = substr($logo_url, strlen($home_url));
            $file = wp_normalize_path($abspath . $relative);
            if (file_exists($file)) {
                $mime = 'image/png';
                if (function_exists('mime_content_type')) {
                    $mime = mime_content_type($file);
                } else {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $mime_map = array(
                        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                        'png' => 'image/png', 'gif' => 'image/gif',
                        'svg' => 'image/svg+xml', 'webp' => 'image/webp',
                    );
                    $mime = $mime_map[$ext] ?? 'image/png';
                }
                $data = file_get_contents($file);
                if ($data !== false) {
                    return 'data:' . $mime . ';base64,' . base64_encode($data);
                }
            }
        }

    } catch (Exception $e) {
        return '';
    }

    return '';
}
private function remote_image_to_data_uri($url) {
    // ... rest of the method
}

private function fallback_logo_data_uri() {
    // ... rest of the method
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

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts);
        $post_id = absint($atts['id']);
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        if (OKSIA_Post_Types::POST_TYPE !== get_post_type($post_id)) {
            return '';
        }
        return $this->get_brochure_markup($post_id);
    }

    public function render_listing_shortcode($atts) {
        $atts = shortcode_atts(array('limit' => 6), $atts);
        $query = new WP_Query(
            array(
                'post_type' => OKSIA_Post_Types::POST_TYPE,
                'posts_per_page' => absint($atts['limit']),
                'post_status' => 'publish',
            )
        );
        if (!$query->have_posts()) {
            return '<p>' . esc_html__('No smart itineraries found.', 'oksia-smart-itinerary-agent') . '</p>';
        }

        ob_start();
        echo '<div class="oksia-listing-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            $trip = (array) get_post_meta(get_the_ID(), '_oksia_trip_overview', true);
            echo '<article class="oksia-listing-card">';
            echo '<h3><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
            echo '<p><strong>' . esc_html__('Destination:', 'oksia-smart-itinerary-agent') . '</strong> ' . esc_html($trip['destination'] ?? '') . '</p>';
            echo '<p><strong>' . esc_html__('Dates:', 'oksia-smart-itinerary-agent') . '</strong> ' . esc_html(trim(($trip['start_date'] ?? '') . ' - ' . ($trip['end_date'] ?? ''))) . '</p>';
            echo '</article>';
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    public function append_brochure_view($content) {
        if (!is_singular(OKSIA_Post_Types::POST_TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        return $content . $this->get_brochure_markup(get_the_ID(), true);
    }

    public function maybe_render_standalone_quote_view() {
        if (is_admin() || !is_singular(OKSIA_Post_Types::POST_TYPE)) {
            return;
        }

        if (!empty($_GET['oksia_view_pdf']) || !empty($_GET['oksia_download_pdf'])) {
            return;
        }

        $post_id = absint(get_queried_object_id());
        if (!$post_id || OKSIA_Post_Types::POST_TYPE !== get_post_type($post_id)) {
            return;
        }

        $workflow_mode = sanitize_key((string) ($_GET['oksia_quote_workflow'] ?? ''));
        $template_preview_requested = !empty($_GET['oksia_quote_preview']);
        $current_stage = $this->get_quote_stage($post_id);

        if ($template_preview_requested) {
            $this->render_quote_template_preview_document($post_id);
            exit;
        }

        if ('confirmed' === $workflow_mode && 'cancelled' !== $current_stage) {
            $this->render_quote_confirmation_workspace($post_id);
            exit;
        }

        $title = wp_strip_all_tags(get_the_title($post_id));
        $quote_markup = $this->build_quote_template_document($post_id, false);
        if ('' === trim((string) $quote_markup)) {
            return;
        }

        status_header(200);
        nocache_headers();
        echo $quote_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private function render_quote_template_preview_document($post_id) {
        status_header(200);
        nocache_headers();
        header('Content-Type: text/html; charset=' . (get_bloginfo('charset') ?: 'UTF-8'));

        $template_key = sanitize_key((string) ($_GET['oksia_quote_template'] ?? ''));
        if ('' === $template_key && class_exists('OKSIA_Quote_Templates')) {
            $template_key = OKSIA_Quote_Templates::get_selected_template_key($post_id);
        }
        if (!class_exists('OKSIA_Quote_Templates')) {
            $template_key = 'default';
        } else {
            $template_key = OKSIA_Quote_Templates::normalize_template_key($template_key);
        }

        $markup = class_exists('OKSIA_Quote_Templates') ? OKSIA_Quote_Templates::get_template_markup($template_key) : '';
        if ('' === trim((string) $markup)) {
            $markup = '<!doctype html><html><head><meta charset="' . esc_attr(get_bloginfo('charset') ?: 'UTF-8') . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html__('Template Preview', 'oksia-smart-itinerary-agent') . '</title><style>html,body{margin:0;padding:0;background:#f4f7fc;font-family:system-ui,sans-serif} .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}.card{background:#fff;border:1px solid #dbe6f2;border-radius:18px;padding:28px;max-width:720px;width:100%;text-align:center;box-shadow:0 16px 40px rgba(15,23,42,.08)}</style></head><body><div class="wrap"><div class="card"><h1>' . esc_html__('Preview unavailable', 'oksia-smart-itinerary-agent') . '</h1><p>' . esc_html__('The selected template could not be loaded.', 'oksia-smart-itinerary-agent') . '</p></div></div></body></html>';
        }

        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private function render_quote_confirmation_workspace($post_id) {
        status_header(200);
        nocache_headers();

        $title = wp_strip_all_tags(get_the_title($post_id));
        if ('' === trim((string) $title)) {
            $title = __('Confirm Quote', 'oksia-smart-itinerary-agent');
        }

        $workflow_mode = sanitize_key((string) ($_GET['oksia_quote_workflow'] ?? ''));
        $frontend_notice = sanitize_text_field(wp_unslash($_GET['oksia_quote_notice'] ?? ''));
        $error_fields = isset($_GET['oksia_quote_error_fields']) ? array_filter(array_map('sanitize_key', explode(',', wp_unslash($_GET['oksia_quote_error_fields'])))) : array();
        $share_url = $this->get_quote_share_url($post_id);
        if ('' === $share_url) {
            $share_url = get_permalink($post_id);
        }
        $dashboard_url = $this->get_dashboard_url();

        $saved_template_key = class_exists('OKSIA_Quote_Templates') ? OKSIA_Quote_Templates::get_selected_template_key($post_id) : 'default';
        $allowed_templates = $this->get_quote_template_options($post_id);
        if (empty($allowed_templates)) {
            $allowed_templates = array('default' => __('Default', 'oksia-smart-itinerary-agent'));
        }
        if (!isset($allowed_templates[$saved_template_key])) {
            foreach ($allowed_templates as $template_key => $template_label) {
                $saved_template_key = $template_key;
                break;
            }
        }
        $preview_url = $this->get_quote_preview_url($post_id, $saved_template_key);

        $travel_pnr = trim((string) get_post_meta($post_id, '_oksia_travel_pnr', true));
        $hotel_pnr = trim((string) get_post_meta($post_id, '_oksia_hotel_pnr', true));
        $handler_type = trim((string) get_post_meta($post_id, '_oksia_handler_type', true));
        $handler_name = trim((string) get_post_meta($post_id, '_oksia_handler_name', true));
        $fallback_hotel_pnr = trim((string) get_post_meta($post_id, '_oksia_quote_confirmation_note', true));
        if ('' === $hotel_pnr && '' !== $fallback_hotel_pnr) {
            $hotel_pnr = $fallback_hotel_pnr;
        }

        $colors = $this->get_quote_color_palette($post_id);
        $primary_color = $this->normalize_hex_color($colors['primary'], '#000066');
        $secondary_color = $this->normalize_hex_color($colors['secondary'], '#336699');
        $accent_color = $this->normalize_hex_color($colors['accent'], '#99FFFF');
        $status_note = '';
        if ('confirmed' === $workflow_mode) {
            $status_note = __('Fill optional PNR details, choose a template, then confirm the quote.', 'oksia-smart-itinerary-agent');
        }
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($title); ?></title>
            <style>
                html, body { margin: 0; padding: 0; min-height: 100%; background: #f4f7fc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1f2937; }
                body { overflow-x: hidden; }
                .oksia-post-confirm-shell { min-height: 100vh; padding: 18px; box-sizing: border-box; }
                .oksia-post-confirm-topbar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 16px;
                    padding: 14px 18px;
                    border: 1px solid #dbe7f3;
                    border-radius: 18px;
                    background: #fff;
                    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
                    margin-bottom: 18px;
                }
                .oksia-post-confirm-topbar h1 { margin: 0; font-size: 18px; line-height: 1.2; }
                .oksia-post-confirm-topbar p { margin: 4px 0 0; color: #64748b; font-size: 13px; }
                .oksia-post-confirm-actions { display: flex; gap: 10px; flex-wrap: wrap; }
                .oksia-post-confirm-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 11px 15px;
                    border-radius: 12px;
                    text-decoration: none;
                    font-weight: 700;
                    font-size: 13px;
                    border: 1px solid transparent;
                }
                .oksia-post-confirm-btn--light { background: #fff; color: #0f172a; border-color: #dbe7f3; }
                .oksia-post-confirm-btn--accent { background: <?php echo esc_attr($primary_color); ?>; color: #fff; }
                .oksia-post-confirm-grid {
                    display: grid;
                    grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
                    gap: 18px;
                    align-items: start;
                }
                .oksia-post-confirm-card {
                    background: #fff;
                    border: 1px solid #dbe7f3;
                    border-radius: 18px;
                    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
                    padding: 18px;
                }
                .oksia-post-confirm-card h2 { margin: 0 0 8px; font-size: 18px; }
                .oksia-post-confirm-card p.note { margin: 0 0 16px; color: #64748b; font-size: 13px; line-height: 1.5; }
                .oksia-post-confirm-status {
                    margin-bottom: 14px;
                    padding: 10px 12px;
                    border-radius: 12px;
                    background: #f8fbff;
                    border: 1px solid #dbe7f3;
                    color: #334155;
                    font-size: 13px;
                }
                .oksia-post-confirm-grid-fields {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 12px;
                }
                .oksia-post-confirm-field { display: grid; gap: 6px; }
                .oksia-post-confirm-field--full { grid-column: 1 / -1; }
                .oksia-post-confirm-field label { font-size: 13px; font-weight: 700; color: #0f172a; }
                .oksia-post-confirm-field input,
                .oksia-post-confirm-field select {
                    width: 100%;
                    box-sizing: border-box;
                    border: 1px solid #bfd1e8;
                    border-radius: 12px;
                    padding: 12px 13px;
                    font: inherit;
                    color: inherit;
                    background: #fff;
                }
                .oksia-post-confirm-field input:focus,
                .oksia-post-confirm-field select:focus {
                    outline: none;
                    border-color: <?php echo esc_attr($primary_color); ?>;
                    box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.10);
                }
                .oksia-post-confirm-field--error input,
                .oksia-post-confirm-field--error select {
                    border-color: #d63638;
                    background: #fff5f5;
                }
                .oksia-post-confirm-template-row { display: grid; gap: 10px; margin-top: 12px; }
                .oksia-post-confirm-template-row select { width: 100%; }
                .oksia-post-confirm-actions-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                    margin-top: 16px;
                }
                .oksia-post-confirm-submit {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 46px;
                    padding: 0 18px;
                    border: 0;
                    border-radius: 12px;
                    color: #fff;
                    font: inherit;
                    font-weight: 700;
                    cursor: pointer;
                    background: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?> 0%, <?php echo esc_attr($secondary_color); ?> 100%);
                }
                .oksia-post-confirm-preview {
                    display: grid;
                    gap: 12px;
                }
                .oksia-post-confirm-preview__head {
                    display: flex;
                    justify-content: space-between;
                    gap: 12px;
                    align-items: center;
                }
                .oksia-post-confirm-preview__head strong { font-size: 16px; }
                .oksia-post-confirm-preview__frame {
                    width: 100%;
                    min-height: 820px;
                    border: 1px solid #dbe7f3;
                    border-radius: 18px;
                    background: #fff;
                }
                .oksia-post-confirm-hint {
                    font-size: 12px;
                    color: #64748b;
                }
                @media (max-width: 1100px) {
                    .oksia-post-confirm-grid { grid-template-columns: 1fr; }
                    .oksia-post-confirm-preview__frame { min-height: 640px; }
                }
                @media (max-width: 680px) {
                    .oksia-post-confirm-shell { padding: 12px; }
                    .oksia-post-confirm-topbar { flex-direction: column; align-items: flex-start; }
                    .oksia-post-confirm-grid-fields { grid-template-columns: 1fr; }
                    .oksia-post-confirm-preview__frame { min-height: 540px; }
                }
            </style>
        </head>
        <body style="--oksia-primary: <?php echo esc_attr($primary_color); ?>; --oksia-secondary: <?php echo esc_attr($secondary_color); ?>; --oksia-accent: <?php echo esc_attr($accent_color); ?>;">
            <div class="oksia-post-confirm-shell">
                <div class="oksia-post-confirm-topbar">
                    <div>
                        <h1><?php echo esc_html($title); ?></h1>
                        <p><?php echo esc_html($status_note ?: __('Choose a template, add optional PNR details, and confirm the quote.', 'oksia-smart-itinerary-agent')); ?></p>
                    </div>
                    <div class="oksia-post-confirm-actions">
                        <a class="oksia-post-confirm-btn oksia-post-confirm-btn--light" href="<?php echo esc_url($dashboard_url); ?>"><?php esc_html_e('Back to Dashboard', 'oksia-smart-itinerary-agent'); ?></a>
                        <a class="oksia-post-confirm-btn oksia-post-confirm-btn--light" href="<?php echo esc_url($share_url); ?>"><?php esc_html_e('Back to Quote', 'oksia-smart-itinerary-agent'); ?></a>
                    </div>
                </div>

                <div class="oksia-post-confirm-grid">
                    <div class="oksia-post-confirm-card">
                        <h2><?php esc_html_e('Post Confirmation Setup', 'oksia-smart-itinerary-agent'); ?></h2>
                        <p class="note"><?php esc_html_e('PNR fields are optional. Add them now or later. The preview on the right shows the selected template first page only.', 'oksia-smart-itinerary-agent'); ?></p>
                        <?php if ('error' === $frontend_notice) : ?>
                            <div class="oksia-post-confirm-status"><?php esc_html_e('Please complete any missing required data and try again.', 'oksia-smart-itinerary-agent'); ?></div>
                        <?php elseif ('confirmed' === $frontend_notice) : ?>
                            <div class="oksia-post-confirm-status"><?php esc_html_e('Quote confirmed successfully.', 'oksia-smart-itinerary-agent'); ?></div>
                        <?php elseif ('cancelled' === $frontend_notice) : ?>
                            <div class="oksia-post-confirm-status"><?php esc_html_e('Quote cancelled and locked.', 'oksia-smart-itinerary-agent'); ?></div>
                        <?php endif; ?>

                        <form method="post" action="<?php echo esc_url(get_permalink($post_id)); ?>">
                            <?php wp_nonce_field('oksia_frontend_quote_action_' . $post_id, 'oksia_frontend_nonce'); ?>
                            <input type="hidden" name="oksia_frontend_quote_id" value="<?php echo esc_attr((string) $post_id); ?>" />
                            <input type="hidden" name="oksia_quote_post_confirm" value="1" />
                            <input type="hidden" name="oksia_quote_template_style" id="oksia_quote_template_style" value="<?php echo esc_attr($saved_template_key); ?>" />
                            <div class="oksia-post-confirm-grid-fields">
                                <div class="oksia-post-confirm-field <?php echo in_array('travel_pnr', $error_fields, true) ? 'oksia-post-confirm-field--error' : ''; ?> oksia-post-confirm-field--full">
                                    <label for="oksia_travel_pnr"><?php esc_html_e('Flight / Bus / Rail PNR', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" id="oksia_travel_pnr" name="oksia_travel_pnr" value="<?php echo esc_attr($travel_pnr); ?>" placeholder="<?php esc_attr_e('Optional, multiple values can be comma separated', 'oksia-smart-itinerary-agent'); ?>" />
                                </div>
                                <div class="oksia-post-confirm-field <?php echo in_array('hotel_pnr', $error_fields, true) ? 'oksia-post-confirm-field--error' : ''; ?> oksia-post-confirm-field--full">
                                    <label for="oksia_hotel_pnr"><?php esc_html_e('Hotel PNR', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" id="oksia_hotel_pnr" name="oksia_hotel_pnr" value="<?php echo esc_attr($hotel_pnr); ?>" placeholder="<?php esc_attr_e('Optional hotel reservation or confirmation id', 'oksia-smart-itinerary-agent'); ?>" />
                                </div>
                                <div class="oksia-post-confirm-field">
                                    <label for="oksia_handler_type"><?php esc_html_e('Handler', 'oksia-smart-itinerary-agent'); ?></label>
                                    <select id="oksia_handler_type" name="oksia_handler_type">
                                        <?php
                                        $handler_options = array(
                                            '' => __('Select handler', 'oksia-smart-itinerary-agent'),
                                            'DMC' => __('DMC', 'oksia-smart-itinerary-agent'),
                                            'Direct Vendors' => __('Direct Vendors', 'oksia-smart-itinerary-agent'),
                                            'Travel Agency' => __('Travel Agency', 'oksia-smart-itinerary-agent'),
                                            'Other Agent' => __('Other Agent', 'oksia-smart-itinerary-agent'),
                                        );
                                        foreach ($handler_options as $value => $label) :
                                        ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($handler_type, $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="oksia-post-confirm-field" id="oksia_handler_name_wrap" <?php echo ('' === $handler_type || !in_array($handler_type, array('DMC', 'Other Agent'), true)) ? 'style="display:none;"' : ''; ?>>
                                    <label for="oksia_handler_name"><?php esc_html_e('If DMC or Other Agent, write name', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" id="oksia_handler_name" name="oksia_handler_name" value="<?php echo esc_attr($handler_name); ?>" placeholder="<?php esc_attr_e('Enter handler name', 'oksia-smart-itinerary-agent'); ?>" />
                                </div>
                                <div class="oksia-post-confirm-field oksia-post-confirm-field--full">
                                    <label for="oksia_quote_template_style_select"><?php esc_html_e('Select Template', 'oksia-smart-itinerary-agent'); ?></label>
                                    <select id="oksia_quote_template_style_select" name="oksia_quote_template_style_select">
                                        <?php foreach ($allowed_templates as $template_key => $template_label) : ?>
                                            <option value="<?php echo esc_attr($template_key); ?>" <?php selected($saved_template_key, $template_key); ?>><?php echo esc_html($template_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="oksia-post-confirm-hint"><?php esc_html_e('Default follows agency colors. Premium and Business unlock additional styles.', 'oksia-smart-itinerary-agent'); ?></p>
                                </div>
                            </div>
                            <div class="oksia-post-confirm-actions-row">
                                <span class="oksia-post-confirm-hint"><?php esc_html_e('PNR fields are optional. Confirming now will generate the PDF only for this confirmed quote.', 'oksia-smart-itinerary-agent'); ?></span>
                                <button type="submit" name="oksia_frontend_quote_action" value="confirmed" class="oksia-post-confirm-submit"><?php esc_html_e('Confirm & Generate PDF', 'oksia-smart-itinerary-agent'); ?></button>
                            </div>
                        </form>
                    </div>

                    <div class="oksia-post-confirm-card oksia-post-confirm-preview">
                        <div class="oksia-post-confirm-preview__head">
                            <strong><?php esc_html_e('Template Preview', 'oksia-smart-itinerary-agent'); ?></strong>
                            <span class="oksia-post-confirm-hint"><?php esc_html_e('First page only', 'oksia-smart-itinerary-agent'); ?></span>
                        </div>
                        <iframe class="oksia-post-confirm-preview__frame" id="oksia_template_preview_frame" src="<?php echo esc_url($preview_url); ?>" title="<?php echo esc_attr__('Template preview', 'oksia-smart-itinerary-agent'); ?>"></iframe>
                    </div>
                </div>
            </div>
            <script>
                (function () {
                    var templateSelect = document.getElementById('oksia_quote_template_style_select');
                    var templateHidden = document.getElementById('oksia_quote_template_style');
                    var previewFrame = document.getElementById('oksia_template_preview_frame');
                    var handlerSelect = document.getElementById('oksia_handler_type');
                    var handlerWrap = document.getElementById('oksia_handler_name_wrap');

                    function updateHandlerVisibility() {
                        if (!handlerSelect || !handlerWrap) {
                            return;
                        }
                        var value = handlerSelect.value || '';
                        var show = value === 'DMC' || value === 'Other Agent';
                        handlerWrap.style.display = show ? '' : 'none';
                        if (!show) {
                            var input = handlerWrap.querySelector('input');
                            if (input) {
                                input.value = '';
                            }
                        }
                    }

                    function updatePreview() {
                        if (!templateSelect || !previewFrame) {
                            return;
                        }
                        var value = templateSelect.value || 'default';
                        if (templateHidden) {
                            templateHidden.value = value;
                        }
                        var url = <?php echo wp_json_encode(remove_query_arg(array('oksia_quote_preview', 'oksia_quote_template'), $preview_url)); ?>;
                        previewFrame.src = url + '&oksia_quote_preview=1&oksia_quote_template=' + encodeURIComponent(value);
                    }

                    if (templateSelect) {
                        templateSelect.addEventListener('change', updatePreview);
                    }
                    if (handlerSelect) {
                        handlerSelect.addEventListener('change', updateHandlerVisibility);
                    }
                    updateHandlerVisibility();
                }());
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    public function maybe_download_pdf() {
        $post_id = absint($_GET['oksia_view_pdf'] ?? ($_GET['oksia_download_pdf'] ?? 0));
        if (!$post_id) {
            return;
        }

        if (!$post_id || OKSIA_Post_Types::POST_TYPE !== get_post_type($post_id)) {
            wp_die(esc_html__('Invalid quote selected for PDF export.', 'oksia-smart-itinerary-agent'));
        }

        $quote_stage = trim((string) get_post_meta($post_id, '_oksia_quote_stage', true));
        if ('cancelled' === $quote_stage) {
            wp_die(esc_html__('This quote has been cancelled and cannot be opened again.', 'oksia-smart-itinerary-agent'));
        }

        // Allow PDF view/download for both 'send' (finalized draft) and 'confirmed' status
        if (!in_array($quote_stage, array('send', 'confirmed'), true)) {
            wp_die(esc_html__('PDF generation is available only after the quote is finalized and sent or confirmed. Use the share link for drafts.', 'oksia-smart-itinerary-agent'));
        }

        if ('confirmed' === $quote_stage && !current_user_can('manage_options') && !current_user_can('edit_users') && !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('You are not allowed to open this confirmed quote.', 'oksia-smart-itinerary-agent'));
        }

        if (!empty($_GET['oksia_view_pdf']) && empty($_GET['oksia_download_pdf'])) {
            $this->render_pdf_preview_page($post_id);
            exit;
        }

        $inline = !empty($_GET['oksia_pdf_inline']) || !empty($_GET['oksia_view_pdf']);
        $this->generate_pdf_download($post_id, $inline);
        exit;
    }

    private function get_dashboard_url() {
        $plugin = OKSIA_Smart_Itinerary_Agent_Plugin::instance();
        if ($plugin && !empty($plugin->workspace) && method_exists($plugin->workspace, 'get_dashboard_url')) {
            return $plugin->workspace->get_dashboard_url();
        }

        return home_url('/');
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

        return rtrim(trim((string) get_permalink($post_id)), " \t\n\r\0\x0B?&");
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

    private function get_quote_agency_id($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return 0;
        }

        $agency_id = absint(get_post_meta($post_id, '_oksia_agency_id', true));
        if ($agency_id > 0 && class_exists('OKSIA_Agencies') && OKSIA_Agencies::POST_TYPE === get_post_type($agency_id)) {
            return $agency_id;
        }

        $author_id = absint(get_post_field('post_author', $post_id));
        if ($author_id > 0 && class_exists('OKSIA_Agencies')) {
            $agency_id = absint(get_user_meta($author_id, OKSIA_Agencies::USER_META_AGENCY_ID, true));
            if ($agency_id > 0 && OKSIA_Agencies::POST_TYPE === get_post_type($agency_id)) {
                return $agency_id;
            }
        }

        $primary_agency_id = absint(get_option('oksia_primary_agency_post_id', 0));
        if ($primary_agency_id > 0 && (!class_exists('OKSIA_Agencies') || OKSIA_Agencies::POST_TYPE === get_post_type($primary_agency_id))) {
            return $primary_agency_id;
        }

        return 0;
    }

    private function get_quote_template_options($post_id) {
        $agency_id = $this->get_quote_agency_id($post_id);
        if (class_exists('OKSIA_Quote_Templates')) {
            $options = OKSIA_Quote_Templates::get_allowed_template_options_for_agency($agency_id);
            if (!empty($options)) {
                return $options;
            }
        }

        return array('default' => __('Default', 'oksia-smart-itinerary-agent'));
    }

    private function get_quote_preview_url($post_id, $template_key = '') {
        $share_url = $this->get_quote_share_url($post_id);
        if ('' === $share_url) {
            $share_url = get_permalink($post_id);
        }

        $args = array('oksia_quote_preview' => '1');
        $template_key = sanitize_key((string) $template_key);
        if ('' !== $template_key) {
            $args['oksia_quote_template'] = $template_key;
        }

        return add_query_arg($args, $share_url);
    }

    private function build_quote_template_document($post_id, $include_print_button = true) {
        $post_id = absint($post_id);
        if (!$post_id || OKSIA_Post_Types::POST_TYPE !== get_post_type($post_id)) {
            return '';
        }

        $template_key = class_exists('OKSIA_Quote_Templates') ? OKSIA_Quote_Templates::get_selected_template_key($post_id) : 'default';
        $template_markup = class_exists('OKSIA_Quote_Templates') ? OKSIA_Quote_Templates::get_template_markup($template_key) : '';
        $template_css = '';
        $template_body_attrs = '';

        if ('' !== trim((string) $template_markup)) {
            if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $template_markup, $matches) && !empty($matches[1])) {
                $template_css = implode("\n", array_map('trim', $matches[1]));
            }

            if (preg_match('/<body\b([^>]*)>/is', $template_markup, $body_matches)) {
                $template_body_attrs = trim((string) $body_matches[1]);
            }
        }

        $colors = $this->get_quote_color_palette($post_id);
        $primary_color = $this->normalize_hex_color($colors['primary'], '#000066');
        $secondary_color = $this->normalize_hex_color($colors['secondary'], '#336699');
        $accent_color = $this->normalize_hex_color($colors['accent'], '#99FFFF');
        $body_classes = array(
            'oksia-template-document',
            'oksia-template-document--' . sanitize_html_class($template_key),
        );
        $body_style = array(
            'margin:0',
            'padding:0',
            'background:#fff',
            'color:#1f2937',
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif',
            '--oksia-primary:' . $primary_color,
            '--oksia-secondary:' . $secondary_color,
            '--oksia-accent:' . $accent_color,
        );

        if ('' !== $template_body_attrs) {
            if (preg_match('/class\s*=\s*"([^"]*)"/i', $template_body_attrs, $class_matches)) {
                $body_classes = array_merge($body_classes, preg_split('/\s+/', trim($class_matches[1])));
            }
            if (preg_match('/style\s*=\s*"([^"]*)"/i', $template_body_attrs, $style_matches)) {
                $body_style[] = trim($style_matches[1]);
            }
        }

        $body_classes = array_values(array_unique(array_filter(array_map('sanitize_html_class', $body_classes))));
        $body_style = array_values(array_filter(array_map('trim', $body_style)));

        $markup = $this->get_brochure_markup($post_id, $include_print_button);
        if ('' === trim((string) $markup)) {
            return '';
        }

        if (preg_match('/^\s*<!doctype\s+html/i', $markup) || preg_match('/^\s*<html\b/i', $markup)) {
            return $markup;
        }

        $admin_css_file = OKSIA_PATH . 'assets/css/admin.css';
        $admin_css = file_exists($admin_css_file) ? (string) file_get_contents($admin_css_file) : '';
        $document_title = wp_strip_all_tags(get_the_title($post_id));
        if ('' === trim((string) $document_title)) {
            $document_title = __('Quote', 'oksia-smart-itinerary-agent');
        }

        return '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr(get_bloginfo('charset') ?: 'UTF-8') . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html($document_title) . '</title><style>' . $admin_css . "\n" . $template_css . '</style></head><body class="' . esc_attr(implode(' ', $body_classes)) . '" style="' . esc_attr(implode('; ', $body_style)) . '">' . $markup . '</body></html>';
    }

    private function render_default_quote_document($post_id, $include_print_button = true) {
        $trip = wp_parse_args((array) get_post_meta($post_id, '_oksia_trip_overview', true), array('destination' => '', 'start_date' => '', 'end_date' => '', 'total_nights' => 0, 'travelers' => 0, 'salutation' => 'Mr', 'client_name' => '', 'trip_type' => 'Domestic', 'adults' => 0, 'adult_with_bed' => 0, 'child_without_bed' => 0));
        $quote = wp_parse_args((array) get_post_meta($post_id, '_oksia_quote_details', true), array('hotel_category' => '', 'occupancy' => '', 'rooms' => '', 'meal_plan' => '', 'meal_transfers' => '', 'pickup_from' => '', 'drop_to' => '', 'first_transfer' => '', 'last_transfer' => '', 'sightseeing_vehicle' => '', 'vehicle_type' => '', 'currency' => 'INR', 'exchange_rate' => '', 'transaction_cost' => '1.9', 'additional_cost' => '0', 'effective_rate' => '', 'adult_rate' => '', 'with_bed_rate' => '', 'child_rate' => '', 'adult_rate_quote' => '', 'with_bed_rate_quote' => '', 'child_rate_quote' => ''));
        $hotel_plan = (array) get_post_meta($post_id, '_oksia_hotel_plan', true);
        $operational = wp_parse_args((array) get_post_meta($post_id, '_oksia_operational_notes', true), array('summary' => '', 'inclusions' => '', 'exclusions' => '', 'important_notes' => '', 'child_policy' => '', 'booking_policy' => '', 'cancellation_policy' => ''));
        $operational['child_policy'] = (string) get_option('oksia_default_child_policy', '');
        $operational['booking_policy'] = (string) get_option('oksia_default_booking_policy', '');
        $operational['cancellation_policy'] = (string) get_option('oksia_default_cancellation_policy', '');
        $days = (array) get_post_meta($post_id, '_oksia_days', true);
        $quote_id = (string) get_post_meta($post_id, '_oksia_quote_id', true);
        if ('' === $quote_id) {
            $quote_id = 'OK' . wp_date('ymd', current_time('timestamp')) . '01';
        }

        $brand_name = get_option('oksia_agency_name', 'OK');
        $brand_phone = get_option('oksia_agency_phone', '');
        $brand_location = get_option('oksia_agency_location', '');
		$brand_logo_raw = $this->get_brand_logo_src($this->get_quote_agency_id($post_id));
        $brand_logo = $this->get_logo_as_data_url($brand_logo_raw);
        $billing_company = get_option('oksia_billing_company', 'EKTA CORPORATION');
        $billing_email = get_option('oksia_billing_email', '');
        $colors = $this->get_quote_color_palette($post_id);
        $primary_color = $this->normalize_hex_color($colors['primary'], '#000066');
        $secondary_color = $this->normalize_hex_color($colors['secondary'], '#336699');
        $accent_color = $this->normalize_hex_color($colors['accent'], '#99FFFF');
        $is_primary_dark = $this->is_dark_color($primary_color);
        $on_primary = $is_primary_dark ? '#ffffff' : '#000000';
        $primary_soft = $this->mix_hex_colors($primary_color, '#ffffff', $is_primary_dark ? 0.82 : 0.90);
        $primary_softer = $this->mix_hex_colors($primary_color, '#ffffff', $is_primary_dark ? 0.90 : 0.95);
        $cell_heading_color = $is_primary_dark ? $primary_color : '#1e2f40';
        $border_soft = '#C3C4C7';
        $footer_text = get_option('oksia_footer_text', '');
        $disclaimer_text = get_option('oksia_disclaimer_text', 'This is quotation only, no bookings are hold or confirmed. Prices are valid for 24hrs.');
        $quote_stage = trim((string) get_post_meta($post_id, '_oksia_quote_stage', true));
        $confirmation_note = trim((string) get_post_meta($post_id, '_oksia_quote_confirmation_note', true));
        $confirmed_by = trim((string) get_post_meta($post_id, '_oksia_quote_confirmed_by_name', true));
        if ('confirmed' === $quote_stage) {
            $confirmation_bits = array();
            if ('' !== $confirmation_note) {
                $confirmation_bits[] = sprintf(__('Hotel Confirmation / PNR: %s', 'oksia-smart-itinerary-agent'), $confirmation_note);
            }
            if ('' !== $confirmed_by) {
                $confirmation_bits[] = sprintf(__('Confirmed by: %s', 'oksia-smart-itinerary-agent'), $confirmed_by);
            }
            $disclaimer_text = __('This is a confirmed itinerary.', 'oksia-smart-itinerary-agent');
            if (!empty($confirmation_bits)) {
                $disclaimer_text .= ' ' . implode(' | ', $confirmation_bits);
            }
        }
        $total_days = max(0, (int) $trip['total_nights']) + ('' !== $trip['start_date'] && '' !== $trip['end_date'] ? 1 : 0);
        $currency_code = $this->normalize_currency_code($quote['currency']);
        $show_inr_reference = ('International' === $trip['trip_type']) && ('INR' !== $currency_code);
        $pdf_mode = ! $include_print_button;
        $meal_transfers_value = $this->normalize_meal_transfers_value($trip['trip_type'], $quote['meal_plan'], $quote['meal_transfers']);
        $client_name = trim(($trip['salutation'] ? $trip['salutation'] . ' ' : '') . $trip['client_name']);
        $version_label = class_exists('OKSIA_Admin') ? OKSIA_Admin::get_quote_version_label($post_id) : 'v1';

        ob_start();
        ?>
        <style>
            @page { size: A4; margin: 12mm; }
            html, body { margin: 0; padding: 0; width: 100%; max-width: none; background: #fff; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
            .oksia-doc-wrap { width: 100%; }
            .oksia-doc-page {
                width: 100%;
                box-sizing: border-box;
                position: relative;
                min-height: 100vh;
                page-break-after: always;
                padding: 0 0 72px 0;
            }
            .oksia-doc-page:last-child { page-break-after: auto; }
            .oksia-doc-main { width: 100%; box-sizing: border-box; padding: 0 0 72px 0; }
            .oksia-doc-footer {
                position: absolute;
                left: 0;
                right: 0;
                bottom: 0;
                padding: 14px 0 0;
                margin-top: 18px;
                border-top: 1px solid #e2e8f0;
                background: #fff;
            }
            .oksia-doc-footer-grid { display: flex; justify-content: space-between; gap: 12px; font-size: 10px; color: #64748b; }
            .oksia-doc-page-number { text-align: right; font-size: 10px; font-weight: 600; color: #475569; }
            @media print {
                body { margin: 0; padding: 0; }
                .oksia-doc-page { page-break-after: always !important; }
                .oksia-doc-page:last-child { page-break-after: auto !important; }
            }
        </style>
        <div class="oksia-doc-wrap" style="--oksia-primary: <?php echo esc_attr($primary_color); ?>; --oksia-secondary: <?php echo esc_attr($secondary_color); ?>; --oksia-accent: <?php echo esc_attr($accent_color); ?>; --oksia-theme-primary: <?php echo esc_attr($primary_color); ?>; --oksia-theme-secondary: <?php echo esc_attr($secondary_color); ?>; --oksia-theme-accent: <?php echo esc_attr($accent_color); ?>; --oksia-on-primary: <?php echo esc_attr($on_primary); ?>; --oksia-primary-soft: <?php echo esc_attr($primary_soft); ?>; --oksia-primary-softer: <?php echo esc_attr($primary_softer); ?>; --oksia-cell-heading-color: <?php echo esc_attr($cell_heading_color); ?>; --oksia-border-soft: <?php echo esc_attr($border_soft); ?>;">
            <div class="oksia-doc-page">
                <div class="oksia-doc-main">
                    <?php echo $this->render_quote_header($brand_logo, $brand_name, $billing_company, $disclaimer_text, $quote_id, $version_label, $client_name, $trip['destination'], sprintf('%dN / %dD', (int) $trip['total_nights'], $total_days), (string) $trip['travelers']); ?>
                    <?php echo $this->render_quote_intro($client_name, $operational['summary'], $footer_text); ?>
                    <?php echo $this->render_confirmation_summary_block($post_id); ?>
                    <div class="oksia-sheet-grid">
                        <?php echo $this->render_trip_details_box($trip); ?>
                        <?php echo $this->render_accommodation_box($quote, $trip['trip_type'], $meal_transfers_value); ?>
                    </div>
                    <div class="oksia-sheet-grid">
                        <?php echo $this->render_transfers_box($quote); ?>
                        <?php echo $this->render_rates_box($quote, $show_inr_reference, $pdf_mode); ?>
                    </div>
                    <?php if (!empty($hotel_plan)) : ?>
                        <section class="oksia-sheet-block oksia-sheet-block--full">
                            <h3><?php esc_html_e('Hotel Details', 'oksia-smart-itinerary-agent'); ?></h3>
                            <table class="oksia-sheet-table oksia-sheet-table--hotels">
                                <tbody>
                                    <?php foreach ($hotel_plan as $stay) : ?>
                                        <tr>
                                            <td><?php echo esc_html((string) ($stay['nights'] ?? '')); ?></td>
                                            <td><?php echo esc_html($stay['hotel'] ?? ''); ?></td>
                                            <td><?php echo esc_html($stay['city'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </section>
                    <?php endif; ?>
                </div>
                <div class="oksia-doc-footer">
                    <div class="oksia-doc-footer-grid">
                        <div><strong><?php echo esc_html($brand_name); ?></strong><br><?php echo esc_html($brand_phone); ?> | <?php echo esc_html($billing_email); ?> | <?php echo esc_html($brand_location); ?></div>
                        <div class="oksia-doc-page-number">Page 1</div>
                    </div>
                </div>
            </div>
            <div class="oksia-doc-page">
                <div class="oksia-doc-main">
                    <div class="oksia-sheet-grid oksia-sheet-grid--notes">
                        <section class="oksia-sheet-block">
                            <h3><?php esc_html_e('Inclusions', 'oksia-smart-itinerary-agent'); ?></h3>
                            <?php echo $this->render_multiline_list($operational['inclusions']); ?>
                        </section>
                        <section class="oksia-sheet-block">
                            <h3><?php esc_html_e('Exclusion', 'oksia-smart-itinerary-agent'); ?></h3>
                            <?php echo $this->render_multiline_list($this->build_exclusion_list($operational['exclusions'], $trip, $quote)); ?>
                        </section>
                    </div>
                    <div class="oksia-policy-grid oksia-policy-grid--sheet">
                        <?php echo $this->render_policy_card(__('Child Policy', 'oksia-smart-itinerary-agent'), $operational['child_policy']); ?>
                        <?php echo $this->render_policy_card(__('Booking Policy', 'oksia-smart-itinerary-agent'), $operational['booking_policy']); ?>
                        <?php echo $this->render_policy_card(__('Cancellation Policy', 'oksia-smart-itinerary-agent'), $operational['cancellation_policy']); ?>
                        <?php if (!empty($operational['important_notes'])) : ?>
                            <?php echo $this->render_policy_card(__('Important Notes', 'oksia-smart-itinerary-agent'), $operational['important_notes']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="oksia-doc-footer">
                    <div class="oksia-doc-footer-grid">
                        <div><strong><?php echo esc_html($brand_name); ?></strong><br><?php echo esc_html($brand_phone); ?> | <?php echo esc_html($billing_email); ?> | <?php echo esc_html($brand_location); ?></div>
                        <div class="oksia-doc-page-number">Page 2</div>
                    </div>
                </div>
            </div>
            <?php if (!empty($days)) : ?>
            <div class="oksia-doc-page">
                <div class="oksia-doc-main">
                    <section class="oksia-sheet-block oksia-sheet-block--full">
                        <h3><?php esc_html_e('Day wise Tentative Schedule', 'oksia-smart-itinerary-agent'); ?></h3>
                        <table class="oksia-sheet-table oksia-sheet-table--days">
                            <tbody>
                                <?php foreach ($days as $index => $day) : ?>
                                    <?php $day_number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?>
                                    <tr>
                                        <td class="oksia-day-index"><?php echo esc_html('Day ' . $day_number); ?></td>
                                        <td class="oksia-day-detail">
                                            <div class="oksia-day-copy">
                                                <?php if (!empty($day['location'])) : ?>
                                                    <div class="oksia-day-location"><?php echo esc_html(sprintf(__('Today\'s Location: %s', 'oksia-smart-itinerary-agent'), $day['location'])); ?></div>
                                                <?php endif; ?>
                                                <strong><?php echo esc_html($this->normalize_day_title($day['title'] ?? '', $day_number)); ?></strong>
                                                <div class="oksia-day-description"><?php echo esc_html($day['description'] ?? ''); ?></div>
                                            </div>
                                        </td>
                                        <td class="oksia-day-visual">
                                            <?php if (!empty($day['image_url'])) : ?>
                                                <img src="<?php echo esc_url($day['image_url']); ?>" alt="<?php echo esc_attr($day['title'] ?? ''); ?>" />
                                            <?php else : ?>
                                                <div class="oksia-day-visual__placeholder"><?php esc_html_e('Image coming soon', 'oksia-smart-itinerary-agent'); ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                </div>
                <div class="oksia-doc-footer">
                    <div class="oksia-doc-footer-grid">
                        <div><strong><?php echo esc_html($brand_name); ?></strong><br><?php echo esc_html($brand_phone); ?> | <?php echo esc_html($billing_email); ?> | <?php echo esc_html($brand_location); ?></div>
                        <div class="oksia-doc-page-number">Page 3</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function maybe_handle_frontend_quote_action() {
        if (empty($_POST['oksia_frontend_quote_action'])) {
            return;
        }

        $post_id = absint($_POST['oksia_frontend_quote_id'] ?? 0);
        if (!$post_id || OKSIA_Post_Types::POST_TYPE !== get_post_type($post_id)) {
            wp_die(esc_html__('Invalid quote selected for quote actions.', 'oksia-smart-itinerary-agent'));
        }

        if (!is_user_logged_in()) {
            wp_die(esc_html__('You must be logged in to perform this action.', 'oksia-smart-itinerary-agent'));
        }

        // Allow admins, managers, or users who can edit the post
        $can_manage = current_user_can('manage_options') || current_user_can('edit_users');
        $can_edit_post = current_user_can('edit_post', $post_id);
        $has_oksia_role = current_user_can('read');  // Basic capability check for OKSIA users

        if (!($can_manage || $can_edit_post || $has_oksia_role)) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'oksia-smart-itinerary-agent'));
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['oksia_frontend_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'oksia_frontend_quote_action_' . $post_id)) {
            wp_die(esc_html__('Security check failed for quote actions.', 'oksia-smart-itinerary-agent'));
        }

        $action = sanitize_text_field(wp_unslash($_POST['oksia_frontend_quote_action']));
        $is_post_confirm_workspace = !empty($_POST['oksia_quote_post_confirm']);
        $plugin = OKSIA_Smart_Itinerary_Agent_Plugin::instance();
        if (!$plugin || empty($plugin->admin) || !method_exists($plugin->admin, 'handle_quote_state_action')) {
            wp_die(esc_html__('Quote actions are unavailable right now.', 'oksia-smart-itinerary-agent'));
        }
        // Save selected template from confirmation form
        if ($is_post_confirm_workspace) {
            $selected_template = sanitize_key(wp_unslash($_POST['oksia_quote_template_style'] ?? ''));
            if ($selected_template && class_exists('OKSIA_Quote_Templates')) {
                OKSIA_Quote_Templates::set_selected_template_key($post_id, $selected_template);
            }
        }
        $result = $plugin->admin->handle_quote_state_action($post_id, $action);
        if (is_wp_error($result)) {
            $error_fields = array();
            $error_data = $result->get_error_data();
            if (is_array($error_data) && !empty($error_data['fields'])) {
                $error_fields = array_values(array_filter(array_map('sanitize_key', (array) $error_data['fields'])));
            }

            $redirect_args = array(
                'oksia_quote_notice' => 'error',
            );
            if (!empty($error_fields)) {
                $redirect_args['oksia_quote_error_fields'] = implode(',', $error_fields);
            }

            $redirect_target = $this->get_quote_share_url($post_id);
            if (in_array($action, array('confirmed', 'cancelled'), true)) {
                $redirect_target = add_query_arg('oksia_quote_workflow', $action, $redirect_target);
            }
            wp_safe_redirect(add_query_arg($redirect_args, $redirect_target));
            exit;
        }

        if ('download_draft' === $action) {
            wp_safe_redirect(add_query_arg('oksia_view_pdf', $post_id, $this->get_quote_share_url($post_id)));
            exit;
        }

        if (in_array($action, array('confirmed', 'finalize'), true)) {
            if (!$is_post_confirm_workspace) {
                wp_safe_redirect(add_query_arg('oksia_quote_workflow', 'confirmed', $this->get_quote_share_url($post_id)));
                exit;
            }
            wp_safe_redirect(add_query_arg('oksia_download_pdf', $post_id, $this->get_quote_share_url($post_id)));
            exit;
        }

        if ('cancelled' === $action) {
            wp_safe_redirect(add_query_arg('oksia_quote_notice', 'cancelled', $this->get_dashboard_url()));
            exit;
        }

        $notice = ('finalize' === $action) ? 'confirmed' : $action;
        wp_safe_redirect(add_query_arg('oksia_quote_notice', $notice, $this->get_quote_share_url($post_id)));
        exit;
    }

    private function get_brochure_markup($post_id, $include_print_button = true) {
        $base_template_file = OKSIA_PATH . 'templates/output-template.php';
        if (file_exists($base_template_file)) {
            ob_start();
            include $base_template_file;
            $markup = (string) ob_get_clean();
            if ('' === trim($markup)) {
                return '';
            }

            return $this->replace_output_template_tokens($post_id, $markup);
        }

        return '';

        $trip = wp_parse_args((array) get_post_meta($post_id, '_oksia_trip_overview', true), array('destination' => '', 'start_date' => '', 'end_date' => '', 'total_nights' => 0, 'travelers' => 0, 'salutation' => 'Mr', 'client_name' => '', 'trip_type' => 'Domestic', 'adults' => 0, 'adult_with_bed' => 0, 'child_without_bed' => 0));
        $quote = wp_parse_args((array) get_post_meta($post_id, '_oksia_quote_details', true), array('hotel_category' => '', 'occupancy' => '', 'rooms' => '', 'meal_plan' => '', 'meal_transfers' => '', 'pickup_from' => '', 'drop_to' => '', 'first_transfer' => '', 'last_transfer' => '', 'sightseeing_vehicle' => '', 'vehicle_type' => '', 'currency' => 'INR', 'exchange_rate' => '', 'transaction_cost' => '1.9', 'additional_cost' => '0', 'effective_rate' => '', 'adult_rate' => '', 'with_bed_rate' => '', 'child_rate' => '', 'adult_rate_quote' => '', 'with_bed_rate_quote' => '', 'child_rate_quote' => ''));
        $hotel_plan = (array) get_post_meta($post_id, '_oksia_hotel_plan', true);
        $operational = wp_parse_args((array) get_post_meta($post_id, '_oksia_operational_notes', true), array('summary' => '', 'inclusions' => '', 'exclusions' => '', 'important_notes' => '', 'child_policy' => '', 'booking_policy' => '', 'cancellation_policy' => ''));
        $operational['child_policy'] = (string) get_option('oksia_default_child_policy', '');
        $operational['booking_policy'] = (string) get_option('oksia_default_booking_policy', '');
        $operational['cancellation_policy'] = (string) get_option('oksia_default_cancellation_policy', '');
        $days = (array) get_post_meta($post_id, '_oksia_days', true);
        $quote_id = (string) get_post_meta($post_id, '_oksia_quote_id', true);
        if ('' === $quote_id) {
            $quote_id = 'OK' . wp_date('ymd', current_time('timestamp')) . '01';
        }

        $brand_name = get_option('oksia_agency_name', 'OK');
        $brand_phone = get_option('oksia_agency_phone', '');
        $brand_website = get_option('oksia_agency_website', '');
        $brand_location = get_option('oksia_agency_location', '');
        $brand_logo_raw = $this->get_brand_logo_src($this->get_quote_agency_id($post_id));
        $brand_logo = $this->get_logo_as_data_url($brand_logo_raw);
        $iata_code = get_option('oksia_iata_code', '');
        $billing_company = get_option('oksia_billing_company', 'EKTA CORPORATION');
        $billing_gst = get_option('oksia_billing_gst', '');
        $billing_state = get_option('oksia_billing_state', '');
        $billing_email = get_option('oksia_billing_email', '');
        $colors = $this->get_quote_color_palette($post_id);
        $primary_color = $this->normalize_hex_color($colors['primary'], '#000066');
        $secondary_color = $this->normalize_hex_color($colors['secondary'], '#336699');
        $accent_color = $this->normalize_hex_color($colors['accent'], '#99FFFF');
        $is_primary_dark = $this->is_dark_color($primary_color);
        $on_primary = $is_primary_dark ? '#ffffff' : '#000000';
        $primary_soft = $this->mix_hex_colors($primary_color, '#ffffff', $is_primary_dark ? 0.82 : 0.90);
        $primary_softer = $this->mix_hex_colors($primary_color, '#ffffff', $is_primary_dark ? 0.90 : 0.95);
        $cell_heading_color = $is_primary_dark ? $primary_color : '#1e2f40';
        $border_soft = '#C3C4C7';
        $footer_text = get_option('oksia_footer_text', '');
        $disclaimer_text = get_option('oksia_disclaimer_text', 'This is quotation only, no bookings are hold or confirmed. Prices are valid for 24hrs.');
        $quote_stage = trim((string) get_post_meta($post_id, '_oksia_quote_stage', true));
        $confirmation_note = trim((string) get_post_meta($post_id, '_oksia_quote_confirmation_note', true));
        $confirmed_by = trim((string) get_post_meta($post_id, '_oksia_quote_confirmed_by_name', true));
        if ('confirmed' === $quote_stage) {
            $confirmation_bits = array();
            if ('' !== $confirmation_note) {
                $confirmation_bits[] = sprintf(__('Hotel Confirmation / PNR: %s', 'oksia-smart-itinerary-agent'), $confirmation_note);
            }
            if ('' !== $confirmed_by) {
                $confirmation_bits[] = sprintf(__('Confirmed by: %s', 'oksia-smart-itinerary-agent'), $confirmed_by);
            }
            $disclaimer_text = __('This is a confirmed itinerary.', 'oksia-smart-itinerary-agent');
            if (!empty($confirmation_bits)) {
                $disclaimer_text .= ' ' . implode(' | ', $confirmation_bits);
            }
        }

        $total_days = max(0, (int) $trip['total_nights']) + ('' !== $trip['start_date'] && '' !== $trip['end_date'] ? 1 : 0);
        $currency_code = $this->normalize_currency_code($quote['currency']);
        $show_inr_reference = ('International' === $trip['trip_type']) && ('INR' !== $currency_code);
        $pdf_mode = ! $include_print_button;
        $meal_transfers_value = $this->normalize_meal_transfers_value($trip['trip_type'], $quote['meal_plan'], $quote['meal_transfers']);
        $client_name = trim(($trip['salutation'] ? $trip['salutation'] . ' ' : '') . $trip['client_name']);
        $staff_meta = $this->get_quote_staff_meta($post_id);
        $version_label = class_exists('OKSIA_Admin') ? OKSIA_Admin::get_quote_version_label($post_id) : 'v1';
        $last_updated_by = class_exists('OKSIA_Admin') ? OKSIA_Admin::get_quote_last_updated_by_name($post_id) : get_option('oksia_agency_name', 'OK');
        $is_finalized = '1' === (string) get_post_meta($post_id, '_oksia_quote_finalized', true);
        $can_edit_quote = is_user_logged_in() && current_user_can('edit_post', $post_id);
        $frontend_notice = sanitize_text_field(wp_unslash($_GET['oksia_quote_notice'] ?? ''));
        $workflow_mode = sanitize_key((string) ($_GET['oksia_quote_workflow'] ?? ''));
        $error_fields = isset($_GET['oksia_quote_error_fields']) ? array_filter(array_map('sanitize_key', explode(',', wp_unslash($_GET['oksia_quote_error_fields'])))) : array();
        $title = get_the_title($post_id);
        if ('' === trim((string) $title)) {
            $title = __('Draft Preview', 'oksia-smart-itinerary-agent');
        }
        $share_url = $this->get_quote_share_url($post_id);
        if ('' === $share_url) {
            $share_url = get_permalink($post_id);
        }
        $download_file_url = add_query_arg('oksia_download_pdf', $post_id, $share_url);
        $overlay_open = in_array($workflow_mode, array('confirmed', 'cancelled'), true);

        ob_start();
        ?>
        <?php if ($include_print_button && '' !== $frontend_notice && $can_edit_quote) : ?>
            <div class="oksia-brochure-notice">
                <?php if ('error' === $frontend_notice) : ?>
                    <p class="oksia-brochure-notice__message oksia-brochure-notice__message--error"><?php esc_html_e('Could not finalize the quote. Please check the quote status message and try again.', 'oksia-smart-itinerary-agent'); ?></p>
                <?php elseif ('finalize' === $frontend_notice) : ?>
                    <p class="oksia-brochure-notice__message oksia-brochure-notice__message--success"><?php esc_html_e('Quote finalized successfully.', 'oksia-smart-itinerary-agent'); ?></p>
                <?php elseif ('reopen' === $frontend_notice) : ?>
                    <p class="oksia-brochure-notice__message oksia-brochure-notice__message--success"><?php esc_html_e('Quote reopened for edits.', 'oksia-smart-itinerary-agent'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($include_print_button && !$overlay_open) : ?>
            <div class="oksia-pdf-actions">
                <?php if ($can_edit_quote) : ?>
                    <form class="oksia-browser-actions" method="post" action="<?php echo esc_url(get_permalink($post_id)); ?>">
                        <?php wp_nonce_field('oksia_frontend_quote_action_' . $post_id, 'oksia_frontend_nonce'); ?>
                        <input type="hidden" name="oksia_frontend_quote_id" value="<?php echo esc_attr((string) $post_id); ?>" />
                        <button type="submit" name="oksia_frontend_quote_action" value="download_draft" class="oksia-print-button oksia-print-button--download oksia-browser-action oksia-browser-action--agency-primary"><?php esc_html_e('Generate Draft PDF', 'oksia-smart-itinerary-agent'); ?></button>
                        <?php if (! $is_finalized) : ?>
                            <button type="submit" name="oksia_frontend_quote_action" value="finalize" class="oksia-print-button oksia-browser-action oksia-browser-action--agency-secondary"><?php esc_html_e('Finalize Quote', 'oksia-smart-itinerary-agent'); ?></button>
                        <?php endif; ?>
                        <button type="button" class="oksia-print-button oksia-browser-action oksia-browser-action--agency-accent oksia-share-link-button" data-oksia-share-url="<?php echo esc_url($share_url); ?>"><?php esc_html_e('Share Link', 'oksia-smart-itinerary-agent'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($include_print_button) : ?>
            <script>
                (function () {
                    if (window.__oksiaShareLinkBound) {
                        return;
                    }
                    window.__oksiaShareLinkBound = true;

                    document.addEventListener('click', function (event) {
                        var button = event.target && event.target.closest ? event.target.closest('.oksia-share-link-button') : null;
                        if (!button) {
                            return;
                        }

                        var url = button.getAttribute('data-oksia-share-url') || '';
                        if (!url) {
                            return;
                        }

                        var done = function () {
                            var original = button.textContent;
                            button.textContent = 'Copied';
                            setTimeout(function () {
                                button.textContent = original;
                            }, 1200);
                        };

                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(url).then(done).catch(function () {
                                window.prompt('Copy this link', url);
                            });
                            return;
                        }

                        window.prompt('Copy this link', url);
                    });
                })();
            </script>
        <?php endif; ?>
        <section class="oksia-brochure oksia-quote-layout" style="--oksia-primary: <?php echo esc_attr($primary_color); ?>; --oksia-secondary: <?php echo esc_attr($secondary_color); ?>; --oksia-accent: <?php echo esc_attr($accent_color); ?>; --oksia-theme-primary: <?php echo esc_attr($primary_color); ?>; --oksia-theme-secondary: <?php echo esc_attr($secondary_color); ?>; --oksia-theme-accent: <?php echo esc_attr($accent_color); ?>; --oksia-on-primary: <?php echo esc_attr($on_primary); ?>; --oksia-primary-soft: <?php echo esc_attr($primary_soft); ?>; --oksia-primary-softer: <?php echo esc_attr($primary_softer); ?>; --oksia-cell-heading-color: <?php echo esc_attr($cell_heading_color); ?>; --oksia-border-soft: <?php echo esc_attr($border_soft); ?>;">
            <div class="oksia-quote-page">
                <?php echo $this->render_quote_header($brand_logo, $brand_name, $billing_company, $disclaimer_text, $quote_id, $version_label, $client_name, $trip['destination'], sprintf('%dN / %dD', (int) $trip['total_nights'], $total_days), (string) $trip['travelers']); ?>

                <?php echo $this->render_quote_intro($client_name, $operational['summary'], $footer_text); ?>
                <?php echo $this->render_confirmation_summary_block($post_id); ?>

                <div class="oksia-sheet-grid">
                    <?php echo $this->render_trip_details_box($trip); ?>
                    <?php echo $this->render_accommodation_box($quote, $trip['trip_type'], $meal_transfers_value); ?>
                </div>

        <?php if (!empty($hotel_plan)) : ?>
                    <section class="oksia-sheet-block oksia-sheet-block--full">
                        <h3><?php esc_html_e('Hotel Details', 'oksia-smart-itinerary-agent'); ?></h3>
                        <table class="oksia-sheet-table oksia-sheet-table--hotels">
                            <colgroup>
                                <col class="oksia-hotel-col oksia-hotel-col--nights" />
                                <col class="oksia-hotel-col oksia-hotel-col--name" />
                                <col class="oksia-hotel-col oksia-hotel-col--city" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Nights', 'oksia-smart-itinerary-agent'); ?></th>
                                    <th><?php esc_html_e('Hotel Name', 'oksia-smart-itinerary-agent'); ?></th>
                                    <th><?php esc_html_e('City', 'oksia-smart-itinerary-agent'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hotel_plan as $stay) : ?>
                                    <tr>
                                        <td><?php echo esc_html((string) ($stay['nights'] ?? '')); ?></td>
                                        <td><?php echo esc_html($stay['hotel'] ?? ''); ?></td>
                                        <td><?php echo esc_html($stay['city'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                <?php endif; ?>

            </div>

            <div class="oksia-quote-page oksia-quote-page--force-break">
                <div class="oksia-sheet-grid">
                    <?php echo $this->render_transfers_box($quote); ?>
                    <?php echo $this->render_rates_box($quote, $show_inr_reference, $pdf_mode); ?>
                </div>

                <div class="oksia-sheet-grid oksia-sheet-grid--notes">
                    <section class="oksia-sheet-block">
                        <h3><?php esc_html_e('Inclusions', 'oksia-smart-itinerary-agent'); ?></h3>
                        <?php echo $this->render_multiline_list($operational['inclusions']); ?>
                    </section>
                    <section class="oksia-sheet-block">
                        <h3><?php esc_html_e('Exclusion', 'oksia-smart-itinerary-agent'); ?></h3>
                        <?php echo $this->render_multiline_list($this->build_exclusion_list($operational['exclusions'], $trip, $quote)); ?>
                    </section>
                </div>

                <div class="oksia-policy-grid oksia-policy-grid--sheet">
                    <?php echo $this->render_policy_card(__('Child Policy', 'oksia-smart-itinerary-agent'), $operational['child_policy']); ?>
                    <?php echo $this->render_policy_card(__('Booking Policy', 'oksia-smart-itinerary-agent'), $operational['booking_policy']); ?>
                    <?php echo $this->render_policy_card(__('Cancellation Policy', 'oksia-smart-itinerary-agent'), $operational['cancellation_policy']); ?>
                    <?php if (!empty($operational['important_notes'])) : ?>
                        <?php echo $this->render_policy_card(__('Important Notes', 'oksia-smart-itinerary-agent'), $operational['important_notes']); ?>
                    <?php endif; ?>
                </div>

            </div>

            <?php if (!empty($days)) : ?>
                <div class="oksia-quote-page oksia-quote-page--schedule oksia-quote-page--force-break">
                    <section class="oksia-sheet-block oksia-sheet-block--full">
                        <h3><?php esc_html_e('Day wise Tentative Schedule', 'oksia-smart-itinerary-agent'); ?></h3>
                        <table class="oksia-sheet-table oksia-sheet-table--days">
                            <tbody>
                                <?php foreach ($days as $index => $day) : ?>
                                    <?php $day_number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?>
                                    <tr>
                                        <td class="oksia-day-index"><?php echo esc_html('Day ' . $day_number); ?></td>
                                        <td class="oksia-day-detail">
                                            <div class="oksia-day-copy">
                                                <?php if (!empty($day['location'])) : ?>
                                                    <div class="oksia-day-location"><?php echo esc_html(sprintf(__('Today\'s Location: %s', 'oksia-smart-itinerary-agent'), $day['location'])); ?></div>
                                                <?php endif; ?>
                                                <strong><?php echo esc_html($this->normalize_day_title($day['title'] ?? '', $day_number)); ?></strong>
                                                <div class="oksia-day-description"><?php echo esc_html($day['description'] ?? ''); ?></div>
                                            </div>
                                        </td>
                                        <td class="oksia-day-visual">
                                            <?php if (!empty($day['image_url'])) : ?>
                                                <img src="<?php echo esc_url($day['image_url']); ?>" alt="<?php echo esc_attr($day['title'] ?? ''); ?>" />
                                            <?php else : ?>
                                                <div class="oksia-day-visual__placeholder"><?php esc_html_e('Image coming soon', 'oksia-smart-itinerary-agent'); ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                </div>
            <?php endif; ?>

            <?php echo $this->render_quote_footer($brand_name, $brand_phone, $billing_email, $brand_location); ?>
        </section>
        <?php if ($include_print_button && $overlay_open) : ?>
            <?php
            $close_overlay_url = remove_query_arg(array('oksia_quote_workflow', 'oksia_quote_error_fields', 'oksia_quote_notice'), $share_url);
            $overlay_title = ('cancelled' === $workflow_mode) ? __('Cancel this quote', 'oksia-smart-itinerary-agent') : __('Confirm this quote', 'oksia-smart-itinerary-agent');
            $overlay_note = ('cancelled' === $workflow_mode)
                ? __('Cancelled quotes are final and cannot be reopened.', 'oksia-smart-itinerary-agent')
                : __('Use the fields below for agency review. Only the hotel confirmation / PNR note is meant for the client PDF; the other fields stay internal.', 'oksia-smart-itinerary-agent');
            ?>
            <div class="oksia-review-overlay" role="dialog" aria-modal="true" aria-labelledby="oksia-review-overlay-title" style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 12px;">
                <a class="oksia-review-overlay__backdrop" href="<?php echo esc_url($close_overlay_url); ?>" aria-label="<?php esc_attr_e('Close review overlay', 'oksia-smart-itinerary-agent'); ?>" style="position: absolute; inset: 0; background: rgba(10, 20, 36, 0.74); backdrop-filter: blur(4px);"></a>
                <div class="oksia-preview-card" style="position: relative; z-index: 1; width: min(720px, calc(100vw - 24px)); max-height: calc(100vh - 24px); overflow: auto; box-shadow: 0 24px 48px rgba(15, 23, 42, 0.28); border-radius: 18px;">
                <div class="oksia-preview-bar" style="margin: -18px -18px 14px; border-radius: 18px 18px 0 0;">
                        <div class="oksia-preview-bar__title"><?php echo esc_html($title); ?></div>
                        <a class="oksia-review-overlay__close" href="<?php echo esc_url($close_overlay_url); ?>" aria-label="<?php esc_attr_e('Close review overlay', 'oksia-smart-itinerary-agent'); ?>" title="<?php esc_attr_e('Close', 'oksia-smart-itinerary-agent'); ?>">
                            <span aria-hidden="true">×</span>
                        </a>
                    </div>
                    <h3 id="oksia-review-overlay-title" class="oksia-preview-card__title"><?php echo esc_html($overlay_title); ?></h3>
                    <p class="oksia-preview-card__note"><?php echo esc_html($overlay_note); ?></p>

                    <?php if ('cancelled' === $workflow_mode) : ?>
                        <form method="post" action="<?php echo esc_url(get_permalink($post_id)); ?>">
                            <?php wp_nonce_field('oksia_frontend_quote_action_' . $post_id, 'oksia_frontend_nonce'); ?>
                            <input type="hidden" name="oksia_frontend_quote_id" value="<?php echo esc_attr((string) $post_id); ?>" />
                            <div class="oksia-preview-grid">
                                <div class="oksia-preview-field <?php echo in_array('cancel_reason', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?> oksia-preview-field--full">
                                    <label for="oksia_cancel_reason"><?php esc_html_e('Cancellation reason', 'oksia-smart-itinerary-agent'); ?></label>
                                    <select id="oksia_cancel_reason" name="oksia_cancel_reason">
                                        <option value=""><?php esc_html_e('Select reason', 'oksia-smart-itinerary-agent'); ?></option>
                                        <option value="Budget Issues"><?php esc_html_e('Budget Issues', 'oksia-smart-itinerary-agent'); ?></option>
                                        <option value="Ghost Client"><?php esc_html_e('Ghost Client', 'oksia-smart-itinerary-agent'); ?></option>
                                        <option value="Confirmed Outside"><?php esc_html_e('Confirmed Outside', 'oksia-smart-itinerary-agent'); ?></option>
                                        <option value="Other"><?php esc_html_e('Other', 'oksia-smart-itinerary-agent'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="oksia-preview-footer">
                                <div class="oksia-preview-footer__actions">
                                    <button type="submit" name="oksia_frontend_quote_action" value="cancelled" class="oksia-preview-submit oksia-preview-submit--danger"><?php esc_html_e('Cancel Quote', 'oksia-smart-itinerary-agent'); ?></button>
                                </div>
                            </div>
                        </form>
                    <?php else : ?>
                        <form method="post" action="<?php echo esc_url(get_permalink($post_id)); ?>">
                            <?php wp_nonce_field('oksia_frontend_quote_action_' . $post_id, 'oksia_frontend_nonce'); ?>
                            <input type="hidden" name="oksia_frontend_quote_id" value="<?php echo esc_attr((string) $post_id); ?>" />
                            <div class="oksia-preview-grid">
                                <div class="oksia-preview-field <?php echo in_array('confirmation_note', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?> oksia-preview-field--full">
                                    <label for="oksia_confirmation_note"><?php esc_html_e('Hotel Confirmation / PNR', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" id="oksia_confirmation_note" name="oksia_confirmation_note" placeholder="Hotel confirmation number or PNR" />
                                </div>
                                <div class="oksia-preview-field <?php echo in_array('confirmed_by', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                    <label for="oksia_confirmed_by"><?php esc_html_e('Confirmed by', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" id="oksia_confirmed_by" name="oksia_confirmed_by" placeholder="Main admin / Manager name" />
                                </div>
                                <div class="oksia-preview-field <?php echo in_array('dmc_name', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                    <label for="oksia_dmc_name"><?php esc_html_e('DMC Name', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" id="oksia_dmc_name" name="oksia_dmc_name" placeholder="DMC company name" />
                                </div>
                                <div class="oksia-preview-field <?php echo in_array('dmc_number', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                    <label for="oksia_dmc_number"><?php esc_html_e('DMC Number', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" id="oksia_dmc_number" name="oksia_dmc_number" placeholder="DMC contact number" />
                                </div>
                                <div class="oksia-preview-field <?php echo in_array('driver_name', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                    <label for="oksia_driver_name"><?php esc_html_e('Driver Name', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" id="oksia_driver_name" name="oksia_driver_name" placeholder="Driver assigned to the trip" />
                                </div>
                                <div class="oksia-preview-field <?php echo in_array('dmc_quote_id', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                    <label for="oksia_dmc_quote_id"><?php esc_html_e('DMC Quote ID', 'oksia-smart-itinerary-agent'); ?></label>
                                    <input type="text" id="oksia_dmc_quote_id" name="oksia_dmc_quote_id" placeholder="Agency internal reference" />
                                </div>
                            </div>
                            <div class="oksia-preview-footer">
                                <div class="oksia-preview-footer__actions">
                                    <button type="submit" name="oksia_frontend_quote_action" value="confirmed" class="oksia-preview-submit oksia-preview-submit--accent"><?php esc_html_e('Confirm & Download', 'oksia-smart-itinerary-agent'); ?></button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    public function generate_pdf_file($post_id) {
        // Try multiple temp directory paths for LocalWP compatibility
        $temp_dirs = array(
            trailingslashit(sys_get_temp_dir()) . 'oksia-pdf',
            WP_CONTENT_DIR . '/oksia-temp/pdf',
            ABSPATH . 'wp-content/oksia-temp/pdf',
        );

        $temp_dir = '';
        foreach ($temp_dirs as $dir) {
            if (@is_writable(dirname($dir)) || @is_writable($dir)) {
                $temp_dir = $dir;
                break;
            }
        }

        // Fallback to sys_get_temp_dir
        if (empty($temp_dir)) {
            $temp_dir = trailingslashit(sys_get_temp_dir()) . 'oksia-pdf';
        }

        if (!file_exists($temp_dir)) {
            $mkdir_result = @wp_mkdir_p($temp_dir);
            if (!$mkdir_result) {
                return new WP_Error(
                    'oksia_pdf_temp_dir',
                    sprintf(
                        __('PDF export failed: Could not create temp directory at %s. Please check directory permissions.', 'oksia-smart-itinerary-agent'),
                        $temp_dir
                    )
                );
            }
        }

        if (!is_writable($temp_dir)) {
            return new WP_Error(
                'oksia_pdf_temp_writable',
                sprintf(
                    __('PDF export failed: Temp directory %s is not writable. Please check permissions.', 'oksia-smart-itinerary-agent'),
                    $temp_dir
                )
            );
        }

        $stamp = wp_generate_password(10, false, false);
        $html_file = trailingslashit($temp_dir) . 'quote-' . $post_id . '-' . $stamp . '.html';
        $pdf_file = trailingslashit($temp_dir) . 'quote-' . $post_id . '-' . $stamp . '.pdf';
        $browsers = $this->find_browser_executables();

        if (empty($browsers)) {
            return new WP_Error(
                'oksia_pdf_browser_missing',
                __('PDF export is unavailable because Chrome or Chromium was not found on this server. Please install Google Chrome, Chromium, or Microsoft Edge.', 'oksia-smart-itinerary-agent')
            );
        }

        if (!function_exists('exec')) {
            return new WP_Error(
                'oksia_pdf_exec_disabled',
                __('PDF export is unavailable because command execution (exec) is disabled on this server.', 'oksia-smart-itinerary-agent')
            );
        }

        $html = $this->build_pdf_html($post_id);
        if ('' === trim((string) $html)) {
            return new WP_Error(
                'oksia_pdf_html_empty',
                __('PDF export failed because the quote HTML could not be built.', 'oksia-smart-itinerary-agent')
            );
        }

        $write_result = @file_put_contents($html_file, $html);
        if (!$write_result || !file_exists($html_file)) {
            return new WP_Error(
                'oksia_pdf_html_write',
                sprintf(
                    __('PDF export failed: Could not write HTML file to %s', 'oksia-smart-itinerary-agent'),
                    $html_file
                )
            );
        }

        $last_error = '';
        foreach ($browsers as $browser) {
            // Build correct file:// URL for the OS
            $file_url = $this->build_file_url($html_file);

            if (!$file_url) {
                $last_error = "Could not build valid file URL for: $html_file";
                continue;
            }

            $command = sprintf(
                '"%s" --headless --disable-gpu --no-first-run --no-default-browser-check --hide-scrollbars --allow-file-access-from-files --run-all-compositor-stages-before-draw --virtual-time-budget=5000 --no-pdf-header-footer --print-to-pdf="%s" "%s"',
                $browser,
                $pdf_file,
                $file_url
            );

            $output = array();
            $exit_code = 0;
            @exec($command . ' 2>&1', $output, $exit_code);

            if (0 === $exit_code && file_exists($pdf_file) && filesize($pdf_file) > 0) {
                @unlink($html_file);

                return array(
                    'file' => $pdf_file,
                    'filename' => $this->build_pdf_filename($post_id),
                );
            }

            $last_error = trim(implode("\n", array_filter(array_map('trim', $output))));
            @unlink($pdf_file);
        }

        @unlink($html_file);

        if ('' !== $last_error) {
            return new WP_Error(
                'oksia_pdf_failed',
                sprintf(
                    __('PDF export failed while generating the file. Error: %s', 'oksia-smart-itinerary-agent'),
                    $last_error
                )
            );
        }

        return new WP_Error(
            'oksia_pdf_failed',
            __('PDF export failed while generating the file. No browser could generate the PDF.', 'oksia-smart-itinerary-agent')
        );
    }

    /**
     * Build a proper file:// URL for different operating systems
     */
    private function build_file_url($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($is_windows) {
            // Convert Windows path to file URL
            // C:\path\to\file → file:///C:/path/to/file
            $file_path = str_replace('\\', '/', $file_path);

            // Handle UNC paths (\\server\share)
            if (strpos($file_path, '//') === 0) {
                return 'file:' . $file_path;
            }

            // Regular Windows path
            if (preg_match('/^([a-zA-Z]):(.*)/', $file_path, $matches)) {
                return 'file:///' . $matches[1] . ':' . $matches[2];
            }

            return 'file:///' . $file_path;
        } else {
            // Unix-like systems (Linux, macOS)
            return 'file://' . $file_path;
        }
    }

    private function generate_pdf_download($post_id, $inline = false) {
        $result = $this->generate_pdf_file($post_id);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        while (ob_get_level()) {
            ob_end_clean();
        }
        nocache_headers();
        status_header(200);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $result['filename'] . '"');
        header('Content-Length: ' . filesize($result['file']));
        readfile($result['file']);

        @unlink($result['file']);
    }

    private function render_pdf_preview_page($post_id) {
        $share_url = class_exists('OKSIA_Admin') ? OKSIA_Admin::get_quote_share_url($post_id) : get_permalink($post_id);
        if (!$share_url) {
            $share_url = get_permalink($post_id);
        }
        $download_url = add_query_arg(
            array(
                'oksia_download_pdf' => $post_id,
                'oksia_pdf_inline' => 1,
            ),
            $share_url
        );
        $download_file_url = add_query_arg('oksia_download_pdf', $post_id, $share_url);
        $clean_url = $share_url;
        $title = get_the_title($post_id);
        if ('' === trim((string) $title)) {
            $title = __('Draft Preview', 'oksia-smart-itinerary-agent');
        }
        $quote_stage = trim((string) get_post_meta($post_id, '_oksia_quote_stage', true));
        $frontend_notice = sanitize_key((string) ($_GET['oksia_quote_notice'] ?? ''));
        $workflow_mode = sanitize_key((string) ($_GET['oksia_quote_workflow'] ?? ''));
        $error_fields = isset($_GET['oksia_quote_error_fields']) ? array_filter(array_map('sanitize_key', explode(',', wp_unslash($_GET['oksia_quote_error_fields'])))) : array();
        $can_manage_finalized_quotes = current_user_can('manage_options') || current_user_can('edit_users');
        $colors = $this->get_quote_color_palette($post_id);
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($title); ?></title>
            <style>
                html, body { height: 100%; margin: 0; }
                body { font-family: Arial, sans-serif; background: #f4f7fb; color: var(--oksia-primary, #000066); }
                .oksia-preview-shell { min-height: 100%; display: flex; flex-direction: column; }
                .oksia-preview-bar {
                    display: flex;
                    gap: 12px;
                    align-items: center;
                    justify-content: space-between;
                    padding: 14px 20px;
                    background: var(--oksia-primary, #000066);
                    color: #fff;
                    flex-wrap: wrap;
                }
                .oksia-preview-bar__title { font-weight: 700; }
                .oksia-preview-bar__actions { display: flex; gap: 10px; flex-wrap: wrap; }
                .oksia-preview-btn, .oksia-preview-submit {
                    display: inline-block;
                    padding: 10px 14px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 700;
                    border: 1px solid transparent;
                    cursor: pointer;
                }
                .oksia-preview-btn--light, .oksia-preview-submit--light { background: #ffffff; color: var(--oksia-primary, #000066); }
                .oksia-preview-btn--accent, .oksia-preview-submit--accent { background: var(--oksia-accent, #99FFFF); color: #fff; }
                .oksia-preview-btn--danger, .oksia-preview-submit--danger { background: #d63638; color: #fff; }
                .oksia-preview-content { padding: 16px 20px 0; display: grid; gap: 16px; }
                .oksia-preview-card {
                    background: #fff;
                    border: 1px solid #d6e4f3;
                    border-radius: 16px;
                    padding: 16px;
                    box-shadow: 0 6px 20px rgba(23, 63, 104, 0.06);
                }
                .oksia-preview-card__title { margin: 0 0 10px; font-size: 16px; font-weight: 700; }
                .oksia-preview-card__note { margin: 0 0 12px; font-size: 13px; color: #5f7391; }
                .oksia-preview-status {
                    padding: 10px 12px;
                    border-radius: 10px;
                    margin-bottom: 12px;
                    background: #f3f8fd;
                    border: 1px solid #d6e4f3;
                }
                .oksia-preview-status--error { background: #fff5f5; border-color: #f0b4b5; color: #7f1d1d; }
                .oksia-preview-status--success { background: #ecfdf5; border-color: #b7ebc7; color: #14532d; }
                .oksia-preview-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
                .oksia-preview-field { display: grid; gap: 6px; }
                .oksia-preview-field label { font-weight: 700; font-size: 13px; }
                .oksia-preview-field input,
                .oksia-preview-field select {
                    width: 100%;
                    border: 1px solid #bfd1e8;
                    border-radius: 10px;
                    padding: 10px 12px;
                    font: inherit;
                    color: inherit;
                    background: #fff;
                }
                .oksia-preview-field--full { grid-column: 1 / -1; }
                .oksia-preview-field--error input,
                .oksia-preview-field--error select {
                    border-color: #d63638;
                    box-shadow: 0 0 0 1px #d63638 inset;
                    background: #fff5f5;
                }
                .oksia-preview-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
                .oksia-preview-frame { width: 100%; height: calc(100vh - 62px); border: 0; display: block; background: #fff; }
                @media (max-width: 900px) {
                    .oksia-preview-grid { grid-template-columns: 1fr; }
                    .oksia-preview-frame { height: 72vh; }
                }
            </style>
        </head>
        <body style="--oksia-primary: <?php echo esc_attr($colors['primary']); ?>; --oksia-secondary: <?php echo esc_attr($colors['secondary']); ?>; --oksia-accent: <?php echo esc_attr($colors['accent']); ?>;">
            <div class="oksia-preview-shell" style="--oksia-primary: <?php echo esc_attr($colors['primary']); ?>; --oksia-secondary: <?php echo esc_attr($colors['secondary']); ?>; --oksia-accent: <?php echo esc_attr($colors['accent']); ?>;">
                <div class="oksia-preview-bar">
                    <div class="oksia-preview-bar__title"><?php echo esc_html($title); ?></div>
                    <div class="oksia-preview-bar__actions">
                        <a class="oksia-preview-btn oksia-preview-btn--light" href="<?php echo esc_url($clean_url); ?>"><?php esc_html_e('Back to Quote', 'oksia-smart-itinerary-agent'); ?></a>
                        <a class="oksia-preview-btn oksia-preview-btn--accent" href="<?php echo esc_url($download_file_url); ?>"><?php esc_html_e('Download PDF', 'oksia-smart-itinerary-agent'); ?></a>
                    </div>
                </div>
                <div class="oksia-preview-content">
                    <?php if ('error' === $frontend_notice) : ?>
                        <div class="oksia-preview-status oksia-preview-status--error"><?php esc_html_e('Please complete the missing fields below and try again.', 'oksia-smart-itinerary-agent'); ?></div>
                    <?php elseif ('confirmed' === $frontend_notice) : ?>
                        <div class="oksia-preview-status oksia-preview-status--success"><?php esc_html_e('Quote confirmed successfully.', 'oksia-smart-itinerary-agent'); ?></div>
                    <?php elseif ('reopen' === $frontend_notice) : ?>
                        <div class="oksia-preview-status oksia-preview-status--success"><?php esc_html_e('Quote reopened for editing.', 'oksia-smart-itinerary-agent'); ?></div>
                    <?php elseif ('cancelled' === $frontend_notice) : ?>
                        <div class="oksia-preview-status oksia-preview-status--success"><?php esc_html_e('Quote cancelled and locked.', 'oksia-smart-itinerary-agent'); ?></div>
                    <?php endif; ?>

                    <?php if ('confirmed' === $quote_stage || 'cancelled' === $quote_stage) : ?>
                        <div class="oksia-preview-card">
                            <h3 class="oksia-preview-card__title"><?php esc_html_e('Workflow locked', 'oksia-smart-itinerary-agent'); ?></h3>
                            <p class="oksia-preview-card__note"><?php esc_html_e('This quote has already been processed. Only authorized admins can reopen confirmed quotes, and cancelled quotes stay closed forever.', 'oksia-smart-itinerary-agent'); ?></p>
                            <?php if ('confirmed' === $quote_stage && $can_manage_finalized_quotes) : ?>
                                <form method="post" action="<?php echo esc_url(get_permalink($post_id)); ?>">
                                    <?php wp_nonce_field('oksia_frontend_quote_action_' . $post_id, 'oksia_frontend_nonce'); ?>
                                    <input type="hidden" name="oksia_frontend_quote_id" value="<?php echo esc_attr((string) $post_id); ?>" />
                                    <input type="hidden" name="oksia_frontend_quote_action" value="reopen" />
                                    <div class="oksia-preview-actions">
                                        <button type="submit" class="oksia-preview-submit oksia-preview-submit--light"><?php esc_html_e('Reopen Draft', 'oksia-smart-itinerary-agent'); ?></button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php elseif ('cancelled' === $workflow_mode) : ?>
                        <div class="oksia-preview-card">
                            <h3 class="oksia-preview-card__title"><?php esc_html_e('Cancel this quote', 'oksia-smart-itinerary-agent'); ?></h3>
                            <p class="oksia-preview-card__note"><?php esc_html_e('Cancelled quotes are final and cannot be reopened.', 'oksia-smart-itinerary-agent'); ?></p>
                            <form method="post" action="<?php echo esc_url(get_permalink($post_id)); ?>">
                                <?php wp_nonce_field('oksia_frontend_quote_action_' . $post_id, 'oksia_frontend_nonce'); ?>
                                <input type="hidden" name="oksia_frontend_quote_id" value="<?php echo esc_attr((string) $post_id); ?>" />
                                <div class="oksia-preview-grid">
                                    <div class="oksia-preview-field <?php echo in_array('cancel_reason', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?> oksia-preview-field--full">
                                        <label for="oksia_cancel_reason"><?php esc_html_e('Cancellation reason', 'oksia-smart-itinerary-agent'); ?></label>
                                        <select id="oksia_cancel_reason" name="oksia_cancel_reason">
                                            <option value=""><?php esc_html_e('Select reason', 'oksia-smart-itinerary-agent'); ?></option>
                                            <option value="Budget Issues"><?php esc_html_e('Budget Issues', 'oksia-smart-itinerary-agent'); ?></option>
                                            <option value="Ghost Client"><?php esc_html_e('Ghost Client', 'oksia-smart-itinerary-agent'); ?></option>
                                            <option value="Confirmed Outside"><?php esc_html_e('Confirmed Outside', 'oksia-smart-itinerary-agent'); ?></option>
                                            <option value="Other"><?php esc_html_e('Other', 'oksia-smart-itinerary-agent'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="oksia-preview-actions">
                                    <button type="submit" name="oksia_frontend_quote_action" value="cancelled" class="oksia-preview-submit oksia-preview-submit--danger"><?php esc_html_e('Cancel Quote', 'oksia-smart-itinerary-agent'); ?></button>
                                </div>
                            </form>
                        </div>
                    <?php elseif ('confirmed' === $workflow_mode) : ?>
                        <div class="oksia-preview-card">
                            <h3 class="oksia-preview-card__title"><?php esc_html_e('Confirm this quote', 'oksia-smart-itinerary-agent'); ?></h3>
                            <p class="oksia-preview-card__note"><?php esc_html_e('Use the fields below for agency review. Only the hotel confirmation / PNR note is meant for the client PDF; the other fields stay internal.', 'oksia-smart-itinerary-agent'); ?></p>
                            <form method="post" action="<?php echo esc_url(get_permalink($post_id)); ?>">
                                <?php wp_nonce_field('oksia_frontend_quote_action_' . $post_id, 'oksia_frontend_nonce'); ?>
                                <input type="hidden" name="oksia_frontend_quote_id" value="<?php echo esc_attr((string) $post_id); ?>" />
                                <div class="oksia-preview-grid">
                                    <div class="oksia-preview-field <?php echo in_array('confirmation_note', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?> oksia-preview-field--full">
                                        <label for="oksia_confirmation_note"><?php esc_html_e('Hotel Confirmation / PNR', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_confirmation_note" name="oksia_confirmation_note" placeholder="Hotel confirmation number or PNR" />
                                    </div>
                                    <div class="oksia-preview-field <?php echo in_array('confirmed_by', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                        <label for="oksia_confirmed_by"><?php esc_html_e('Confirmed by', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_confirmed_by" name="oksia_confirmed_by" placeholder="Main admin / Manager name" />
                                    </div>
                                    <div class="oksia-preview-field <?php echo in_array('dmc_name', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                        <label for="oksia_dmc_name"><?php esc_html_e('DMC Name', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_dmc_name" name="oksia_dmc_name" placeholder="DMC company name" />
                                    </div>
                                    <div class="oksia-preview-field <?php echo in_array('dmc_number', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                        <label for="oksia_dmc_number"><?php esc_html_e('DMC Number', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_dmc_number" name="oksia_dmc_number" placeholder="DMC contact number" />
                                    </div>
                                    <div class="oksia-preview-field <?php echo in_array('driver_name', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                        <label for="oksia_driver_name"><?php esc_html_e('Driver Name', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_driver_name" name="oksia_driver_name" placeholder="Driver assigned to the trip" />
                                    </div>
                                    <div class="oksia-preview-field <?php echo in_array('dmc_quote_id', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                        <label for="oksia_dmc_quote_id"><?php esc_html_e('DMC Quote ID', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_dmc_quote_id" name="oksia_dmc_quote_id" placeholder="Agency internal reference" />
                                    </div>
                                </div>
                                <div class="oksia-preview-actions">
                                    <button type="submit" name="oksia_frontend_quote_action" value="confirmed" class="oksia-preview-submit oksia-preview-submit--accent"><?php esc_html_e('Confirm Quote', 'oksia-smart-itinerary-agent'); ?></button>
                                </div>
                            </form>
                        </div>
                    <?php else : ?>
                        <div class="oksia-preview-card">
                            <h3 class="oksia-preview-card__title"><?php esc_html_e('Confirm this quote', 'oksia-smart-itinerary-agent'); ?></h3>
                            <p class="oksia-preview-card__note"><?php esc_html_e('Use the fields below for agency review. Only the hotel confirmation / PNR note is meant for the client PDF; the other fields stay internal.', 'oksia-smart-itinerary-agent'); ?></p>
                            <form method="post" action="<?php echo esc_url(get_permalink($post_id)); ?>">
                                <?php wp_nonce_field('oksia_frontend_quote_action_' . $post_id, 'oksia_frontend_nonce'); ?>
                                <input type="hidden" name="oksia_frontend_quote_id" value="<?php echo esc_attr((string) $post_id); ?>" />
                                <div class="oksia-preview-grid">
                                    <div class="oksia-preview-field <?php echo in_array('confirmation_note', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?> oksia-preview-field--full">
                                        <label for="oksia_confirmation_note"><?php esc_html_e('Hotel Confirmation / PNR', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_confirmation_note" name="oksia_confirmation_note" placeholder="Hotel confirmation number or PNR" />
                                    </div>
                                    <div class="oksia-preview-field <?php echo in_array('confirmed_by', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                        <label for="oksia_confirmed_by"><?php esc_html_e('Confirmed by', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_confirmed_by" name="oksia_confirmed_by" placeholder="Main admin / Manager name" />
                                    </div>
                                    <div class="oksia-preview-field <?php echo in_array('dmc_name', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                        <label for="oksia_dmc_name"><?php esc_html_e('DMC Name', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_dmc_name" name="oksia_dmc_name" placeholder="DMC company name" />
                                    </div>
                                    <div class="oksia-preview-field <?php echo in_array('dmc_number', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                        <label for="oksia_dmc_number"><?php esc_html_e('DMC Number', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_dmc_number" name="oksia_dmc_number" placeholder="DMC contact number" />
                                    </div>
                                    <div class="oksia-preview-field <?php echo in_array('driver_name', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                        <label for="oksia_driver_name"><?php esc_html_e('Driver Name', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_driver_name" name="oksia_driver_name" placeholder="Driver assigned to the trip" />
                                    </div>
                                    <div class="oksia-preview-field <?php echo in_array('dmc_quote_id', $error_fields, true) ? 'oksia-preview-field--error' : ''; ?>">
                                        <label for="oksia_dmc_quote_id"><?php esc_html_e('DMC Quote ID', 'oksia-smart-itinerary-agent'); ?></label>
                                        <input type="text" id="oksia_dmc_quote_id" name="oksia_dmc_quote_id" placeholder="Agency internal reference" />
                                    </div>
                                </div>
                                <div class="oksia-preview-actions">
                                    <button type="submit" name="oksia_frontend_quote_action" value="confirmed" class="oksia-preview-submit oksia-preview-submit--accent"><?php esc_html_e('Confirm Quote', 'oksia-smart-itinerary-agent'); ?></button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <iframe class="oksia-preview-frame" src="<?php echo esc_url($download_url); ?>" title="<?php echo esc_attr($title); ?>"></iframe>
                <script>
                    history.replaceState({}, document.title, <?php echo wp_json_encode($clean_url); ?>);
                    (function () {
                        var firstError = document.querySelector('.oksia-preview-field--error input, .oksia-preview-field--error select');
                        if (firstError && firstError.focus) {
                            firstError.focus();
                        }
                    }());
                </script>
            </div>
        </body>
        </html>
        <?php
    }

    private function build_pdf_filename($post_id) {
        $trip = wp_parse_args((array) get_post_meta($post_id, '_oksia_trip_overview', true), array('salutation' => 'Mr', 'client_name' => ''));
        $quote_id = (string) get_post_meta($post_id, '_oksia_quote_id', true);
        $client_name = trim(($trip['salutation'] ? $trip['salutation'] . ' ' : '') . $trip['client_name']);
        $client_name = preg_replace('/[^A-Za-z0-9]+/', '-', $client_name);
        $client_name = trim((string) $client_name, '-');
        $quote_id = preg_replace('/[^A-Za-z0-9]+/', '-', $quote_id);
        $quote_id = trim((string) $quote_id, '-');

        if ('' === $client_name) {
            $client_name = 'Quote';
        }
        if ('' === $quote_id) {
            $quote_id = (string) $post_id;
        }

        return $client_name . '-' . $quote_id . '.pdf';
    }

    private function find_browser_executables() {
        $found = array();

        // Detect OS
        $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // Windows candidates
        if ($is_windows) {
            $candidates = array(
                'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
                'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            );

            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $found[] = $candidate;
                }
            }
        } else {
            // Linux/Mac candidates
            $candidates = array(
                '/usr/bin/google-chrome',
                '/usr/bin/chromium-browser',
                '/usr/bin/chromium',
                '/snap/bin/chromium',
                '/usr/bin/chrome',
                '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
                '/Applications/Chromium.app/Contents/MacOS/Chromium',
            );

            foreach ($candidates as $candidate) {
                if (file_exists($candidate) && is_executable($candidate)) {
                    $found[] = $candidate;
                }
            }
        }

        // Try to find chrome/chromium in PATH as fallback
        if (empty($found)) {
            $path_commands = $is_windows
                ? array('where chrome', 'where chromium', 'where edge')
                : array('which google-chrome', 'which chromium-browser', 'which chromium', 'which chrome');

            foreach ($path_commands as $cmd) {
                $output = array();
                @exec($cmd . ' 2>/dev/null', $output);
                if (!empty($output[0]) && is_executable($output[0])) {
                    $found[] = trim($output[0]);
                    break;
                }
            }
        }

        return $found;
    }

    private function find_browser_executable() {
        $found = $this->find_browser_executables();
        if (!empty($found)) {
            return $found[0];
        }

        return '';
    }

    private function build_pdf_html($post_id) {
        return $this->build_quote_template_document($post_id, false);
    }

    private function replace_output_template_tokens($post_id, $markup) {
        $post_id = absint($post_id);
        if (!$post_id || '' === trim((string) $markup)) {
            return (string) $markup;
        }

        $trip = wp_parse_args((array) get_post_meta($post_id, '_oksia_trip_overview', true), array(
            'destination' => '',
            'start_date' => '',
            'end_date' => '',
            'total_nights' => 0,
            'travelers' => 0,
            'salutation' => 'Mr',
            'client_name' => '',
            'trip_type' => 'Domestic',
            'adults' => 0,
            'adult_with_bed' => 0,
            'child_without_bed' => 0,
        ));
        $quote = wp_parse_args((array) get_post_meta($post_id, '_oksia_quote_details', true), array(
            'hotel_category' => '',
            'occupancy' => '',
            'rooms' => '',
            'meal_plan' => '',
            'meal_transfers' => '',
            'pickup_from' => '',
            'drop_to' => '',
            'first_transfer' => '',
            'last_transfer' => '',
            'sightseeing_vehicle' => '',
            'vehicle_type' => '',
            'currency' => 'INR',
            'exchange_rate' => '',
            'transaction_cost' => '1.9',
            'additional_cost' => '0',
            'effective_rate' => '',
            'adult_rate' => '',
            'with_bed_rate' => '',
            'child_rate' => '',
            'adult_rate_quote' => '',
            'with_bed_rate_quote' => '',
            'child_rate_quote' => '',
        ));
        $hotel_plan = (array) get_post_meta($post_id, '_oksia_hotel_plan', true);
        $operational = wp_parse_args((array) get_post_meta($post_id, '_oksia_operational_notes', true), array(
            'summary' => '',
            'inclusions' => '',
            'exclusions' => '',
            'important_notes' => '',
            'child_policy' => '',
            'booking_policy' => '',
            'cancellation_policy' => '',
        ));
        $operational['child_policy'] = (string) get_option('oksia_default_child_policy', '');
        $operational['booking_policy'] = (string) get_option('oksia_default_booking_policy', '');
        $operational['cancellation_policy'] = (string) get_option('oksia_default_cancellation_policy', '');
        $days = (array) get_post_meta($post_id, '_oksia_days', true);

        $quote_id = trim((string) get_post_meta($post_id, '_oksia_quote_id', true));
        if ('' === $quote_id) {
            $quote_id = 'OK' . wp_date('ymd', current_time('timestamp')) . '01';
        }

        $agency_id = $this->get_quote_agency_id($post_id);
        $brand_name = trim((string) get_the_title($agency_id));
        if ('' === $brand_name) {
            $brand_name = trim((string) get_option('oksia_agency_name', 'OK'));
        }
        $brand_phone = trim((string) get_post_meta($agency_id, 'oksia_agency_phone', true));
        if ('' === $brand_phone) {
            $brand_phone = trim((string) get_option('oksia_agency_phone', ''));
        }
        $brand_email = trim((string) get_post_meta($agency_id, 'oksia_agency_email', true));
        if ('' === $brand_email) {
            $brand_email = trim((string) get_post_meta($agency_id, 'oksia_billing_email', true));
        }
        if ('' === $brand_email) {
            $brand_email = trim((string) get_option('oksia_billing_email', ''));
        }
        $brand_location = trim((string) get_post_meta($agency_id, 'oksia_agency_location', true));
        if ('' === $brand_location) {
            $brand_location = trim((string) get_option('oksia_agency_location', ''));
        }
        $billing_company = trim((string) get_post_meta($agency_id, 'oksia_billing_company', true));
        if ('' === $billing_company) {
            $billing_company = trim((string) get_option('oksia_billing_company', 'EKTA CORPORATION'));
        }
        $brand_logo = $this->get_brand_logo_src($agency_id);
        $brand_logo_data = $this->get_logo_as_data_url($brand_logo);
        $brand_logo_src = '' !== $brand_logo_data ? $brand_logo_data : $brand_logo;
        $logo_mark = '' !== trim((string) $brand_logo_src)
            ? '<img src="' . esc_attr($brand_logo_src) . '" alt="' . esc_attr($brand_name) . '" class="brand-logo" />'
            : '<div class="brand-logo-text">' . esc_html($brand_name) . '</div>';
        $version_label = class_exists('OKSIA_Admin') ? OKSIA_Admin::get_quote_version_label($post_id) : 'v1';
        $client_name = trim(($trip['salutation'] ? $trip['salutation'] . ' ' : '') . $trip['client_name']);
        $total_pax = absint($trip['travelers']);
        if ($total_pax <= 0) {
            $total_pax = absint($trip['adults']) + absint($trip['adult_with_bed']) + absint($trip['child_without_bed']);
        }
        $currency_code = $this->normalize_currency_code($quote['currency']);
        $travel_pnr = trim((string) get_post_meta($post_id, '_oksia_travel_pnr', true));
        $hotel_pnr = trim((string) get_post_meta($post_id, '_oksia_hotel_pnr', true));
        $confirmation_note = trim((string) get_post_meta($post_id, '_oksia_quote_confirmation_note', true));
        $handler_name = trim((string) get_post_meta($post_id, '_oksia_handler_name', true));
        $handler_type = trim((string) get_post_meta($post_id, '_oksia_handler_type', true));
        $quote_stage = $this->get_quote_stage($post_id);
        if ('' === $handler_name) {
            $handler_name = $handler_type;
        }

        $booking_reference_parts = array();
        foreach (array($confirmation_note, $travel_pnr, $hotel_pnr) as $part) {
            $part = trim((string) $part);
            if ('' !== $part) {
                $booking_reference_parts[] = $part;
            }
        }
        $booking_reference = !empty($booking_reference_parts)
            ? implode(' | ', $booking_reference_parts)
            : __('Not specified.', 'oksia-smart-itinerary-agent');
        $booking_reference_block = '';
        if (in_array($quote_stage, array('confirmed', 'send'), true) && '' !== trim((string) $booking_reference) && __('Not specified.', 'oksia-smart-itinerary-agent') !== $booking_reference) {
            $booking_reference_block = '<div class="booking-ref-card"><div class="booking-ref-card__label">' . esc_html__('Booking Reference', 'oksia-smart-itinerary-agent') . '</div><div class="booking-ref-card__value">' . esc_html($booking_reference) . '</div></div>';
        }
        $footer_contacts_parts = array();
        if ('' !== $brand_phone) {
            $footer_contacts_parts[] = $brand_phone;
        }
        if ('' !== $brand_email) {
            $footer_contacts_parts[] = $brand_email;
        }
        if ('' !== $brand_location) {
            $footer_contacts_parts[] = $brand_location;
        }
        $footer_contacts = !empty($footer_contacts_parts) ? implode(' | ', array_map('esc_html', $footer_contacts_parts)) : '';
        $footer_line = esc_html($brand_name);
        if ('' !== $footer_contacts) {
            $footer_line .= '<br>' . $footer_contacts;
        }

        $adult_rate = $quote['adult_rate_quote'] ?: $quote['adult_rate'];
        $with_bed_rate = $quote['with_bed_rate_quote'] ?: $quote['with_bed_rate'];
        $child_rate = $quote['child_rate_quote'] ?: $quote['child_rate'];

        $replacements = array(
            '{{COMPANY_NAME}}' => esc_html($brand_name),
            '{{LOGO_MARK}}' => $logo_mark,
            '{{QUOTE_NUMBER}}' => esc_html($quote_id),
            '{{QUOTE_VERSION}}' => esc_html($version_label),
            '{{DESTINATION_NAME}}' => esc_html((string) $trip['destination']),
            '{{CLIENT_NAME}}' => esc_html($client_name),
            '{{TOTAL_PAX}}' => esc_html((string) $total_pax),
            '{{CHECKIN_DATE}}' => esc_html($this->format_date_display($trip['start_date'])),
            '{{CHECKOUT_DATE}}' => esc_html($this->format_date_display($trip['end_date'])),
            '{{CURRENCY}}' => esc_html($currency_code),
            '{{ADULT_PAX}}' => esc_html((string) absint($trip['adults'])),
            '{{ADULT_RATE}}' => esc_html($this->format_amount_only($adult_rate)),
            '{{WITH_BED_PAX}}' => esc_html((string) absint($trip['adult_with_bed'])),
            '{{WITH_BED_RATE}}' => esc_html($this->format_amount_only($with_bed_rate)),
            '{{CHILD_PAX}}' => esc_html((string) absint($trip['child_without_bed'])),
            '{{CHILD_RATE}}' => esc_html($this->format_amount_only($child_rate)),
            '{{HOTEL_CATEGORY}}' => esc_html((string) $quote['hotel_category']),
            '{{ROOM_COUNT}}' => esc_html((string) $quote['rooms']),
            '{{OCCUPANCY}}' => esc_html((string) $quote['occupancy']),
            '{{MEAL_PLAN}}' => esc_html((string) $quote['meal_plan']),
            '{{VEHICLE_NAME}}' => esc_html((string) ($quote['sightseeing_vehicle'] ?: $quote['vehicle_type'])),
            '{{VEHICLE_TYPE}}' => esc_html((string) $quote['vehicle_type']),
            '{{PICKUP_LOCATION}}' => esc_html((string) $quote['pickup_from']),
            '{{DROP_LOCATION}}' => esc_html((string) $quote['drop_to']),
            '{{SIGHTSEEING_VEHICLE}}' => esc_html((string) $quote['sightseeing_vehicle']),
            '{{HOTEL_LIST}}' => $this->render_output_template_hotels($hotel_plan),
            '{{BOOKING_REFERENCE_BLOCK}}' => $booking_reference_block,
            '{{HANDLER_NAME}}' => esc_html($handler_name),
            '{{CONTACT_PHONE}}' => esc_html($brand_phone),
            '{{CONTACT_EMAIL}}' => esc_html($brand_email),
            '{{FOOTER_CONTACTS}}' => $footer_contacts,
            '{{FOOTER_LINE}}' => $footer_line,
            '{{INCLUSIONS}}' => $this->render_multiline_list((string) $operational['inclusions']),
            '{{EXCLUSIONS}}' => $this->render_multiline_list($this->build_exclusion_list((string) $operational['exclusions'], $trip, $quote)),
            '{{CHILD_POLICY}}' => $this->render_multiline_list((string) $operational['child_policy']),
            '{{BOOKING_POLICY}}' => $this->render_multiline_list((string) $operational['booking_policy']),
            '{{CANCELLATION_POLICY}}' => $this->render_multiline_list((string) $operational['cancellation_policy']),
            '{{IMPORTANT_NOTES}}' => $this->render_multiline_list((string) $operational['important_notes']),
            '{{ITINERARY_DAYS}}' => $this->render_output_template_days($days),
        );

        return strtr($markup, $replacements);
    }

    private function render_output_template_hotels($hotel_plan) {
        $hotel_plan = (array) $hotel_plan;
        if (empty($hotel_plan)) {
            return '';
        }

        $html = '<div class="hotel-summary-list">';
        foreach ($hotel_plan as $stay) {
            if (!is_array($stay)) {
                continue;
            }

            $nights = trim((string) ($stay['nights'] ?? ''));
            $hotel = trim((string) ($stay['hotel'] ?? ''));
            $city = trim((string) ($stay['city'] ?? ''));
            if ('' === $nights && '' === $hotel && '' === $city) {
                continue;
            }

            $parts = array();
            if ('' !== $hotel) {
                $parts[] = $hotel;
            }
            if ('' !== $city) {
                $parts[] = $city;
            }
            if ('' !== $nights) {
                $parts[] = sprintf(_n('%s Night', '%s Nights', (int) $nights, 'oksia-smart-itinerary-agent'), $nights);
            }
            if (!empty($parts)) {
                $html .= '<div class="hotel-summary-item"><strong>Hotels:</strong> ' . esc_html(implode(' - ', $parts)) . '</div>';
            }
        }
        $html .= '</div>';

        return $html;
    }

    private function render_output_template_days($days) {
        $days = (array) $days;
        if (empty($days)) {
            return '';
        }

        $html = '';
        foreach ($days as $index => $day) {
            if (!is_array($day)) {
                continue;
            }

            $day_number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $raw_title = trim((string) ($day['title'] ?? ''));
            $title = '' !== $raw_title ? $this->normalize_day_title($raw_title, $day_number) : '';
            $location = trim((string) ($day['location'] ?? ''));
            $description = trim((string) ($day['description'] ?? ''));
            $image_url = trim((string) ($day['image_url'] ?? ''));
            if ('' === $title && '' === $location && '' === $description && '' === $image_url) {
                continue;
            }

            $html .= '<div class="day-card">';
            $html .= '<div class="day-title"><span>' . esc_html(sprintf(__('Day %s:', 'oksia-smart-itinerary-agent'), $day_number)) . '</span>' . ('' !== $title ? ' ' . esc_html($title) : '') . '</div>';
            if ('' !== $location) {
                $html .= '<div class="day-text"><strong>' . esc_html(sprintf(__('Today\'s Location: %s', 'oksia-smart-itinerary-agent'), $location)) . '</strong></div>';
            }
            if ('' !== $description) {
                $html .= '<div class="day-text">' . esc_html($description) . '</div>';
            }
            if ('' !== $image_url) {
                $html .= '<div style="margin-top:12px;"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" style="max-width:100%;height:auto;border-radius:10px;display:block;" /></div>';
            }
            $html .= '</div>';
        }

        return $html;
    }

    private function render_quote_header($brand_logo, $brand_name, $billing_company, $disclaimer_text, $quote_id, $version_label, $client_name, $destination, $duration, $pax) {
        ob_start();
        ?>
        <div class="oksia-header-band">
            <div class="oksia-header-brand">
                <?php if ($brand_logo) : ?>
                    <img src="<?php echo esc_attr($brand_logo); ?>" alt="<?php echo esc_attr($brand_name); ?>" class="oksia-header-brand__logo" />
                <?php else : ?>
                    <div class="oksia-header-brand__text"><?php echo esc_html($brand_name); ?></div>
                <?php endif; ?>
                <p class="oksia-header-brand__unit">A unit of <strong><em><?php echo esc_html($billing_company); ?></em></strong></p>
            </div>
            <div class="oksia-header-right">
                <div class="oksia-header-meta">
                    <div>
                        <span><?php esc_html_e('Quote ID', 'oksia-smart-itinerary-agent'); ?></span>
                        <strong><?php echo esc_html($quote_id); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Version', 'oksia-smart-itinerary-agent'); ?></span>
                        <strong><?php echo esc_html($version_label); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Client', 'oksia-smart-itinerary-agent'); ?></span>
                        <strong><?php echo esc_html($client_name ?: __('Your curated trip snapshot', 'oksia-smart-itinerary-agent')); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Destination', 'oksia-smart-itinerary-agent'); ?></span>
                        <strong><?php echo esc_html($destination); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Duration', 'oksia-smart-itinerary-agent'); ?></span>
                        <strong><?php echo esc_html($duration); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Pax', 'oksia-smart-itinerary-agent'); ?></span>
                        <strong><?php echo esc_html($pax); ?></strong>
                    </div>
                </div>
                <div class="oksia-disclaimer-strip"><?php echo esc_html($disclaimer_text); ?></div>
            </div>
        </div>
        <div class="oksia-header-separator"></div>
        <?php
        return ob_get_clean();
    }

    private function render_quote_intro($client_name, $summary, $footer_text) {
        $summary = trim((string) $summary);
        $footer_text = trim((string) $footer_text);
        if ('' === $summary && '' === $footer_text && '' === $client_name) {
            return '';
        }

        ob_start();
        ?>
        <section class="oksia-intro-band">
            <div class="oksia-intro-copy">
                <?php if ('' !== $client_name) : ?>
                    <p class="oksia-intro-eyebrow"><?php esc_html_e('Personalized Trip Quotation prepared for', 'oksia-smart-itinerary-agent'); ?></p>
                    <h2><?php echo esc_html($client_name); ?></h2>
                <?php else : ?>
                    <p class="oksia-intro-eyebrow"><?php esc_html_e('Personalized Trip Quotation prepared for', 'oksia-smart-itinerary-agent'); ?></p>
                    <h2><?php esc_html_e('Your curated trip snapshot', 'oksia-smart-itinerary-agent'); ?></h2>
                <?php endif; ?>

                <?php if ('' !== $summary) : ?>
                    <p class="oksia-intro-summary"><?php echo nl2br(esc_html($summary)); ?></p>
                <?php endif; ?>

                <?php if ('' !== $footer_text) : ?>
                    <p class="oksia-intro-note"><?php echo esc_html($footer_text); ?></p>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function render_confirmation_summary_block($post_id) {
        $travel_pnr = trim((string) get_post_meta($post_id, '_oksia_travel_pnr', true));
        $hotel_pnr = trim((string) get_post_meta($post_id, '_oksia_hotel_pnr', true));
        $handler_type = trim((string) get_post_meta($post_id, '_oksia_handler_type', true));
        $handler_name = trim((string) get_post_meta($post_id, '_oksia_handler_name', true));
        $confirmation_note = trim((string) get_post_meta($post_id, '_oksia_quote_confirmation_note', true));

        if ('' === $hotel_pnr && '' !== $confirmation_note && '' === $travel_pnr) {
            $hotel_pnr = $confirmation_note;
        }

        if ('' === $travel_pnr && '' === $hotel_pnr && '' === $handler_type && '' === $handler_name) {
            return '';
        }

        ob_start();
        ?>
        <section class="oksia-sheet-block oksia-sheet-block--full">
            <h3><?php esc_html_e('Post Confirmation Details', 'oksia-smart-itinerary-agent'); ?></h3>
            <div class="oksia-sheet-note-box">
                <?php if ('' !== $travel_pnr) : ?>
                    <p><strong><?php esc_html_e('Flight / Bus / Rail PNR:', 'oksia-smart-itinerary-agent'); ?></strong> <?php echo esc_html($travel_pnr); ?></p>
                <?php endif; ?>
                <?php if ('' !== $hotel_pnr) : ?>
                    <p><strong><?php esc_html_e('Hotel PNR:', 'oksia-smart-itinerary-agent'); ?></strong> <?php echo esc_html($hotel_pnr); ?></p>
                <?php endif; ?>
                <?php if ('' !== $handler_type) : ?>
                    <p><strong><?php esc_html_e('Handler:', 'oksia-smart-itinerary-agent'); ?></strong> <?php echo esc_html($handler_type); ?></p>
                <?php endif; ?>
                <?php if ('' !== $handler_name) : ?>
                    <p><strong><?php esc_html_e('Handler Name:', 'oksia-smart-itinerary-agent'); ?></strong> <?php echo esc_html($handler_name); ?></p>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function get_quote_staff_meta($post_id) {
        $user_id = absint(get_post_field('post_author', $post_id));
        if (!$user_id) {
            $user_id = absint(get_option('oksia_main_agency_user_id', 0));
        }
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $user = $user_id ? get_userdata($user_id) : null;
        $prepared_by = $user && !empty($user->display_name) ? trim($user->display_name) : get_option('oksia_agency_name', 'OK');
        $position = $this->get_user_position_label($user);

        return array(
            'prepared_by' => $prepared_by,
            'position' => $position,
        );
    }

    private function get_user_position_label($user) {
        return '';
    }

    private function render_quote_footer($agency_name, $brand_phone, $billing_email, $brand_location, $page_number = '') {
        $agency_name = trim((string) $agency_name);
        $brand_phone = trim((string) $brand_phone);
        $billing_email = trim((string) $billing_email);
        $brand_location = trim((string) $brand_location);
        if ('' === $agency_name && '' === $brand_phone && '' === $billing_email && '' === $brand_location) {
            return '';
        }
        ob_start();
        ?>
        <div class="footer-sig">
            <p>Thank you for choosing us. For booking confirmation, please contact your account manager.</p>
            <div style="margin-top:15px;border-top:1px dashed var(--border);padding-top:15px;font-family:sans-serif;font-size:11px;color:var(--muted);line-height:1.6;">
                <strong><?php echo esc_html($agency_name); ?></strong><br>
                <strong>Phone:</strong> <?php echo esc_html($brand_phone); ?> |
                <strong>Email:</strong> <?php echo esc_html($billing_email); ?> |
                <strong>Address:</strong> <?php echo esc_html($brand_location); ?>
            </div>
            <?php if ('' !== trim((string) $page_number)) : ?>
                <div style="margin-top:10px;text-align:right;font-size:10px;font-weight:600;color:#475569;">Page <?php echo esc_html($page_number); ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_matrix_box($title, $rows, $footer_note = '') {
        ob_start();
        ?>
        <section class="oksia-sheet-block oksia-sheet-block--rates">
            <h3><?php echo esc_html($title); ?></h3>
            <table class="oksia-sheet-table">
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php if (empty($row)) { continue; } ?>
                        <tr>
                            <?php foreach ($row as $cell) : ?>
                                <td><?php echo esc_html((string) $cell); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php
        return ob_get_clean();
    }

    private function render_trip_details_box($trip) {
        ob_start();
        ?>
        <section class="oksia-sheet-block">
            <h3><?php esc_html_e('Trip Details', 'oksia-smart-itinerary-agent'); ?></h3>
            <table class="oksia-sheet-table oksia-sheet-table--three">
                <tbody>
                    <tr>
                        <th class="oksia-highlight-checkin"><?php esc_html_e('Check In', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Adult', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Extra with Bed', 'oksia-smart-itinerary-agent'); ?></th>
                    </tr>
                    <tr>
                        <td><?php echo esc_html($this->format_date_display($trip['start_date'])); ?></td>
                        <td><?php echo esc_html((string) $trip['adults']); ?></td>
                        <td><?php echo esc_html((string) $trip['adult_with_bed']); ?></td>
                    </tr>
                    <tr>
                        <th class="oksia-highlight-checkout"><?php esc_html_e('Check Out', 'oksia-smart-itinerary-agent'); ?></th>
                        <th colspan="2"><?php esc_html_e('Child without Bed (6 to 10 Years)', 'oksia-smart-itinerary-agent'); ?></th>
                    </tr>
                    <tr>
                        <td><?php echo esc_html($this->format_date_display($trip['end_date'])); ?></td>
                        <td colspan="2"><?php echo esc_html((string) $trip['child_without_bed']); ?></td>
                    </tr>
                </tbody>
            </table>
        </section>
        <?php
        return ob_get_clean();
    }

    private function render_accommodation_box($quote, $trip_type = 'Domestic', $meal_transfers_value = '') {
        $meal_transfers_applicable = $this->meal_transfers_is_applicable($trip_type, $quote['meal_plan']);
        $meal_transfers_value = $this->normalize_meal_transfers_value($trip_type, $quote['meal_plan'], $meal_transfers_value);
        ob_start();
        ?>
        <section class="oksia-sheet-block">
            <h3><?php esc_html_e('Accommodation & Meal Details', 'oksia-smart-itinerary-agent'); ?></h3>
            <table class="oksia-sheet-table oksia-sheet-table--two">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Hotel Category', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Meal Plan', 'oksia-smart-itinerary-agent'); ?></th>
                    </tr>
                    <tr>
                        <td><?php echo esc_html($quote['hotel_category']); ?></td>
                        <td><?php echo esc_html($quote['meal_plan']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Rooms', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Occupancy', 'oksia-smart-itinerary-agent'); ?></th>
                    </tr>
                    <tr>
                        <td><?php echo esc_html((string) $quote['rooms']); ?></td>
                        <td><?php echo esc_html((string) $quote['occupancy']); ?></td>
                    </tr>
                    <?php if ($meal_transfers_applicable) : ?>
                    <tr>
                        <th><?php esc_html_e('Meal Transfers', 'oksia-smart-itinerary-agent'); ?></th>
                        <td><?php echo esc_html($meal_transfers_value); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php
        return ob_get_clean();
    }

    private function render_transfers_box($quote) {
        ob_start();
        ?>
        <section class="oksia-sheet-block">
            <h3><?php esc_html_e('Transfers Details', 'oksia-smart-itinerary-agent'); ?></h3>
            <table class="oksia-sheet-table oksia-sheet-table--three">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Pick up from', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('First Pick', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Sightseeing', 'oksia-smart-itinerary-agent'); ?></th>
                    </tr>
                    <tr>
                        <td><?php echo esc_html($quote['pickup_from']); ?></td>
                        <td><?php echo esc_html($quote['first_transfer']); ?></td>
                        <td><?php echo esc_html($quote['sightseeing_vehicle']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Drop at', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Last Drop', 'oksia-smart-itinerary-agent'); ?></th>
                        <th><?php esc_html_e('Vehicle Type', 'oksia-smart-itinerary-agent'); ?></th>
                    </tr>
                    <tr>
                        <td><?php echo esc_html($quote['drop_to']); ?></td>
                        <td><?php echo esc_html($quote['last_transfer']); ?></td>
                        <td><?php echo esc_html($quote['vehicle_type']); ?></td>
                    </tr>
                </tbody>
            </table>
        </section>
        <?php
        return ob_get_clean();
    }

    private function render_rates_box($quote, $show_inr_reference, $pdf_mode = false) {
        $rate_columns = array();
        $currency_code = $this->normalize_currency_code($quote['currency']);
        $use_cell_highlight = ('INR' !== $currency_code);
        $reference_currency = $pdf_mode ? '' : $currency_code;
        $effective_rate = $this->resolve_effective_rate($quote, $currency_code);
        $has_reference_values = false;

        $adult_rate = trim((string) $quote['adult_rate']);
        if ('' !== $adult_rate) {
            $adult_reference = $this->calculate_inr_reference_amount($quote['adult_rate'], $currency_code, $effective_rate);
            $rate_columns[] = array(
                'label' => __('Per Adult Rate', 'oksia-smart-itinerary-agent'),
                'hint' => '',
                'value' => $this->format_money($quote['adult_rate'], $currency_code),
                'reference' => $show_inr_reference ? $this->format_amount_only($adult_reference) : '',
                'primary' => false,
            );
            if ('' !== (string) $adult_reference) {
                $has_reference_values = true;
            }
        }

        $with_bed_rate = trim((string) $quote['with_bed_rate']);
        if ('' !== $with_bed_rate && (float) $with_bed_rate > 0) {
            $with_bed_reference = $this->calculate_inr_reference_amount($quote['with_bed_rate'], $currency_code, $effective_rate);
            $rate_columns[] = array(
                'label' => __('Extra with Bed', 'oksia-smart-itinerary-agent'),
                'hint' => '',
                'value' => $this->format_money($quote['with_bed_rate'], $currency_code),
                'reference' => $show_inr_reference ? $this->format_amount_only($with_bed_reference) : '',
                'primary' => false,
            );
            if ('' !== (string) $with_bed_reference) {
                $has_reference_values = true;
            }
        }

        $child_rate = trim((string) $quote['child_rate']);
        if ('' !== $child_rate && (float) $child_rate > 0) {
            $child_reference = $this->calculate_inr_reference_amount($quote['child_rate'], $currency_code, $effective_rate);
            $rate_columns[] = array(
                'label' => __('Child No Bed', 'oksia-smart-itinerary-agent'),
                'hint' => '',
                'value' => $this->format_money($quote['child_rate'], $currency_code),
                'reference' => $show_inr_reference ? $this->format_amount_only($child_reference) : '',
                'primary' => false,
            );
            if ('' !== (string) $child_reference) {
                $has_reference_values = true;
            }
        }

        if (empty($rate_columns)) {
            return '';
        }

        $show_reference_row = ('INR' !== $currency_code && $show_inr_reference && $has_reference_values);
        $rates_block_classes = 'oksia-sheet-block oksia-sheet-block--rates';
        if (!$show_reference_row) {
            $rates_block_classes .= ' oksia-sheet-block--rates-no-reference';
        }

        ob_start();
        ?>
        <section class="<?php echo esc_attr($rates_block_classes); ?>">
            <h3><?php esc_html_e('Rates Details', 'oksia-smart-itinerary-agent'); ?></h3>
            <?php if ($pdf_mode) : ?>
                <?php
                $rate_table_classes = 'oksia-sheet-table oksia-sheet-table--three oksia-sheet-table--rates';
                if ('INR' !== $currency_code) {
                    $rate_table_classes .= ' oksia-sheet-table--rates-foreign';
                }
                ?>
                <table class="<?php echo esc_attr($rate_table_classes); ?>">
                    <tbody>
                        <tr>
                            <?php foreach ($rate_columns as $rate_column) : ?>
                                <th><?php echo esc_html($rate_column['label']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($rate_columns as $rate_column) : ?>
                                <td class="oksia-rate-cell<?php echo $use_cell_highlight ? ' oksia-rate-cell--foreign' : ''; ?>">
                                    <div class="oksia-rate-stack">
                                        <span class="oksia-rate-figure<?php echo $use_cell_highlight ? ' oksia-rate-figure--foreign' : ''; ?>"><?php echo esc_html($rate_column['value']); ?></span>
                                        <?php if ('' !== (string) $rate_column['hint']) : ?>
                                            <span class="oksia-rate-hint"><?php echo esc_html($rate_column['hint']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php if ($show_reference_row) : ?>
                            <tr>
                                <th class="oksia-rate-reference-title" colspan="<?php echo esc_attr((string) count($rate_columns)); ?>"><?php esc_html_e('Rate in INR for Reference Only', 'oksia-smart-itinerary-agent'); ?></th>
                            </tr>
                            <tr>
                                <?php foreach ($rate_columns as $rate_column) : ?>
                                    <td class="oksia-rate-cell oksia-rate-cell--reference<?php echo $use_cell_highlight ? ' oksia-rate-cell--foreign' : ''; ?>">
                                        <div class="oksia-rate-stack">
                                            <span class="oksia-rate-figure oksia-rate-figure--reference<?php echo $use_cell_highlight ? ' oksia-rate-figure--foreign' : ''; ?>"><?php echo esc_html($rate_column['reference']); ?></span>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <table class="oksia-sheet-table oksia-sheet-table--three oksia-sheet-table--rates-browser">
                    <tbody>
                        <tr>
                            <?php foreach ($rate_columns as $rate_column) : ?>
                                <th><?php echo esc_html($rate_column['label']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($rate_columns as $rate_column) : ?>
                                <td><?php echo esc_html($rate_column['value']); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php if ($show_reference_row) : ?>
                            <tr>
                                <th class="oksia-rate-reference-title" colspan="<?php echo esc_attr((string) count($rate_columns)); ?>"><?php esc_html_e('Rate in INR for Reference Only', 'oksia-smart-itinerary-agent'); ?></th>
                            </tr>
                            <tr>
                                <?php foreach ($rate_columns as $rate_column) : ?>
                                    <td><?php echo esc_html($rate_column['reference']); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private function render_fact($label, $value) {
        if ('' === (string) $value) {
            return '';
        }
        return '<p><strong>' . esc_html($label) . '</strong><span>' . esc_html((string) $value) . '</span></p>';
    }

    private function render_multiline_list($text) {
        $items = $this->normalize_list_items($text);
        if (empty($items)) {
            return '<p>' . esc_html__('Not specified.', 'oksia-smart-itinerary-agent') . '</p>';
        }
        $html = '<ul class="oksia-simple-list">';
        foreach ($items as $item) {
            $html .= '<li>' . esc_html($item) . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    private function build_exclusion_list($raw_exclusions, $trip, $quote) {
        $items = $this->normalize_list_items($raw_exclusions);
        $auto_items = $this->get_auto_exclusion_items($trip, $quote);
        if (empty($auto_items)) {
            return $items;
        }

        $existing = array();
        foreach ($items as $item) {
            $existing[strtolower($item)] = true;
        }

        foreach ($auto_items as $auto_item) {
            $key = strtolower($auto_item);
            if (!isset($existing[$key])) {
                $items[] = $auto_item;
                $existing[$key] = true;
            }
        }

        return $items;
    }

    private function get_auto_exclusion_items($trip, $quote) {
        $items = array();
        $trip_type = trim((string) ($trip['trip_type'] ?? ''));
        $travel_mode = trim((string) ($quote['travel_mode'] ?? ''));
        $has_flight = ('Flight' === $travel_mode) || $this->quote_has_positive_amount($quote, array('multi_flight_adult', 'multi_flight_with_bed', 'multi_flight_child'));
        $has_visa = $this->quote_has_positive_amount($quote, array('multi_visa_adult', 'multi_visa_with_bed', 'multi_visa_child'));
        $has_tax = $this->quote_has_positive_amount($quote, array('multi_tourism_tax_adult', 'multi_tourism_tax_with_bed', 'multi_tourism_tax_child'));
        $has_tip = $this->quote_has_positive_amount($quote, array('multi_tip_adult', 'multi_tip_with_bed', 'multi_tip_child'));

        if ($has_flight) {
            $items[] = __('Flight fare is excluded unless explicitly included in the final quote.', 'oksia-smart-itinerary-agent');
        }
        if ('International' === $trip_type && $has_visa) {
            $items[] = __('Visa fees are excluded unless explicitly included in the final quote.', 'oksia-smart-itinerary-agent');
        }
        if ('International' === $trip_type && $has_tax) {
            $items[] = __('Tourism tax is excluded unless explicitly included in the final quote.', 'oksia-smart-itinerary-agent');
        }
        if ($has_tip) {
            $items[] = __('Tips are excluded unless explicitly included in the final quote.', 'oksia-smart-itinerary-agent');
        }

        return $items;
    }

    private function quote_has_positive_amount($quote, $keys) {
        foreach ((array) $keys as $key) {
            $amount = $this->parse_decimal_value($quote[$key] ?? '');
            if ($amount > 0) {
                return true;
            }
        }
        return false;
    }

    private function render_quote_card($title, $items) {
        $rows = '';
        foreach ($items as $label => $value) {
            if ('' === trim((string) $value)) {
                continue;
            }
            $rows .= '<p><strong>' . esc_html($label) . '</strong><span>' . esc_html((string) $value) . '</span></p>';
        }
        if ('' === $rows) {
            return '';
        }
        return '<div class="oksia-quote-card"><h3>' . esc_html($title) . '</h3>' . $rows . '</div>';
    }

    private function render_policy_card($title, $text) {
        $text = trim((string) $text);
        if ('' === $text) {
            return '';
        }
        return '<div class="oksia-policy-card"><h3>' . esc_html($title) . '</h3>' . $this->render_multiline_list($text) . '</div>';
    }

    private function format_money($value, $currency) {
        if ('' === trim((string) $value)) {
            return '';
        }
        return trim($this->normalize_currency_code($currency) . ' ' . $value);
    }

    private function format_amount_only($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        return $value;
    }

    private function calculate_inr_reference_amount($value, $currency_code, $effective_rate) {
        $amount = $this->parse_decimal_value($value);
        if ($amount <= 0) {
            return '';
        }

        if ('INR' === $currency_code) {
            return number_format($amount, 2, '.', '');
        }

        if ($effective_rate <= 0) {
            return '';
        }

        return number_format($amount * $effective_rate, 2, '.', '');
    }

    private function resolve_effective_rate($quote, $currency_code) {
        if ('INR' === $currency_code) {
            return 1.0;
        }

        $snapshot_exchange_rate = $this->get_currency_snapshot_rate_inr($currency_code);
        $transaction_cost = $this->parse_decimal_value($quote['transaction_cost'] ?? 0);
        if ($transaction_cost < 0) {
            $transaction_cost = 0.0;
        }

        $exchange_rate = $this->parse_decimal_value($quote['exchange_rate'] ?? '');
        if ($exchange_rate <= 0 && $snapshot_exchange_rate > 0) {
            $exchange_rate = $snapshot_exchange_rate;
        } elseif ($snapshot_exchange_rate > 1 && $exchange_rate <= 1) {
            $exchange_rate = $snapshot_exchange_rate;
        }

        $expected_effective_rate = $exchange_rate > 0 ? ($exchange_rate + $transaction_cost) : 0.0;
        $effective_rate = $this->parse_decimal_value($quote['effective_rate'] ?? '');
        if ($effective_rate > 0) {
            // Reject stale/legacy values (for example old 2.9 from deprecated calc) when an expected rate is available.
            if ($expected_effective_rate > 0) {
                $difference = abs($effective_rate - $expected_effective_rate);
                $tolerance = max(0.5, $expected_effective_rate * 0.1);
                if ($difference <= $tolerance) {
                    return $effective_rate;
                }
            } elseif ($snapshot_exchange_rate <= 0 || $effective_rate > 1) {
                return $effective_rate;
            }
        }

        if ($expected_effective_rate > 0) {
            return $expected_effective_rate;
        }

        return 0.0;
    }

    private function get_currency_snapshot_rate_inr($currency_code) {
        if (! class_exists('OKSIA_Workspace')) {
            return 0.0;
        }

        $snapshot = get_option(OKSIA_Workspace::OPTION_CURRENCY_SNAPSHOT, array());
        $current = (array) ($snapshot['current'] ?? array());
        $normalized_currency = $this->normalize_currency_code($currency_code);

        foreach ($current as $code => $row) {
            if ($this->normalize_currency_code($code) !== $normalized_currency) {
                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $value = $this->parse_decimal_value($row['value'] ?? '');
            if ($value > 0) {
                return $value;
            }
        }

        return 0.0;
    }

    private function normalize_list_items($value) {
        if (is_array($value)) {
            $items = array_map('trim', array_map('strval', $value));
        } else {
            $items = array_map('trim', preg_split('/\r\n|\r|\n/', (string) $value));
        }

        return array_values(array_filter($items, static function ($item) {
            return '' !== $item;
        }));
    }

    private function parse_decimal_value($value) {
        $value = is_scalar($value) ? (string) $value : '';
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        if ('' === $value || '-' === $value || '.' === $value || '-.' === $value) {
            return 0.0;
        }
        return (float) $value;
    }

    private function normalize_hex_color($color, $fallback = '#000000') {
        $normalized = sanitize_hex_color((string) $color);
        if ('' === (string) $normalized) {
            $normalized = sanitize_hex_color((string) $fallback);
        }
        return $normalized ? strtoupper($normalized) : '#000000';
    }

    private function hex_to_rgb($hex) {
        $hex = $this->normalize_hex_color($hex, '#000000');
        $hex = ltrim($hex, '#');
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        );
    }

    private function is_dark_color($hex) {
        $rgb = $this->hex_to_rgb($hex);
        $luminance = (0.299 * $rgb['r']) + (0.587 * $rgb['g']) + (0.114 * $rgb['b']);
        return $luminance < 150;
    }

    private function mix_hex_colors($base, $mix_with, $weight = 0.5) {
        $weight = max(0.0, min(1.0, (float) $weight));
        $base_rgb = $this->hex_to_rgb($base);
        $mix_rgb = $this->hex_to_rgb($mix_with);

        $r = (int) round(($base_rgb['r'] * (1 - $weight)) + ($mix_rgb['r'] * $weight));
        $g = (int) round(($base_rgb['g'] * (1 - $weight)) + ($mix_rgb['g'] * $weight));
        $b = (int) round(($base_rgb['b'] * (1 - $weight)) + ($mix_rgb['b'] * $weight));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    private function hex_to_rgba($hex, $alpha = 1) {
        $rgb = $this->hex_to_rgb($hex);
        $alpha = max(0, min(1, (float) $alpha));
        return sprintf('rgba(%d, %d, %d, %.3f)', $rgb['r'], $rgb['g'], $rgb['b'], $alpha);
    }

    private function get_quote_color_palette($post_id) {
        $fallback = array(
            'primary' => '#000066',
            'secondary' => '#336699',
            'accent' => '#99FFFF',
        );

        $agency_id = absint(get_post_meta($post_id, '_oksia_agency_id', true));
        if (class_exists('OKSIA_Agencies') && method_exists(OKSIA_Agencies::instance(), 'get_agency_colors')) {
            $colors = OKSIA_Agencies::instance()->get_agency_colors($agency_id);
            if (is_array($colors)) {
                return array(
                    'primary' => !empty($colors['primary']) ? $colors['primary'] : $fallback['primary'],
                    'secondary' => !empty($colors['secondary']) ? $colors['secondary'] : $fallback['secondary'],
                    'accent' => !empty($colors['accent']) ? $colors['accent'] : $fallback['accent'],
                );
            }
        }

        return $fallback;
    }

    private function normalize_currency_code($currency) {
        $currency = strtoupper(trim((string) $currency));
        $currency = preg_replace('/[^A-Z]/', '', $currency);
        return '' !== $currency ? $currency : 'INR';
    }

    private function format_date_display($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return $value;
        }

        return wp_date('d-m-Y', $timestamp);
    }
}
