(function ($) {
    function replaceIndex(templateHtml, index) {
        return templateHtml.replace(/__INDEX__/g, index);
    }

    function bindDocumentUploader(button) {
        button.on('click', function (event) {
            event.preventDefault();
            const card = $(this).closest('.oksia-document-card');
            const frame = wp.media({
                title: 'Select document',
                button: { text: 'Use this file' },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                card.find('.oksia-attachment-id').val(attachment.id || '');
                card.find('.oksia-document-url').val(attachment.url || '');
            });

            frame.open();
        });
    }

    function bindDayImageUploader(button) {
        button.on('click', function (event) {
            event.preventDefault();
            const card = $(this).closest('.oksia-day-card');
            const frame = wp.media({
                title: 'Select day image',
                button: { text: 'Use this image' },
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                card.find('.oksia-day-image-id').val(attachment.id || '');
                card.find('.oksia-day-image-url').val(attachment.url || '');
                card.find('.oksia-day-image-preview').text(attachment.filename || attachment.title || 'Image selected');
            });

            frame.open();
        });
    }

    function initSmartSuggestions() {
        const allowedLists = ['oksia-city-options', 'oksia-hotel-options', 'oksia-sightseeing-options'];
        let $menu = $('#oksia-smart-suggestions');

        if (!$menu.length) {
            $menu = $('<div id="oksia-smart-suggestions" class="oksia-smart-suggestions" aria-hidden="true"><ul class="oksia-smart-suggestions__list"></ul></div>');
            $('body').append($menu);
        }

        function hideMenu() {
            $menu.hide().attr('aria-hidden', 'true');
            $menu.find('.oksia-smart-suggestions__list').empty();
            $menu.removeData('target-input');
        }

        function getOptions(listId) {
            if (!listId || allowedLists.indexOf(listId) === -1) {
                return [];
            }

            const list = document.getElementById(listId);
            if (!list) {
                return [];
            }

            return Array.from(list.querySelectorAll('option'))
                .map(function (option) { return String(option.value || '').trim(); })
                .filter(Boolean);
        }

        function positionMenu($input) {
            const rect = $input[0].getBoundingClientRect();
            $menu.css({
                position: 'fixed',
                left: rect.left + 'px',
                top: (rect.bottom + 4) + 'px',
                width: rect.width + 'px'
            });
        }

        function showMatches($input) {
            const value = String($input.val() || '').trim();
            const listId = $input.attr('list') || '';
            const options = getOptions(listId);
            const $list = $menu.find('.oksia-smart-suggestions__list');
            const lowerValue = value.toLowerCase();

            if (!value || !options.length) {
                hideMenu();
                return;
            }

            const matches = options.filter(function (option) {
                return option.toLowerCase().indexOf(lowerValue) !== -1;
            }).slice(0, 8);

            if (!matches.length) {
                hideMenu();
                return;
            }

            $list.empty();
            matches.forEach(function (match) {
                const $item = $('<li type="button" class="oksia-smart-suggestions__item"></li>');
                $item.text(match).attr('data-value', match);
                $list.append($item);
            });

            positionMenu($input);
            $menu.data('target-input', $input);
            $menu.show().attr('aria-hidden', 'false');
        }

        $(document)
            .on('input focus', '.oksia-city-input, .oksia-hotel-input, input[list="oksia-sightseeing-options"]', function () {
                showMatches($(this));
            })
            .on('keydown', '.oksia-city-input, .oksia-hotel-input, input[list="oksia-sightseeing-options"]', function (event) {
                if ('Escape' === event.key) {
                    hideMenu();
                }
            });

        $(document).on('mousedown', '#oksia-smart-suggestions .oksia-smart-suggestions__item', function (event) {
            event.preventDefault();
            const $target = $menu.data('target-input');
            if (!$target || !$target.length) {
                hideMenu();
                return;
            }

            const value = String($(this).attr('data-value') || '').trim();
            if (value) {
                $target.val(value).trigger('input').trigger('change');
            }
            hideMenu();
        });

        $(window).on('scroll resize', function () {
            const $target = $menu.data('target-input');
            if (!$target || !$target.length || !$menu.is(':visible')) {
                return;
            }
            positionMenu($target);
        });

        $(document).on('click', function (event) {
            if ($(event.target).closest('#oksia-smart-suggestions, .oksia-city-input, .oksia-hotel-input, input[list="oksia-sightseeing-options"]').length) {
                return;
            }
            hideMenu();
        });

        hideMenu();
    }

    function recalculateNights() {
        const start = $('#oksia_start_date').val();
        const end = $('#oksia_end_date').val();
        const today = new Date().toISOString().split('T')[0];
        $('#oksia_start_date').attr('min', today);

        if (start) {
            $('#oksia_end_date').attr('min', start);
            if (end && end < start) {
                $('#oksia_end_date').val(start);
            }
        } else {
            $('#oksia_end_date').attr('min', today);
        }

        if (!start || !end) {
            $('#oksia_total_nights').val('');
            updateHotelNightsStatus();
            return;
        }

        const startDate = new Date(start + 'T00:00:00');
        const endDate = new Date(end + 'T00:00:00');
        const diffMs = endDate - startDate;
        const nights = diffMs > 0 ? Math.round(diffMs / 86400000) : 0;
        $('#oksia_total_nights').val(nights);

        if (typeof syncAgentBriefRows === 'function') {
            syncAgentBriefRows();
        }
        updateHotelNightsStatus();
    }

    function updateHotelNightsStatus() {
        const $status = $('#oksia-hotel-night-check');
        if (!$status.length) {
            return true;
        }
        const startDateValue = String($('#oksia_start_date').val() || '').trim();
        const endDateValue = String($('#oksia_end_date').val() || '').trim();

        const expectedNights = Math.max(0, Number($('#oksia_total_nights').val() || 0));
        let plannedNights = 0;
        let hasRelevantValues = false;

        $('#oksia-hotel-plan .oksia-hotel-plan-row').each(function () {
            const $row = $(this);
            const city = String($row.find('input[name*="[city]"]').val() || '').trim();
            const hotel = String($row.find('input[name*="[hotel]"]').val() || '').trim();
            const nights = Math.max(0, Number($row.find('input[name*="[nights]"]').val() || 0));
            if (city || hotel || nights > 0) {
                hasRelevantValues = true;
            }
            plannedNights += nights;
        });

        const $plan = $('#oksia-hotel-plan');
        $status.removeClass('oksia-hotel-night-check--ok oksia-hotel-night-check--warn oksia-hotel-night-check--error');
        $plan.removeClass('oksia-hotel-plan--valid oksia-hotel-plan--invalid');

        if (!startDateValue || !endDateValue) {
            $status.text('');
            return true;
        }

        if (expectedNights < 1) {
            $status.text('');
            return true;
        }

        if (!hasRelevantValues) {
            $status.text('');
            return true;
        }

        if (plannedNights === expectedNights) {
            $status.addClass('oksia-hotel-night-check--ok').text('Stay nights matched: ' + plannedNights + ' / ' + expectedNights + '.');
            $plan.addClass('oksia-hotel-plan--valid');
            return true;
        }

        $status.addClass('oksia-hotel-night-check--error').text('Stay nights matched: ' + plannedNights + ' / ' + expectedNights + '.');
        $plan.addClass('oksia-hotel-plan--invalid');
        return false;
    }

    function placeHotelNightsStatusUnderCity() {
        return;
    }

    function escapeAttr(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function getAgentBriefDayCount() {
        const nights = Math.max(0, Number($('#oksia_total_nights').val() || 0));
        return Math.max(1, nights + 1);
    }

    function stripDayPrefix(value) {
        return String(value || '').replace(/^(?:\s*Day\s*\d+\s*:\s*)+/i, '').trim();
    }

    function buildAgentBriefRow(dayNumber, value) {
        const trimmed = stripDayPrefix(value);
        const leisureClass = trimmed ? '' : ' oksia-agent-brief-day-row--leisure';
        const stateLabel = trimmed ? 'Planned day' : 'Leisure Day';

        return (
            '<div class="oksia-agent-brief-day-row' + leisureClass + '" data-day-number="' + dayNumber + '">' +
                '<div class="oksia-agent-brief-day-row__top">' +
                    '<span class="oksia-agent-brief-day-badge">Day ' + dayNumber + '</span>' +
                    '<span class="oksia-agent-brief-day-state">' + stateLabel + '</span>' +
                '</div>' +
                '<input type="text" class="widefat oksia-agent-brief-day-input" value="' + escapeAttr(trimmed) + '" placeholder="Arrival, sightseeing, dinner, etc." />' +
            '</div>'
        );
    }

    function syncAgentBriefRows() {
        const $container = $('#oksia-agent-brief-days');
        const $hidden = $('#oksia_source_brief');
        if (!$container.length || !$hidden.length) {
            return;
        }

        const desiredCount = getAgentBriefDayCount();
        const currentValues = [];
        $container.find('.oksia-agent-brief-day-input').each(function () {
            currentValues.push(stripDayPrefix($(this).val() || ''));
        });

        if ($container.children('.oksia-agent-brief-day-row').length !== desiredCount) {
            let html = '';
            for (let i = 0; i < desiredCount; i += 1) {
                html += buildAgentBriefRow(i + 1, currentValues[i] || '');
            }
            $container.html(html);
        }

        const lines = [];
        $container.find('.oksia-agent-brief-day-row').each(function (index) {
            const $row = $(this);
            const $input = $row.find('.oksia-agent-brief-day-input');
            const normalized = stripDayPrefix($input.val() || '');
            const isLeisure = !normalized;
            const label = normalized || 'Leisure Day';

            if (($input.val() || '').trim() !== normalized) {
                $input.val(normalized);
            }

            $row.toggleClass('oksia-agent-brief-day-row--leisure', isLeisure);
            $row.find('.oksia-agent-brief-day-state').text(isLeisure ? 'Leisure Day' : 'Planned day');
            lines.push('Day ' + (index + 1) + ': ' + label);
        });

        $hidden.val(lines.join('\n'));
    }

    function recalculateTravelers() {
        const adults = Number($('#oksia_adults').val() || 0);
        const withBed = Number($('#oksia_adult_with_bed').val() || 0);
        const childWithoutBed = Number($('#oksia_child_without_bed').val() || 0);
        $('#oksia_total_travelers').val(adults + withBed + childWithoutBed);
    }

    function updateDestinationOptions() {
        const tripType = $('#oksia_trip_type').val() || 'Domestic';
        const destination = $('#oksia_destination_field');
        const currentValue = destination.val();
        const options = (window.okAdminData && window.okAdminData.destinations && window.okAdminData.destinations[tripType]) || [];
        const emptyLabel = options.length ? 'Select Destination' : 'Add destinations in Settings';

        destination.empty().append($('<option>', { value: '', text: emptyLabel }));
        options.forEach(function (option) {
            destination.append($('<option>', { value: option, text: option }));
        });

        if (options.includes(currentValue)) {
            destination.val(currentValue);
        } else {
            destination.val('');
        }
    }

    function getTripTypeOptionLists() {
        return (window.okAdminData && window.okAdminData.tripOptions) || {};
    }

    function getTripTypeOptions(listKey, tripType, fallback) {
        const lists = getTripTypeOptionLists();
        const perTrip = lists[listKey] || {};
        const options = perTrip[tripType] || fallback || [];
        return Array.isArray(options) ? options.slice() : [];
    }

    function rebuildSelectOptions($select, options, placeholder) {
        if (!$select.length) {
            return;
        }

        const currentValue = $select.val();
        const normalizedOptions = Array.isArray(options) ? options.filter(function (option) {
            return '' !== String(option || '').trim();
        }) : [];

        $select.empty().append($('<option>', { value: '', text: placeholder }));
        normalizedOptions.forEach(function (option) {
            $select.append($('<option>', { value: option, text: option }));
        });

        if (currentValue) {
            if (normalizedOptions.includes(currentValue)) {
                $select.val(currentValue);
            } else {
                $select.val('');
            }
        }
    }

    function syncTripTypeSelectOptions() {
        const tripType = $('#oksia_trip_type').val() || 'Domestic';
        rebuildSelectOptions(
            $('#oksia_hotel_category'),
            getTripTypeOptions('hotel_categories', tripType, ['3 Star', '4 Star', '5 Star', '3/4 Split', '3/5 Split', '4/5 Split']),
            'Select Hotel Category'
        );
        rebuildSelectOptions(
            $('#oksia_occupancy'),
            getTripTypeOptions('occupancies', tripType, ['Single', 'Double', 'Triple', 'Quad']),
            'Select Occupancy'
        );
        rebuildSelectOptions(
            $('#oksia_meal_plan'),
            getTripTypeOptions('meal_plans', tripType, ['No Meals', 'Breakfast', 'Breakfast & Dinner', 'Breakfast/Lunch/Dinner', 'Breakfast/Lunch/HiTea/Dinner']),
            'Select Meal Plan'
        );
        rebuildSelectOptions(
            $('#oksia_pickup_from'),
            getTripTypeOptions('pickup_points', tripType, []),
            'Select Pickup Point'
        );
        rebuildSelectOptions(
            $('#oksia_drop_to'),
            getTripTypeOptions('drop_points', tripType, []),
            'Select Drop Point'
        );
        rebuildSelectOptions(
            $('#oksia_first_transfer'),
            getTripTypeOptions('transfer_modes', tripType, ['Private', 'SIC - Sharing in Coach']),
            'Select Transfer'
        );
        rebuildSelectOptions(
            $('#oksia_last_transfer'),
            getTripTypeOptions('transfer_modes', tripType, ['Private', 'SIC - Sharing in Coach']),
            'Select Transfer'
        );
        rebuildSelectOptions(
            $('#oksia_sightseeing_vehicle'),
            getTripTypeOptions('sightseeing_vehicles', tripType, ['Private', 'SIC - Sharing in Coach']),
            'Select Vehicle'
        );
        rebuildSelectOptions(
            $('#oksia_vehicle_type'),
            getTripTypeOptions('vehicle_types', tripType, ['Tempo Traveller', 'Innova', 'Sedan', 'SUV', 'Coach', 'Minibus']),
            'Select Vehicle Type'
        );
    }

    function toggleConditionalFields() {
        const tripType = $('#oksia_trip_type').val() || 'Domestic';
        $('[data-show-trip-type]').each(function () {
            const field = $(this);
            field.toggle(field.data('show-trip-type') === tripType);
        });
    }

    function syncTripTypeCurrencyLock() {
        const tripType = $('#oksia_trip_type').val() || 'Domestic';
        const isDomestic = tripType === 'Domestic';
        const $currency = $('#oksia_currency');
        const $multiCurrency = $('#oksia_multi_currency');

        if ($currency.length) {
            if (isDomestic) {
                const currentCurrency = ($currency.val() || 'INR').trim();
                if (currentCurrency && 'INR' !== currentCurrency) {
                    $currency.data('oksiaPreviousCurrency', currentCurrency);
                }
                $currency.val('INR').prop('disabled', true);
            } else {
                const previousCurrency = $currency.data('oksiaPreviousCurrency');
                $currency.prop('disabled', false);
                if (previousCurrency) {
                    $currency.val(previousCurrency);
                }
            }
        }

        if ($multiCurrency.length) {
            if (isDomestic) {
                const currentMultiCurrency = ($multiCurrency.val() || $currency.val() || 'INR').trim();
                if (currentMultiCurrency && 'INR' !== currentMultiCurrency) {
                    $multiCurrency.data('oksiaPreviousCurrency', currentMultiCurrency);
                }
                $multiCurrency.val('INR').prop('disabled', true);
            } else {
                const previousMultiCurrency = $multiCurrency.data('oksiaPreviousCurrency');
                $multiCurrency.prop('disabled', false);
                if (previousMultiCurrency) {
                    $multiCurrency.val(previousMultiCurrency);
                }
            }
        }

        if (isDomestic) {
            $('#oksia_exchange_rate').val('1');
        }
    }

    function syncTripTypeMultiVendorRows() {
        const tripType = $('#oksia_trip_type').val() || 'Domestic';
        const isDomestic = tripType === 'Domestic';
        const hiddenComponents = ['visa', 'tourism_tax', 'tip'];

        hiddenComponents.forEach(function (componentKey) {
            const $row = $('.oksia-multi-rate-table__row[data-component="' + componentKey + '"]');
            if (!$row.length) {
                return;
            }

            $row.toggle(!isDomestic);
            $row.find('input').prop('disabled', isDomestic);

            if (isDomestic) {
                $row.find('input').val('');
            }
        });
    }

    function syncTripTypeState() {
        updateDestinationOptions();
        syncTripTypeSelectOptions();
        toggleConditionalFields();
        syncMealTransfersField();
        syncTripTypeCurrencyLock();
        syncTripTypeMultiVendorRows();
        if (typeof syncAgentRateWorkspaceState === 'function') {
            syncAgentRateWorkspaceState();
        }
    }

    function syncMealTransfersField() {
        const tripType = $('#oksia_trip_type').val() || 'Domestic';
        const mealPlan = $('#oksia_meal_plan').val() || '';
        const field = $('#oksia_meal_transfers').closest('.oksia-conditional-field');
        const select = $('#oksia_meal_transfers');
        const allowedMealPlans = [
            'Breakfast & Dinner',
            'Breakfast/Lunch/Dinner',
            'Breakfast/Lunch/HiTea/Dinner'
        ];
        const shouldShow = tripType === 'International' && allowedMealPlans.includes(mealPlan);

        if (!field.length || !select.length) {
            return;
        }

        field.toggle(shouldShow);

        if (!shouldShow) {
            select.val('');
            return;
        }

        const currentValue = select.val();
        if (!currentValue || !['Included', 'Excluded'].includes(currentValue)) {
            select.val('Excluded');
        }
    }

    function recalculateQuotedRates() {
        if (isAgentRateWorkspace()) {
            ensureAgentRatePanels();
            syncAgentRateWorkspaceState();
            if ('multi' === getAgentVendorMode()) {
                recalculateAgentMultiRates();
                return;
            }
        }

        const currency = $('#oksia_currency').val() || 'INR';
        const exchangeRateInput = Number($('#oksia_exchange_rate').val() || 0);
        const exchangeRate = normalizeExchangeRateForCurrency(currency, exchangeRateInput);
        const adultMarkup = Number($('#oksia_adult_markup').val() || 0);
        const withBedMarkup = Number($('#oksia_with_bed_markup').val() || 0);
        const childMarkup = Number($('#oksia_child_markup').val() || 0);
        const transactionCost = Number($('#oksia_transaction_cost').val() || 0);
        const effectiveRate = currency === 'INR' ? 1 : (exchangeRate > 0 ? (exchangeRate + transactionCost) : 0);
        const baseRates = [
            { source: '#oksia_adult_rate', output: '#oksia_adult_rate_quote', final: '#oksia_single_adult_final', markup: adultMarkup },
            { source: '#oksia_with_bed_rate', output: '#oksia_with_bed_rate_quote', final: '#oksia_single_with_bed_final', markup: withBedMarkup },
            { source: '#oksia_child_rate', output: '#oksia_child_rate_quote', final: '#oksia_single_child_final', markup: childMarkup }
        ];
        const shouldShowReference = shouldShowAgentInrReference();

        $('#oksia_effective_rate').val(currency === 'INR' ? '1' : (effectiveRate > 0 ? effectiveRate.toFixed(4) : ''));
        $('.oksia-selected-currency-label').text(currency);

        baseRates.forEach(function (pair) {
            const raw = Number($(pair.source).val() || 0);
            const totalSelectedCurrency = raw + pair.markup;

            if (!raw && !pair.markup) {
                $(pair.output).val('');
                setAgentRateValue(pair.final, '');
                return;
            }

            const quoted = currency === 'INR'
                ? totalSelectedCurrency
                : totalSelectedCurrency * effectiveRate;

            setAgentRateValue(pair.final, formatAgentRateNumber(totalSelectedCurrency, 2));
            $(pair.output).val(shouldShowReference && (currency === 'INR' || effectiveRate > 0) ? quoted.toFixed(2) : '');
        });

        if (!shouldShowReference) {
            setAgentRateValue('#oksia_adult_rate_quote', '');
            setAgentRateValue('#oksia_with_bed_rate_quote', '');
            setAgentRateValue('#oksia_child_rate_quote', '');
        }

        recalculatePackageTotals();
    }

    function recalculatePackageTotals() {
        if (isAgentRateWorkspace() && 'multi' === getAgentVendorMode()) {
            recalculateAgentMultiRates();
            return;
        }

        const adults = Number($('#oksia_adults').val() || 0);
        const withBed = Number($('#oksia_adult_with_bed').val() || 0);
        const childWithoutBed = Number($('#oksia_child_without_bed').val() || 0);
        const adultRate = Number($('#oksia_adult_rate').val() || 0);
        const withBedRate = Number($('#oksia_with_bed_rate').val() || 0);
        const childRate = Number($('#oksia_child_rate').val() || 0);
        const adultMarkup = Number($('#oksia_adult_markup').val() || 0);
        const withBedMarkup = Number($('#oksia_with_bed_markup').val() || 0);
        const childMarkup = Number($('#oksia_child_markup').val() || 0);

        const baseTotal =
            (adults * adultRate) +
            (withBed * withBedRate) +
            (childWithoutBed * childRate);
        const customerTotal =
            (adults * (adultRate + adultMarkup)) +
            (withBed * (withBedRate + withBedMarkup)) +
            (childWithoutBed * (childRate + childMarkup));

        if (!baseTotal && !customerTotal) {
            $('#oksia_package_base_total').val('');
            $('#oksia_package_customer_total').val('');
            return;
        }

        $('#oksia_package_base_total').val(baseTotal.toFixed(2));
        $('#oksia_package_customer_total').val(customerTotal.toFixed(2));
    }

    function getCurrencyFallbackRate(currencyCode) {
        const normalized = String(currencyCode || 'INR').toUpperCase();
        const rateMap = (window.okAdminData && window.okAdminData.currencyRatesInr) ? window.okAdminData.currencyRatesInr : {};
        const fallbackRate = Number(rateMap[normalized] || 0);
        return Number.isFinite(fallbackRate) && fallbackRate > 0 ? fallbackRate : 0;
    }

    function normalizeExchangeRateForCurrency(currencyCode, rawRate) {
        const normalized = String(currencyCode || 'INR').toUpperCase();
        if ('INR' === normalized) {
            return 1;
        }

        let rate = Number(rawRate || 0);
        if (!Number.isFinite(rate) || rate <= 0) {
            rate = getCurrencyFallbackRate(normalized);
        }

        // Guard against stale INR default values (1) for foreign currencies.
        if (rate > 0 && rate <= 1) {
            const fallbackRate = getCurrencyFallbackRate(normalized);
            if (fallbackRate > 1) {
                rate = fallbackRate;
            }
        }

        return Number.isFinite(rate) && rate > 0 ? rate : 0;
    }

    function fetchExchangeRate() {
        const currency = $('#oksia_currency').val() || 'INR';
        if (currency === 'INR') {
            $('#oksia_exchange_rate').val('1');
            recalculateQuotedRates();
            return;
        }

        const fallbackRate = getCurrencyFallbackRate(currency);
        const hasFallbackRate = fallbackRate > 0;

        const endpoint = (window.okAdminData && window.okAdminData.exchangeApiBase) || 'https://convertz.app/api/currency';
        fetch(endpoint)
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                let rate = 0;
                const rates = payload && payload.rates ? payload.rates : {};
                const base = String((payload && (payload.base || payload.base_code)) || '').toUpperCase();
                const normalizedCurrency = String(currency || 'INR').toUpperCase();
                const normalizedRates = {};

                Object.keys(rates).forEach(function (key) {
                    const normalizedKey = String(key || '').toUpperCase();
                    const numericValue = Number(rates[key]);
                    if (!normalizedKey || !Number.isFinite(numericValue) || numericValue <= 0) {
                        return;
                    }
                    normalizedRates[normalizedKey] = numericValue;
                });

                const inrRate = Number(normalizedRates.INR || 0);
                const selectedRate = Number(normalizedRates[normalizedCurrency] || 0);

                if ('INR' === normalizedCurrency) {
                    rate = 1;
                } else if (inrRate > 0 && selectedRate > 0) {
                    rate = inrRate / selectedRate;
                } else if (base === normalizedCurrency && inrRate > 0) {
                    rate = inrRate;
                } else if ('INR' === base && selectedRate > 0) {
                    rate = 1 / selectedRate;
                }

                rate = normalizeExchangeRateForCurrency(normalizedCurrency, rate);

                if (rate) {
                    $('#oksia_exchange_rate').val(rate.toFixed(4));
                    recalculateQuotedRates();
                    return;
                }

                if (hasFallbackRate) {
                    $('#oksia_exchange_rate').val(fallbackRate.toFixed(4));
                    recalculateQuotedRates();
                } else {
                    $('#oksia_exchange_rate').val('');
                    recalculateQuotedRates();
                }
            })
            .catch(function () {
                if (hasFallbackRate) {
                    $('#oksia_exchange_rate').val(fallbackRate.toFixed(4));
                } else {
                    $('#oksia_exchange_rate').val('');
                }
                recalculateQuotedRates();
            });
    }

    $(function () {
        $('#oksia-add-document').on('click', function () {
            const container = $('#oksia-documents');
            const index = container.children('.oksia-document-card').length;
            const template = $('#tmpl-oksia-document-row').html();
            container.append(replaceIndex(template, index));
            bindDocumentUploader(container.children('.oksia-document-card').last().find('.oksia-upload-document'));
        });

        $('#oksia-add-day').on('click', function () {
            const container = $('#oksia-days');
            const index = container.children('.oksia-day-card').length;
            const template = $('#tmpl-oksia-day-row').html();
            container.append(replaceIndex(template, index));
            bindDayImageUploader(container.children('.oksia-day-card').last().find('.oksia-upload-day-image'));
        });

        $('#oksia-add-hotel-plan').on('click', function () {
            const container = $('#oksia-hotel-plan');
            const index = container.children('.oksia-hotel-plan-row').length;
            const template = $('#tmpl-oksia-hotel-plan-row').html();
            container.append(replaceIndex(template, index));
            updateHotelNightsStatus();
        });

        $(document).on('click', '.oksia-remove-row', function () {
            $(this).closest('.oksia-document-card, .oksia-day-card, .oksia-hotel-plan-row').remove();
            recalculateTravelers();
            updateHotelNightsStatus();
        });

        $('#oksia_trip_type').on('change', function () {
            syncTripTypeState();
            if (typeof fetchExchangeRate === 'function') {
                fetchExchangeRate();
            }
        });

        $('#oksia_meal_plan').on('change', syncMealTransfersField);

        $('#oksia_start_date, #oksia_end_date').on('change input', recalculateNights);
        $(document).on('input change', '#oksia-hotel-plan input[name*="[city]"], #oksia-hotel-plan input[name*="[hotel]"], #oksia-hotel-plan input[name*="[nights]"]', updateHotelNightsStatus);
        $('#oksia_adults, #oksia_adult_with_bed, #oksia_child_without_bed').on('input', function () {
            recalculateTravelers();
            recalculatePackageTotals();
        });
        $(document).on('input change', '.oksia-agent-brief-day-input', syncAgentBriefRows);
        ensureAgentRatePanels();
        syncAgentRateWorkspaceState();

        $('#oksia_currency').on('change', function () {
            const currency = ($('#oksia_currency').val() || 'INR').trim();
            if ('multi' === getAgentVendorMode() && $('#oksia_multi_currency').length) {
                $('#oksia_multi_currency').val(currency);
            }
            syncAgentRateWorkspaceState();
            fetchExchangeRate();
        });
        $(document).on('change', '#oksia_multi_currency', function () {
            if ('multi' !== getAgentVendorMode()) {
                return;
            }
            const currency = ($(this).val() || 'INR').trim();
            $('#oksia_currency').val(currency);
            syncAgentRateWorkspaceState();
            fetchExchangeRate();
        });
        $(document).on('change', '#oksia_vendor_mode', function () {
            if ('multi' === getAgentVendorMode() && $('#oksia_multi_currency').length) {
                const currency = ($('#oksia_multi_currency').val() || $('#oksia_currency').val() || 'INR').trim();
                $('#oksia_currency').val(currency);
            }
            syncAgentRateWorkspaceState();
            recalculateQuotedRates();
        });
        $(document).on('input change', '.oksia-rate-panel--multi input', recalculateQuotedRates);
        $('#oksia_transaction_cost, #oksia_adult_rate, #oksia_with_bed_rate, #oksia_child_rate, #oksia_adult_markup, #oksia_with_bed_markup, #oksia_child_markup').on('input', recalculateQuotedRates);

        $('.oksia-upload-document').each(function () {
            bindDocumentUploader($(this));
        });
        $('.oksia-upload-day-image').each(function () {
            bindDayImageUploader($(this));
        });
        initSmartSuggestions();

        syncTripTypeState();
        recalculateNights();
        recalculateTravelers();
        recalculatePackageTotals();
        fetchExchangeRate();
        syncAgentBriefRows();
        updateHotelNightsStatus();

        $('.oksia-agent-workspace form.oksia-intake-card').on('submit', function (event) {
            if (updateHotelNightsStatus()) {
                return;
            }

            event.preventDefault();
            const statusNode = document.getElementById('oksia-hotel-night-check');
            if (statusNode && typeof statusNode.scrollIntoView === 'function') {
                statusNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    });


    const AGENT_RATE_MULTI_COMPONENTS = [
        { key: 'flight', label: 'Flight/Train/Bus' },
        { key: 'hotel', label: 'Hotels' },
        { key: 'transportation', label: 'Transportation' },
        { key: 'visa', label: 'Visa' },
        { key: 'tourism_tax', label: 'Tourism Tax' },
        { key: 'tip', label: 'Tip' }
    ];

    const AGENT_RATE_MULTI_PASSENGERS = [
        { key: 'adult', label: 'Adult' },
        { key: 'with_bed', label: 'Adult/Child w/ Bed' },
        { key: 'child', label: 'Child No Bed' }
    ];

    function isAgentRateWorkspace() {
        return $('.oksia-agent-workspace').length > 0 && $('#oksia-agent-rate-data').length > 0;
    }

    function getAgentRateSeed() {
        if (!isAgentRateWorkspace()) {
            return {};
        }
        if (window.okAgentRateSeed) {
            return window.okAgentRateSeed;
        }
        const $seed = $('#oksia-agent-rate-data');
        try {
            window.okAgentRateSeed = $seed.length ? (JSON.parse($seed.text() || '{}') || {}) : {};
        } catch (error) {
            window.okAgentRateSeed = {};
        }
        return window.okAgentRateSeed;
    }

    function getAgentVendorMode() {
        const seed = getAgentRateSeed();
        const value = ($('#oksia_vendor_mode').val() || seed.vendor_mode || 'multi');
        return String(value).toLowerCase() === 'multi' ? 'multi' : 'single';
    }

    function getAgentCurrencyMultiplier() {
        const currency = $('#oksia_currency').val() || 'INR';
        const exchangeRateInput = Number($('#oksia_exchange_rate').val() || 0);
        const exchangeRate = normalizeExchangeRateForCurrency(currency, exchangeRateInput);
        const transactionCost = Number($('#oksia_transaction_cost').val() || 0);
        return currency === 'INR' ? 1 : (exchangeRate > 0 ? (exchangeRate + transactionCost) : 0);
    }

    function shouldShowAgentInrReference() {
        const tripType = $('#oksia_trip_type').val() || 'Domestic';
        const currency = $('#oksia_currency').val() || 'INR';
        return tripType === 'International' && currency !== 'INR';
    }

    function setAgentRateValue(selector, value) {
        const $field = $(selector);
        if ($field.length) {
            $field.val(value);
        }
    }

    function formatAgentRateNumber(value, precision) {
        const number = Number(value || 0);
        if (!Number.isFinite(number)) {
            return '';
        }
        if (0 === number && '' === String(value || '').trim()) {
            return '';
        }
        return number.toFixed(precision || 2);
    }

    function buildAgentRateModeToolbar() {
        return (
            '<div class="oksia-rate-mode-toolbar">' +
                '<p class="oksia-rate-mode-field">' +
                    '<label for="oksia_vendor_mode">Vendor Mode</label>' +
                    '<select id="oksia_vendor_mode" name="oksia_quote[vendor_mode]" class="widefat">' +
                        '<option value="multi">Multi Vendor</option>' +
                        '<option value="single">Single Vendor</option>' +
                    '</select>' +
                '</p>' +
                '<div class="oksia-rate-mode-field oksia-rate-mode-field--single-currency"><div id="oksia-toolbar-currency-slot"></div></div>' +
                '<div class="oksia-rate-mode-field oksia-rate-mode-field--single-exchange"><div id="oksia-toolbar-exchange-slot"></div></div>' +
                '<div class="oksia-rate-mode-field oksia-rate-mode-field--single-transaction"><div id="oksia-toolbar-transaction-slot"></div></div>' +
                '<div class="oksia-rate-mode-field oksia-rate-mode-field--single-effective"><div id="oksia-toolbar-effective-slot"></div></div>' +
                '<p class="oksia-rate-mode-field oksia-rate-mode-field--multi-currency">' +
                    '<label for="oksia_multi_currency">Quote Currency</label>' +
                    '<select id="oksia_multi_currency" name="oksia_quote[multi_currency]" class="widefat"></select>' +
                '</p>' +
            '</div>'
        );
    }

    function buildAgentSingleRatePanelHead() {
        return (
            '<div class="oksia-rate-panel__head oksia-rate-panel__head--single">' +
                '<div>' +
                    '<div class="oksia-rate-panel__title">Single Vendor Rates</div>' +
                '</div>' +
            '</div>'
        );
    }

    function buildAgentSingleRateTable() {
        return (
            '<div class="oksia-single-rate-table">' +
                '<div class="oksia-single-rate-table__head">' +
                    '<div>Rates</div>' +
                    '<div>Adult</div>' +
                    '<div>Adult/Child w/ Bed</div>' +
                    '<div>Child No Bed</div>' +
                '</div>' +
                '<div class="oksia-single-rate-table__row" data-row="rate">' +
                    '<div class="oksia-single-rate-table__label"><span class="oksia-single-rate-table__label-text">Rate</span></div>' +
                    '<div class="oksia-single-rate-table__cell" id="oksia-single-rate-adult"></div>' +
                    '<div class="oksia-single-rate-table__cell" id="oksia-single-rate-with-bed"></div>' +
                    '<div class="oksia-single-rate-table__cell" id="oksia-single-rate-child"></div>' +
                '</div>' +
                '<div class="oksia-single-rate-table__row" data-row="markup">' +
                    '<div class="oksia-single-rate-table__label"><span class="oksia-single-rate-table__label-text">Mark up</span></div>' +
                    '<div class="oksia-single-rate-table__cell" id="oksia-single-markup-adult"></div>' +
                    '<div class="oksia-single-rate-table__cell" id="oksia-single-markup-with-bed"></div>' +
                    '<div class="oksia-single-rate-table__cell" id="oksia-single-markup-child"></div>' +
                '</div>' +
                '<div class="oksia-single-rate-table__row oksia-single-rate-table__row--final" data-row="final">' +
                    '<div class="oksia-single-rate-table__label"><span class="oksia-single-rate-table__label-text">Final</span></div>' +
                    '<div class="oksia-single-rate-table__cell"><input type="text" id="oksia_single_adult_final" class="widefat" readonly /></div>' +
                    '<div class="oksia-single-rate-table__cell"><input type="text" id="oksia_single_with_bed_final" class="widefat" readonly /></div>' +
                    '<div class="oksia-single-rate-table__cell"><input type="text" id="oksia_single_child_final" class="widefat" readonly /></div>' +
                '</div>' +
                '<div class="oksia-single-rate-table__row oksia-single-rate-table__row--reference oksia-agent-inr-reference-row" data-row="reference">' +
                    '<div class="oksia-single-rate-table__label"><span class="oksia-single-rate-table__label-text">INR Reference only</span></div>' +
                    '<div class="oksia-single-rate-table__cell" id="oksia-single-reference-adult"></div>' +
                    '<div class="oksia-single-rate-table__cell" id="oksia-single-reference-with-bed"></div>' +
                    '<div class="oksia-single-rate-table__cell" id="oksia-single-reference-child"></div>' +
                '</div>' +
            '</div>'
        );
    }

    function moveAgentRateInputToCell(inputSelector, cellSelector) {
        const $input = $(inputSelector);
        const $cell = $(cellSelector);
        if (!$input.length || !$cell.length || $cell.find(inputSelector).length) {
            return;
        }
        $cell.empty().append($input);
    }

    function moveAgentRateFieldBlockToSlot(fieldSelector, slotSelector) {
        const $field = $(fieldSelector);
        const $slot = $(slotSelector);
        if (!$field.length || !$slot.length || $slot.find(fieldSelector).length) {
            return;
        }
        const $block = $field.closest('p');
        if (!$block.length) {
            return;
        }
        $slot.empty().append($block);
    }

    function buildAgentSingleRatePanel() {
        return (
            '<div class="oksia-rate-panel oksia-rate-panel--single" data-rate-mode-panel="single">' +
                buildAgentSingleRatePanelHead() +
            '</div>'
        );
    }

    function populateAgentMultiCurrencyOptions() {
        const $source = $('#oksia_currency');
        const $target = $('#oksia_multi_currency');
        if (!$source.length || !$target.length) {
            return;
        }
        if ($target.children().length) {
            return;
        }

        const options = [];
        $source.find('option').each(function () {
            const value = ($(this).attr('value') || '').trim();
            if (!value) {
                return;
            }
            options.push({ value: value, label: ($(this).text() || value).trim() });
        });

        if (!options.length) {
            options.push({ value: 'INR', label: 'INR' });
        }

        options.forEach(function (opt) {
            $target.append($('<option>', { value: opt.value, text: opt.label }));
        });
    }

    function buildAgentMultiRateRow(component, seed) {
        const label = component.label;
        const labelClass = '';
        const values = {
            adult: seed['multi_' + component.key + '_adult'] || '',
            with_bed: seed['multi_' + component.key + '_with_bed'] || '',
            child: seed['multi_' + component.key + '_child'] || ''
        };

        return (
            '<div class="oksia-multi-rate-table__row" data-component="' + component.key + '">' +
                '<div class="oksia-multi-rate-table__label"><span class="oksia-multi-rate-table__label-text' + labelClass + '">' + escapeAttr(label) + '</span></div>' +
                '<div class="oksia-multi-rate-table__cell"><input type="number" step="0.01" min="0" id="oksia_multi_' + component.key + '_adult" name="oksia_quote[multi_' + component.key + '_adult]" class="widefat" value="' + escapeAttr(values.adult) + '" /></div>' +
                '<div class="oksia-multi-rate-table__cell"><input type="number" step="0.01" min="0" id="oksia_multi_' + component.key + '_with_bed" name="oksia_quote[multi_' + component.key + '_with_bed]" class="widefat" value="' + escapeAttr(values.with_bed) + '" /></div>' +
                '<div class="oksia-multi-rate-table__cell"><input type="number" step="0.01" min="0" id="oksia_multi_' + component.key + '_child" name="oksia_quote[multi_' + component.key + '_child]" class="widefat" value="' + escapeAttr(values.child) + '" /></div>' +
            '</div>'
        );
    }

    function buildAgentMultiRatePanel() {
        const seed = getAgentRateSeed();
        const rows = AGENT_RATE_MULTI_COMPONENTS.map(function (component) {
            return buildAgentMultiRateRow(component, seed);
        }).join('');

        return (
            '<div class="oksia-rate-panel oksia-rate-panel--multi" data-rate-mode-panel="multi">' +
                '<div class="oksia-rate-panel__head">' +
                    '<div>' +
                        '<div class="oksia-rate-panel__title">Multi Vendor Rates</div>' +
                    '</div>' +
                '</div>' +
                '<div class="oksia-multi-rate-table">' +
                    '<div class="oksia-multi-rate-table__head">' +
                        '<div>Rates</div>' +
                        '<div>Adult</div>' +
                        '<div>Adult/Child w/ Bed</div>' +
                        '<div>Child No Bed</div>' +
                    '</div>' +
                    rows +
                    '<div class="oksia-multi-rate-table__row oksia-multi-rate-table__row--markup" data-row="markup">' +
                        '<div class="oksia-multi-rate-table__label"><span class="oksia-multi-rate-table__label-text">Mark up</span></div>' +
                        '<div class="oksia-multi-rate-table__cell"><input type="number" step="0.01" min="0" id="oksia_multi_adult_markup" name="oksia_quote[multi_adult_markup]" class="widefat" value="' + escapeAttr(seed.multi_adult_markup || '') + '" /></div>' +
                        '<div class="oksia-multi-rate-table__cell"><input type="number" step="0.01" min="0" id="oksia_multi_with_bed_markup" name="oksia_quote[multi_with_bed_markup]" class="widefat" value="' + escapeAttr(seed.multi_with_bed_markup || '') + '" /></div>' +
                        '<div class="oksia-multi-rate-table__cell"><input type="number" step="0.01" min="0" id="oksia_multi_child_markup" name="oksia_quote[multi_child_markup]" class="widefat" value="' + escapeAttr(seed.multi_child_markup || '') + '" /></div>' +
                    '</div>' +
                    '<div class="oksia-multi-rate-table__row oksia-multi-rate-table__row--final" data-row="final">' +
                        '<div class="oksia-multi-rate-table__label"><span class="oksia-multi-rate-table__label-text">Final</span></div>' +
                        '<div class="oksia-multi-rate-table__cell"><input type="text" id="oksia_multi_adult_final" name="oksia_quote[multi_adult_final]" class="widefat" readonly value="' + escapeAttr(seed.multi_adult_final || '') + '" /></div>' +
                        '<div class="oksia-multi-rate-table__cell"><input type="text" id="oksia_multi_with_bed_final" name="oksia_quote[multi_with_bed_final]" class="widefat" readonly value="' + escapeAttr(seed.multi_with_bed_final || '') + '" /></div>' +
                        '<div class="oksia-multi-rate-table__cell"><input type="text" id="oksia_multi_child_final" name="oksia_quote[multi_child_final]" class="widefat" readonly value="' + escapeAttr(seed.multi_child_final || '') + '" /></div>' +
                    '</div>' +
                    '<div class="oksia-multi-rate-table__row oksia-multi-rate-table__row--reference oksia-agent-inr-reference-row" data-row="reference">' +
                        '<div class="oksia-multi-rate-table__label"><span class="oksia-multi-rate-table__label-text">INR Reference only</span></div>' +
                        '<div class="oksia-multi-rate-table__cell"><input type="text" id="oksia_multi_adult_rate_quote" name="oksia_quote[multi_adult_rate_quote]" class="widefat" readonly value="' + escapeAttr(seed.multi_adult_rate_quote || '') + '" /></div>' +
                        '<div class="oksia-multi-rate-table__cell"><input type="text" id="oksia_multi_with_bed_rate_quote" name="oksia_quote[multi_with_bed_rate_quote]" class="widefat" readonly value="' + escapeAttr(seed.multi_with_bed_rate_quote || '') + '" /></div>' +
                        '<div class="oksia-multi-rate-table__cell"><input type="text" id="oksia_multi_child_rate_quote" name="oksia_quote[multi_child_rate_quote]" class="widefat" readonly value="' + escapeAttr(seed.multi_child_rate_quote || '') + '" /></div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
    }

    function applyAgentRateSeed() {
        if (window.okAgentRateSeedApplied) {
            return;
        }
        const seed = getAgentRateSeed();
        if (!Object.keys(seed).length) {
            window.okAgentRateSeedApplied = true;
            return;
        }

        $('#oksia_vendor_mode').val(seed.vendor_mode || 'multi');
        if (seed.currency) {
            $('#oksia_currency').val(seed.currency);
        }
        populateAgentMultiCurrencyOptions();
        $('#oksia_multi_currency').val(seed.multi_currency || seed.currency || 'INR');

        setAgentRateValue('#oksia_adult_rate', seed.adult_rate || '');
        setAgentRateValue('#oksia_with_bed_rate', seed.with_bed_rate || '');
        setAgentRateValue('#oksia_child_rate', seed.child_rate || '');
        setAgentRateValue('#oksia_adult_markup', seed.adult_markup || '');
        setAgentRateValue('#oksia_with_bed_markup', seed.with_bed_markup || '');
        setAgentRateValue('#oksia_child_markup', seed.child_markup || '');
        setAgentRateValue('#oksia_adult_rate_quote', seed.adult_rate_quote || '');
        setAgentRateValue('#oksia_with_bed_rate_quote', seed.with_bed_rate_quote || '');
        setAgentRateValue('#oksia_child_rate_quote', seed.child_rate_quote || '');
        window.okAgentRateSeedApplied = true;
    }

    function ensureAgentRatePanels() {
        if (!isAgentRateWorkspace()) {
            return;
        }

        if (!$('#oksia_vendor_mode').length) {
            const $ratesLabel = $('.oksia-intake-section-label').filter(function () {
                return 'Rates' === ($(this).text() || '').trim();
            }).first();

            if (!$ratesLabel.length) {
                return;
            }

            const $singleBlocks = $('#oksia_currency').closest('.oksia-grid--five')
                .add($('#oksia_adult_rate').closest('.oksia-grid--three').first())
                .add($('#oksia_adult_markup').closest('.oksia-rate-markup-row'))
                .add($('#oksia_adult_rate_quote').closest('.oksia-grid--three').first());

            if ($singleBlocks.length) {
                const $setupRow = $('#oksia_currency').closest('.oksia-grid--five');
                const $rateRow = $('#oksia_adult_rate').closest('.oksia-grid--three').first();
                const $markupRow = $('#oksia_adult_markup').closest('.oksia-rate-markup-row');
                const $referenceRow = $('#oksia_adult_rate_quote').closest('.oksia-grid--three').first();
                const $singlePanel = $(buildAgentSingleRatePanel());

                $setupRow.before($singlePanel);
                $setupRow.addClass('oksia-single-rate-row oksia-single-rate-row--setup');
                $singlePanel.append($setupRow);
                $singlePanel.append(buildAgentSingleRateTable());

                moveAgentRateInputToCell('#oksia_adult_rate', '#oksia-single-rate-adult');
                moveAgentRateInputToCell('#oksia_with_bed_rate', '#oksia-single-rate-with-bed');
                moveAgentRateInputToCell('#oksia_child_rate', '#oksia-single-rate-child');
                moveAgentRateInputToCell('#oksia_adult_markup', '#oksia-single-markup-adult');
                moveAgentRateInputToCell('#oksia_with_bed_markup', '#oksia-single-markup-with-bed');
                moveAgentRateInputToCell('#oksia_child_markup', '#oksia-single-markup-child');
                moveAgentRateInputToCell('#oksia_adult_rate_quote', '#oksia-single-reference-adult');
                moveAgentRateInputToCell('#oksia_with_bed_rate_quote', '#oksia-single-reference-with-bed');
                moveAgentRateInputToCell('#oksia_child_rate_quote', '#oksia-single-reference-child');

                $rateRow.remove();
                $markupRow.remove();
                $referenceRow.remove();
            }

            $ratesLabel.after(buildAgentRateModeToolbar());
            moveAgentRateFieldBlockToSlot('#oksia_currency', '#oksia-toolbar-currency-slot');
            moveAgentRateFieldBlockToSlot('#oksia_exchange_rate', '#oksia-toolbar-exchange-slot');
            moveAgentRateFieldBlockToSlot('#oksia_transaction_cost', '#oksia-toolbar-transaction-slot');
            moveAgentRateFieldBlockToSlot('#oksia_effective_rate', '#oksia-toolbar-effective-slot');
            $('#oksia_additional_cost').closest('p').remove();
            const $singleSetup = $('.oksia-rate-panel--single .oksia-single-rate-row--setup');
            if ($singleSetup.length && !$singleSetup.find('p').length) {
                $singleSetup.remove();
            }
            populateAgentMultiCurrencyOptions();

            const $packageSummaryBlock = $('.oksia-package-summary-block').first();
            const $packageLabel = $('.oksia-intake-section-label').filter(function () {
                return 'Package Summary' === ($(this).text() || '').trim();
            }).first();
            const panelHtml = buildAgentMultiRatePanel();
            if ($packageSummaryBlock.length) {
                $(panelHtml).insertBefore($packageSummaryBlock);
            } else if ($packageLabel.length) {
                $(panelHtml).insertBefore($packageLabel);
            } else if ($('.oksia-rate-panel--single').length) {
                $('.oksia-rate-panel--single').after(panelHtml);
            }
        }

        applyAgentRateSeed();
        syncAgentRateWorkspaceState();
    }

    function syncAgentRateWorkspaceState() {
        if (!isAgentRateWorkspace()) {
            return;
        }

        const vendorMode = getAgentVendorMode();
        const showReference = shouldShowAgentInrReference();
        const $workspace = $('.oksia-agent-workspace').first();
        const quoteCurrency = ($('#oksia_currency').val() || 'INR').trim();
        const $multiCurrency = $('#oksia_multi_currency');

        if ($workspace.length) {
            $workspace.attr('data-rate-mode', vendorMode);
            $workspace.attr('data-show-inr-reference', showReference ? '1' : '0');
        }

        $('.oksia-rate-mode-field--multi-currency').toggle('multi' === vendorMode);
        $('.oksia-rate-mode-field--single-currency').toggle('single' === vendorMode);
        $('.oksia-rate-mode-field--single-exchange').toggle(true);
        $('.oksia-rate-mode-field--single-transaction').toggle(true);
        $('.oksia-rate-mode-field--single-effective').toggle(true);
        populateAgentMultiCurrencyOptions();
        if ($multiCurrency.length) {
            if ('multi' === vendorMode) {
                if (!$multiCurrency.val()) {
                    $multiCurrency.val(quoteCurrency);
                }
            } else {
                $multiCurrency.val(quoteCurrency);
            }
        }
        $('.oksia-agent-inr-reference-row').toggle(showReference);
    }

    function getAgentMultiRateSnapshot() {
        const currency = $('#oksia_currency').val() || 'INR';
        const effectiveRate = getAgentCurrencyMultiplier();
        const showReference = shouldShowAgentInrReference();
        const counts = {
            adult: Number($('#oksia_adults').val() || 0),
            with_bed: Number($('#oksia_adult_with_bed').val() || 0),
            child: Number($('#oksia_child_without_bed').val() || 0)
        };
        const componentTotals = { adult: 0, with_bed: 0, child: 0 };
        const hasValues = { adult: false, with_bed: false, child: false };

        AGENT_RATE_MULTI_COMPONENTS.forEach(function (component) {
            AGENT_RATE_MULTI_PASSENGERS.forEach(function (passenger) {
                const value = Number($('#oksia_multi_' + component.key + '_' + passenger.key).val() || 0);
                if (value) {
                    hasValues[passenger.key] = true;
                }
                componentTotals[passenger.key] += value;
            });
        });

        const markup = {
            adult: Number($('#oksia_multi_adult_markup').val() || 0),
            with_bed: Number($('#oksia_multi_with_bed_markup').val() || 0),
            child: Number($('#oksia_multi_child_markup').val() || 0)
        };

        if (markup.adult || markup.with_bed || markup.child) {
            hasValues.adult = true;
            hasValues.with_bed = true;
            hasValues.child = true;
        }

        const finalTotals = {
            adult: componentTotals.adult + markup.adult,
            with_bed: componentTotals.with_bed + markup.with_bed,
            child: componentTotals.child + markup.child
        };

        const referenceTotals = {
            adult: showReference && (currency === 'INR' || effectiveRate > 0) ? (currency === 'INR' ? finalTotals.adult : finalTotals.adult * effectiveRate) : '',
            with_bed: showReference && (currency === 'INR' || effectiveRate > 0) ? (currency === 'INR' ? finalTotals.with_bed : finalTotals.with_bed * effectiveRate) : '',
            child: showReference && (currency === 'INR' || effectiveRate > 0) ? (currency === 'INR' ? finalTotals.child : finalTotals.child * effectiveRate) : ''
        };

        return {
            currency: currency,
            effectiveRate: effectiveRate,
            showReference: showReference,
            counts: counts,
            componentTotals: componentTotals,
            markup: markup,
            finalTotals: finalTotals,
            referenceTotals: referenceTotals,
            packageBaseTotal: (counts.adult * componentTotals.adult) + (counts.with_bed * componentTotals.with_bed) + (counts.child * componentTotals.child),
            packageCustomerTotal: (counts.adult * finalTotals.adult) + (counts.with_bed * finalTotals.with_bed) + (counts.child * finalTotals.child),
            hasValues: hasValues
        };
    }

    function applyAgentMultiRateSnapshot(snapshot) {
        if (!snapshot) {
            return;
        }

        const currency = snapshot.currency || 'INR';
        $('#oksia_effective_rate').val(currency === 'INR' ? '1' : snapshot.effectiveRate.toFixed(4));
        $('.oksia-selected-currency-label').text(currency);
        syncAgentRateWorkspaceState();

        if (!snapshot.hasValues.adult && !snapshot.hasValues.with_bed && !snapshot.hasValues.child) {
            setAgentRateValue('#oksia_multi_adult_final', '');
            setAgentRateValue('#oksia_multi_with_bed_final', '');
            setAgentRateValue('#oksia_multi_child_final', '');
            setAgentRateValue('#oksia_multi_adult_rate_quote', '');
            setAgentRateValue('#oksia_multi_with_bed_rate_quote', '');
            setAgentRateValue('#oksia_multi_child_rate_quote', '');
            setAgentRateValue('#oksia_adult_rate', '');
            setAgentRateValue('#oksia_with_bed_rate', '');
            setAgentRateValue('#oksia_child_rate', '');
            setAgentRateValue('#oksia_adult_markup', '');
            setAgentRateValue('#oksia_with_bed_markup', '');
            setAgentRateValue('#oksia_child_markup', '');
            setAgentRateValue('#oksia_adult_rate_quote', '');
            setAgentRateValue('#oksia_with_bed_rate_quote', '');
            setAgentRateValue('#oksia_child_rate_quote', '');
            setAgentRateValue('#oksia_package_base_total', '');
            setAgentRateValue('#oksia_package_customer_total', '');
            return;
        }

        const finalValues = {
            adult: formatAgentRateNumber(snapshot.finalTotals.adult, 2),
            with_bed: formatAgentRateNumber(snapshot.finalTotals.with_bed, 2),
            child: formatAgentRateNumber(snapshot.finalTotals.child, 2)
        };
        const referenceValues = {
            adult: snapshot.showReference ? formatAgentRateNumber(snapshot.referenceTotals.adult, 2) : '',
            with_bed: snapshot.showReference ? formatAgentRateNumber(snapshot.referenceTotals.with_bed, 2) : '',
            child: snapshot.showReference ? formatAgentRateNumber(snapshot.referenceTotals.child, 2) : ''
        };

        setAgentRateValue('#oksia_multi_adult_final', finalValues.adult);
        setAgentRateValue('#oksia_multi_with_bed_final', finalValues.with_bed);
        setAgentRateValue('#oksia_multi_child_final', finalValues.child);
        setAgentRateValue('#oksia_multi_adult_rate_quote', referenceValues.adult);
        setAgentRateValue('#oksia_multi_with_bed_rate_quote', referenceValues.with_bed);
        setAgentRateValue('#oksia_multi_child_rate_quote', referenceValues.child);
        setAgentRateValue('#oksia_adult_rate', finalValues.adult);
        setAgentRateValue('#oksia_with_bed_rate', finalValues.with_bed);
        setAgentRateValue('#oksia_child_rate', finalValues.child);
        setAgentRateValue('#oksia_adult_markup', formatAgentRateNumber(snapshot.markup.adult, 2));
        setAgentRateValue('#oksia_with_bed_markup', formatAgentRateNumber(snapshot.markup.with_bed, 2));
        setAgentRateValue('#oksia_child_markup', formatAgentRateNumber(snapshot.markup.child, 2));
        setAgentRateValue('#oksia_adult_rate_quote', referenceValues.adult);
        setAgentRateValue('#oksia_with_bed_rate_quote', referenceValues.with_bed);
        setAgentRateValue('#oksia_child_rate_quote', referenceValues.child);
        setAgentRateValue('#oksia_package_base_total', formatAgentRateNumber(snapshot.packageBaseTotal, 2));
        setAgentRateValue('#oksia_package_customer_total', formatAgentRateNumber(snapshot.packageCustomerTotal, 2));
    }

    function recalculateAgentMultiRates() {
        applyAgentMultiRateSnapshot(getAgentMultiRateSnapshot());
    }

})(jQuery);

