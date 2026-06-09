<div class="wrap">
    <h1>All Quotes 
        <button class="page-title-action export-quotes">Export CSV</button>
    </h1>
    
    <form method="get" id="quotes-filter-form">
        <input type="hidden" name="page" value="oksia-quotes">
        <div class="tablenav top">
            <div class="alignleft actions">
                <select id="bulk-action-select">
                    <option value="">Bulk Actions</option>
                    <option value="confirm">Confirm</option>
                    <option value="cancel">Cancel</option>
                    <option value="delete">Delete</option>
                </select>
                <button id="apply-bulk-action" class="button action">Apply</button>
            </div>
            <div class="alignright">
                <input type="text" name="s" placeholder="Search quotes..." value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>">
                <select name="status_filter">
                    <option value="">All Status</option>
                    <option value="draft" <?php selected(isset($_GET['status_filter']) && $_GET['status_filter'] === 'draft'); ?>>Draft</option>
                    <option value="sent" <?php selected(isset($_GET['status_filter']) && $_GET['status_filter'] === 'sent'); ?>>Sent</option>
                    <option value="confirmed" <?php selected(isset($_GET['status_filter']) && $_GET['status_filter'] === 'confirmed'); ?>>Confirmed</option>
                    <option value="cancelled" <?php selected(isset($_GET['status_filter']) && $_GET['status_filter'] === 'cancelled'); ?>>Cancelled</option>
                </select>
                <input type="submit" class="button" value="Filter">
            </div>
        </div>
    </form>
    
    <?php
    global $wpdb;
    $agencies_table = $wpdb->prefix . 'oksia_agencies';
    $quotes_table = $wpdb->prefix . 'oksia_quotes';
    
    $per_page = 20;
    $page_number = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page_number - 1) * $per_page;
    
    $where = '1=1';
    if (isset($_GET['s']) && !empty($_GET['s'])) {
        $search = $wpdb->esc_like(sanitize_text_field($_GET['s']));
        $where .= $wpdb->prepare(" AND q.quote_number LIKE %s", "%$search%");
    }
    if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
        $status = sanitize_text_field($_GET['status_filter']);
        $where .= $wpdb->prepare(" AND q.status = %s", $status);
    }
    
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $quotes_table q WHERE $where");
    $quotes = $wpdb->get_results("
        SELECT q.*, a.name as agency_name 
        FROM $quotes_table q
        LEFT JOIN $agencies_table a ON q.agency_id = a.id
        WHERE $where 
        ORDER BY q.created_at DESC 
        LIMIT $per_page OFFSET $offset
    ");
    $total_pages = ceil($total_items / $per_page);
    ?>
    
    <table class="wp-list-table widefat fixed striped" id="quotes-table">
        <thead>
            <tr>
                <th width="50"><input type="checkbox" id="cb-select-all"></th>
                <th>ID</th>
                <th>Quote #</th>
                <th>Agency</th>
                <th>Client</th>
                <th>Destination</th>
                <th>Status</th>
                <th>Version</th>
                <th>Views</th>
                <th>Created</th>
                <th width="150">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($quotes)): ?>
                <tr><td colspan="11">No quotes found.<?php echo $where; ?></td></tr>
            <?php else: ?>
                <?php foreach ($quotes as $quote): 
                    $client_data = unserialize($quote->client_data);
                ?>
                    <tr data-id="<?php echo $quote->id; ?>">
                        <td><input type="checkbox" class="quote-cb" value="<?php echo $quote->id; ?>"></td>
                        <td><?php echo $quote->id; ?></td>
                        <td><code><?php echo esc_html($quote->quote_number); ?></code></td>
                        <td><?php echo esc_html($quote->agency_name ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($client_data['client_name'] ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($client_data['destination'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $quote->status; ?>">
                                <?php echo ucfirst($quote->status); ?>
                            </span>
                        </td>
                        <td>v<?php echo $quote->version; ?></td>
                        <td><?php echo $quote->view_count; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($quote->created_at)); ?></td>
                        <td>
                            <button class="button button-small view-quote" data-id="<?php echo $quote->id; ?>">View</button>
                            <button class="button button-small edit-quote" data-id="<?php echo $quote->id; ?>">Edit</button>
                            <button class="button button-small delete-quote" data-id="<?php echo $quote->id; ?>">Delete</button>
                            <a href="<?php echo home_url("/quote/{$quote->share_token}"); ?>" class="button button-small" target="_blank">Share</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $page_number
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Include Modals -->
<?php include_once OKSIA_PLUGIN_DIR . 'templates/admin/quote-edit-modal.php'; ?>
<?php include_once OKSIA_PLUGIN_DIR . 'templates/admin/quote-view-modal.php'; ?>

<style>
.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}
.status-draft { background: #e2e3e5; color: #383d41; }
.status-sent { background: #d1ecf1; color: #0c5460; }
.status-confirmed { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.button-small {
    margin: 0 2px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Select all checkbox
    $('#cb-select-all').on('change', function() {
        $('.quote-cb').prop('checked', $(this).prop('checked'));
    });
    
    // Bulk action
    $('#apply-bulk-action').on('click', function(e) {
        e.preventDefault();
        var action = $('#bulk-action-select').val();
        var ids = $('.quote-cb:checked').map(function() { return $(this).val(); }).get();
        
        if (!action) {
            alert('Please select an action');
            return;
        }
        if (ids.length === 0) {
            alert('Please select quotes');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oksia_bulk_quote_action',
                bulk_action: action,
                ids: ids,
                nonce: '<?php echo wp_create_nonce('oksia_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });
    
    // View quote
    $('.view-quote').on('click', function() {
        var id = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oksia_get_quote',
                quote_id: id,
                nonce: '<?php echo wp_create_nonce('oksia_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var quote = response.data;
                    $('#view_quote_id').text(quote.id);
                    $('#view_quote_number').text(quote.quote_number);
                    $('#view_quote_status').text(quote.status);
                    $('#view_quote_version').text(quote.version);
                    $('#view_quote_views').text(quote.view_count);
                    $('#view_client_name').text(quote.client_data.client_name || 'N/A');
                    $('#view_client_email').text(quote.client_data.email || 'N/A');
                    $('#view_client_phone').text(quote.client_data.phone || 'N/A');
                    $('#view_destination').text(quote.client_data.destination || 'N/A');
                    $('#view_quote_created').text(quote.created_at);
                    $('#view-quote-modal').show();
                }
            }
        });
    });
    
    // Edit quote
    $('.edit-quote').on('click', function() {
        var id = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oksia_get_quote',
                quote_id: id,
                nonce: '<?php echo wp_create_nonce('oksia_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var quote = response.data;
                    $('#edit_quote_id').val(quote.id);
                    $('#edit_quote_number').val(quote.quote_number);
                    $('#edit_quote_status').val(quote.status);
                    $('#edit_client_name').val(quote.client_data.client_name || '');
                    $('#edit_client_email').val(quote.client_data.email || '');
                    $('#edit_client_phone').val(quote.client_data.phone || '');
                    $('#edit_destination').val(quote.client_data.destination || '');
                    $('#edit-quote-modal').show();
                }
            }
        });
    });
    
    // Save edit quote
    $('#edit-quote-form').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oksia_update_quote',
                quote_id: $('#edit_quote_id').val(),
                status: $('#edit_quote_status').val(),
                nonce: '<?php echo wp_create_nonce('oksia_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });
    
    // Delete quote
    $('.delete-quote').on('click', function() {
        if (!confirm('Are you sure?')) return;
        
        var id = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oksia_delete_quote',
                quote_id: id,
                nonce: '<?php echo wp_create_nonce('oksia_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });
    
    // Export quotes
    $('.export-quotes').on('click', function() {
        window.location.href = ajaxurl + '?action=oksia_export_quotes&nonce=<?php echo wp_create_nonce('oksia_admin_nonce'); ?>';
    });
    
    // Close modals
    $('.close-modal').on('click', function() {
        $('#edit-quote-modal, #view-quote-modal, #edit-agency-modal, #view-agency-modal').hide();
    });
});
</script>