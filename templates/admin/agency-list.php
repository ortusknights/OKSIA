<div class="wrap">
    <h1>Agencies
        <a href="#" class="page-title-action add-new-agency">Add New</a>
    </h1>

    <form method="post">
        <?php wp_nonce_field('oksia_bulk_agencies'); ?>

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="oksia_bulk_action">
                    <option value="">Bulk Actions</option>
                    <option value="activate">Activate</option>
                    <option value="suspend">Suspend</option>
                    <option value="delete">Delete</option>
                </select>
                <input type="submit" class="button action" value="Apply">
            </div>

            <div class="alignright">
                <input type="text" name="search" placeholder="Search agencies..." value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>">
                <input type="submit" class="button" value="Search">
            </div>
        </div>

        <?php
        global $wpdb;
        $table = $wpdb->prefix . 'oksia_agencies';

        $per_page = 20;
        $page_number = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page_number - 1) * $per_page;

        $where = '';
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = $wpdb->esc_like(sanitize_text_field($_GET['s']));
            $where = $wpdb->prepare(" WHERE name LIKE %s OR code LIKE %s", "%$search%", "%$search%");
        }

        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $status = sanitize_text_field($_GET['status']);
            $where .= empty($where) ? $wpdb->prepare(" WHERE status = %s", $status) : $wpdb->prepare(" AND status = %s", $status);
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
        $agencies = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
        $total_pages = ceil($total_items / $per_page);
        ?>

        <table class="wp-list-table widefat fixed striped">
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
                    <tr><td colspan="8">No agencies found.</td></tr>
                <?php else: ?>
                    <?php foreach ($agencies as $agency): ?>
                        <tr>
                            <td><input type="checkbox" name="agency_ids[]" value="<?php echo $agency->id; ?>"></td>
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
                                <a href="#" class="view-agency" data-id="<?php echo $agency->id; ?>">View</a> |
                                <a href="#" class="edit-agency" data-id="<?php echo $agency->id; ?>">Edit</a> |
                                <?php if ($agency->code !== 'OKSIA1'): ?>
                                    <a href="#" class="delete-agency" data-id="<?php echo $agency->id; ?>">Delete</a>
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
    </form>
</div>

<!-- View Agency Modal -->
<div id="view-agency-modal" style="display:none;">
    <div class="modal-content">
        <h2>Agency Details</h2>
        <div id="agency-details"></div>
        <button class="button close-modal">Close</button>
    </div>
</div>

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
.modal-content {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 20px;
    border-radius: 8px;
    width: 500px;
    max-width: 90%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    z-index: 10000;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.view-agency').on('click', function(e) {
        e.preventDefault();
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
                    var data = response.data;
                    $('#agency-details').html('<p><strong>Name:</strong> ' + data.name + '</p>' +
                        '<p><strong>Code:</strong> ' + data.code + '</p>' +
                        '<p><strong>Status:</strong> ' + data.status + '</p>' +
                        '<p><strong>Created:</strong> ' + data.created_at + '</p>');
                    $('#view-agency-modal').show();
                }
            }
        });
    });

    $('.close-modal, #view-agency-modal').on('click', function(e) {
        if (e.target === this) {
            $('#view-agency-modal').hide();
        }
    });
});
</script>
