<?php
namespace OKSIA\Services;

class SupportService {

    private $ticket_table;

    public function __construct() {
        global $wpdb;
        $this->ticket_table = $wpdb->prefix . 'oksia_support_tickets';
        $this->create_table_if_not_exists();
    }

    private function create_table_if_not_exists() {
        global $wpdb;

        $sql = "CREATE TABLE IF NOT EXISTS {$this->ticket_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            agency_id bigint(20),
            subject varchar(255) NOT NULL,
            message text NOT NULL,
            status enum('open','in-progress','resolved','closed') DEFAULT 'open',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_status (status)
        )";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function create_ticket($user_id, $subject, $message, $agency_id = null) {
        global $wpdb;

        $data = [
            'user_id' => $user_id,
            'subject' => sanitize_text_field($subject),
            'message' => sanitize_textarea_field($message),
            'status' => 'open'
        ];

        if ($agency_id) {
            $data['agency_id'] = $agency_id;
        }

        $wpdb->insert($this->ticket_table, $data);

        return $wpdb->insert_id;
    }

    public function get_tickets($user_id, $status = null) {
        global $wpdb;

        $sql = $wpdb->prepare("SELECT * FROM {$this->ticket_table} WHERE user_id = %d", $user_id);

        if ($status) {
            $sql .= $wpdb->prepare(" AND status = %s", $status);
        }

        $sql .= " ORDER BY created_at DESC";

        return $wpdb->get_results($sql);
    }

    public function update_ticket_status($ticket_id, $status) {
        global $wpdb;

        return $wpdb->update($this->ticket_table, ['status' => $status], ['id' => $ticket_id]);
    }

    public function get_ticket($ticket_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->ticket_table} WHERE id = %d", $ticket_id));
    }
}
