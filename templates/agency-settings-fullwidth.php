<?php
$oksia_agency_details_id = 0;
if (isset($this) && method_exists($this, 'get_current_agency_id')) {
    $oksia_agency_details_id = absint($this->get_current_agency_id());
}
if (!$oksia_agency_details_id && class_exists('OKSIA_Agencies')) {
    $oksia_agency_details_id = absint(get_option(OKSIA_Agencies::OPTION_PRIMARY_AGENCY_ID, 0));
}

$oksia_agency_type_options = class_exists('OKSIA_Agencies') ? OKSIA_Agencies::get_agency_type_options() : array();
$oksia_company_type_options = class_exists('OKSIA_Agencies') ? OKSIA_Agencies::get_company_type_options() : array();

$oksia_get_agency_detail = static function($meta_key, $option_key = '') use ($oksia_agency_details_id) {
    $value = '';

    if ($oksia_agency_details_id > 0) {
        $value = trim((string) get_post_meta($oksia_agency_details_id, $meta_key, true));
    }

    if ('' === $value && '' !== $option_key) {
        $value = trim((string) get_option($option_key, ''));
    }

    return $value;
};

$oksia_format_choice = static function($value, array $choices) {
    $value = trim((string) $value);
    if ('' === $value) {
        return __('Not set', 'oksia-smart-itinerary-agent');
    }

    return isset($choices[$value]) ? $choices[$value] : $value;
};

$oksia_agency_name_value = '';
if ($oksia_agency_details_id > 0) {
    $oksia_agency_name_value = trim((string) get_post_field('post_title', $oksia_agency_details_id, 'raw'));
}
if ('' === $oksia_agency_name_value) {
    $oksia_agency_name_value = trim((string) get_option('oksia_agency_name', ''));
}

$oksia_agency_readonly_cards = array(
    array('label' => 'GST Name', 'value' => $oksia_get_agency_detail('oksia_billing_company', 'oksia_billing_company')),
    array('label' => 'GST Number / PAN Number', 'value' => $oksia_get_agency_detail('oksia_billing_gst', 'oksia_billing_gst')),
    array('label' => 'Company Type', 'value' => $oksia_format_choice($oksia_get_agency_detail(OKSIA_Agencies::META_LEGAL_ENTITY, 'oksia_agency_legal_entity'), $oksia_company_type_options)),
    array('label' => 'Agency Name', 'value' => $oksia_agency_name_value),
    array('label' => 'Agency Type', 'value' => $oksia_format_choice($oksia_get_agency_detail(OKSIA_Agencies::META_AGENCY_TYPE, 'oksia_agency_type'), $oksia_agency_type_options)),
    array('label' => 'Agency Website', 'value' => $oksia_get_agency_detail('oksia_agency_website', 'oksia_agency_website')),
    array('label' => 'Name', 'value' => $oksia_get_agency_detail('oksia_authorize_name', 'oksia_authorize_name')),
    array('label' => 'Contact', 'value' => $oksia_get_agency_detail('oksia_agency_phone', 'oksia_agency_phone')),
    array('label' => 'Email', 'value' => $oksia_get_agency_detail('oksia_agency_email', 'oksia_agency_email')),
    array('label' => 'Building', 'value' => $oksia_get_agency_detail('oksia_agency_building', 'oksia_agency_building')),
    array('label' => 'Landmark', 'value' => $oksia_get_agency_detail('oksia_agency_landmark', 'oksia_agency_landmark')),
    array('label' => 'Area', 'value' => $oksia_get_agency_detail('oksia_agency_area', 'oksia_agency_area')),
    array('label' => 'City', 'value' => $oksia_get_agency_detail('oksia_agency_location', 'oksia_agency_location')),
    array('label' => 'State', 'value' => $oksia_get_agency_detail('oksia_agency_state', 'oksia_agency_state')),
    array('label' => 'Pincode', 'value' => $oksia_get_agency_detail('oksia_agency_pincode', 'oksia_agency_pincode')),
);

$oksia_agency_editable_fields = array(
    array('label' => 'FB Page', 'option' => 'oksia_agency_fb_page', 'value' => $oksia_get_agency_detail('oksia_agency_fb_page', 'oksia_agency_fb_page'), 'type' => 'text'),
    array('label' => 'Instagram', 'option' => 'oksia_agency_instagram', 'value' => $oksia_get_agency_detail('oksia_agency_instagram', 'oksia_agency_instagram'), 'type' => 'text'),
    array('label' => 'Google', 'option' => 'oksia_agency_google', 'value' => $oksia_get_agency_detail('oksia_agency_google', 'oksia_agency_google'), 'type' => 'text'),
    array('label' => 'Agency Logo', 'option' => 'oksia_agency_logo_url', 'value' => $oksia_get_agency_detail('oksia_agency_logo_url', 'oksia_agency_logo_url'), 'type' => 'file'),
    array('label' => 'Agency Tag Line', 'option' => 'oksia_intake_tagline', 'value' => $oksia_get_agency_detail('oksia_intake_tagline', 'oksia_intake_tagline'), 'type' => 'text'),
    array('label' => 'IATA/TIDS', 'option' => 'oksia_iata_code', 'value' => $oksia_get_agency_detail('oksia_iata_code', 'oksia_iata_code'), 'type' => 'text'),
);

$oksia_agencies_instance = class_exists('OKSIA_Agencies') ? OKSIA_Agencies::instance() : null;
$oksia_subscription_models = class_exists('OKSIA_Agencies') ? OKSIA_Agencies::get_subscription_models() : array(
    'economy' => array('code' => 'E', 'label' => __('Economy', 'oksia-smart-itinerary-agent'), 'users' => 1, 'range' => __('1 user', 'oksia-smart-itinerary-agent')),
    'premium' => array('code' => 'P', 'label' => __('Premium', 'oksia-smart-itinerary-agent'), 'users' => 3, 'range' => __('2-4 users', 'oksia-smart-itinerary-agent')),
    'business' => array('code' => 'B', 'label' => __('Business', 'oksia-smart-itinerary-agent'), 'users' => 5, 'range' => __('5+ users', 'oksia-smart-itinerary-agent')),
);
$oksia_current_subscription = ($oksia_agencies_instance && $oksia_agency_details_id > 0) ? $oksia_agencies_instance->get_agency_subscription_tier($oksia_agency_details_id) : $oksia_subscription_models['economy'];
$oksia_trial_info = ($oksia_agencies_instance && $oksia_agency_details_id > 0) ? $oksia_agencies_instance->get_agency_trial_info($oksia_agency_details_id) : array(
    'status' => 'unknown',
    'trial_expires_at' => '',
    'days_left' => null,
);
$oksia_plan_feature_labels = array(
    'unlimited_quotes' => __('Unlimited Quotes', 'oksia-smart-itinerary-agent'),
    'currency_rates' => __('Currency Rates', 'oksia-smart-itinerary-agent'),
    'world_clock' => __('World Clock', 'oksia-smart-itinerary-agent'),
    'age_calculator' => __('Age Calculator', 'oksia-smart-itinerary-agent'),
    'pdf_output' => __('PDF Output', 'oksia-smart-itinerary-agent'),
    'agency_color' => __('Agency Color', 'oksia-smart-itinerary-agent'),
    'single_mode' => __('Single Mode', 'oksia-smart-itinerary-agent'),
    'multi_mode' => __('Multi Mode', 'oksia-smart-itinerary-agent'),
    'quote_share_link' => __('Quote Share Link', 'oksia-smart-itinerary-agent'),
    'client_intake_feature' => __('Client Intake', 'oksia-smart-itinerary-agent'),
    'markup' => __('Markup', 'oksia-smart-itinerary-agent'),
    'inr_conversion' => __('INR Conversion', 'oksia-smart-itinerary-agent'),
);
$oksia_plan_feature_matrix_defaults = array();
foreach (array_keys($oksia_subscription_models) as $oksia_plan_key) {
    $oksia_plan_feature_matrix_defaults[$oksia_plan_key] = array_fill_keys(array_keys($oksia_plan_feature_labels), 1);
}
$oksia_plan_feature_matrix = wp_parse_args((array) get_option('oksia_plan_feature_matrix', array()), $oksia_plan_feature_matrix_defaults);
foreach ($oksia_plan_feature_matrix_defaults as $oksia_plan_key => $oksia_feature_defaults) {
    $oksia_plan_feature_matrix[$oksia_plan_key] = wp_parse_args((array) ($oksia_plan_feature_matrix[$oksia_plan_key] ?? array()), $oksia_feature_defaults);
}
$oksia_current_plan_key = 'economy';
foreach ($oksia_subscription_models as $oksia_plan_key => $oksia_plan_meta) {
    if (($oksia_plan_meta['code'] ?? '') === ($oksia_current_subscription['code'] ?? '')) {
        $oksia_current_plan_key = $oksia_plan_key;
        break;
    }
}
$oksia_plan_order = array('economy' => 1, 'premium' => 2, 'business' => 3);
$oksia_billing_info_cards = array(
    array('label' => 'GST Name', 'value' => $oksia_get_agency_detail('oksia_billing_company', 'oksia_billing_company')),
    array('label' => 'GST Number / PAN Number', 'value' => $oksia_get_agency_detail('oksia_billing_gst', 'oksia_billing_gst')),
    array('label' => 'GST Email Address', 'value' => $oksia_get_agency_detail('oksia_billing_email', 'oksia_billing_email')),
    array('label' => 'State', 'value' => $oksia_get_agency_detail('oksia_agency_state', 'oksia_agency_state')),
);
$oksia_subscription_cards = array_values($oksia_subscription_models);

$oksia_accommodation_placeholders = array(
    'hotel_categories' => 'e.g. 3 Star, 4 Star, 5 Star',
    'occupancies' => 'e.g. Single, Double, Triple, Quad',
    'meal_plans' => 'e.g. No Meals, Breakfast, Breakfast & Dinner',
    'meal_transfer_types' => 'e.g. Included, Excluded',
);

$oksia_accommodation_values = array(
    'hotel_categories_domestic' => (string) get_option('oksia_hotel_categories_domestic', ''),
    'hotel_categories_international' => (string) get_option('oksia_hotel_categories_international', ''),
    'occupancies_domestic' => (string) get_option('oksia_occupancies_domestic', ''),
    'occupancies_international' => (string) get_option('oksia_occupancies_international', ''),
    'meal_plans_domestic' => (string) get_option('oksia_meal_plans_domestic', ''),
    'meal_plans_international' => (string) get_option('oksia_meal_plans_international', ''),
    'meal_transfer_types_domestic' => (string) get_option('oksia_meal_transfer_types_domestic', ''),
    'meal_transfer_types_international' => (string) get_option('oksia_meal_transfer_types_international', ''),
);

$oksia_transportation_placeholders = array(
    'pickup_points' => 'e.g. Airport, Railway Station',
    'drop_points' => 'e.g. Airport, Railway Station',
    'transfer_modes' => 'e.g. Private, SIC - Sharing in Coach',
    'sightseeing_vehicles' => 'e.g. Private, SIC - Sharing in Coach, Disposable Vehicle',
    'vehicle_types' => 'e.g. Sedan, Ertiga/Xylo, Innova/SUV',
);

$oksia_transportation_values = array(
    'pickup_points_domestic' => (string) get_option('oksia_pickup_points_domestic', ''),
    'pickup_points_international' => (string) get_option('oksia_pickup_points_international', ''),
    'drop_points_domestic' => (string) get_option('oksia_drop_points_domestic', ''),
    'drop_points_international' => (string) get_option('oksia_drop_points_international', ''),
    'transfer_modes_domestic' => (string) get_option('oksia_transfer_modes_domestic', ''),
    'transfer_modes_international' => (string) get_option('oksia_transfer_modes_international', ''),
    'sightseeing_vehicles_domestic' => (string) get_option('oksia_sightseeing_vehicles_domestic', ''),
    'sightseeing_vehicles_international' => (string) get_option('oksia_sightseeing_vehicles_international', ''),
    'vehicle_types_domestic' => (string) get_option('oksia_vehicle_types_domestic', ''),
    'vehicle_types_international' => (string) get_option('oksia_vehicle_types_international', ''),
);

$oksia_policy_placeholders = array(
    'inclusions' => "Accommodation in selected hotels with base room category\nSelected meals as per itinerary\nSightseeing as specified in the itinerary",
    'exclusions' => "Sightseeing other than specified in the itinerary is chargeable\nPersonal expenses and extra meals are not included\nAny incidental expenses not specified are excluded",
    'child_policy' => "Below 5 years: Complimentary\nUp to 7 years: Chargeable without bed\nAbove 7 years: Chargeable with bed\nAbove 10 years: Extra adult charge",
    'cancellation_policy' => "0 to 10 days before check-in: 100% charge\n11 to 20 days before check-in: 75% charge\n21 to 30 days before check-in: 35% charge",
    'booking_policy' => "Booking confirmation after 50% advance payment\nVouchers will be issued once services are reconfirmed\n100% payment required before travel",
    'refund_policy' => "Refund will be completed within 7 working days",
);

$oksia_policy_values = array(
    'default_inclusions' => (string) get_option('oksia_default_inclusions', ''),
    'default_exclusions' => (string) get_option('oksia_default_exclusions', ''),
    'default_child_policy' => (string) get_option('oksia_default_child_policy', ''),
    'default_cancellation_policy' => (string) get_option('oksia_default_cancellation_policy', ''),
    'default_booking_policy' => (string) get_option('oksia_default_booking_policy', ''),
    'default_refund_policy' => (string) get_option('oksia_default_refund_policy', ''),
    'domestic_inclusions' => (string) get_option('oksia_domestic_inclusions', ''),
    'domestic_exclusions' => (string) get_option('oksia_domestic_exclusions', ''),
    'domestic_child_policy' => (string) get_option('oksia_domestic_child_policy', ''),
    'domestic_cancellation_policy' => (string) get_option('oksia_domestic_cancellation_policy', ''),
    'domestic_booking_policy' => (string) get_option('oksia_domestic_booking_policy', ''),
    'domestic_refund_policy' => (string) get_option('oksia_domestic_refund_policy', ''),
);

$oksia_team_user_label = static function($user_id) {
    $user_id = absint($user_id);
    if (!$user_id) {
        return '';
    }

    $user = get_user_by('id', $user_id);
    if (!$user instanceof WP_User) {
        return '';
    }

    $label = trim((string) $user->display_name);
    if (!empty($user->user_email)) {
        $label .= ' (' . $user->user_email . ')';
    }

    return trim($label);
};

$oksia_team_user_list = static function($ids) use ($oksia_team_user_label) {
    $labels = array();
    foreach ((array) $ids as $id) {
        $label = $oksia_team_user_label($id);
        if ('' !== $label) {
            $labels[] = $label;
        }
    }
    return implode(', ', $labels);
};

$oksia_team_values = array(
    'main_agency_user_id' => $oksia_team_user_label(get_option('oksia_main_agency_user_id', 0)),
    'agency_manager_user_ids' => $oksia_team_user_list(get_option('oksia_agency_manager_user_ids', array())),
    'agency_staff_user_ids' => $oksia_team_user_list(get_option('oksia_agency_staff_user_ids', array())),
    'primary_color' => (string) get_option('oksia_primary_color', '#000066'),
    'secondary_color' => (string) get_option('oksia_secondary_color', '#336699'),
    'accent_color' => (string) get_option('oksia_accent_color', '#99FFFF'),
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Agency Workflow Profile</title>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1d4ed8 0%, #111827 100%);
            --accent-glow: rgba(29, 78, 216, 0.15);
            --bg-canvas: #f4f7fc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --transition: all 0.2s ease-in-out;
        }

        body {
            background-color: var(--bg-canvas);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        html {
            overflow-y: scroll;
            scrollbar-gutter: stable both-edges;
        }

        /* 100% Full-width responsive container breakout */
        .workspace-container {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            width: 100%;
            margin: 0;
            padding: 16px;
            box-sizing: border-box;
        }

        .premium-card-wrapper {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            padding: 20px;
            width: 100%;
            box-sizing: border-box;
        }

        .tab-switcher {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        /* Responsive 10-Tab Horizontal Scrolling Engine */
        .tabs-nav {
            display: flex;
            gap: 6px;
            background: #f1f5f9;
            padding: 6px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
            width: 100%;
            box-sizing: border-box;
            align-items: center;
            
            /* Ensures smooth swipe experience for dense 10-tab collections */
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; 
        }
        
        .tabs-nav::-webkit-scrollbar {
            display: none; 
        }

        .tab-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: transparent;
            color: var(--text-muted);
            border-radius: var(--radius-sm);
            padding: 12px 18px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            user-select: none;
            transition: var(--transition);
            border: none;
            flex-shrink: 0;
        }

        .tab-button svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
            flex-shrink: 0;
        }

        /* CSS Engine for Active Tab Selection Checks */
        #tab-agency:checked ~ .tabs-nav label[for="tab-agency"],
        #tab-manager:checked ~ .tabs-nav label[for="tab-manager"],
        #tab-destination:checked ~ .tabs-nav label[for="tab-destination"],
        #tab-accommodation:checked ~ .tabs-nav label[for="tab-accommodation"],
        #tab-transportation:checked ~ .tabs-nav label[for="tab-transportation"],
        #tab-inclusions:checked ~ .tabs-nav label[for="tab-inclusions"],
        #tab-booking:checked ~ .tabs-nav label[for="tab-booking"],
        #tab-child:checked ~ .tabs-nav label[for="tab-child"],
        #tab-visa:checked ~ .tabs-nav label[for="tab-visa"],
        #tab-billing:checked ~ .tabs-nav label[for="tab-billing"] {
            background: var(--bg-card);
            color: #2563eb;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .tab-panel {
            display: none;
            animation: fadeIn 0.2s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Display Logic for Active Panels */
        #tab-agency:checked ~ .panels-container #panel-agency,
        #tab-manager:checked ~ .panels-container #panel-manager,
        #tab-destination:checked ~ .panels-container #panel-destination,
        #tab-accommodation:checked ~ .panels-container #panel-accommodation,
        #tab-transportation:checked ~ .panels-container #panel-transportation,
        #tab-inclusions:checked ~ .panels-container #panel-inclusions,
        #tab-booking:checked ~ .panels-container #panel-booking,
        #tab-child:checked ~ .panels-container #panel-child,
        #tab-visa:checked ~ .panels-container #panel-visa,
        #tab-billing:checked ~ .panels-container #panel-billing {
            display: block;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 6px;
            color: var(--text-main);
        }

        .section-note {
            margin: 0 0 24px;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.4;
        }

        /* Auto-sizing responsive layout grids */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .settings-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .settings-field label {
            font-weight: 600;
            color: var(--text-main);
            font-size: 13px;
        }

        .settings-field input[type="text"],
        .settings-field input[type="email"],
        .settings-field textarea,
        .settings-field input[type="color"],
        .settings-field select {
            padding: 12px;
            border: 1px solid var(--border-color);
            background-color: #f8fafc;
            border-radius: var(--radius-sm);
            font-size: 14px;
            color: var(--text-main);
            outline: none;
            width: 100%;
            box-sizing: border-box;
            transition: var(--transition);
        }

        .settings-field input[type="text"]:focus,
        .settings-field input[type="email"]:focus,
        .settings-field textarea:focus,
        .settings-field input[type="color"]:focus,
        .settings-field select:focus {
            border-color: #2563eb;
            background-color: var(--bg-card);
            box-shadow: 0 0 0 4px var(--accent-glow);
        }

        .settings-field textarea {
            min-height: 110px;
            resize: vertical;
        }

        .settings-field input[type="color"] {
            height: 44px;
            padding: 6px;
        }

        .settings-field .description {
            margin: 0;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .nested-settings-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            margin-top: 10px;
        }

        .nested-column-title {
            font-size: 15px;
            font-weight: 700;
            margin: 0;
            color: var(--text-main);
        }

        .billing-overview-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .billing-summary-card,
        .billing-plan-card {
            border: 1px solid var(--border-color);
            background: #f8fafc;
            border-radius: var(--radius-lg);
            padding: 18px;
            box-sizing: border-box;
        }

        .billing-summary-card h4,
        .billing-plan-card h4 {
            margin: 0 0 8px;
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }

        .billing-summary-value {
            font-size: 20px;
            font-weight: 800;
            color: #1d4ed8;
            margin: 0 0 8px;
        }

        .billing-summary-note {
            margin: 0;
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .billing-dashboard-row {
            display: flex;
            align-items: stretch;
            gap: 16px;
            width: 100%;
            margin-bottom: 24px;
        }

        .billing-summary-card {
            flex: 0 0 30%;
            max-width: 30%;
        }

        .billing-fields-card {
            flex: 1;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-sizing: border-box;
        }

        .form-inputs-row {
            display: flex;
            gap: 12px;
        }

        .input-group {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .input-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
        }

        .input-group input {
            padding: 10px 12px;
            font-size: 13px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            outline: none;
            background: #ffffff;
            color: var(--text-main);
            box-sizing: border-box;
        }

        .input-group input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .billing-plan-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .billing-plan-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
        }

        .billing-panel-topline {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 18px;
        }

        .billing-panel-topline .section-note {
            margin-bottom: 0;
            max-width: 62%;
        }

        .billing-panel-summary {
            min-width: 260px;
            padding: 14px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: #f8fafc;
            box-sizing: border-box;
        }

        .billing-panel-summary__label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            margin: 0 0 4px;
        }

        .billing-panel-summary__value {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }

        .billing-plan-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 4px;
        }

        .billing-plan-head h4 {
            margin: 0;
        }

        .billing-plan-card--active {
            border-color: #2563eb;
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.12);
        }

        .billing-plan-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
        }

        .billing-card-list {
            display: grid;
            gap: 10px;
        }

        .billing-feature-list {
            display: grid;
            gap: 8px;
            margin: 0 0 14px;
            padding: 0;
            list-style: none;
        }

        .billing-feature-list li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 13px;
            line-height: 1.45;
            color: var(--text-main);
        }

        .billing-feature-list li.is-disabled {
            color: var(--text-muted);
        }

        .billing-feature-icon {
            width: 18px;
            height: 18px;
            border-radius: 999px;
            flex: 0 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0;
            font-weight: 800;
            margin-top: 1px;
        }

        .billing-feature-icon--on {
            background: #dcfce7;
            color: #166534;
        }

        .billing-feature-icon--off {
            background: #fee2e2;
            color: #b91c1c;
        }

        .billing-feature-icon--on::before,
        .billing-feature-icon--off::before {
            font-size: 12px;
            line-height: 1;
        }

        .billing-feature-icon--on::before {
            content: '✓';
        }

        .billing-feature-icon--off::before {
            content: '×';
        }

        .billing-plan-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            border: 1px solid transparent;
            text-decoration: none;
            margin-top: 6px;
            box-sizing: border-box;
        }

        .billing-plan-action--current {
            background: #e2e8f0;
            color: var(--text-muted);
            border-color: #cbd5e1;
            cursor: default;
            pointer-events: none;
        }

        .billing-plan-action--upgrade {
            background: #1d4ed8;
            color: #ffffff;
        }

        .billing-plan-action--downgrade {
            background: #f59e0b;
            color: #ffffff;
        }

        .billing-card-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 10px 12px;
            background: #f8fafc;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }

        .billing-card-item label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .billing-card-item span {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            word-break: break-word;
        }

        .readonly-value {
            min-height: 38px;
            padding: 9px 12px;
            border: 1px solid var(--border-color);
            background: #f8fafc;
            border-radius: var(--radius-sm);
            font-size: 14px;
            color: var(--text-main);
            box-sizing: border-box;
            display: flex;
            align-items: center;
            line-height: 1.4;
            word-break: break-word;
        }

        .readonly-value--muted {
            color: var(--text-muted);
        }

        .readonly-value img {
            display: block;
            max-width: 100%;
            max-height: 96px;
            object-fit: contain;
        }

        .form-actions {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
        }

        .btn-submit {
            background: var(--primary-gradient);
            color: #ffffff;
            border: none;
            padding: 14px 24px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            transition: var(--transition);
        }

        .oksia-mobile-warning {
            display: none;
            width: 100%;
            box-sizing: border-box;
            padding: 24px 18px;
            margin: 16px auto;
            border: 1px solid #fde68a;
            border-radius: var(--radius-lg);
            background: #fffbeb;
            color: #92400e;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 600;
            text-align: center;
        }

        @media (min-width: 600px) {
            .workspace-container { padding: 24px; }
            .premium-card-wrapper { padding: 32px; }
            .settings-grid { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
            .billing-overview-grid,
            .billing-plan-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .btn-submit { width: auto; }
            .form-actions { justify-content: flex-end; }
        }

        @media (max-width: 900px) {
            .billing-dashboard-row {
                flex-direction: column;
            }

            .billing-summary-card {
                flex: 1 1 auto;
                max-width: none;
            }

            .billing-overview-grid,
            .billing-plan-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767px) {
            body {
                padding: 14px 12px;
            }

            .oksia-mobile-warning {
                display: block;
            }

            .workspace-container,
            .premium-card-wrapper {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="oksia-mobile-warning">
        Agency Master Settings can't be changed from mobile. Please switch to a computer to change AMS.
    </div>

    <div class="workspace-container">
        <div class="premium-card-wrapper">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                
                <input type="hidden" name="action" value="oksia_save_agency_settings">
                <?php wp_nonce_field('oksia_save_agency_settings', 'oksia_agency_settings_nonce'); ?>
                <input type="hidden" name="_wp_http_referer" value="/agency-settings/">

                <!-- Functional Tab Switches -->
                <input type="radio" name="profile_tabs" id="tab-agency" class="tab-switcher" checked>
                <input type="radio" name="profile_tabs" id="tab-manager" class="tab-switcher">
                <input type="radio" name="profile_tabs" id="tab-destination" class="tab-switcher">
                <input type="radio" name="profile_tabs" id="tab-accommodation" class="tab-switcher">
                <input type="radio" name="profile_tabs" id="tab-transportation" class="tab-switcher">
                <input type="radio" name="profile_tabs" id="tab-inclusions" class="tab-switcher">
                <input type="radio" name="profile_tabs" id="tab-booking" class="tab-switcher">
                <input type="radio" name="profile_tabs" id="tab-child" class="tab-switcher">
                <input type="radio" name="profile_tabs" id="tab-visa" class="tab-switcher">
                <input type="radio" name="profile_tabs" id="tab-billing" class="tab-switcher">

                                <!-- 10-Item Nav Menu Strip -->
                <div class="tabs-nav">
                    <label for="tab-agency" class="tab-button">
                        <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
                        Agency Details
                    </label>
                <label for="tab-manager" class="tab-button">
                    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.67 0-8 1.34-8 4v2h10v-2c0-1.3.42-2.48 1.15-3.48C9.58 13.19 8.06 13 8 13zm8 0c-.64 0-1.26.06-1.84.17.55.75.84 1.62.84 2.53v2h9v-2c0-2.66-5-4.7-8-4.7z"/></svg>
                    Team Manager
                </label>
                    <label for="tab-destination" class="tab-button">
                        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        Destination
                    </label>
                    <label for="tab-accommodation" class="tab-button">
                        <svg viewBox="0 0 24 24"><path d="M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V5H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4z"/></svg>
                        Accommodation
                    </label>
                <label for="tab-transportation" class="tab-button">
                    <svg viewBox="0 0 24 24"><path d="M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11h1a1 1 0 0 1 1 1v5h-2a2 2 0 0 1-4 0H9a2 2 0 0 1-4 0H3v-5a1 1 0 0 1 1-1h1zm1.8 0h10.4l-1.1-3.3a.7.7 0 0 0-.7-.5H8.6a.7.7 0 0 0-.7.5L6.8 11zM7 14a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm10 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg>
                    Transportation
                </label>
                    <label for="tab-inclusions" class="tab-button">
                        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        Inclusions & Exclusions
                    </label>
                    <label for="tab-booking" class="tab-button">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        Booking & Refund
                    </label>
                    <label for="tab-child" class="tab-button">
                        <svg viewBox="0 0 24 24"><path d="M12 6c1.11 0 2-.89 2-2s-.89-2-2-2-2 .89-2 2 .89 2 2 2zm3.5 3.5c-.28 0-.53-.11-.71-.29L13.1 7.5c-.3-.3-.7-.5-1.1-.5s-.8.2-1.1.5L9.21 9.21c-.18.18-.43.29-.71.29-.55 0-1-.45-1-1 0-.28.11-.53.29-.71l1.71-1.71c.58-.58 1.35-.92 2.2-.92.85 0 1.62.34 2.2.92l1.71 1.71c.18.18.29.43.29.71 0 .55-.45 1-1 1zm-1.6 4.31c.21-.3.51-.55.87-.7l1.9-.76c.46-.19.73-.67.62-1.16-.11-.48-.59-.79-1.08-.68l-2.61.52c-.67.13-1.22.58-1.47 1.19l-.53 1.32h-3.4c-.66 0-1.2.54-1.2 1.2v4.8c0 .66.54 1.2 1.2 1.2h1.2v3.6c0 .66.54 1.2 1.2 1.2h2.4c.66 0 1.2-.54 1.2-1.2v-3.6h1.2c.66 0 1.2-.54 1.2-1.2v-6.03l-.42-.1z"/></svg>
                        Child & Cancellation
                    </label>
                    <label for="tab-visa" class="tab-button">
                        <svg viewBox="0 0 24 24"><path d="M21 4H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-1 14H4V6h16v12zm-6.5-2c1.38 0 2.5-1.12 2.5-2.5S14.88 11 13.5 11s-2.5 1.12-2.5 2.5 1.12 2.5 2.5 2.5z"/></svg>
                        Visa & Forex
                    </label>
                    <label for="tab-billing" class="tab-button">
                        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V6h16v12zm-2-8H6V8h12v2z"/></svg>
                        Billing
                    </label>
                </div>

                <div class="panels-container">
                <!-- PANEL 1: AGENCY -->
                <div class="tab-panel" id="panel-agency">
                    <h3 class="section-title">Agency Details</h3>
                    <p class="section-note">These values come from the registration form. The top section is read only, while the last six fields can be edited here.</p>
                    <div class="settings-grid">
                        <?php foreach ($oksia_agency_readonly_cards as $card) : ?>
                            <div class="settings-field<?php echo !empty($card['span']) ? ' oksia-settings-field--span-' . absint($card['span']) : ''; ?>">
                                <label><?php echo esc_html($card['label']); ?></label>
                                <div class="readonly-value<?php echo empty($card['value']) ? ' readonly-value--muted' : ''; ?>">
                                    <?php echo '' !== trim((string) $card['value']) ? esc_html($card['value']) : esc_html__('Not set', 'oksia-smart-itinerary-agent'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($oksia_agency_editable_fields as $card) : ?>
                            <div class="settings-field">
                                <label for="<?php echo esc_attr($card['option']); ?>"><?php echo esc_html($card['label']); ?></label>
                                <?php if ('file' === $card['type']) : ?>
                                    <input type="hidden" id="<?php echo esc_attr($card['option']); ?>" name="<?php echo esc_attr($card['option']); ?>" value="<?php echo esc_attr($card['value']); ?>">
                                    <?php if (!empty($card['value'])) : ?>
                                        <div class="readonly-value" style="margin-bottom:8px;">
                                            <img src="<?php echo esc_url($card['value']); ?>" alt="<?php echo esc_attr($card['label']); ?>">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" id="<?php echo esc_attr($card['option']); ?>_upload" name="<?php echo esc_attr($card['option']); ?>_upload" accept="image/*">
                                <?php else : ?>
                                    <input type="text" id="<?php echo esc_attr($card['option']); ?>" name="<?php echo esc_attr($card['option']); ?>" value="<?php echo esc_attr($card['value']); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- PANEL 2: MANAGER -->
                <div class="tab-panel" id="panel-manager">
                    <h3 class="section-title">Team Manager</h3>
                    <p class="section-note">Assign core personnel parameters and workspace operations.</p>
                    <div class="settings-grid">
                        <div class="settings-field">
                            <label for="oksia_main_agency_user_id_lookup">Main Admin</label>
                            <input type="text" id="oksia_main_agency_user_id_lookup" name="oksia_main_agency_user_id_lookup" value="<?php echo esc_attr($oksia_team_values['main_agency_user_id']); ?>" placeholder="Use current admin fallback">
                        </div>
                        <div class="settings-field">
                            <label for="oksia_agency_manager_user_ids_lookup">Manager</label>
                            <textarea id="oksia_agency_manager_user_ids_lookup" name="oksia_agency_manager_user_ids_lookup" rows="5" placeholder="Type names, separate with comma"><?php echo esc_textarea($oksia_team_values['agency_manager_user_ids']); ?></textarea>
                        </div>
                        <div class="settings-field">
                            <label for="oksia_agency_staff_user_ids_lookup">Staff</label>
                            <textarea id="oksia_agency_staff_user_ids_lookup" name="oksia_agency_staff_user_ids_lookup" rows="5" placeholder="Type names, separate with comma"><?php echo esc_textarea($oksia_team_values['agency_staff_user_ids']); ?></textarea>
                        </div>
                        <div class="settings-field">
                            <label for="oksia_primary_color">Primary Color</label>
                            <input type="color" id="oksia_primary_color" name="oksia_primary_color" value="<?php echo esc_attr($oksia_team_values['primary_color']); ?>">
                        </div>
                        <div class="settings-field">
                            <label for="oksia_secondary_color">Secondary</label>
                            <input type="color" id="oksia_secondary_color" name="oksia_secondary_color" value="<?php echo esc_attr($oksia_team_values['secondary_color']); ?>">
                        </div>
                        <div class="settings-field">
                            <label for="oksia_accent_color">Accent</label>
                            <input type="color" id="oksia_accent_color" name="oksia_accent_color" value="<?php echo esc_attr($oksia_team_values['accent_color']); ?>">
                        </div>
                    </div>
                </div>

                <!-- PANEL 3: DESTINATION -->
                <div class="tab-panel" id="panel-destination">
                    <h3 class="section-title">Destination Coverage</h3>
                    <p class="section-note">Define regional operational hubs for both markets.</p>
                    <div class="settings-grid">
                        <div class="settings-field">
                            <label for="destinations_domestic">Domestic Destinations</label>
                            <textarea id="destinations_domestic" name="oksia_domestic_destinations" rows="8" placeholder="e.g. Goa&#10;Kerala&#10;Himachal"><?php echo esc_textarea((string) get_option('oksia_domestic_destinations', '')); ?></textarea>
                            <p class="description">Use one value per line.</p>
                        </div>
                        <div class="settings-field">
                            <label for="destinations_international">International Destinations</label>
                            <textarea id="destinations_international" name="oksia_international_destinations" rows="8" placeholder="e.g. Dubai&#10;Thailand&#10;Europe"><?php echo esc_textarea((string) get_option('oksia_international_destinations', '')); ?></textarea>
                            <p class="description">Use one value per line.</p>
                        </div>
                    </div>
                </div>

                <!-- PANEL 4: ACCOMMODATION -->
                <div class="tab-panel" id="panel-accommodation">
                    <h3 class="section-title">Accommodation Vendor Tier Rules</h3>
                    <p class="section-note">Configure lodging rules for local and overseas properties.</p>
                    <div class="settings-grid">
                        <div class="settings-field">
                            <h4 class="nested-column-title">Domestic</h4>
                            <div class="nested-settings-grid">
                                <div class="settings-field">
                                    <label for="oksia_hotel_categories_domestic">Hotel Categories</label>
                                    <textarea id="oksia_hotel_categories_domestic" name="oksia_hotel_categories_domestic" rows="5" placeholder="<?php echo esc_attr($oksia_accommodation_placeholders['hotel_categories']); ?>"><?php echo esc_textarea($oksia_accommodation_values['hotel_categories_domestic']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_occupancies_domestic">Occupancies</label>
                                    <textarea id="oksia_occupancies_domestic" name="oksia_occupancies_domestic" rows="5" placeholder="<?php echo esc_attr($oksia_accommodation_placeholders['occupancies']); ?>"><?php echo esc_textarea($oksia_accommodation_values['occupancies_domestic']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_meal_plans_domestic">Meal Plans</label>
                                    <textarea id="oksia_meal_plans_domestic" name="oksia_meal_plans_domestic" rows="5" placeholder="<?php echo esc_attr($oksia_accommodation_placeholders['meal_plans']); ?>"><?php echo esc_textarea($oksia_accommodation_values['meal_plans_domestic']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_meal_transfer_types_domestic">Meal Transfer</label>
                                    <textarea id="oksia_meal_transfer_types_domestic" name="oksia_meal_transfer_types_domestic" rows="5" placeholder="<?php echo esc_attr($oksia_accommodation_placeholders['meal_transfer_types']); ?>"><?php echo esc_textarea($oksia_accommodation_values['meal_transfer_types_domestic']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                            </div>
                        </div>
                        <div class="settings-field">
                            <h4 class="nested-column-title">International</h4>
                            <div class="nested-settings-grid">
                                <div class="settings-field">
                                    <label for="oksia_hotel_categories_international">Hotel Categories</label>
                                    <textarea id="oksia_hotel_categories_international" name="oksia_hotel_categories_international" rows="5" placeholder="<?php echo esc_attr($oksia_accommodation_placeholders['hotel_categories']); ?>"><?php echo esc_textarea($oksia_accommodation_values['hotel_categories_international']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_occupancies_international">Occupancies</label>
                                    <textarea id="oksia_occupancies_international" name="oksia_occupancies_international" rows="5" placeholder="<?php echo esc_attr($oksia_accommodation_placeholders['occupancies']); ?>"><?php echo esc_textarea($oksia_accommodation_values['occupancies_international']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_meal_plans_international">Meal Plans</label>
                                    <textarea id="oksia_meal_plans_international" name="oksia_meal_plans_international" rows="5" placeholder="<?php echo esc_attr($oksia_accommodation_placeholders['meal_plans']); ?>"><?php echo esc_textarea($oksia_accommodation_values['meal_plans_international']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_meal_transfer_types_international">Meal Transfer</label>
                                    <textarea id="oksia_meal_transfer_types_international" name="oksia_meal_transfer_types_international" rows="5" placeholder="<?php echo esc_attr($oksia_accommodation_placeholders['meal_transfer_types']); ?>"><?php echo esc_textarea($oksia_accommodation_values['meal_transfer_types_international']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANEL 5: TRANSPORTATION -->
                <div class="tab-panel" id="panel-transportation">
                    <h3 class="section-title">Transportation Fleet Mechanics</h3>
                    <p class="section-note">Set preferred vehicle or transfer choices by region.</p>
                    <div class="settings-grid">
                        <div class="settings-field">
                            <h4 class="nested-column-title">Domestic</h4>
                            <div class="nested-settings-grid">
                                <div class="settings-field">
                                    <label for="oksia_pickup_points_domestic">Pickup Points</label>
                                    <textarea id="oksia_pickup_points_domestic" name="oksia_pickup_points_domestic" rows="5" placeholder="<?php echo esc_attr($oksia_transportation_placeholders['pickup_points']); ?>"><?php echo esc_textarea($oksia_transportation_values['pickup_points_domestic']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_drop_points_domestic">Drop Points</label>
                                    <textarea id="oksia_drop_points_domestic" name="oksia_drop_points_domestic" rows="5" placeholder="<?php echo esc_attr($oksia_transportation_placeholders['drop_points']); ?>"><?php echo esc_textarea($oksia_transportation_values['drop_points_domestic']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_transfer_modes_domestic">Transfer Modes</label>
                                    <textarea id="oksia_transfer_modes_domestic" name="oksia_transfer_modes_domestic" rows="5" placeholder="<?php echo esc_attr($oksia_transportation_placeholders['transfer_modes']); ?>"><?php echo esc_textarea($oksia_transportation_values['transfer_modes_domestic']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_sightseeing_vehicles_domestic">Sightseeing Vehicles</label>
                                    <textarea id="oksia_sightseeing_vehicles_domestic" name="oksia_sightseeing_vehicles_domestic" rows="5" placeholder="<?php echo esc_attr($oksia_transportation_placeholders['sightseeing_vehicles']); ?>"><?php echo esc_textarea($oksia_transportation_values['sightseeing_vehicles_domestic']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_vehicle_types_domestic">Vehicle Types</label>
                                    <textarea id="oksia_vehicle_types_domestic" name="oksia_vehicle_types_domestic" rows="5" placeholder="<?php echo esc_attr($oksia_transportation_placeholders['vehicle_types']); ?>"><?php echo esc_textarea($oksia_transportation_values['vehicle_types_domestic']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                            </div>
                        </div>
                        <div class="settings-field">
                            <h4 class="nested-column-title">International</h4>
                            <div class="nested-settings-grid">
                                <div class="settings-field">
                                    <label for="oksia_pickup_points_international">Pickup Points</label>
                                    <textarea id="oksia_pickup_points_international" name="oksia_pickup_points_international" rows="5" placeholder="<?php echo esc_attr($oksia_transportation_placeholders['pickup_points']); ?>"><?php echo esc_textarea($oksia_transportation_values['pickup_points_international']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_drop_points_international">Drop Points</label>
                                    <textarea id="oksia_drop_points_international" name="oksia_drop_points_international" rows="5" placeholder="<?php echo esc_attr($oksia_transportation_placeholders['drop_points']); ?>"><?php echo esc_textarea($oksia_transportation_values['drop_points_international']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_transfer_modes_international">Transfer Modes</label>
                                    <textarea id="oksia_transfer_modes_international" name="oksia_transfer_modes_international" rows="5" placeholder="<?php echo esc_attr($oksia_transportation_placeholders['transfer_modes']); ?>"><?php echo esc_textarea($oksia_transportation_values['transfer_modes_international']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_sightseeing_vehicles_international">Sightseeing Vehicles</label>
                                    <textarea id="oksia_sightseeing_vehicles_international" name="oksia_sightseeing_vehicles_international" rows="5" placeholder="<?php echo esc_attr($oksia_transportation_placeholders['sightseeing_vehicles']); ?>"><?php echo esc_textarea($oksia_transportation_values['sightseeing_vehicles_international']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_vehicle_types_international">Vehicle Types</label>
                                    <textarea id="oksia_vehicle_types_international" name="oksia_vehicle_types_international" rows="5" placeholder="<?php echo esc_attr($oksia_transportation_placeholders['vehicle_types']); ?>"><?php echo esc_textarea($oksia_transportation_values['vehicle_types_international']); ?></textarea>
                                    <p class="description">Use one value per line.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- PANEL 6: INCLUSIONS & EXCLUSIONS -->
                <div class="tab-panel" id="panel-inclusions">
                    <h3 class="section-title">Inclusions & Exclusions Formulas</h3>
                    <p class="section-note">Map package pricing components separately.</p>
                    <div class="settings-grid">
                        <div class="settings-field">
                            <h4 class="nested-column-title">Domestic</h4>
                            <div class="nested-settings-grid">
                                <div class="settings-field">
                                    <label for="oksia_domestic_inclusions">Inclusion</label>
                                    <textarea id="oksia_domestic_inclusions" name="oksia_domestic_inclusions" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['inclusions']); ?>"><?php echo esc_textarea($oksia_policy_values['domestic_inclusions']); ?></textarea>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_domestic_exclusions">Exclusions</label>
                                    <textarea id="oksia_domestic_exclusions" name="oksia_domestic_exclusions" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['exclusions']); ?>"><?php echo esc_textarea($oksia_policy_values['domestic_exclusions']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="settings-field">
                            <h4 class="nested-column-title">International</h4>
                            <div class="nested-settings-grid">
                                <div class="settings-field">
                                    <label for="oksia_default_inclusions">Inclusion</label>
                                    <textarea id="oksia_default_inclusions" name="oksia_default_inclusions" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['inclusions']); ?>"><?php echo esc_textarea($oksia_policy_values['default_inclusions']); ?></textarea>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_default_exclusions">Exclusions</label>
                                    <textarea id="oksia_default_exclusions" name="oksia_default_exclusions" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['exclusions']); ?>"><?php echo esc_textarea($oksia_policy_values['default_exclusions']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- PANEL 7: BOOKING & REFUND -->
                <div class="tab-panel" id="panel-booking">
                    <h3 class="section-title">Booking & Refund Legalities</h3>
                    <p class="section-note">Outline advance payment structures by market type.</p>
                    <div class="settings-grid">
                        <div class="settings-field">
                            <h4 class="nested-column-title">Domestic</h4>
                            <div class="nested-settings-grid">
                                <div class="settings-field">
                                    <label for="oksia_domestic_booking_policy">Booking</label>
                                    <textarea id="oksia_domestic_booking_policy" name="oksia_domestic_booking_policy" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['booking_policy']); ?>"><?php echo esc_textarea($oksia_policy_values['domestic_booking_policy']); ?></textarea>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_domestic_refund_policy">Refund</label>
                                    <textarea id="oksia_domestic_refund_policy" name="oksia_domestic_refund_policy" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['refund_policy']); ?>"><?php echo esc_textarea($oksia_policy_values['domestic_refund_policy']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="settings-field">
                            <h4 class="nested-column-title">International</h4>
                            <div class="nested-settings-grid">
                                <div class="settings-field">
                                    <label for="oksia_default_booking_policy">Booking</label>
                                    <textarea id="oksia_default_booking_policy" name="oksia_default_booking_policy" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['booking_policy']); ?>"><?php echo esc_textarea($oksia_policy_values['default_booking_policy']); ?></textarea>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_default_refund_policy">Refund</label>
                                    <textarea id="oksia_default_refund_policy" name="oksia_default_refund_policy" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['refund_policy']); ?>"><?php echo esc_textarea($oksia_policy_values['default_refund_policy']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANEL 8: CHILD & CANCELLATION -->
                <div class="tab-panel" id="panel-child">
                    <h3 class="section-title">Child & Cancellation Policies</h3>
                    <p class="section-note">Establish age bands and penalty rules for both operations.</p>
                    <div class="settings-grid">
                        <div class="settings-field">
                            <h4 class="nested-column-title">Domestic</h4>
                            <div class="nested-settings-grid">
                                <div class="settings-field">
                                    <label for="oksia_domestic_child_policy">Child Policy</label>
                                    <textarea id="oksia_domestic_child_policy" name="oksia_domestic_child_policy" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['child_policy']); ?>"><?php echo esc_textarea($oksia_policy_values['domestic_child_policy']); ?></textarea>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_domestic_cancellation_policy">Cancellation</label>
                                    <textarea id="oksia_domestic_cancellation_policy" name="oksia_domestic_cancellation_policy" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['cancellation_policy']); ?>"><?php echo esc_textarea($oksia_policy_values['domestic_cancellation_policy']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="settings-field">
                            <h4 class="nested-column-title">International</h4>
                            <div class="nested-settings-grid">
                                <div class="settings-field">
                                    <label for="oksia_default_child_policy">Child Policy</label>
                                    <textarea id="oksia_default_child_policy" name="oksia_default_child_policy" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['child_policy']); ?>"><?php echo esc_textarea($oksia_policy_values['default_child_policy']); ?></textarea>
                                </div>
                                <div class="settings-field">
                                    <label for="oksia_default_cancellation_policy">Cancellation</label>
                                    <textarea id="oksia_default_cancellation_policy" name="oksia_default_cancellation_policy" rows="5" placeholder="<?php echo esc_attr($oksia_policy_placeholders['cancellation_policy']); ?>"><?php echo esc_textarea($oksia_policy_values['default_cancellation_policy']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- PANEL 9: VISA & FOREX -->
            <div class="tab-panel" id="panel-visa">
                <h3 class="section-title">Visa Requirements & Forex Conversions</h3>
                <p class="section-note">Coming Soon...!!!</p>
            </div>

            <!-- PANEL 10: BILLING -->
            <div class="tab-panel" id="panel-billing">
                <h3 class="section-title">Billing</h3>
                <div class="billing-dashboard-row">
                    <?php if (empty($oksia_trial_info['is_expired'])) : ?>
                        <div class="billing-summary-card">
                            <p class="summary-label">Trial Ends on</p>
                            <p class="summary-value"><?php echo esc_html(!empty($oksia_trial_info['trial_expires_at']) ? mysql2date(get_option('date_format'), (string) $oksia_trial_info['trial_expires_at']) : __('Not set', 'oksia-smart-itinerary-agent')); ?></p>
                            <p class="summary-subnote"><?php echo esc_html($oksia_current_subscription['label'] ?? 'Economy'); ?> &middot; <?php echo esc_html($oksia_current_subscription['range'] ?? '1 user'); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="billing-fields-card">
                        <div class="form-inputs-row">
                            <div class="input-group">
                                <label for="oksia_gst_name">GST Name</label>
                                <input type="text" id="oksia_gst_name" value="<?php echo esc_attr($oksia_get_agency_detail('oksia_billing_company', 'oksia_billing_company')); ?>" placeholder="<?php esc_attr_e('Not set', 'oksia-smart-itinerary-agent'); ?>" readonly>
                            </div>
                            <div class="input-group">
                                <label for="oksia_gst_pan">GST / PAN Number</label>
                                <input type="text" id="oksia_gst_pan" value="<?php echo esc_attr($oksia_get_agency_detail('oksia_billing_gst', 'oksia_billing_gst')); ?>" placeholder="<?php esc_attr_e('Not set', 'oksia-smart-itinerary-agent'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-inputs-row">
                            <div class="input-group">
                                <label for="oksia_gst_email">GST Email Address</label>
                                <input type="email" id="oksia_gst_email" value="<?php echo esc_attr($oksia_get_agency_detail('oksia_billing_email', 'oksia_billing_email')); ?>" placeholder="<?php esc_attr_e('Not set', 'oksia-smart-itinerary-agent'); ?>" readonly>
                            </div>
                            <div class="input-group">
                                <label for="oksia_state">State</label>
                                <input type="text" id="oksia_state" value="<?php echo esc_attr($oksia_get_agency_detail('oksia_agency_state', 'oksia_agency_state')); ?>" placeholder="<?php esc_attr_e('Not set', 'oksia-smart-itinerary-agent'); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-grid" style="margin-top:24px;">
                    <div class="settings-field">
                        <h4 class="nested-column-title">Plan Comparison</h4>
                        <div class="billing-plan-grid">
                            <?php foreach ($oksia_subscription_cards as $plan_key => $plan_meta) : ?>
                                <?php
                                $plan_rank = (int) ($oksia_plan_order[$plan_key] ?? 0);
                                $current_plan_rank = (int) ($oksia_plan_order[$oksia_current_plan_key] ?? 0);
                                $plan_is_current = $plan_key === $oksia_current_plan_key;
                                $action_label = $plan_is_current ? __('Current Plan', 'oksia-smart-itinerary-agent') : ($plan_rank > $current_plan_rank ? __('Upgrade', 'oksia-smart-itinerary-agent') : __('Downgrade', 'oksia-smart-itinerary-agent'));
                                $action_class = $plan_is_current ? 'billing-plan-action--current' : ($plan_rank > $current_plan_rank ? 'billing-plan-action--upgrade' : 'billing-plan-action--downgrade');
                                ?>
                                <div class="billing-plan-card<?php echo $plan_is_current ? ' billing-plan-card--active' : ''; ?>">
                                    <div class="billing-plan-head">
                                        <h4><?php echo esc_html($plan_meta['label'] ?? ucfirst((string) $plan_key)); ?></h4>
                                    </div>
                                    <p class="billing-summary-note" style="margin-bottom:12px;"><?php echo esc_html($plan_meta['range'] ?? ''); ?></p>
                                    <ul class="billing-feature-list">
                                        <?php foreach ($oksia_plan_feature_labels as $feature_key => $feature_label) : ?>
                                            <?php $feature_enabled = !empty($oksia_plan_feature_matrix[$plan_key][$feature_key]); ?>
                                            <li class="<?php echo $feature_enabled ? '' : 'is-disabled'; ?>">
                                                <span class="billing-feature-icon <?php echo $feature_enabled ? 'billing-feature-icon--on' : 'billing-feature-icon--off'; ?>"><?php echo $feature_enabled ? '✓' : '×'; ?></span>
                                                <span><?php echo esc_html($feature_label); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="billing-card-list">
                                        <div class="billing-card-item">
                                            <label>Users</label>
                                            <span><?php echo esc_html((string) ($plan_meta['users'] ?? '')); ?></span>
                                        </div>
                                        <div class="billing-card-item">
                                            <label>Plan Code</label>
                                            <span><?php echo esc_html((string) ($plan_meta['code'] ?? '')); ?></span>
                                        </div>
                                    </div>
                                    <a href="#" class="billing-plan-action <?php echo esc_attr($action_class); ?>"><?php echo esc_html($action_label); ?></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            </div> <!-- End of .panels-container -->

            <div class="form-actions">
                <button type="submit" class="btn-submit">Save System Changes</button>
            </div>
            
        </form>
    </div> <!-- End of .premium-card-wrapper -->
</div> <!-- End of .workspace-container -->

</body>
</html>
