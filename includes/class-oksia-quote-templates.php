<?php

if (!defined('ABSPATH')) {
    exit;
}

class OKSIA_Quote_Templates {
    const META_TEMPLATE_KEY = '_oksia_quote_template_style';
    const DEFAULT_TEMPLATE_KEY = 'default';

    public static function get_catalog() {
        return array(
            'default' => array(
                'label' => __('Default', 'oksia-smart-itinerary-agent'),
                'file' => 'Default.txt',
            ),
            '3d-style' => array(
                'label' => __('3D Style', 'oksia-smart-itinerary-agent'),
                'file' => '3D Style.txt',
            ),
            'funny' => array(
                'label' => __('Funny', 'oksia-smart-itinerary-agent'),
                'file' => 'Funny.txt',
            ),
            'group' => array(
                'label' => __('Group', 'oksia-smart-itinerary-agent'),
                'file' => 'Group.txt',
            ),
            'luxury' => array(
                'label' => __('Luxury', 'oksia-smart-itinerary-agent'),
                'file' => 'Luxury.txt',
            ),
            'military' => array(
                'label' => __('Military', 'oksia-smart-itinerary-agent'),
                'file' => 'Military.txt',
            ),
            'romantic' => array(
                'label' => __('Romantic', 'oksia-smart-itinerary-agent'),
                'file' => 'Romantic.txt',
            ),
            'seasons-style' => array(
                'label' => __('Seasons Style', 'oksia-smart-itinerary-agent'),
                'file' => 'Seasons Style.txt',
            ),
            'spiritual' => array(
                'label' => __('Spiritual', 'oksia-smart-itinerary-agent'),
                'file' => 'Spiritual.txt',
            ),
            'sporty' => array(
                'label' => __('Sporty', 'oksia-smart-itinerary-agent'),
                'file' => 'Sporty.txt',
            ),
        );
    }

    public static function get_default_template_key() {
        return self::DEFAULT_TEMPLATE_KEY;
    }

    public static function normalize_template_key($template_key) {
        $template_key = sanitize_key((string) $template_key);
        $catalog = self::get_catalog();

        if (isset($catalog[$template_key])) {
            return $template_key;
        }

        return self::DEFAULT_TEMPLATE_KEY;
    }

    public static function get_template_label($template_key) {
        $template_key = self::normalize_template_key($template_key);
        $catalog = self::get_catalog();

        return (string) ($catalog[$template_key]['label'] ?? __('Default', 'oksia-smart-itinerary-agent'));
    }

    public static function get_template_file_path($template_key) {
        $template_key = self::normalize_template_key($template_key);
        $catalog = self::get_catalog();

        if (empty($catalog[$template_key]['file'])) {
            return '';
        }

        $path = plugin_dir_path(OKSIA_FILE) . 'templates/' . $catalog[$template_key]['file'];
        return file_exists($path) ? $path : '';
    }

    public static function get_template_markup($template_key) {
        $path = self::get_template_file_path($template_key);
        if ('' === $path) {
            return '';
        }

        $markup = (string) file_get_contents($path);
        return '' !== trim($markup) ? $markup : '';
    }

    public static function get_selected_template_key($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return self::DEFAULT_TEMPLATE_KEY;
        }

        return self::normalize_template_key(get_post_meta($post_id, self::META_TEMPLATE_KEY, true));
    }

    public static function set_selected_template_key($post_id, $template_key) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return false;
        }

        return update_post_meta($post_id, self::META_TEMPLATE_KEY, self::normalize_template_key($template_key));
    }

    public static function ensure_default_template_key($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return false;
        }

        $current = trim((string) get_post_meta($post_id, self::META_TEMPLATE_KEY, true));
        if ('' !== $current) {
            return true;
        }

        return self::set_selected_template_key($post_id, self::DEFAULT_TEMPLATE_KEY);
    }

    public static function get_allowed_template_keys_for_plan($plan_key) {
        $catalog_keys = array_keys(self::get_catalog());
        $plan_key = sanitize_key((string) $plan_key);

        $non_default_keys = array_values(array_filter($catalog_keys, function ($template_key) {
            return self::DEFAULT_TEMPLATE_KEY !== $template_key;
        }));

        $default_map = array(
            'economy' => array(self::DEFAULT_TEMPLATE_KEY),
            'premium' => array_merge(array(self::DEFAULT_TEMPLATE_KEY), array_slice($non_default_keys, 0, 4)),
            'business' => $catalog_keys,
        );

        $allowed = $default_map[$plan_key] ?? array(self::DEFAULT_TEMPLATE_KEY);
        $allowed = array_values(array_unique(array_filter(array_map(array(__CLASS__, 'normalize_template_key'), $allowed))));

        return apply_filters('oksia_quote_template_allowed_keys', $allowed, $plan_key, $catalog_keys);
    }

    public static function get_allowed_template_options_for_agency($agency_id) {
        $agency_id = absint($agency_id);
        $plan_key = 'economy';

        if ($agency_id && class_exists('OKSIA_Agencies')) {
            $tier = OKSIA_Agencies::instance()->get_agency_subscription_tier($agency_id);
            if (is_array($tier) && !empty($tier['label'])) {
                $plan_key = sanitize_key((string) $tier['label']);
            }
        }

        $allowed_keys = self::get_allowed_template_keys_for_plan($plan_key);
        $catalog = self::get_catalog();
        $options = array();

        foreach ($allowed_keys as $template_key) {
            if (isset($catalog[$template_key])) {
                $options[$template_key] = (string) $catalog[$template_key]['label'];
            }
        }

        return $options;
    }
}
