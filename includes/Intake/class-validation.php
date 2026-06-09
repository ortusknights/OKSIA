<?php
namespace OKSIA\Intake;

class Validation {

    public function validate_client_intake($data) {
        $errors = [];

        if (empty($data['salutation'])) {
            $errors['salutation'] = 'Salutation is required';
        }

        if (empty($data['client_name']) || strlen($data['client_name']) < 2 || strlen($data['client_name']) > 100) {
            $errors['client_name'] = 'Name must be between 2 and 100 characters';
        }

        if (empty($data['email']) || !is_email($data['email'])) {
            $errors['email'] = 'Valid email is required';
        }

        if (empty($data['phone']) || !preg_match('/^[0-9]{10,15}$/', $data['phone'])) {
            $errors['phone'] = 'Valid phone number required (10-15 digits)';
        }

        if (empty($data['trip_type']) || !in_array($data['trip_type'], ['Domestic', 'International'])) {
            $errors['trip_type'] = 'Valid trip type required';
        }

        if (empty($data['destination']) || strlen($data['destination']) < 2) {
            $errors['destination'] = 'Destination is required';
        }

        if (empty($data['start_date']) || strtotime($data['start_date']) < strtotime('today')) {
            $errors['start_date'] = 'Start date must be today or later';
        }

        if (empty($data['end_date']) || strtotime($data['end_date']) <= strtotime($data['start_date'])) {
            $errors['end_date'] = 'End date must be after start date';
        }

        if (empty($data['adults']) || $data['adults'] < 1 || $data['adults'] > 20) {
            $errors['adults'] = 'Adults must be between 1 and 20';
        }

        if (isset($data['children']) && ($data['children'] < 0 || $data['children'] > 10)) {
            $errors['children'] = 'Children must be between 0 and 10';
        }

        if (isset($data['infants']) && ($data['infants'] < 0 || $data['infants'] > 5)) {
            $errors['infants'] = 'Infants must be between 0 and 5';
        }

        if (isset($data['special_requests']) && strlen($data['special_requests']) > 1000) {
            $errors['special_requests'] = 'Special requests cannot exceed 1000 characters';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validate_agent_intake($data) {
        return $this->validate_client_intake($data);
    }

    public function sanitize_intake_data($data) {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['email'])) {
                $sanitized[$key] = sanitize_email($value);
            } elseif (in_array($key, ['client_name', 'destination', 'salutation'])) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (in_array($key, ['special_requests', 'budget', 'agent_notes'])) {
                $sanitized[$key] = sanitize_textarea_field($value);
            } elseif (in_array($key, ['adults', 'children', 'infants'])) {
                $sanitized[$key] = intval($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }
}
