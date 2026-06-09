<?php
namespace OKSIA\Frontend;

class Dashboard {

    public function render_dashboard() {
        if (!is_user_logged_in() || !current_user_can('oksia_view_own_quotes')) {
            return '<p>Please login to access the dashboard.</p>';
        }

        ob_start();
        include OKSIA_PLUGIN_DIR . 'templates/dashboard/dashboard-main.php';
        return ob_get_clean();
    }

    public function ajax_get_stats() {
        check_ajax_referer('oksia_frontend_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }

        $agency_repo = new \OKSIA\Agency\AgencyRepository();
        $agency = $agency_repo->get_by_user(get_current_user_id());

        if (!$agency) {
            wp_send_json_error('Agency not found');
        }

        $quote_repo = new \OKSIA\Quote\QuoteRepository();
        $quotes = $quote_repo->get_by_agency($agency->id);

        $stats = [
            'total' => count($quotes),
            'draft' => 0,
            'sent' => 0,
            'confirmed' => 0,
            'cancelled' => 0
        ];

        foreach ($quotes as $quote) {
            $stats[$quote->status]++;
        }

        wp_send_json_success($stats);
    }

    public function ajax_get_quotes() {
        check_ajax_referer('oksia_frontend_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }

        $agency_repo = new \OKSIA\Agency\AgencyRepository();
        $agency = $agency_repo->get_by_user(get_current_user_id());

        if (!$agency) {
            wp_send_json_error('Agency not found');
        }

        $quote_repo = new \OKSIA\Quote\QuoteRepository();
        $quotes = $quote_repo->get_by_agency($agency->id);

        wp_send_json_success($quotes);
    }
}
