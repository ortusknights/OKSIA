<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_Post_Types {
    const POST_TYPE = 'oksia_itinerary';
    const TICKET_POST_TYPE = 'oksia_ticket';

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_ticket_post_type'));
    }

    public function register_post_type() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name' => __('Smart Itineraries', 'oksia-smart-itinerary-agent'),
                    'singular_name' => __('Smart Itinerary', 'oksia-smart-itinerary-agent'),
                    'add_new' => __('Add New', 'oksia-smart-itinerary-agent'),
                    'add_new_item' => __('Create Smart Itinerary', 'oksia-smart-itinerary-agent'),
                    'edit_item' => __('Edit Smart Itinerary', 'oksia-smart-itinerary-agent'),
                    'new_item' => __('New Smart Itinerary', 'oksia-smart-itinerary-agent'),
                    'view_item' => __('View Smart Itinerary', 'oksia-smart-itinerary-agent'),
                    'search_items' => __('Search Smart Itineraries', 'oksia-smart-itinerary-agent'),
                ),
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'menu_position' => 25,
                'menu_icon' => 'dashicons-media-document',
                'supports' => array('title', 'editor', 'thumbnail'),
                'has_archive' => false,
                'rewrite' => array('slug' => 'smart-itinerary'),
                'show_in_rest' => true,
                'capability_type' => array('oksia_itinerary', 'oksia_itineraries'),
                'map_meta_cap' => true,
                'capabilities' => array(
                    'edit_post' => 'edit_oksia_itinerary',
                    'read_post' => 'read_oksia_itinerary',
                    'delete_post' => 'delete_oksia_itinerary',
                    'edit_posts' => 'edit_oksia_itineraries',
                    'edit_others_posts' => 'edit_others_oksia_itineraries',
                    'publish_posts' => 'publish_oksia_itineraries',
                    'read_private_posts' => 'read_private_oksia_itineraries',
                    'delete_posts' => 'delete_oksia_itineraries',
                    'delete_private_posts' => 'delete_private_oksia_itineraries',
                    'delete_published_posts' => 'delete_published_oksia_itineraries',
                    'delete_others_posts' => 'delete_others_oksia_itineraries',
                    'edit_private_posts' => 'edit_private_oksia_itineraries',
                    'edit_published_posts' => 'edit_published_oksia_itineraries',
                    'create_posts' => 'edit_oksia_itineraries',
                ),
            )
        );
    }

    public function register_ticket_post_type() {
        register_post_type(
            self::TICKET_POST_TYPE,
            array(
                'labels' => array(
                    'name' => __('Support Tickets', 'oksia-smart-itinerary-agent'),
                    'singular_name' => __('Support Ticket', 'oksia-smart-itinerary-agent'),
                ),
                'public' => false,
                'show_ui' => false,
                'show_in_menu' => false,
                'show_in_rest' => false,
                'has_archive' => false,
                'exclude_from_search' => true,
                'publicly_queryable' => false,
                'rewrite' => false,
                'supports' => array('title', 'editor'),
            )
        );
    }
}

