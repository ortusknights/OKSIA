<?php
namespace OKSIA\Agency;

class AgencyCPT {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_oksia_agency', [$this, 'save_meta_data']);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'oksia_agency_details',
            'Agency Details',
            [$this, 'render_meta_box'],
            'oksia_agency',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('oksia_agency_meta', 'oksia_agency_nonce');

        $agency_code = get_post_meta($post->ID, '_agency_code', true);
        $agency_status = get_post_meta($post->ID, '_agency_status', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="agency_code">Agency Code</label></th>
                <td><input type="text" name="agency_code" id="agency_code" value="<?php echo esc_attr($agency_code); ?>" class="regular-text" readonly></td>
            </tr>
            <tr>
                <th><label for="agency_status">Status</label></th>
                <td>
                    <select name="agency_status">
                        <option value="pending" <?php selected($agency_status, 'pending'); ?>>Pending</option>
                        <option value="active" <?php selected($agency_status, 'active'); ?>>Active</option>
                        <option value="suspended" <?php selected($agency_status, 'suspended'); ?>>Suspended</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta_data($post_id) {
        if (!isset($_POST['oksia_agency_nonce']) || !wp_verify_nonce($_POST['oksia_agency_nonce'], 'oksia_agency_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['agency_status'])) {
            update_post_meta($post_id, '_agency_status', sanitize_text_field($_POST['agency_status']));
        }
    }
}
