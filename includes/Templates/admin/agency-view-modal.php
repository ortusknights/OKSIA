<div id="view-agency-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; width:500px; max-width:90%; border-radius:8px; padding:20px;">
        <h2>Agency Details</h2>
        <div id="view-agency-details">
            <p><strong>ID:</strong> <span id="view_agency_id"></span></p>
            <p><strong>Name:</strong> <span id="view_agency_name"></span></p>
            <p><strong>Code:</strong> <span id="view_agency_code"></span></p>
            <p><strong>Status:</strong> <span id="view_agency_status"></span></p>
            <p><strong>Created By:</strong> <span id="view_agency_created_by"></span></p>
            <p><strong>Created:</strong> <span id="view_agency_created"></span></p>
        </div>
        <button class="button close-modal">Close</button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.view-agency').on('click', function() {
        var id = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oksia_get_agency',
                agency_id: id,
                nonce: '<?php echo wp_create_nonce('oksia_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var a = response.data;
                    $('#view_agency_id').text(a.id);
                    $('#view_agency_name').text(a.name);
                    $('#view_agency_code').text(a.code);
                    $('#view_agency_status').text(a.status);
                    $('#view_agency_created_by').text(a.created_by);
                    $('#view_agency_created').text(a.created_at);
                    $('#view-agency-modal').show();
                }
            }
        });
    });
});
</script>
