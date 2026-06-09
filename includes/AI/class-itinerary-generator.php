<?php
namespace OKSIA\AI;

class ItineraryGenerator {

    private $ai_client;
    private $prompt_builder;
    private $parser;

    public function __construct() {
        $this->ai_client = new AIClient();
        $this->prompt_builder = new PromptBuilder();
        $this->parser = new ResponseParser();
    }

    public function generate($client_data) {
        $prompt = $this->prompt_builder->build_from_client_data($client_data);

        $ai_response = $this->ai_client->generate($prompt);

        if (!$ai_response['success']) {
            return [
                'success' => false,
                'error' => $ai_response['error'],
                'itinerary' => $this->parser->get_fallback_itinerary()
            ];
        }

        $itinerary = $this->parser->parse($ai_response['content']);

        return [
            'success' => true,
            'itinerary' => $itinerary
        ];
    }

    public function regenerate($quote_id) {
        $quote_repo = new \OKSIA\Quote\QuoteRepository();
        $quote = $quote_repo->get($quote_id);

        if (!$quote) {
            return ['success' => false, 'error' => 'Quote not found'];
        }

        $result = $this->generate($quote->client_data);

        if ($result['success']) {
            $quote_repo->update($quote_id, [
                'itinerary_data' => serialize($result['itinerary'])
            ]);
        }

        return $result;
    }

    public function is_available() {
        return $this->ai_client->is_configured();
    }
}
