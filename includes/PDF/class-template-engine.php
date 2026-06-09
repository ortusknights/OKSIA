<?php
namespace OKSIA\PDF;

class TemplateEngine {

    public function render($quote_data) {
        $template_path = apply_filters('oksia_pdf_template_path', OKSIA_PLUGIN_DIR . 'templates/pdf/quote-template.php', $quote_data);

        if (!file_exists($template_path)) {
            return $this->get_fallback_html($quote_data);
        }

        ob_start();

        // Extract variables for template
        $quote = (object)$quote_data;
        $client = (object)($quote_data['client_data'] ?? []);
        $itinerary = (object)($quote_data['itinerary_data'] ?? []);
        $currency = get_option('oksia_base_currency', 'INR');

        include $template_path;

        return ob_get_clean();
    }

    private function get_fallback_html($quote_data) {
        $client = $quote_data['client_data'] ?? [];
        $itinerary = $quote_data['itinerary_data'] ?? [];

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Travel Itinerary</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; margin: 40px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .itin-day { margin: 20px 0; padding: 15px; background: #f5f5f5; }
                .day-title { font-size: 18px; font-weight: bold; color: #2c3e50; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Travel Itinerary</h1>
                <p>Quote #: ' . ($quote_data['quote_number'] ?? 'N/A') . '</p>
            </div>

            <div class="client-info">
                <h2>Client Details</h2>
                <p><strong>Name:</strong> ' . ($client['client_name'] ?? '') . '</p>
                <p><strong>Email:</strong> ' . ($client['email'] ?? '') . '</p>
                <p><strong>Phone:</strong> ' . ($client['phone'] ?? '') . '</p>
            </div>';

        if (!empty($itinerary['days'])) {
            $html .= '<h2>Itinerary</h2>';
            foreach ($itinerary['days'] as $day) {
                $html .= '<div class="itin-day">
                    <div class="day-title">Day ' . ($day['day'] ?? '') . ': ' . ($day['title'] ?? '') . '</div>
                    <p><strong>Location:</strong> ' . ($day['location'] ?? '') . '</p>
                    <p>' . ($day['description'] ?? '') . '</p>
                    <p><strong>Logistics:</strong> ' . ($day['logistics'] ?? '') . '</p>
                </div>';
            }
        }

        $html .= '</body></html>';

        return $html;
    }
}
