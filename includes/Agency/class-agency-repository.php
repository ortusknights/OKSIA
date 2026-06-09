<?php
namespace OKSIA\Agency;

class AgencyRepository {

    private $wpdb;
    private $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'oksia_agencies';
    }

    public function create($data) {
        $defaults = [
            'name' => '',
            'code' => $this->generate_unique_code(),
            'slug' => '',
            'status' => 'pending',
            'settings' => serialize([]),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ];

        $data = wp_parse_args($data, $defaults);
        $data['slug'] = sanitize_title($data['name']);

        $this->wpdb->insert($this->table, $data);
        return $this->wpdb->insert_id;
    }

    public function get($id) {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $this->table WHERE id = %d", $id));
    }

    public function get_by_code($code) {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $this->table WHERE code = %s", $code));
    }

    public function get_by_user($user_id) {
        $agency_code = get_user_meta($user_id, 'oksia_agency_code', true);
        if ($agency_code) {
            return $this->get_by_code($agency_code);
        }
        return null;
    }

    public function update($id, $data) {
        return $this->wpdb->update($this->table, $data, ['id' => $id]);
    }

    public function delete($id) {
        return $this->wpdb->delete($this->table, ['id' => $id]);
    }

    public function get_all($status = null) {
        $sql = "SELECT * FROM $this->table";
        if ($status) {
            $sql .= $this->wpdb->prepare(" WHERE status = %s", $status);
        }
        $sql .= " ORDER BY created_at DESC";
        return $this->wpdb->get_results($sql);
    }

    private function generate_unique_code() {
        $prefix = 'OKSIA';
        $code = $prefix . strtoupper(wp_generate_password(6, false, false));

        while ($this->get_by_code($code)) {
            $code = $prefix . strtoupper(wp_generate_password(6, false, false));
        }

        return $code;
    }
}
