<?php
namespace OKSIA\PDF;

class PDFDownload {

    private $generator;

    public function __construct() {
        $this->generator = new PDFGenerator();
    }

    public function download_quote($quote_id, $output = 'inline') {
        $quote_repo = new \OKSIA\Quote\QuoteRepository();
        $quote = $quote_repo->get($quote_id);

        if (!$quote) {
            wp_die('Quote not found');
        }

        $quote_data = [
            'id' => $quote->id,
            'quote_number' => $quote->quote_number,
            'status' => $quote->status,
            'version' => $quote->version,
            'client_data' => (array)$quote->client_data,
            'itinerary_data' => (array)$quote->itinerary_data,
            'created_at' => $quote->created_at
        ];

        return $this->generator->generate($quote_data, $output);
    }

    public function handle_download_request() {
        if (!isset($_GET['oksia_download_pdf']) || !isset($_GET['quote_id'])) {
            return;
        }

        $quote_id = intval($_GET['quote_id']);
        $nonce = $_GET['nonce'] ?? '';

        if (!wp_verify_nonce($nonce, 'oksia_download_pdf_' . $quote_id)) {
            wp_die('Invalid request');
        }

        $quote_repo = new \OKSIA\Quote\QuoteRepository();
        $quote = $quote_repo->get($quote_id);

        if (!$quote) {
            wp_die('Quote not found');
        }

        // Check permission
        $user_id = get_current_user_id();
        $agency_code = get_user_meta($user_id, 'oksia_agency_code', true);

        if (!current_user_can('manage_options') && $agency_code) {
            $agency_repo = new \OKSIA\Agency\AgencyRepository();
            $agency = $agency_repo->get_by_code($agency_code);

            if (!$agency || $agency->id != $quote->agency_id) {
                wp_die('Unauthorized');
            }
        } elseif (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $output = isset($_GET['download']) ? 'attachment' : 'inline';
        $this->download_quote($quote_id, $output);
    }
}
