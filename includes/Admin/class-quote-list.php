<?php
namespace OKSIA\Admin;

class QuoteList {

    public function process_bulk_actions() {
        global $wpdb;
        $table = $wpdb->prefix . 'oksia_quotes';

        if (isset($_POST['oksia_bulk_action']) && isset($_POST['quote_ids'])) {
            check_admin_referer('oksia_bulk_quotes');

            $action = sanitize_text_field($_POST['oksia_bulk_action']);
            $ids = array_map('intval', $_POST['quote_ids']);
            $ids_string = implode(',', $ids);

            switch ($action) {
                case 'confirm':
                    $wpdb->query("UPDATE $table SET status = 'confirmed' WHERE id IN ($ids_string)");
                    break;
                case 'cancel':
                    $wpdb->query("UPDATE $table SET status = 'cancelled' WHERE id IN ($ids_string)");
                    break;
                case 'delete':
                    foreach ($ids as $id) {
                        $wpdb->delete($table, ['id' => $id]);
                    }
                    break;
            }
        }
    }

    public function ajax_get_quote() {
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
}
