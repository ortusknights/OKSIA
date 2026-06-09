<?php
namespace OKSIA\API;

class RestEndpoints {

    private $namespace = 'oksia/v1';

    public function register_routes() {
        register_rest_route($this->namespace, '/quotes', [
            'methods' => 'GET',
            'callback' => [$this, 'get_quotes'],
            'permission_callback' => [$this, 'check_auth']
        ]);

        register_rest_route($this->namespace, '/quotes', [
            'methods' => 'POST',
            'callback' => [$this, 'create_quote'],
            'permission_callback' => [$this, 'check_auth']
        ]);

        register_rest_route($this->namespace, '/quotes/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_quote'],
            'permission_callback' => [$this, 'check_auth']
        ]);

        register_rest_route($this->namespace, '/quotes/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_quote'],
            'permission_callback' => [$this, 'check_auth']
        ]);

        register_rest_route($this->namespace, '/quotes/share/(?P<token>[a-f0-9]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_shared_quote'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($this->namespace, '/agencies', [
            'methods' => 'GET',
            'callback' => [$this, 'get_agencies'],
            'permission_callback' => [$this, 'check_admin']
        ]);

        register_rest_route($this->namespace, '/dashboard/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard_stats'],
            'permission_callback' => [$this, 'check_auth']
        ]);
    }

    public function check_auth($request) {
        return is_user_logged_in();
    }

    public function check_admin($request) {
        return current_user_can('manage_options');
    }

    public function get_quotes($request) {
        $agency_repo = new \OKSIA\Agency\AgencyRepository();
        $agency = $agency_repo->get_by_user(get_current_user_id());

        if (!$agency) {
            return new \WP_Error('no_agency', 'No agency found', ['status' => 404]);
        }

        $quote_repo = new \OKSIA\Quote\QuoteRepository();
        $quotes = $quote_repo->get_by_agency($agency->id);

        return rest_ensure_response($quotes);
    }

    public function create_quote($request) {
        $params = $request->get_json_params();

        $intake = new \OKSIA\Intake\AgentIntake();
        $result = $intake->process_submission($params);

        return rest_ensure_response($result);
    }

    public function get_quote($request) {
        $id = $request['id'];

        $quote_repo = new \OKSIA\Quote\QuoteRepository();
        $quote = $quote_repo->get($id);

        if (!$quote) {
            return new \WP_Error('not_found', 'Quote not found', ['status' => 404]);
        }

        $agency_repo = new \OKSIA\Agency\AgencyRepository();
        $agency = $agency_repo->get_by_user(get_current_user_id());

        if ($agency && $quote->agency_id != $agency->id && !current_user_can('manage_options')) {
            return new \WP_Error('forbidden', 'Access denied', ['status' => 403]);
        }

        return rest_ensure_response($quote);
    }

    public function update_quote($request) {
        $id = $request['id'];
        $params = $request->get_json_params();

        $quote_repo = new \OKSIA\Quote\QuoteRepository();
        $quote = $quote_repo->get($id);

        if (!$quote) {
            return new \WP_Error('not_found', 'Quote not found', ['status' => 404]);
        }

        if (isset($params['itinerary_data'])) {
            $params['itinerary_data'] = serialize($params['itinerary_data']);
        }

        if (isset($params['client_data'])) {
            $params['client_data'] = serialize($params['client_data']);
        }

        $quote_repo->update($id, $params);

        return rest_ensure_response(['success' => true]);
    }

    public function get_shared_quote($request) {
        $token = $request['token'];

        $sharing = new \OKSIA\Quote\QuoteSharing();
        $quote = $sharing->get_shared_quote($token);

        if (!$quote) {
            return new \WP_Error('not_found', 'Quote not found', ['status' => 404]);
        }

        return rest_ensure_response($quote);
    }

    public function get_agencies($request) {
        $agency_repo = new \OKSIA\Agency\AgencyRepository();
        $agencies = $agency_repo->get_all();

        return rest_ensure_response($agencies);
    }

    public function get_dashboard_stats($request) {
        $agency_repo = new \OKSIA\Agency\AgencyRepository();
        $agency = $agency_repo->get_by_user(get_current_user_id());

        if (!$agency) {
            return new \WP_Error('no_agency', 'No agency found', ['status' => 404]);
        }

        $quote_repo = new \OKSIA\Quote\QuoteRepository();
        $quotes = $quote_repo->get_by_agency($agency->id);

        $stats = [
            'total' => count($quotes),
            'draft' => 0,
            'sent' => 0,
            'confirmed' => 0,
            'cancelled' => 0
        ];

        foreach ($quotes as $quote) {
            $stats[$quote->status]++;
        }

        return rest_ensure_response($stats);
    }
}
