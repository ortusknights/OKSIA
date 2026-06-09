<?php
namespace OKSIA\Admin;

class AdminAjaxHandlers {

    public function __construct() {
        add_action('wp_ajax_oksia_get_agency', [$this, 'get_agency']);
        add_action('wp_ajax_oksia_update_agency', [$this, 'update_agency']);
        add_action('wp_ajax_oksia_get_quote', [$this, 'get_quote']);
        add_action('wp_ajax_oksia_update_quote', [$this, 'update_quote']);
        add_action('wp_ajax_oksia_delete_agency', [$this, 'delete_agency']);
        add_action('wp_ajax_oksia_delete_quote', [$this, 'delete_quote']);
        add_action('wp_ajax_oksia_bulk_agency_action', [$this, 'bulk_agency_action']);
        add_action('wp_ajax_oksia_bulk_quote_action', [$this, 'bulk_quote_action']);
        add_action('wp_ajax_oksia_export_agencies', [$this, 'export_agencies_csv']);
        add_action('wp_ajax_oksia_export_quotes', [$this, 'export_quotes_csv']);
    }

    public function get_agency() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_agencies';
        $agency_id = intval($_POST['agency_id']);

        $agency = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $agency_id));

        if ($agency) {
            $agency->settings = unserialize($agency->settings);
            wp_send_json_success($agency);
        } else {
            wp_send_json_error('Agency not found');
        }
    }

    public function update_agency() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_agencies';
        $agency_id = intval($_POST['agency_id']);

        $data = [];
        if (isset($_POST['name'])) $data['name'] = sanitize_text_field($_POST['name']);
        if (isset($_POST['status'])) $data['status'] = sanitize_text_field($_POST['status']);

        if (!empty($data)) {
            $wpdb->update($table, $data, ['id' => $agency_id]);
            wp_send_json_success(['message' => 'Agency updated']);
        } else {
            wp_send_json_error('No data to update');
        }
    }

    public function delete_agency() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_agencies';
        $agency_id = intval($_POST['agency_id']);

        // Prevent deleting demo agency
        $agency = $wpdb->get_row($wpdb->prepare("SELECT code FROM $table WHERE id = %d", $agency_id));
        if ($agency && $agency->code === 'OKSIA1') {
            wp_send_json_error('Cannot delete demo agency');
            return;
        }

        $wpdb->delete($table, ['id' => $agency_id]);
        $wpdb->delete($wpdb->prefix . 'oksia_quotes', ['agency_id' => $agency_id]);

        wp_send_json_success(['message' => 'Agency deleted']);
    }

    public function bulk_agency_action() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_agencies';
        $action = sanitize_text_field($_POST['bulk_action']);
        $ids = array_map('intval', $_POST['ids']);

        // Remove demo agency from delete list
        if ($action === 'delete') {
            $demo_id = $wpdb->get_var("SELECT id FROM $table WHERE code = 'OKSIA1'");
            if ($demo_id && in_array($demo_id, $ids)) {
                $ids = array_diff($ids, [$demo_id]);
            }
        }

        if (empty($ids)) {
            wp_send_json_error('No valid agencies selected');
            return;
        }

        $ids_string = implode(',', $ids);

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

        wp_send_json_success(['message' => 'Bulk action completed']);
    }

    public function get_quote() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_quotes';
        $quote_id = intval($_POST['quote_id']);

        $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $quote_id));

        if ($quote) {
            $quote->client_data = unserialize($quote->client_data);
            $quote->itinerary_data = unserialize($quote->itinerary_data);
            wp_send_json_success($quote);
        } else {
            wp_send_json_error('Quote not found');
        }
    }

    public function update_quote() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_quotes';
        $quote_id = intval($_POST['quote_id']);

        $data = [];
        if (isset($_POST['status'])) $data['status'] = sanitize_text_field($_POST['status']);

        if (!empty($data)) {
            $wpdb->update($table, $data, ['id' => $quote_id]);
            wp_send_json_success(['message' => 'Quote updated']);
        } else {
            wp_send_json_error('No data to update');
        }
    }

    public function delete_quote() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_quotes';
        $quote_id = intval($_POST['quote_id']);

        $wpdb->delete($table, ['id' => $quote_id]);

        wp_send_json_success(['message' => 'Quote deleted']);
    }

    public function bulk_quote_action() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_quotes';
        $action = sanitize_text_field($_POST['bulk_action']);
        $ids = array_map('intval', $_POST['ids']);

        if (empty($ids)) {
            wp_send_json_error('No quotes selected');
            return;
        }

        $ids_string = implode(',', $ids);

        switch ($action) {
            case 'confirm':
                $wpdb->query("UPDATE $table SET status = 'confirmed' WHERE id IN ($ids_string)");
                break;
            case 'cancel':
                $wpdb->query("UPDATE $table SET status = 'cancelled' WHERE id IN ($ids_string)");
                break;
            case 'delete':
                $wpdb->query("DELETE FROM $table WHERE id IN ($ids_string)");
                break;
        }

        wp_send_json_success(['message' => 'Bulk action completed']);
    }

    public function export_agencies_csv() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_agencies';
        $agencies = $wpdb->get_results("SELECT * FROM $table");

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="agencies-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Code', 'Status', 'Created Date']);

        foreach ($agencies as $agency) {
            fputcsv($output, [$agency->id, $agency->name, $agency->code, $agency->status, $agency->created_at]);
        }

        fclose($output);
        exit;
    }

    public function export_quotes_csv() {
        check_ajax_referer('oksia_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oksia_quotes';
        $quotes = $wpdb->get_results("SELECT * FROM $table");

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="quotes-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Quote Number', 'Status', 'Version', 'Views', 'Created Date']);

        foreach ($quotes as $quote) {
            fputcsv($output, [$quote->id, $quote->quote_number, $quote->status, $quote->version, $quote->view_count, $quote->created_at]);
        }

        fclose($output);
        exit;
    }
}
