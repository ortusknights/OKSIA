(function ($) {
    'use strict';

    /* ------------------------------------------------------------------
       State
    ------------------------------------------------------------------ */
    var tripType = 'Domestic';
    var pax      = { adults: 1, c611: 0, inf: 0 };
    var minPax   = { adults: 1, c611: 0, inf: 0 };

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function todayIso() {
        var now = new Date();
        return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
    }

    /* ------------------------------------------------------------------
       Boot: populate first destination list
    ------------------------------------------------------------------ */
    $(function () {
        loadDestinations('Domestic');

        /* Trip type cards */
        $('.oksia-tt').on('click', function () {
            var chosen = $(this).data('type');
            if (chosen === tripType) return;
            tripType = chosen;
            $('.oksia-tt').removeClass('oksia-tt--on');
            $(this).addClass('oksia-tt--on');
            loadDestinations(chosen);
        });

        /* Pax buttons */
        $(document).on('click', '.oksia-pax-btn', function () {
            var key = $(this).data('key');
            var dir = parseInt($(this).data('dir'), 10);
            pax[key] = Math.max(minPax[key], pax[key] + dir);
            $('#oksia-pv-' + key).text(pax[key]);
        });

        /* Submit */
        $('#oksia-intake-submit').on('click', function () {
            submitForm();
        });

        $(document).on('input change', '#oksia-salutation, #oksia-name, #oksia-phone, #oksia-email, #oksia-dest, #oksia-start-date, #oksia-end-date, #oksia-nights', function () {
            $(this).closest('.oksia-intake-field').removeClass('oksia-intake-field--error');
        });

        $('#oksia-start-date, #oksia-end-date').attr('min', todayIso());
        $('#oksia-start-date, #oksia-end-date').on('change input', recalculateNights);
        recalculateNights();
    });

    /* ------------------------------------------------------------------
       Populate destination <select> based on trip type
    ------------------------------------------------------------------ */
    function loadDestinations(type) {
        var $sel  = $('#oksia-dest');
        var list  = (okIntake.destinations[type] || []);
        $sel.empty().append('<option value="">Select destination</option>');
        if (list.length === 0) {
            $sel.append('<option value="" disabled>No destinations added — check Settings</option>');
        } else {
            $.each(list, function (i, dest) {
                $sel.append($('<option>', { value: dest, text: dest }));
            });
        }
    }

    function recalculateNights() {
        var start = $('#oksia-start-date').val();
        var end = $('#oksia-end-date').val();
        var today = todayIso();

        $('#oksia-start-date').attr('min', today);

        if (start) {
            $('#oksia-end-date').attr('min', start);
            if (end && end < start) {
                $('#oksia-end-date').val(start);
                end = start;
            }
        } else {
            $('#oksia-end-date').attr('min', today);
        }

        if (!start || !end) {
            $('#oksia-nights').val('');
            return;
        }

        var startDate = new Date(start + 'T00:00:00');
        var endDate = new Date(end + 'T00:00:00');
        var diffDays = Math.round((endDate - startDate) / 86400000);

        $('#oksia-nights').val(diffDays > 0 ? diffDays : '');
    }

    /* ------------------------------------------------------------------
       Validation + AJAX submission
    ------------------------------------------------------------------ */
    function showErr(msg) {
        $('#oksia-intake-err').text(msg).show();
    }

    function clearErr() {
        $('#oksia-intake-err').hide().text('');
    }

    function clearFieldErrors() {
        $('.oksia-intake-field--error').removeClass('oksia-intake-field--error');
    }

    function markFieldError(selector) {
        $(selector).closest('.oksia-intake-field').addClass('oksia-intake-field--error');
    }

    function submitForm() {
        clearErr();
        clearFieldErrors();

        recalculateNights();

        var salutation = $('#oksia-salutation').val() || 'Mr';
        var name   = $('#oksia-name').val().trim();
        var phone  = $('#oksia-phone').val().trim();
        var email  = $('#oksia-email').val().trim();
        var dest   = $('#oksia-dest').val();
        var start  = $('#oksia-start-date').val();
        var end    = $('#oksia-end-date').val();
        var nights = parseInt($('#oksia-nights').val(), 10);
        var portalMode = $('#oksia-portal-mode').val() || 'public';

        var missing = [];

        if (!name) {
            missing.push('full name');
            markFieldError('#oksia-name');
        }
        if (phone.length < 10 || phone.search(/^\d{10}$/) < 0) {
            missing.push('phone number');
            markFieldError('#oksia-phone');
        }
        if (!email || email.indexOf('@') < 0) {
            missing.push('email address');
            markFieldError('#oksia-email');
        }
        if (!dest) {
            missing.push('destination');
            markFieldError('#oksia-dest');
        }
        if (!start) {
            missing.push('start date');
            markFieldError('#oksia-start-date');
        }
        if (!end) {
            missing.push('end date');
            markFieldError('#oksia-end-date');
        }
        if (!nights || nights < 1) {
            missing.push('number of nights');
            markFieldError('#oksia-nights');
        }

        if (missing.length) {
            return showErr('Please complete the required trip fields: ' + missing.join(', ') + '.');
        }

        var $btn = $('#oksia-intake-submit');
        $btn.prop('disabled', true).text('Getting quotation...');

        $.post(
            okIntake.ajaxUrl,
            {
                action:        'oksia_submit_intake',
                nonce:         okIntake.nonce,
                salutation:    salutation,
                name:          name,
                phone:         phone,
                email:         email,
                trip_type:     tripType,
                destination:   dest,
                start_date:    start,
                end_date:      end,
                nights:        nights,
                portal_mode:   portalMode,
                adults:        pax.adults,
                children_611:  pax.c611,
                infants:       pax.inf,
            },
            function (response) {
            $btn.prop('disabled', false).text('Get Quotation');

                if (!response.success) {
                    showErr(response.data || 'Something went wrong. Please try again.');
                    return;
                }

                var d = response.data;

                /* Build pax summary string */
                var paxParts = [d.adults + ' adult' + (d.adults > 1 ? 's' : '')];
                if (d.c611 > 0) paxParts.push(d.c611 + ' child' + (d.c611 > 1 ? 'ren' : '') + ' (6–11)');
                if (d.inf  > 0) paxParts.push(d.inf  + ' infant' + (d.inf  > 1 ? 's' : ''));

                $('#oksia-qid-out').text(d.quote_id);
                $('#oksia-success-details').html(
                    '<div>' + escHtml((d.salutation ? d.salutation + ' ' : '') + d.name)  + ' &nbsp;&middot;&nbsp; ' + escHtml(d.phone) + ' &nbsp;&middot;&nbsp; ' + escHtml(d.email) + '</div>' +
                    '<div>' + escHtml(d.type)  + ' &nbsp;&middot;&nbsp; ' + escHtml(d.dest)  + ' &nbsp;&middot;&nbsp; ' + escHtml(d.start) + ' to ' + escHtml(d.end) + ' &nbsp;&middot;&nbsp; ' + d.nights + ' night' + (d.nights > 1 ? 's' : '') + '</div>' +
                    '<div>' + escHtml(paxParts.join(' &nbsp;&middot;&nbsp; ')) + '</div>'
                );

                $('#oksia-intake-form-card').fadeOut(200, function () {
                    $('#oksia-intake-success').fadeIn(300);
                });
            },
            'json'
        ).fail(function () {
            $btn.prop('disabled', false).text('Get Quotation');
            showErr('Network error — please check your connection and try again.');
        });
    }

    /* ------------------------------------------------------------------
       Tiny HTML escaper (prevents XSS in success card)
    ------------------------------------------------------------------ */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

}(jQuery));



