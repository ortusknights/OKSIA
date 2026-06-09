<?php
namespace OKSIA\Quote;

class QuoteRepository {

    private $wpdb;
    private $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'oksia_quotes';
    }

    public function create($data) {
        $defaults = [
            'quote_number' => $this->generate_quote_number(),
            'agency_id' => 0,
            'status' => 'draft',
            'version' => 1,
            'client_data' => serialize([]),
            'itinerary_data' => serialize([]),
            'share_token' => $this->generate_share_token(),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ];

        $data = wp_parse_args($data, $defaults);

        $this->wpdb->insert($this->table, $data);
        $id = $this->wpdb->insert_id;

        do_action('oksia_quote_created', $id, $data['agency_id']);

        return $id;
    }

    public function get($id) {
        $quote = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $this->table WHERE id = %d", $id));
        if ($quote) {
            $quote->client_data = unserialize($quote->client_data);
            $quote->itinerary_data = unserialize($quote->itinerary_data);
        }
        return $quote;
    }

    public function get_by_number($quote_number) {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $this->table WHERE quote_number = %s", $quote_number));
    }

    public function get_by_token($token) {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $this->table WHERE share_token = %s", $token));
    }

    public function get_by_agency($agency_id, $status = null) {
        $sql = $this->wpdb->prepare("SELECT * FROM $this->table WHERE agency_id = %d", $agency_id);
        if ($status) {
            $sql .= $this->wpdb->prepare(" AND status = %s", $status);
        }
        $sql .= " ORDER BY created_at DESC";
        return $this->wpdb->get_results($sql);
    }

    public function update($id, $data) {
        $data['updated_at'] = current_time('mysql');
        $result = $this->wpdb->update($this->table, $data, ['id' => $id]);

        if ($result) {
            do_action('oksia_quote_updated', $id, $data['agency_id'] ?? null);
        }

        return $result;
    }

    public function delete($id) {
        return $this->wpdb->delete($this->table, ['id' => $id]);
    }

    public function increment_view_count($id) {
        $this->wpdb->query($this->wpdb->prepare("UPDATE $this->table SET view_count = view_count + 1 WHERE id = %d", $id));
    }

    private function generate_quote_number() {
        $agency_code = $this->get_current_agency_code();
        $date = current_time('ymd');
        $sequence = $this->get_next_sequence($date);

        return $agency_code . $date . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    private function get_next_sequence($date) {
        $like = $this->wpdb->esc_like($date) . '%';
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $this->table WHERE quote_number LIKE %s",
            $like
        ));
        return $count + 1;
    }

    private function get_current_agency_code() {
        $user_id = get_current_user_id();
        $agency_code = get_user_meta($user_id, 'oksia_agency_code', true);
        return $agency_code ?: 'OKSIA';
    }

    private function generate_share_token() {
        return bin2hex(random_bytes(32));
    }
}
