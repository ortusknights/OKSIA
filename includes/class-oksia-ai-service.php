<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_AI_Service {
    private function get_openai_api_key() {
        $candidates = array();

        if (defined('OKSIA_OPENAI_API_KEY') && '' !== trim((string) OKSIA_OPENAI_API_KEY)) {
            $candidates[] = trim((string) OKSIA_OPENAI_API_KEY);
        }

        $env_key = getenv('OKSIA_OPENAI_API_KEY');
        if (false !== $env_key && '' !== trim((string) $env_key)) {
            $candidates[] = trim((string) $env_key);
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['basedir'])) {
            $secrets_file = trailingslashit($uploads['basedir']) . 'oksia-secret/openai-api-key.txt';
            if (file_exists($secrets_file)) {
                $file_key = trim((string) file_get_contents($secrets_file));
                if ('' !== $file_key) {
                    $candidates[] = $file_key;
                }
            }
        }

        $option_key = trim((string) get_option('oksia_openai_api_key', ''));
        if ('' !== $option_key) {
            $candidates[] = $option_key;
        }

        foreach ($candidates as $candidate) {
            if ('' !== $candidate) {
                return $candidate;
            }
        }

        return '';
    }

    public function generate_itinerary_draft($post_id) {
        $api_key = $this->get_openai_api_key();
        if ('' === $api_key) {
            return new WP_Error('oksia_missing_key', __('Add your OpenAI API key in Settings or place it in a secure key file before generating a draft.', 'oksia-smart-itinerary-agent'));
        }

        $context = $this->build_trip_context($post_id);
        if (empty($context['trip_overview'])) {
            return new WP_Error('oksia_missing_trip_data', __('Add destination, dates, and at least one source note or document before generating.', 'oksia-smart-itinerary-agent'));
        }

        $payload = $this->build_payload(
            $this->get_system_prompt(),
            $context,
            'itinerary_draft',
            $this->get_output_schema()
        );

        return $this->run_request($payload);
    }

    private function extract_output_text($body) {
        if (!is_array($body)) {
            return '';
        }

        if (!empty($body['output_text']) && is_string($body['output_text'])) {
            return $body['output_text'];
        }

        if (!empty($body['output']) && is_array($body['output'])) {
            foreach ($body['output'] as $output_item) {
                if (empty($output_item['content']) || !is_array($output_item['content'])) {
                    continue;
                }

                foreach ($output_item['content'] as $content_item) {
                    if (!empty($content_item['text']) && is_string($content_item['text'])) {
                        return $content_item['text'];
                    }
                }
            }
        }

        return '';
    }

    public function build_trip_context($post_id) {
        $documents = get_post_meta($post_id, '_oksia_documents', true);
        $days = get_post_meta($post_id, '_oksia_days', true);
        $trip = get_post_meta($post_id, '_oksia_trip_overview', true);
        $quote = get_post_meta($post_id, '_oksia_quote_details', true);
        $hotel_plan = get_post_meta($post_id, '_oksia_hotel_plan', true);
        $operations = get_post_meta($post_id, '_oksia_operational_notes', true);
        $source_brief = get_post_meta($post_id, '_oksia_source_brief', true);

        return array(
            'trip_overview' => is_array($trip) ? $trip : array(),
            'quote_details' => is_array($quote) ? $quote : array(),
            'hotel_plan' => is_array($hotel_plan) ? array_values($hotel_plan) : array(),
            'reusable_suggestions' => array(
                'cities' => $this->get_reusable_list('oksia_saved_cities'),
                'hotels' => $this->get_reusable_list('oksia_saved_hotels'),
                'sightseeing' => $this->get_reusable_list('oksia_saved_sightseeing'),
            ),
            'source_brief' => sanitize_textarea_field((string) $source_brief),
            'documents' => is_array($documents) ? $this->normalize_documents($documents) : array(),
            'existing_days' => is_array($days) ? array_values($days) : array(),
            'operational_notes' => is_array($operations) ? $operations : array(),
        );
    }

    private function get_reusable_list($option_name) {
        $values = get_option($option_name, array());
        if (!is_array($values)) {
            return array();
        }

        $values = array_filter(array_map('sanitize_text_field', $values));
        $values = array_values(array_unique($values));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);

        return $values;
    }

    private function normalize_documents($documents) {
        $normalized = array();

        foreach ($documents as $document) {
            if (!is_array($document)) {
                continue;
            }

            $normalized[] = array(
                'attachment_id' => isset($document['attachment_id']) ? absint($document['attachment_id']) : 0,
                'title' => isset($document['title']) ? sanitize_text_field((string) $document['title']) : '',
                'type' => isset($document['type']) ? sanitize_text_field((string) $document['type']) : '',
                'notes' => isset($document['notes']) ? sanitize_textarea_field((string) $document['notes']) : '',
                'url' => isset($document['url']) ? esc_url_raw((string) $document['url']) : '',
            );
        }

        return $normalized;
    }

    private function get_system_prompt() {
        return 'You are a travel operations assistant. Convert uploaded travel-booking context into a clean client-facing itinerary draft. If source_brief is provided, treat it as the main planning input and turn it into a day-wise itinerary automatically. Use documents, quote details, stay plan, reusable suggestions, and structured data as supporting operational context. If existing_days are provided, treat them as the latest working itinerary and revise them conservatively instead of starting from scratch; preserve the day order, city sequence, hotel sequence, and sightseeing flow where possible. Prefer reusable suggestions for city, hotel, and sightseeing names so terminology stays consistent with prior quotes. Always return valid JSON matching the schema. Prefer chronological sequencing, preserve operational details, create brochure-friendly copy, and flag assumptions conservatively. For each day, write fuller description text that adds at least one useful contextual travel point beyond the source brief, such as pacing, a practical traveler note, or a local highlight. Do not merely rephrase the provided input. Keep logistics concise and operational.';
    }

    private function build_payload($system_prompt, $context, $schema_name, $schema) {
        $model = trim((string) get_option('oksia_openai_model', ''));
        if ('' === $model) {
            $model = 'gpt-4.1-mini';
        }

        return array(
            'model' => $model,
            'input' => array(
                array(
                    'role' => 'system',
                    'content' => array(
                        array(
                            'type' => 'input_text',
                            'text' => $system_prompt,
                        ),
                    ),
                ),
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'input_text',
                            'text' => wp_json_encode($context),
                        ),
                    ),
                ),
            ),
            'text' => array(
                'format' => array(
                    'type' => 'json_schema',
                    'name' => $schema_name,
                    'schema' => $schema,
                    'strict' => true,
                ),
            ),
        );
    }

    private function run_request($payload) {
        $api_key = $this->get_openai_api_key();
        $response = wp_remote_post(
            'https://api.openai.com/v1/responses',
            array(
                'timeout' => 45,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode($payload),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $status) {
            $message = isset($body['error']['message']) ? $body['error']['message'] : __('OpenAI request failed.', 'oksia-smart-itinerary-agent');
            return new WP_Error('oksia_openai_error', $message);
        }

        $output_text = $this->extract_output_text($body);
        $data = json_decode($output_text, true);

        if (!is_array($data)) {
            return new WP_Error('oksia_invalid_ai_output', __('The AI response could not be parsed into structured data.', 'oksia-smart-itinerary-agent'));
        }

        return $data;
    }

    private function get_output_schema() {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'summary' => array('type' => 'string'),
                'important_notes' => array('type' => 'string'),
                'inclusions' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                ),
                'exclusions' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                ),
                'days' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => array(
                            'title' => array('type' => 'string'),
                            'location' => array('type' => 'string'),
                            'description' => array('type' => 'string'),
                            'logistics' => array('type' => 'string'),
                        ),
                        'required' => array('title', 'location', 'description', 'logistics'),
                    ),
                ),
            ),
            'required' => array('summary', 'important_notes', 'inclusions', 'exclusions', 'days'),
        );
    }

}

