<?php
namespace OKSIA\Quote;

class QuoteStatus {

    private $repository;
    private $allowed_transitions = [
        'draft' => ['sent', 'cancelled'],
        'sent' => ['confirmed', 'cancelled', 'draft'],
        'confirmed' => [],
        'cancelled' => []
    ];

    public function __construct() {
        $this->repository = new QuoteRepository();
    }

    public function transition($quote_id, $new_status) {
        $quote = $this->repository->get($quote_id);

        if (!$quote) {
            return ['success' => false, 'message' => 'Quote not found'];
        }

        if (!$this->can_transition($quote->status, $new_status)) {
            return ['success' => false, 'message' => "Cannot transition from {$quote->status} to {$new_status}"];
        }

        if (!$this->user_can_transition($new_status)) {
            return ['success' => false, 'message' => 'You do not have permission for this action'];
        }

        $this->repository->update($quote_id, ['status' => $new_status]);

        do_action("oksia_quote_{$new_status}", $quote_id, $quote->agency_id);

        return ['success' => true, 'message' => "Quote {$new_status} successfully"];
    }

    private function can_transition($current, $new) {
        return isset($this->allowed_transitions[$current]) &&
               in_array($new, $this->allowed_transitions[$current]);
    }

    private function user_can_transition($status) {
        switch ($status) {
            case 'confirmed':
                return current_user_can('oksia_confirm_quotes');
            case 'cancelled':
                return current_user_can('oksia_confirm_quotes');
            default:
                return current_user_can('oksia_edit_quotes');
        }
    }

    public function get_allowed_next_statuses($current_status) {
        return $this->allowed_transitions[$current_status] ?? [];
    }

    public function get_status_label($status) {
        $labels = [
            'draft' => 'Draft',
            'sent' => 'Sent to Client',
            'confirmed' => 'Confirmed',
            'cancelled' => 'Cancelled'
        ];

        return $labels[$status] ?? ucfirst($status);
    }
}
