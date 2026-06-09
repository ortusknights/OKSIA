<?php
namespace OKSIA\AI;

class PromptBuilder {

    public function build_from_client_data($client_data) {
        $client_data = (array)$client_data;

        $duration = $this->calculate_duration(
            $client_data['start_date'] ?? '',
            $client_data['end_date'] ?? ''
        );

        $prompt = "Create a detailed day-by-day travel itinerary for a {$duration}-day trip to {$client_data['destination']}.\n\n";
        $prompt .= "Traveler Details:\n";
        $prompt .= "- Salutation: {$client_data['salutation']}\n";
        $prompt .= "- Travel Type: {$client_data['trip_type']}\n";
        $prompt .= "- Group Size: {$client_data['adults']} adults";

        if (!empty($client_data['children'])) {
            $prompt .= ", {$client_data['children']} children";
        }
        if (!empty($client_data['infants'])) {
            $prompt .= ", {$client_data['infants']} infants";
        }

        $prompt .= "\n";

        if (!empty($client_data['budget'])) {
            $prompt .= "- Budget Range: {$client_data['budget']}\n";
        }

        if (!empty($client_data['special_requests'])) {
            $prompt .= "- Special Requests: {$client_data['special_requests']}\n";
        }

        $prompt .= "\nPlease provide the response in the following JSON format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"summary\": \"Brief overview of the trip\",\n";
        $prompt .= "  \"important_notes\": \"Important travel notes and tips\",\n";
        $prompt .= "  \"inclusions\": [\"item1\", \"item2\"],\n";
        $prompt .= "  \"exclusions\": [\"item1\", \"item2\"],\n";
        $prompt .= "  \"days\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"day\": 1,\n";
        $prompt .= "      \"title\": \"Day title\",\n";
        $prompt .= "      \"location\": \"City/Location\",\n";
        $prompt .= "      \"description\": \"Detailed description of activities\",\n";
        $prompt .= "      \"logistics\": \"Transportation and accommodation details\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n";

        return apply_filters('oksia_ai_prompt', $prompt, $client_data);
    }

    private function calculate_duration($start_date, $end_date) {
        if (empty($start_date) || empty($end_date)) {
            return 5;
        }

        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        $interval = $start->diff($end);

        return $interval->days + 1;
    }
}
