<?php
namespace OKSIA\Services;

class CurrencyService {

    private $base_currency;
    private $rates;
    private $transient_key = 'oksia_currency_rates';

    public function __construct() {
        $this->base_currency = get_option('oksia_base_currency', 'INR');
        $this->load_rates();
    }

    private function load_rates() {
        $this->rates = get_transient($this->transient_key);

        if (!$this->rates) {
            $this->refresh_rates();
        }
    }

    public function refresh_rates() {
        $api_url = "https://api.exchangerate-api.com/v4/latest/{$this->base_currency}";

        $response = wp_remote_get($api_url, ['timeout' => 30]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $this->rates = $body['rates'] ?? [];
            set_transient($this->transient_key, $this->rates, 12 * HOUR_IN_SECONDS);
        } else {
            $this->rates = $this->get_fallback_rates();
        }
    }

    public function convert($amount, $from_currency, $to_currency = null) {
        $to_currency = $to_currency ?: $this->base_currency;

        if ($from_currency === $to_currency) {
            return $amount;
        }

        if (!isset($this->rates[$from_currency]) || !isset($this->rates[$to_currency])) {
            return $amount;
        }

        $amount_in_base = $amount / $this->rates[$from_currency];
        return $amount_in_base * $this->rates[$to_currency];
    }

    public function format($amount, $currency = null) {
        $currency = $currency ?: $this->base_currency;
        $symbols = $this->get_currency_symbols();

        $symbol = $symbols[$currency] ?? $currency;

        return $symbol . ' ' . number_format($amount, 2);
    }

    public function get_rates() {
        return $this->rates;
    }

    private function get_fallback_rates() {
        return [
            'USD' => 0.012,
            'EUR' => 0.011,
            'GBP' => 0.0095,
            'INR' => 1,
            'AUD' => 0.018,
            'CAD' => 0.016,
            'SGD' => 0.016,
            'AED' => 0.044,
            'SAR' => 0.045
        ];
    }

    private function get_currency_symbols() {
        return [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'SGD' => 'S$',
            'AED' => 'د.إ',
            'SAR' => '﷼'
        ];
    }
}
