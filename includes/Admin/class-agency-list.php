<?php
namespace OKSIA\Admin;

class AgencyList {

    public function process_bulk_actions() {
        global $wpdb;
        $table = $wpdb->prefix . 'oksia_agencies';

        if (isset($_POST['oksia_bulk_action']) && isset($_POST['agency_ids'])) {
            check_admin_referer('oksia_bulk_agencies');

            $action = sanitize_text_field($_POST['oksia_bulk_action']);
            $ids = array_map('intval', $_POST['agency_ids']);
            $ids_string = implode(',', $ids);

            // Prevent deleting demo agency
            if ($action === 'delete') {
                $demo_id = $wpdb->get_var("SELECT id FROM $table WHERE code = 'OKSIA1'");
                if ($demo_id && in_array($demo_id, $ids)) {
                    $ids = array_diff($ids, [$demo_id]);
                    $ids_string = implode(',', $ids);
                }
            }

            if (empty($ids_string)) {
                return;
            }

            switch ($action) {
                case 'activate':
                    $wpdb->query("UPDATE $table SET status = 'active' WHERE id IN ($ids_string)");
                    break;
                case 'suspend':
                    $wpdb->query("UPDATE $table SET status = 'suspended' WHERE id IN ($ids_string)");
                    break;
                case 'delete':
                    foreach ($ids as $id) {
                        $wpdb->delete($table, ['id' => $id]);
                        $wpdb->delete($wpdb->prefix . 'oksia_quotes', ['agency_id' => $id]);
                    }
                    break;
            }
        }
    }

    public function ajax_get_agency() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_agencies';
        $agency_id = intval($_POST['agency_id']);

        $agency = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $agency_id));

        if ($agency) {
            wp_send_json_success($agency);
        } else {
            wp_send_json_error('Agency not found');
        }
    }
}
