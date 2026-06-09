<div class="wrap">
    <h1>Agencies 
        <button class="page-title-action export-agencies">Export CSV</button>
    </h1>
    
    <form method="get" id="agencies-filter-form">
        <input type="hidden" name="page" value="oksia-agencies">
        <div class="tablenav top">
            <div class="alignleft actions">
                <select id="bulk-action-select">
                    <option value="">Bulk Actions</option>
                    <option value="activate">Activate</option>
                    <option value="suspend">Suspend</option>
                    <option value="delete">Delete</option>
                </select>
                <button id="apply-bulk-action" class="button action">Apply</button>
            </div>
            <div class="alignright">
                <input type="text" name="s" placeholder="Search agencies..." value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>">
                <select name="status_filter">
                    <option value="">All Status</option>
                    <option value="active" <?php selected(isset($_GET['status_filter']) && $_GET['status_filter'] === 'active'); ?>>Active</option>
                    <option value="pending" <?php selected(isset($_GET['status_filter']) && $_GET['status_filter'] === 'pending'); ?>>Pending</option>
                    <option value="suspended" <?php selected(isset($_GET['status_filter']) && $_GET['status_filter'] === 'suspended'); ?>>Suspended</option>
                </select>
                <input type="submit" class="button" value="Filter">
            </div>
        </div>
    </form>
    
    <?php
    global $wpdb;
    $table = $wpdb->prefix . 'oksia_agencies';
    
    $per_page = 20;
    $page_number = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page_number - 1) * $per_page;
    
    $where = '1=1';
    if (isset($_GET['s']) && !empty($_GET['s'])) {
        $search = $wpdb->esc_like(sanitize_text_field($_GET['s']));
        $where .= $wpdb->prepare(" AND (name LIKE %s OR code LIKE %s)", "%$search%", "%$search%");
    }
    if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
        $status = sanitize_text_field($_GET['status_filter']);
        $where .= $wpdb->prepare(" AND status = %s", $status);
    }
    
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
    $agencies = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
    $total_pages = ceil($total_items / $per_page);
    ?>
    
    <table class="wp-list-table widefat fixed striped" id="agencies-table">
        <thead>
            <tr>
                <th width="50"><input type="checkbox" id="cb-select-all"></th>
                <th>ID</th>
                <th>Agency Name</th>
                <th>Code</th>
                <th>Status</th>
                <th>Created By</th>
                <th>Created</th>
                <th width="150">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($agencies)): ?>
                <tr><td colspan="8">No agencies found.<?php echo $where; ?></td></tr>
            <?php else: ?>
                <?php foreach ($agencies as $agency): ?>
                    <tr data-id="<?php echo $agency->id; ?>">
                        <td><input type="checkbox" class="agency-cb" value="<?php echo $agency->id; ?>"></td>
                        <td><?php echo $agency->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($agency->name); ?></strong>
                            <?php if ($agency->code === 'OKSIA1'): ?>
                                <span class="demo-badge">Demo</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo esc_html($agency->code); ?></code></td>
                        <td>
                            <span class="status-badge status-<?php echo $agency->status; ?>">
                                <?php echo ucfirst($agency->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $user = get_userdata($agency->created_by);
                            echo $user ? esc_html($user->display_name) : 'N/A';
                            ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($agency->created_at)); ?></td>
                        <td>
                            <button class="button button-small view-agency" data-id="<?php echo $agency->id; ?>">View</button>
                            <button class="button button-small edit-agency" data-id="<?php echo $agency->id; ?>">Edit</button>
                            <?php if ($agency->code !== 'OKSIA1'): ?>
                                <button class="button button-small delete-agency" data-id="<?php echo $agency->id; ?>">Delete</button>
                            <?php else: ?>
                                <span class="disabled">Delete</span>
                            <?php endif; ?>
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
<?php include_once OKSIA_PLUGIN_DIR . 'templates/admin/agency-edit-modal.php'; ?>
<?php include_once OKSIA_PLUGIN_DIR . 'templates/admin/agency-view-modal.php'; ?>

<style>
.demo-badge {
    background: #9b59b6;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    margin-left: 5px;
}
.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}
.status-active { background: #d4edda; color: #155724; }
.status-pending { background: #fff3cd; color: #856404; }
.status-suspended { background: #f8d7da; color: #721c24; }
.disabled { color: #ccc; }
.button-small {
    margin: 0 2px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Select all checkbox
    $('#cb-select-all').on('change', function() {
        $('.agency-cb').prop('checked', $(this).prop('checked'));
    });
    
    // Bulk action
    $('#apply-bulk-action').on('click', function(e) {
        e.preventDefault();
        var action = $('#bulk-action-select').val();
        var ids = $('.agency-cb:checked').map(function() { return $(this).val(); }).get();
        
        if (!action) {
            alert('Please select an action');
            return;
        }
        if (ids.length === 0) {
            alert('Please select agencies');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oksia_bulk_agency_action',
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
    
    // Edit agency
    $('.edit-agency').on('click', function() {
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
                    var agency = response.data;
                    $('#edit_agency_id').val(agency.id);
                    $('#edit_agency_name').val(agency.name);
                    $('#edit_agency_status').val(agency.status);
                    $('#edit_agency_code').val(agency.code);
                    $('#edit-agency-modal').show();
                }
            }
        });
    });
    
    // Save edit agency
    $('#edit-agency-form').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oksia_update_agency',
                agency_id: $('#edit_agency_id').val(),
                name: $('#edit_agency_name').val(),
                status: $('#edit_agency_status').val(),
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
    
    // Delete agency
    $('.delete-agency').on('click', function() {
        if (!confirm('Are you sure? This will also delete all quotes from this agency.')) return;
        
        var id = $(this).data('id');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oksia_delete_agency',
                agency_id: id,
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
    
    // Export
    $('.export-agencies').on('click', function() {
        window.location.href = ajaxurl + '?action=oksia_export_agencies&nonce=<?php echo wp_create_nonce('oksia_admin_nonce'); ?>';
    });
    
    // Close modals
    $('.close-modal').on('click', function() {
        $('#edit-agency-modal, #view-agency-modal, #edit-quote-modal, #view-quote-modal').hide();
    });
});
</script>