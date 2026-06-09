<?php
namespace OKSIA\Intake;

class AgentIntake {

    public function render_form() {
        if (!is_user_logged_in() || !current_user_can('oksia_create_quotes')) {
            return '<p>Please login to access this page.</p>';
        }

        ob_start();
        include OKSIA_PLUGIN_DIR . 'templates/intake/agent-form.php';
        return ob_get_clean();
    }

    public function process_submission($data) {
        if (!is_user_logged_in() || !current_user_can('oksia_create_quotes')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $validator = new Validation();

        $validation = $validator->validate_agent_intake($data);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        $agency_repo = new \OKSIA\Agency\AgencyRepository();
        $agency = $agency_repo->get_by_user(get_current_user_id());

        if (!$agency || $agency->status !== 'active') {
            return ['success' => false, 'message' => 'Agency not active'];
        }

        $quote_repo = new \OKSIA\Quote\QuoteRepository();

        $client_data = [
            'salutation' => sanitize_text_field($data['salutation']),
            'client_name' => sanitize_text_field($data['client_name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone']),
            'trip_type' => sanitize_text_field($data['trip_type']),
            'destination' => sanitize_text_field($data['destination']),
            'start_date' => sanitize_text_field($data['start_date']),
            'end_date' => sanitize_text_field($data['end_date']),
            'adults' => intval($data['adults']),
            'children' => intval($data['children'] ?? 0),
            'infants' => intval($data['infants'] ?? 0),
            'budget' => sanitize_textarea_field($data['budget'] ?? ''),
            'special_requests' => sanitize_textarea_field($data['special_requests'] ?? ''),
            'agent_notes' => sanitize_textarea_field($data['agent_notes'] ?? '')
        ];

        $quote_id = $quote_repo->create([
            'agency_id' => $agency->id,
            'client_data' => serialize($client_data)
        ]);

        return [
            'success' => true,
            'quote_id' => $quote_id,
            'quote_number' => $quote_repo->get($quote_id)->quote_number
        ];
    }
}
