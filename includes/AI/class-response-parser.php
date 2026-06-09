<?php
namespace OKSIA\AI;

class ResponseParser {

    public function parse($response_content) {
        $itinerary = [
            'summary' => '',
            'important_notes' => '',
            'inclusions' => [],
            'exclusions' => [],
            'days' => []
        ];

        // Extract JSON from response
        $json_pattern = '/\{[\s\S]*\}/';
        preg_match($json_pattern, $response_content, $matches);

        if (empty($matches)) {
            return $this->get_fallback_itinerary();
        }

        $data = json_decode($matches[0], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->get_fallback_itinerary();
        }

        return [
            'summary' => $data['summary'] ?? 'Your custom travel itinerary',
            'important_notes' => $data['important_notes'] ?? 'Please verify all details before booking',
            'inclusions' => $data['inclusions'] ?? ['Accommodation', 'Transportation'],
            'exclusions' => $data['exclusions'] ?? ['Flight tickets', 'Visa fees', 'Travel insurance'],
            'days' => $this->parse_days($data['days'] ?? [])
        ];
    }

    private function parse_days($days) {
        $parsed_days = [];

        foreach ($days as $index => $day) {
            $parsed_days[] = [
                'day' => $day['day'] ?? ($index + 1),
                'title' => $day['title'] ?? "Day " . ($index + 1),
                'location' => $day['location'] ?? 'TBD',
                'description' => $day['description'] ?? 'Activities to be planned',
                'logistics' => $day['logistics'] ?? 'Details to be confirmed'
            ];
        }

        if (empty($parsed_days)) {
            $parsed_days = $this->get_fallback_days();
        }

        return $parsed_days;
    }

    private function get_fallback_itinerary() {
        return [
            'summary' => 'Custom travel itinerary based on your preferences',
            'important_notes' => 'Please contact us for detailed itinerary customization',
            'inclusions' => ['Accommodation', 'Transportation', 'Sightseeing'],
            'exclusions' => ['Airfare', 'Visa', 'Travel insurance'],
            'days' => $this->get_fallback_days()
        ];
    }

    private function get_fallback_days() {
        return [
            [
                'day' => 1,
                'title' => 'Arrival',
                'location' => 'Destination',
                'description' => 'Arrive at destination, check-in to hotel, relax',
                'logistics' => 'Airport transfer arranged'
            ],
            [
                'day' => 2,
                'title' => 'Exploration',
                'location' => 'Local attractions',
                'description' => 'Explore local culture and famous landmarks',
                'logistics' => 'Private vehicle with guide'
            ],
            [
                'day' => 3,
                'title' => 'Departure',
                'location' => 'Destination',
                'description' => 'Check-out and transfer to airport',
                'logistics' => 'Hotel to airport transfer'
            ]
        ];
    }
}
