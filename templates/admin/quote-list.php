<div class="wrap">
    <h1>All Quotes</h1>

    <form method="post">
        <?php wp_nonce_field('oksia_bulk_quotes'); ?>

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="oksia_bulk_action">
                    <option value="">Bulk Actions</option>
                    <option value="confirm">Confirm</option>
                    <option value="cancel">Cancel</option>
                    <option value="delete">Delete</option>
                </select>
                <input type="submit" class="button action" value="Apply">
            </div>

            <div class="alignright">
                <select name="status_filter">
                    <option value="">All Status</option>
                    <option value="draft" <?php selected(isset($_GET['status']) && $_GET['status'] === 'draft'); ?>>Draft</option>
                    <option value="sent" <?php selected(isset($_GET['status']) && $_GET['status'] === 'sent'); ?>>Sent</option>
                    <option value="confirmed" <?php selected(isset($_GET['status']) && $_GET['status'] === 'confirmed'); ?>>Confirmed</option>
                    <option value="cancelled" <?php selected(isset($_GET['status']) && $_GET['status'] === 'cancelled'); ?>>Cancelled</option>
                </select>
                <input type="submit" class="button" value="Filter">
                <input type="text" name="search" placeholder="Search quotes..." value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>">
                <input type="submit" class="button" value="Search">
            </div>
        </div>

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
            $where .= $wpdb->prepare(" AND quote_number LIKE %s", "%$search%");
        }

        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $status = sanitize_text_field($_GET['status']);
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $quotes_table WHERE $where");
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

        <table class="wp-list-table widefat fixed striped">
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
                    <th width="120">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotes)): ?>
                    <tr><td colspan="11">No quotes found.</td></tr>
                <?php else: ?>
                    <?php foreach ($quotes as $quote):
                        $client_data = unserialize($quote->client_data);
                    ?>
                        <tr>
                            <td><input type="checkbox" name="quote_ids[]" value="<?php echo $quote->id; ?>"></td>
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
                            <td><?php echo date('d/m/Y', strtotime($quote->created_at)); ?></td>
                            <td>
                                <a href="<?php echo home_url("/quote/{$quote->share_token}"); ?>" target="_blank">View</a> |
                                <a href="#" class="edit-quote" data-id="<?php echo $quote->id; ?>">Edit</a> |
                                <a href="#" class="share-quote" data-link="<?php echo $quote->share_url; ?>">Share</a>
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
</style>

<script>
jQuery(document).ready(function($) {
    $('.share-quote').on('click', function(e) {
        e.preventDefault();
        var link = $(this).data('link');
        prompt('Share this link with client:', link);
    });
});
</script>
