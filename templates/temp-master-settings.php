<?php
if (!defined('ABSPATH')) {
    exit;
}

$users = get_users(array(
    'orderby' => 'display_name',
    'order' => 'ASC',
    'fields' => array('ID', 'display_name', 'user_email'),
));

$allow_multi_assignments = count($users) > 1;

$get_value = static function($option_name, $default = '') {
    return get_option($option_name, $default);
};

?>
<style>
    :root {
        --bg: #f4f7fc;
        --card: #ffffff;
        --ink: #0f172a;
        --muted: #64748b;
        --border: #e2e8f0;
        --primary: #1d4ed8;
        --primary-dark: #111827;
        --radius: 16px;
    }
    body {
        margin: 0;
        background: var(--bg);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        -webkit-font-smoothing: antialiased;
    }
    .oksia-temp-shell {
        width: 100%;
        margin: 0;
        padding: 16px;
        box-sizing: border-box;
    }
    .oksia-temp-card {
        width: 100%;
        box-sizing: border-box;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: 0 4px 6px -1px rgba(0,0,0,.05);
        padding: 20px;
    }
    .oksia-temp-header {
        margin-bottom: 20px;
    }
    .oksia-temp-header h1 {
        margin: 0 0 6px;
        font-size: 28px;
        color: var(--ink);
    }
    .oksia-temp-header p {
        margin: 0;
        color: var(--muted);
    }
    .oksia-section {
        margin-top: 24px;
    }
    .oksia-section h2 {
        margin: 0 0 12px;
        font-size: 20px;
        color: var(--ink);
    }
    .oksia-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .oksia-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .oksia-field--span-3 {
        grid-column: span 3;
    }
    .oksia-field label {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
    }
    .oksia-field input[type="text"],
    .oksia-field input[type="email"],
    .oksia-field select,
    .oksia-field textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        box-sizing: border-box;
        background: #fff;
        color: var(--ink);
        font-size: 14px;
    }
    .oksia-field textarea {
        min-height: 110px;
        resize: vertical;
    }
    .oksia-help {
        font-size: 12px;
        color: var(--muted);
        margin: 0;
    }
    .oksia-user-select {
        min-height: 140px;
    }
    .oksia-actions {
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
    }
    .oksia-actions .button {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: #fff;
        border: 0;
        border-radius: 10px;
        padding: 14px 22px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .oksia-actions .button:hover {
        opacity: .95;
    }
    @media (max-width: 900px) {
        .oksia-grid {
            grid-template-columns: 1fr;
        }
        .oksia-field--span-3 {
            grid-column: span 1;
        }
        .oksia-temp-card {
            padding: 16px;
        }
    }
</style>

<div class="oksia-temp-shell">
    <div class="oksia-temp-card">
        <div class="oksia-temp-header">
            <h1><?php esc_html_e('Agency Master Settings', 'oksia-smart-itinerary-agent'); ?></h1>
            <p><?php esc_html_e('Old master settings restored on the temporary page. Use this page for the original agency-level configuration.', 'oksia-smart-itinerary-agent'); ?></p>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
            <?php wp_nonce_field('oksia_settings-options'); ?>
            <input type="hidden" name="option_page" value="oksia_settings">
            <input type="hidden" name="action" value="update">

            <div class="oksia-section">
                <h2><?php esc_html_e('Agency Details', 'oksia-smart-itinerary-agent'); ?></h2>
                <div class="oksia-grid">
                    <div class="oksia-field">
                        <label for="oksia_authorize_name"><?php esc_html_e('Authorize Name', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="text" id="oksia_authorize_name" name="oksia_authorize_name" value="<?php echo esc_attr($get_value('oksia_authorize_name', '')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_agency_phone"><?php esc_html_e('Contact', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="text" id="oksia_agency_phone" name="oksia_agency_phone" value="<?php echo esc_attr($get_value('oksia_agency_phone', '+91-8320-696-872')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_agency_email"><?php esc_html_e('Email', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="email" id="oksia_agency_email" name="oksia_agency_email" value="<?php echo esc_attr($get_value('oksia_agency_email', '')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_agency_name"><?php esc_html_e('Agency Name', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="text" id="oksia_agency_name" name="oksia_agency_name" value="<?php echo esc_attr($get_value('oksia_agency_name', 'OK')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_agency_code"><?php esc_html_e('Agency Code', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="text" id="oksia_agency_code" name="oksia_agency_code" value="<?php echo esc_attr($get_value('oksia_agency_code', 'ECOKSIA1')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_agency_website"><?php esc_html_e('Agency Website', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="text" id="oksia_agency_website" name="oksia_agency_website" value="<?php echo esc_attr($get_value('oksia_agency_website', '')); ?>">
                    </div>
                    <div class="oksia-field oksia-field--span-3">
                        <label for="oksia_company_address"><?php esc_html_e('Agency Address', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_company_address" name="oksia_company_address" rows="3"><?php echo esc_textarea($get_value('oksia_company_address', '')); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_agency_location"><?php esc_html_e('Location', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="text" id="oksia_agency_location" name="oksia_agency_location" value="<?php echo esc_attr($get_value('oksia_agency_location', 'Ahmedabad, GJ, India')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_agency_pincode"><?php esc_html_e('Pincode', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="text" id="oksia_agency_pincode" name="oksia_agency_pincode" value="<?php echo esc_attr($get_value('oksia_agency_pincode', '')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_iata_code"><?php esc_html_e('IATA/TIDS', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="text" id="oksia_iata_code" name="oksia_iata_code" value="<?php echo esc_attr($get_value('oksia_iata_code', '96169710')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_billing_gst"><?php esc_html_e('GST Number', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="text" id="oksia_billing_gst" name="oksia_billing_gst" value="<?php echo esc_attr($get_value('oksia_billing_gst', '24ATNPB9314Q1Z8')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_billing_company"><?php esc_html_e('GST Name', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="text" id="oksia_billing_company" name="oksia_billing_company" value="<?php echo esc_attr($get_value('oksia_billing_company', 'EKTA CORPORATION')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_billing_email"><?php esc_html_e('GST Email Address', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="email" id="oksia_billing_email" name="oksia_billing_email" value="<?php echo esc_attr($get_value('oksia_billing_email', '')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_main_agency_user_id"><?php esc_html_e('Main Admin', 'oksia-smart-itinerary-agent'); ?></label>
                        <select id="oksia_main_agency_user_id" name="oksia_main_agency_user_id" class="regular-text">
                            <option value="0"><?php esc_html_e('Use current admin fallback', 'oksia-smart-itinerary-agent'); ?></option>
                            <?php foreach ($users as $user) : ?>
                                <?php
                                $label = trim((string) $user->display_name);
                                if ('' !== trim((string) $user->user_email)) {
                                    $label .= ' (' . $user->user_email . ')';
                                }
                                ?>
                                <option value="<?php echo esc_attr((string) $user->ID); ?>" <?php selected(absint($get_value('oksia_main_agency_user_id', 0)), $user->ID); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_agency_manager_user_ids"><?php esc_html_e('Manager', 'oksia-smart-itinerary-agent'); ?></label>
                        <?php if ($allow_multi_assignments) : ?>
                            <select id="oksia_agency_manager_user_ids" name="oksia_agency_manager_user_ids[]" class="oksia-user-select" multiple size="6">
                                <?php $selected_ids = array_map('absint', (array) $get_value('oksia_agency_manager_user_ids', array())); ?>
                                <?php foreach ($users as $user) : ?>
                                    <?php
                                    $label = trim((string) $user->display_name);
                                    if ('' !== trim((string) $user->user_email)) {
                                        $label .= ' (' . $user->user_email . ')';
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr((string) $user->ID); ?>" <?php selected(in_array((int) $user->ID, $selected_ids, true), true); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else : ?>
                            <p class="oksia-help"><?php esc_html_e('Manager selection appears when more than one user exists.', 'oksia-smart-itinerary-agent'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_agency_staff_user_ids"><?php esc_html_e('Staff', 'oksia-smart-itinerary-agent'); ?></label>
                        <?php if ($allow_multi_assignments) : ?>
                            <select id="oksia_agency_staff_user_ids" name="oksia_agency_staff_user_ids[]" class="oksia-user-select" multiple size="6">
                                <?php $selected_ids = array_map('absint', (array) $get_value('oksia_agency_staff_user_ids', array())); ?>
                                <?php foreach ($users as $user) : ?>
                                    <?php
                                    $label = trim((string) $user->display_name);
                                    if ('' !== trim((string) $user->user_email)) {
                                        $label .= ' (' . $user->user_email . ')';
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr((string) $user->ID); ?>" <?php selected(in_array((int) $user->ID, $selected_ids, true), true); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else : ?>
                            <p class="oksia-help"><?php esc_html_e('Staff selection appears when more than one user exists.', 'oksia-smart-itinerary-agent'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_primary_color"><?php esc_html_e('Primary Color', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="color" id="oksia_primary_color" name="oksia_primary_color" value="<?php echo esc_attr($get_value('oksia_primary_color', '#000066')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_secondary_color"><?php esc_html_e('Secondary', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="color" id="oksia_secondary_color" name="oksia_secondary_color" value="<?php echo esc_attr($get_value('oksia_secondary_color', '#336699')); ?>">
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_accent_color"><?php esc_html_e('Accent', 'oksia-smart-itinerary-agent'); ?></label>
                        <input type="color" id="oksia_accent_color" name="oksia_accent_color" value="<?php echo esc_attr($get_value('oksia_accent_color', '#99FFFF')); ?>">
                    </div>
                    <div class="oksia-field oksia-field--span-3">
                        <label for="oksia_disclaimer_text"><?php esc_html_e('Disclaimer Text', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_disclaimer_text" name="oksia_disclaimer_text" rows="4"><?php echo esc_textarea($get_value('oksia_disclaimer_text', 'This is quotation only, no bookings are hold or confirmed. Prices are valid for 24hrs.')); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="oksia-section">
                <h2><?php esc_html_e('Master Dropdown List', 'oksia-smart-itinerary-agent'); ?></h2>
                <div class="oksia-grid">
                    <div class="oksia-field">
                        <label for="oksia_domestic_destinations"><?php esc_html_e('Domestic', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_domestic_destinations" name="oksia_domestic_destinations" rows="5"><?php echo esc_textarea($get_value('oksia_domestic_destinations', '')); ?></textarea>
                        <p class="oksia-help"><?php esc_html_e('Use one value per line.', 'oksia-smart-itinerary-agent'); ?></p>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_international_destinations"><?php esc_html_e('International', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_international_destinations" name="oksia_international_destinations" rows="5"><?php echo esc_textarea($get_value('oksia_international_destinations', '')); ?></textarea>
                        <p class="oksia-help"><?php esc_html_e('Use one value per line.', 'oksia-smart-itinerary-agent'); ?></p>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_hotel_categories"><?php esc_html_e('Hotel Categories', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_hotel_categories" name="oksia_hotel_categories" rows="5"><?php echo esc_textarea($get_value('oksia_hotel_categories', "3 Star\n4 Star\n5 Star\n3/4 Split\n3/5 Split\n4/5 Split")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_occupancies"><?php esc_html_e('Occupancies', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_occupancies" name="oksia_occupancies" rows="5"><?php echo esc_textarea($get_value('oksia_occupancies', "Single\nDouble\nTriple\nQuad")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_meal_plans"><?php esc_html_e('Meal Plans', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_meal_plans" name="oksia_meal_plans" rows="5"><?php echo esc_textarea($get_value('oksia_meal_plans', "No Meals\nBreakfast\nBreakfast & Dinner\nBreakfast/Lunch/Dinner\nBreakfast/Lunch/HiTea/Dinner")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_meal_transfer_types"><?php esc_html_e('Meal Transfer', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_meal_transfer_types" name="oksia_meal_transfer_types" rows="5"><?php echo esc_textarea($get_value('oksia_meal_transfer_types', "Included\nExcluded")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_pickup_points"><?php esc_html_e('Pickup Points', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_pickup_points" name="oksia_pickup_points" rows="5"><?php echo esc_textarea($get_value('oksia_pickup_points', '')); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_drop_points"><?php esc_html_e('Drop Points', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_drop_points" name="oksia_drop_points" rows="5"><?php echo esc_textarea($get_value('oksia_drop_points', '')); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_transfer_modes"><?php esc_html_e('Transfer Modes', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_transfer_modes" name="oksia_transfer_modes" rows="5"><?php echo esc_textarea($get_value('oksia_transfer_modes', "Private\nSIC - Sharing in Coach")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_sightseeing_vehicles"><?php esc_html_e('Sightseeing Vehicles', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_sightseeing_vehicles" name="oksia_sightseeing_vehicles" rows="5"><?php echo esc_textarea($get_value('oksia_sightseeing_vehicles', "Private\nSIC - Sharing in Coach")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_vehicle_types"><?php esc_html_e('Vehicle Types', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_vehicle_types" name="oksia_vehicle_types" rows="5"><?php echo esc_textarea($get_value('oksia_vehicle_types', "Tempo Traveller\nInnova\nSedan\nSUV\nCoach\nMinibus")); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="oksia-section">
                <h2><?php esc_html_e('Master International Policies', 'oksia-smart-itinerary-agent'); ?></h2>
                <div class="oksia-grid">
                    <div class="oksia-field">
                        <label for="oksia_default_inclusions"><?php esc_html_e('Inclusion', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_default_inclusions" name="oksia_default_inclusions" rows="5"><?php echo esc_textarea($get_value('oksia_default_inclusions', "Accommodation in selected hotels with base room category\nSelected meals as per itinerary\nSightseeing as specified in the itinerary")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_default_exclusions"><?php esc_html_e('Exlusions', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_default_exclusions" name="oksia_default_exclusions" rows="5"><?php echo esc_textarea($get_value('oksia_default_exclusions', "Sightseeing other than specified in the itinerary is chargeable\nPersonal expenses and extra meals are not included\nAny incidental expenses not specified are excluded")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_default_child_policy"><?php esc_html_e('Child Policy', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_default_child_policy" name="oksia_default_child_policy" rows="5"><?php echo esc_textarea($get_value('oksia_default_child_policy', "Below 5 years: Complimentary\nUp to 7 years: Chargeable without bed\nAbove 7 years: Chargeable with bed\nAbove 10 years: Extra adult charge")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_default_cancellation_policy"><?php esc_html_e('Cancellation', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_default_cancellation_policy" name="oksia_default_cancellation_policy" rows="5"><?php echo esc_textarea($get_value('oksia_default_cancellation_policy', "0 to 10 days before check-in: 100% charge\n11 to 20 days before check-in: 75% charge\n21 to 30 days before check-in: 35% charge")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_default_booking_policy"><?php esc_html_e('Booking', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_default_booking_policy" name="oksia_default_booking_policy" rows="5"><?php echo esc_textarea($get_value('oksia_default_booking_policy', "Booking confirmation after 50% advance payment\nVouchers will be issued once services are reconfirmed\n100% payment required before travel")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_default_refund_policy"><?php esc_html_e('Refund', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_default_refund_policy" name="oksia_default_refund_policy" rows="5"><?php echo esc_textarea($get_value('oksia_default_refund_policy', '')); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="oksia-section">
                <h2><?php esc_html_e('Master Domestic Policies', 'oksia-smart-itinerary-agent'); ?></h2>
                <div class="oksia-grid">
                    <div class="oksia-field">
                        <label for="oksia_domestic_inclusions"><?php esc_html_e('Inclusion', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_domestic_inclusions" name="oksia_domestic_inclusions" rows="5"><?php echo esc_textarea($get_value('oksia_domestic_inclusions', "Accommodation in selected hotels with base room category\nSelected meals as per itinerary\nSightseeing as specified in the itinerary")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_domestic_exclusions"><?php esc_html_e('Exlusions', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_domestic_exclusions" name="oksia_domestic_exclusions" rows="5"><?php echo esc_textarea($get_value('oksia_domestic_exclusions', "Sightseeing other than specified in the itinerary is chargeable\nPersonal expenses and extra meals are not included\nAny incidental expenses not specified are excluded")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_domestic_child_policy"><?php esc_html_e('Child Policy', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_domestic_child_policy" name="oksia_domestic_child_policy" rows="5"><?php echo esc_textarea($get_value('oksia_domestic_child_policy', "Below 5 years: Complimentary\nUp to 7 years: Chargeable without bed\nAbove 7 years: Chargeable with bed\nAbove 10 years: Extra adult charge")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_domestic_cancellation_policy"><?php esc_html_e('Cancellation', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_domestic_cancellation_policy" name="oksia_domestic_cancellation_policy" rows="5"><?php echo esc_textarea($get_value('oksia_domestic_cancellation_policy', "0 to 10 days before check-in: 100% charge\n11 to 20 days before check-in: 75% charge\n21 to 30 days before check-in: 35% charge")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_domestic_booking_policy"><?php esc_html_e('Booking', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_domestic_booking_policy" name="oksia_domestic_booking_policy" rows="5"><?php echo esc_textarea($get_value('oksia_domestic_booking_policy', "Booking confirmation after 50% advance payment\nVouchers will be issued once services are reconfirmed\n100% payment required before travel")); ?></textarea>
                    </div>
                    <div class="oksia-field">
                        <label for="oksia_domestic_refund_policy"><?php esc_html_e('Refund', 'oksia-smart-itinerary-agent'); ?></label>
                        <textarea id="oksia_domestic_refund_policy" name="oksia_domestic_refund_policy" rows="5"><?php echo esc_textarea($get_value('oksia_domestic_refund_policy', '')); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="oksia-actions">
                <button type="submit" class="button"><?php esc_html_e('Save Master Settings', 'oksia-smart-itinerary-agent'); ?></button>
            </div>
        </form>
    </div>
</div>
