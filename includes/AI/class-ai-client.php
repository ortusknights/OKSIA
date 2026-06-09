<?php
namespace OKSIA\AI;

class AIClient {

    private $api_key;
    private $model;
    private $api_url = 'https://api.openai.com/v1/chat/completions';

    public function __construct() {
        $this->api_key = get_option('oksia_openai_api_key', '');
        $this->model = get_option('oksia_openai_model', 'gpt-4.1-mini');
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    public function generate($prompt) {
        if (!$this->is_configured()) {
            return ['success' => false, 'error' => 'OpenAI API key not configured'];
        }

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 60,
            'body' => json_encode([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional travel itinerary planner. Generate detailed day-by-day itineraries in valid JSON format.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 4000
            ])
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return ['success' => false, 'error' => $body['error']['message']];
        }

        return [
            'success' => true,
            'content' => $body['choices'][0]['message']['content'] ?? ''
        ];
    }
}
