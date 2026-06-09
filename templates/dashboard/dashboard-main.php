<?php
/**
 * Agencies List Template
 *
 * @package OKSIA
 */

if (!defined('ABSPATH')) {
    exit;
}

// Security: Ensure only super admins can see this
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'oksia'));
}

$agencies = $this->get_agencies();
$pagination = $this->get_pagination();
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
?>

<div class="wrap oksia-agencies-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Agencies', 'oksia'); ?></h1>
    <a href="#" class="page-title-action" id="oksia-add-agency"><?php esc_html_e('Add New', 'oksia'); ?></a>

    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Agencies updated successfully.', 'oksia'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="oksia-agencies-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="oksia-agencies">

            <div class="filter-group">
                <label for="status"><?php esc_html_e('Status:', 'oksia'); ?></label>
                <select name="status" id="status">
                    <option value=""><?php esc_html_e('All', 'oksia'); ?></option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active', 'oksia'); ?></option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php esc_html_e('Pending', 'oksia'); ?></option>
                    <option value="suspended" <?php selected($status_filter, 'suspended'); ?>><?php esc_html_e('Suspended', 'oksia'); ?></option>
                </select>
            </div>

            <div class="filter-group">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by name or code...', 'oksia'); ?>">
            </div>

            <button type="submit" class="button"><?php esc_html_e('Filter', 'oksia'); ?></button>
            <a href="?page=oksia-agencies" class="button"><?php esc_html_e('Reset', 'oksia'); ?></a>
        </form>
    </div>

    <!-- Bulk Actions Form -->
    <form method="post" action="" id="oksia-agencies-bulk-form">
        <?php wp_nonce_field('oksia_agency_bulk_action', 'oksia_agency_nonce'); ?>

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="bulk_action">
                    <option value=""><?php esc_html_e('Bulk Actions', 'oksia'); ?></option>
                    <option value="activate"><?php esc_html_e('Activate', 'oksia'); ?></option>
                    <option value="suspend"><?php esc_html_e('Suspend', 'oksia'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete Permanently', 'oksia'); ?></option>
                </select>
                <button type="submit" class="button action"><?php esc_html_e('Apply', 'oksia'); ?></button>
            </div>

            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php echo sprintf(esc_html(_n('%s agency', '%s agencies', $pagination['total'], 'oksia')), number_format_i18n($pagination['total'])); ?>
                </span>

                <?php if ($pagination['total_pages'] > 1) : ?>
                    <div class="pagination-links">
                        <?php
                        $current_page = $pagination['current_page'];
                        $total_pages = $pagination['total_pages'];
                        $base_url = remove_query_arg('paged');

                        if ($current_page > 1) {
                            echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '">‹</a>';
                        }

                        for ($i = 1; $i <= $total_pages; $i++) {
                            if ($i === $current_page) {
                                echo '<span class="current-page">' . $i . '</span>';
                            } else {
                                echo '<a class="page-numbers button" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . $i . '</a>';
                            }
                        }

                        if ($current_page < $total_pages) {
                            echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '">›</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Agencies Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="40"><input type="checkbox" id="select-all"></th>
                    <th><?php esc_html_e('ID', 'oksia'); ?></th>
                    <th><?php esc_html_e('Agency Name', 'oksia'); ?></th>
                    <th><?php esc_html_e('Code', 'oksia'); ?></th>
                    <th><?php esc_html_e('Slug', 'oksia'); ?></th>
                    <th><?php esc_html_e('Status', 'oksia'); ?></th>
                    <th><?php esc_html_e('Quotes', 'oksia'); ?></th>
                    <th><?php esc_html_e('Users', 'oksia'); ?></th>
                    <th><?php esc_html_e('Created', 'oksia'); ?></th>
                    <th width="100"><?php esc_html_e('Actions', 'oksia'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agencies)) : ?>
                    <tr>
                        <td colspan="10" style="text-align: center;">
                            <?php esc_html_e('No agencies found.', 'oksia'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($agencies as $agency) : ?>
                        <tr>
                            <td><input type="checkbox" name="agency_ids[]" value="<?php echo esc_attr($agency->id); ?>"></td>
                            <td><?php echo intval($agency->id); ?></td>
                            <td>
                                <strong><?php echo esc_html($agency->name); ?></strong>
                            </td>
                            <td><code><?php echo esc_html($agency->code); ?></code></td>
                            <td><code><?php echo esc_html($agency->slug); ?></code></td>
                            <td><?php echo \OKSIA\Admin\AgencyList::status_badge($agency->status); ?></td>
                            <td><?php echo number_format_i18n(\OKSIA\Admin\AgencyList::get_quote_count($agency->id)); ?></td>
                            <td><?php echo number_format_i18n(\OKSIA\Admin\AgencyList::get_user_count($agency->id)); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($agency->created_at))); ?></td>
                            <td>
                                <a href="#" class="edit-agency" data-id="<?php echo esc_attr($agency->id); ?>"><?php esc_html_e('Edit', 'oksia'); ?></a>
                                |
                                <a href="#" class="delete-agency" data-id="<?php echo esc_attr($agency->id); ?>" data-name="<?php echo esc_attr($agency->name); ?>"><?php esc_html_e('Delete', 'oksia'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions">
                <select name="bulk_action_bottom">
                    <option value=""><?php esc_html_e('Bulk Actions', 'oksia'); ?></option>
                    <option value="activate"><?php esc_html_e('Activate', 'oksia'); ?></option>
                    <option value="suspend"><?php esc_html_e('Suspend', 'oksia'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete Permanently', 'oksia'); ?></option>
                </select>
                <button type="submit" class="button action"><?php esc_html_e('Apply', 'oksia'); ?></button>
            </div>
        </div>
    </form>
</div>

<script>
jQuery(function($) {
    // Select all checkbox
    $('#select-all').on('change', function() {
        $('input[name="agency_ids[]"]').prop('checked', this.checked);
    });

    // Delete confirmation
    $('.delete-agency').on('click', function(e) {
        e.preventDefault();
        var name = $(this).data('name');
        if (confirm('<?php echo esc_js(__('Are you sure you want to delete agency: ', 'oksia')); ?>' + name + '?\n\n<?php echo esc_js(__('This will permanently delete ALL quotes, users, and data associated with this agency. This cannot be undone!', 'oksia')); ?>')) {
            $('#oksia-agencies-bulk-form').append('<input type="hidden" name="bulk_action" value="delete">');
            $('#oksia-agencies-bulk-form').append('<input type="hidden" name="agency_ids[]" value="' + $(this).data('id') + '">');
            $('#oksia-agencies-bulk-form').submit();
        }
    });

    // Edit agency (placeholder - will implement later)
    $('.edit-agency').on('click', function(e) {
        e.preventDefault();
        alert('<?php echo esc_js(__('Agency edit feature coming soon.', 'oksia')); ?>');
    });

    // Add new agency
    $('#oksia-add-agency').on('click', function(e) {
        e.preventDefault();
        alert('<?php echo esc_js(__('Add agency feature coming soon. Use registration form for now.', 'oksia')); ?>');
    });
});
</script>

<style>
.oksia-agencies-wrap .filter-group {
    display: inline-block;
    margin-right: 10px;
    vertical-align: middle;
}

.oksia-agencies-wrap .filter-group select,
.oksia-agencies-wrap .filter-group input {
    vertical-align: middle;
}

.oksia-status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.oksia-status-active {
    background: #d4edda;
    color: #155724;
}

.oksia-status-pending {
    background: #fff3cd;
    color: #856404;
}

.oksia-status-suspended {
    background: #f8d7da;
    color: #721c24;
}

.tablenav .pagination-links {
    display: inline-block;
    margin-left: 10px;
}

.tablenav .pagination-links a,
.tablenav .pagination-links span {
    display: inline-block;
    padding: 4px 8px;
    margin: 0 2px;
}
</style>
