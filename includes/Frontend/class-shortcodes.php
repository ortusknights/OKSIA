<?php
namespace OKSIA\Frontend;

class Shortcodes {

    public function register_all() {
        add_shortcode('oksia_client_intake', [$this, 'client_intake']);
        add_shortcode('oksia_agent_intake', [$this, 'agent_intake']);
        add_shortcode('oksia_dashboard', [$this, 'dashboard']);
        add_shortcode('oksia_quote_viewer', [$this, 'quote_viewer']);
        add_shortcode('oksia_support', [$this, 'support']);
        add_shortcode('oksia_agency_registration', [$this, 'agency_registration']);
    }

    public function client_intake() {
        $intake = new \OKSIA\Intake\ClientIntake();
        return $intake->render_form();
    }

    public function agent_intake() {
        $intake = new \OKSIA\Intake\AgentIntake();
        return $intake->render_form();
    }

    public function dashboard() {
        $dashboard = new Dashboard();
        return $dashboard->render_dashboard();
    }

    public function quote_viewer($atts) {
        $atts = shortcode_atts([
            'token' => '',
            'version' => null
        ], $atts);

        if (empty($atts['token'])) {
            // Try to get from URL
            $url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if (preg_match('/\/quote\/([a-f0-9]+)\/v?(\d+)?/', $url_path, $matches)) {
                $atts['token'] = $matches[1];
                $atts['version'] = $matches[2] ?? null;
            }
        }

        if (empty($atts['token'])) {
            return '<p>Invalid quote link.</p>';
        }

        $viewer = new QuoteViewer();
        return $viewer->render_viewer($atts['token'], $atts['version']);
    }

    public function support() {
        ob_start();
        ?>
        <div class="oksia-support">
            <h2>Support Center</h2>
            <form id="oksia-support-form">
                <input type="text" name="subject" placeholder="Subject" required>
                <textarea name="message" placeholder="Describe your issue" required></textarea>
                <button type="submit">Submit Ticket</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function agency_registration() {
        ob_start();
        ?>
        <div class="oksia-registration">
            <h2>Register Your Agency</h2>
            <form id="oksia-registration-form">
                <input type="text" name="agency_name" placeholder="Agency Name" required>
                <input type="email" name="admin_email" placeholder="Admin Email" required>
                <input type="text" name="admin_name" placeholder="Admin Name" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Register Agency</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
