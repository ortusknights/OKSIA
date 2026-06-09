<?php
namespace OKSIA\Quote;

class QuoteVersioning {

    private $repository;

    public function __construct() {
        $this->repository = new QuoteRepository();
    }

    public function create_new_version($quote_id, $changes = []) {
        $current_quote = $this->repository->get($quote_id);

        if (!$current_quote) {
            return false;
        }

        $new_version_data = [
            'agency_id' => $current_quote->agency_id,
            'quote_number' => $current_quote->quote_number,
            'status' => 'draft',
            'version' => $current_quote->version + 1,
            'client_data' => serialize(array_merge((array)$current_quote->client_data, $changes)),
            'itinerary_data' => $current_quote->itinerary_data,
            'created_by' => get_current_user_id()
        ];

        return $this->repository->create($new_version_data);
    }

    public function get_version_history($quote_number) {
        global $wpdb;
        $table = $wpdb->prefix . 'oksia_quotes';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, version, status, created_at, created_by FROM $table WHERE quote_number = %s ORDER BY version ASC",
            $quote_number
        ));
    }

    public function rollback_to_version($quote_number, $version) {
        global $wpdb;
        $table = $wpdb->prefix . 'oksia_quotes';

        $version_quote = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE quote_number = %s AND version = %d",
            $quote_number,
            $version
        ));

        if (!$version_quote) {
            return false;
        }

        $new_version_data = [
            'agency_id' => $version_quote->agency_id,
            'quote_number' => $quote_number,
            'status' => 'draft',
            'version' => $version_quote->version + 1,
            'client_data' => $version_quote->client_data,
            'itinerary_data' => $version_quote->itinerary_data,
            'created_by' => get_current_user_id()
        ];

        return $this->repository->create($new_version_data);
    }
}
