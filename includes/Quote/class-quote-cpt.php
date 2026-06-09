<?php
namespace OKSIA\Quote;

class QuoteCPT {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_oksia_quote', [$this, 'save_meta_data']);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'oksia_quote_details',
            'Quote Details',
            [$this, 'render_meta_box'],
            'oksia_quote',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('oksia_quote_meta', 'oksia_quote_nonce');

        $quote_number = get_post_meta($post->ID, '_quote_number', true);
        $status = get_post_meta($post->ID, '_status', true);
        $version = get_post_meta($post->ID, '_version', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label>Quote Number</label></th>
                <td><input type="text" value="<?php echo esc_attr($quote_number); ?>" class="regular-text" readonly></td>
            </tr>
            <tr>
                <th><label>Status</label></th>
                <td>
                    <select name="quote_status">
                        <option value="draft" <?php selected($status, 'draft'); ?>>Draft</option>
                        <option value="sent" <?php selected($status, 'sent'); ?>>Sent</option>
                        <option value="confirmed" <?php selected($status, 'confirmed'); ?>>Confirmed</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Version</label></th>
                <td><input type="text" value="<?php echo esc_attr($version); ?>" readonly></td>
            </tr>
        </table>
        <?php
    }

    public function save_meta_data($post_id) {
        if (!isset($_POST['oksia_quote_nonce']) || !wp_verify_nonce($_POST['oksia_quote_nonce'], 'oksia_quote_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['quote_status'])) {
            update_post_meta($post_id, '_status', sanitize_text_field($_POST['quote_status']));
        }
    }
}
