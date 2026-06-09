<?php
namespace OKSIA\Admin;

class AdminMenu {

    public function register_menus() {
        add_menu_page(
            'OKSIA Dashboard',
            'OKSIA',
            'manage_options',
            'oksia',
            [$this, 'render_dashboard'],
            'dashicons-airplane',
            25
        );

        add_submenu_page(
            'oksia',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'oksia',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'oksia',
            'Agencies',
            'Agencies',
            'manage_options',
            'oksia-agencies',
            [$this, 'render_agencies']
        );

        add_submenu_page(
            'oksia',
            'Quotes',
            'Quotes',
            'manage_options',
            'oksia-quotes',
            [$this, 'render_quotes']
        );

        add_submenu_page(
            'oksia',
            'Analytics',
            'Analytics',
            'manage_options',
            'oksia-analytics',
            [$this, 'render_analytics']
        );

        add_submenu_page(
            'oksia',
            'System Status',
            'System Status',
            'manage_options',
            'oksia-system',
            [$this, 'render_system_status']
        );

        add_submenu_page(
            'oksia',
            'Settings',
            'Settings',
            'manage_options',
            'oksia-settings',
            [$this, 'render_settings']
        );
    }

    public function render_dashboard() {
        global $wpdb;
        $agencies_table = $wpdb->prefix . 'oksia_agencies';
        $quotes_table = $wpdb->prefix . 'oksia_quotes';

        $total_agencies = $wpdb->get_var("SELECT COUNT(*) FROM $agencies_table");
        $active_agencies = $wpdb->get_var("SELECT COUNT(*) FROM $agencies_table WHERE status = 'active'");
        $pending_agencies = $wpdb->get_var("SELECT COUNT(*) FROM $agencies_table WHERE status = 'pending'");
        $total_quotes = $wpdb->get_var("SELECT COUNT(*) FROM $quotes_table");
        $confirmed_quotes = $wpdb->get_var("SELECT COUNT(*) FROM $quotes_table WHERE status = 'confirmed'");
        $recent_quotes = $wpdb->get_results("SELECT * FROM $quotes_table ORDER BY created_at DESC LIMIT 10");
        ?>
        <div class="wrap oksia-admin-wrap">
            <h1>OKSIA Dashboard</h1>

            <div class="oksia-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🏢</div>
                    <div class="stat-number"><?php echo $total_agencies; ?></div>
                    <div class="stat-label">Total Agencies</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon">✅</div>
                    <div class="stat-number"><?php echo $active_agencies; ?></div>
                    <div class="stat-label">Active Agencies</div>
                </div>
                <div class="stat-card yellow">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-number"><?php echo $pending_agencies; ?></div>
                    <div class="stat-label">Pending Approval</div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-icon">📋</div>
                    <div class="stat-number"><?php echo $total_quotes; ?></div>
                    <div class="stat-label">Total Quotes</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon">🎉</div>
                    <div class="stat-number"><?php echo $confirmed_quotes; ?></div>
                    <div class="stat-label">Confirmed Quotes</div>
                </div>
            </div>

            <div class="oksia-recent">
                <h2>Recent Quotes</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Quote #</th>
                            <th>Agency</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_quotes): ?>
                            <?php foreach ($recent_quotes as $quote):
                                $agency = $wpdb->get_row($wpdb->prepare("SELECT name FROM $agencies_table WHERE id = %d", $quote->agency_id));
                                $client_data = unserialize($quote->client_data);
                            ?>
                            <tr>
                                <td><code><?php echo esc_html($quote->quote_number); ?></code></td>
                                <td><?php echo esc_html($agency->name ?? 'N/A'); ?></td>
                                <td><span class="status-badge status-<?php echo $quote->status; ?>"><?php echo ucfirst($quote->status); ?></span></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($quote->created_at)); ?></td>
                                <td><a href="admin.php?page=oksia-quotes&view=<?php echo $quote->id; ?>">View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No quotes found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        .oksia-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-top: 4px solid #3498db;
        }
        .stat-card.green { border-top-color: #27ae60; }
        .stat-card.yellow { border-top-color: #f39c12; }
        .stat-card.blue { border-top-color: #3498db; }
        .stat-card.purple { border-top-color: #9b59b6; }
        .stat-icon { font-size: 32px; margin-bottom: 10px; }
        .stat-number { font-size: 32px; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; margin-top: 5px; }
        .oksia-recent { background: white; padding: 20px; border-radius: 12px; margin-top: 20px; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .status-draft { background: #e2e3e5; color: #383d41; }
        .status-sent { background: #d1ecf1; color: #0c5460; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        </style>
        <?php
    }

    public function render_agencies() {
        include OKSIA_PLUGIN_DIR . 'templates/admin/agency-list.php';
    }

    public function render_quotes() {
        include OKSIA_PLUGIN_DIR . 'templates/admin/quote-list.php';
    }

    public function render_analytics() {
        global $wpdb;
        $quotes_table = $wpdb->prefix . 'oksia_quotes';

        // Get monthly stats
        $monthly_stats = $wpdb->get_results("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed
            FROM $quotes_table
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ");
        ?>
        <div class="wrap">
            <h1>Analytics</h1>

            <div class="analytics-section">
                <h2>Quote Trends (Last 6 Months)</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr><th>Month</th><th>Total Quotes</th><th>Confirmed</th><th>Conversion Rate</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_stats as $stat): ?>
                            <tr>
                                <td><?php echo date('F Y', strtotime($stat->month . '-01')); ?></td>
                                <td><?php echo $stat->total; ?></td>
                                <td><?php echo $stat->confirmed; ?></td>
                                <td><?php echo $stat->total > 0 ? round(($stat->confirmed / $stat->total) * 100, 1) : 0; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <style>
        .analytics-section { background: white; padding: 20px; border-radius: 12px; margin-top: 20px; }
        </style>
        <?php
    }

    public function render_system_status() {
        ?>
        <div class="wrap">
            <h1>System Status</h1>

            <div class="system-status">
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Check</th><th>Status</th><th>Info</th></tr></thead>
                    <tbody>
                        <tr>
                            <td>WordPress Version</td>
                            <td><span class="status-ok">✅ OK</span></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td>PHP Version</td>
                            <td><?php echo version_compare(PHP_VERSION, '7.4', '>=') ? '<span class="status-ok">✅ OK</span>' : '<span class="status-error">❌ Required 7.4+</span>'; ?></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td>Database Tables</td>
                            <td><?php
                                global $wpdb;
                                $tables = [$wpdb->prefix . 'oksia_agencies', $wpdb->prefix . 'oksia_quotes'];
                                $all_exist = true;
                                foreach ($tables as $table) {
                                    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) $all_exist = false;
                                }
                                echo $all_exist ? '<span class="status-ok">✅ OK</span>' : '<span class="status-error">❌ Missing</span>';
                            ?></td>
                            <td>Tables created</td>
                        </tr>
                        <tr>
                            <td>OpenAI API Key</td>
                            <td><?php echo get_option('oksia_openai_api_key') ? '<span class="status-ok">✅ Configured</span>' : '<span class="status-warning">⚠️ Not set</span>'; ?></td>
                            <td>Required for AI itinerary generation</td>
                        </tr>
                        <tr>
                            <td>Upload Directory</td>
                            <td><?php echo is_dir(OKSIA_UPLOAD_DIR) ? '<span class="status-ok">✅ OK</span>' : '<span class="status-error">❌ Missing</span>'; ?></td>
                            <td><?php echo OKSIA_UPLOAD_DIR; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <style>
        .status-ok { color: #27ae60; }
        .status-warning { color: #f39c12; }
        .status-error { color: #e74c3c; }
        .system-status { margin-top: 20px; }
        </style>
        <?php
    }

    public function render_settings() {
        include OKSIA_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }
}
