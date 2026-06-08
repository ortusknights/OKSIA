<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_REST {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route(
            'oksia/v1',
            '/workspace',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_workspace'),
                'permission_callback' => array($this, 'can_view_workspace'),
            )
        );

        register_rest_route(
            'oksia/v1',
            '/quotes',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_quotes'),
                'permission_callback' => array($this, 'can_view_workspace'),
                'args' => array(
                    'limit' => array(
                        'sanitize_callback' => 'absint',
                    ),
                    'stage' => array(
                        'sanitize_callback' => 'sanitize_key',
                    ),
                ),
            )
        );

        register_rest_route(
            'oksia/v1',
            '/quotes/(?P<id>\d+)',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_quote'),
                'permission_callback' => array($this, 'can_view_workspace'),
            )
        );
    }

    public function can_view_workspace() {
        return is_user_logged_in() && current_user_can('read');
    }

    public function get_workspace(WP_REST_Request $request) {
        $workspace = OKSIA_Workspace::instance();
        $user = wp_get_current_user();

        return rest_ensure_response(
            array(
                'user' => array(
                    'id' => (int) $user->ID,
                    'display_name' => $user->display_name,
                    'user_login' => $user->user_login,
                    'role' => $workspace->get_user_role_label($user),
                    'can_manage' => current_user_can('manage_options'),
                ),
                'urls' => array(
                    'login' => $workspace->get_login_url(),
                    'dashboard' => $workspace->get_dashboard_url(),
                    'agent_intake' => get_permalink(absint(get_option(OKSIA_Workspace::OPTION_AGENT_INTAKE_PAGE_ID, 0))),
                    'client_intake' => get_permalink(absint(get_option(OKSIA_Workspace::OPTION_CLIENT_INTAKE_PAGE_ID, 0))),
                    'admin' => admin_url(),
                ),
                'stats' => $workspace->get_quote_stats(),
                'recent_quotes' => $workspace->get_recent_quotes(6),
            )
        );
    }

    public function get_quotes(WP_REST_Request $request) {
        $limit = isset($request['limit']) ? absint($request['limit']) : 10;
        $stage = isset($request['stage']) ? sanitize_key((string) $request['stage']) : '';
        $workspace = OKSIA_Workspace::instance();

        return rest_ensure_response(
            array(
                'items' => $workspace->get_recent_quotes($limit, $stage),
            )
        );
    }

    public function get_quote(WP_REST_Request $request) {
        $post_id = absint($request['id']);
        if ($post_id <= 0) {
            return new WP_Error('oksia_invalid_quote', __('Invalid quote ID.', 'oksia-smart-itinerary-agent'), array('status' => 400));
        }

        $post = get_post($post_id);
        if (!$post || OKSIA_Post_Types::POST_TYPE !== $post->post_type) {
            return new WP_Error('oksia_quote_not_found', __('Quote not found.', 'oksia-smart-itinerary-agent'), array('status' => 404));
        }

        $trip = (array) get_post_meta($post_id, '_oksia_trip_overview', true);
        $summary = (string) get_post_meta($post_id, '_oksia_client_summary', true);
        $status = '1' === (string) get_post_meta($post_id, '_oksia_quote_finalized', true) ? 'finalized' : 'draft';
        $quote_id = trim((string) get_post_meta($post_id, '_oksia_quote_id', true));

        return rest_ensure_response(
            array(
                'id' => $post_id,
                'quote_id' => '' !== $quote_id ? $quote_id : $post->post_title,
                'title' => get_the_title($post_id),
                'status' => $status,
                'updated' => get_post_modified_time('c', true, $post_id),
                'trip_overview' => $trip,
                'summary' => $summary,
                'finalized' => 'finalized' === $status,
                'link' => get_permalink($post_id),
            )
        );
    }
}
