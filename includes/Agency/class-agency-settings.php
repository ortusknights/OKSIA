<?php
namespace OKSIA\Agency;

class AgencySettings {

    private $agency_id;
    private $settings;

    public function __construct($agency_id = null) {
        $this->agency_id = $agency_id;
        $this->load_settings();
    }

    private function load_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'oksia_agencies';

        $agency = $wpdb->get_row($wpdb->prepare("SELECT settings FROM $table WHERE id = %d", $this->agency_id));
        if ($agency && $agency->settings) {
            $this->settings = unserialize($agency->settings);
        } else {
            $this->settings = $this->get_defaults();
        }
    }

    public function get($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    public function set($key, $value) {
        $this->settings[$key] = $value;
        $this->save();
    }

    public function save() {
        global $wpdb;
        $table = $wpdb->prefix . 'oksia_agencies';

        $wpdb->update($table, [
            'settings' => serialize($this->settings),
            'updated_at' => current_time('mysql')
        ], ['id' => $this->agency_id]);
    }

    private function get_defaults() {
        return [
            'currency' => get_option('oksia_base_currency', 'INR'),
            'timezone' => 'Asia/Kolkata',
            'date_format' => 'd/m/Y',
            'logo_url' => '',
            'footer_text' => 'Thank you for choosing us!'
        ];
    }
}
