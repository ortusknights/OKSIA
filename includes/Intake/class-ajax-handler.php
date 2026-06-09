<?php
namespace OKSIA\Intake;

class AjaxHandler {

    public function handle_ajax() {
        check_ajax_referer('oksia_frontend_nonce', 'nonce');

        $form_type = isset($_POST['form_type']) ? sanitize_text_field($_POST['form_type']) : '';

        if ($form_type === 'client_intake') {
            $response = $this->handle_client_intake();
        } elseif ($form_type === 'agent_intake') {
            $response = $this->handle_agent_intake();
        } else {
            $response = ['success' => false, 'message' => 'Invalid form type'];
        }

        wp_send_json($response);
    }

    private function handle_client_intake() {
        $intake = new ClientIntake();
        return $intake->process_submission($_POST);
    }

    private function handle_agent_intake() {
        $intake = new AgentIntake();
        return $intake->process_submission($_POST);
    }
}
