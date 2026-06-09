<?php
namespace OKSIA\Services;

class EmailService {

    private $use_smtp;
    private $smtp_host;
    private $smtp_port;
    private $smtp_encryption;
    private $smtp_username;
    private $smtp_password;

    public function __construct() {
        $this->use_smtp = get_option('oksia_enable_smtp', 0);
        $this->smtp_host = get_option('oksia_smtp_host', '');
        $this->smtp_port = get_option('oksia_smtp_port', 587);
        $this->smtp_encryption = get_option('oksia_smtp_encryption', 'tls');
        $this->smtp_username = get_option('oksia_smtp_username', '');
        $this->smtp_password = get_option('oksia_smtp_password', '');

        if ($this->use_smtp) {
            $this->configure_smtp();
        }
    }

    private function configure_smtp() {
        add_action('phpmailer_init', function($phpmailer) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $this->smtp_host;
            $phpmailer->Port = $this->smtp_port;
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $this->smtp_username;
            $phpmailer->Password = $this->smtp_password;
            $phpmailer->SMTPSecure = $this->smtp_encryption;
        });
    }

    public function send_quote_created($to_email, $quote_data) {
        $subject = 'New Quote Created - ' . ($quote_data['quote_number'] ?? '');

        $template_path = OKSIA_PLUGIN_DIR . 'templates/emails/quote-created.php';
        $message = $this->load_template($template_path, $quote_data);

        return $this->send($to_email, $subject, $message);
    }

    public function send_quote_confirmed($to_email, $quote_data) {
        $subject = 'Quote Confirmed - ' . ($quote_data['quote_number'] ?? '');

        $template_path = OKSIA_PLUGIN_DIR . 'templates/emails/quote-confirmed.php';
        $message = $this->load_template($template_path, $quote_data);

        return $this->send($to_email, $subject, $message);
    }

    public function send_quote_shared($to_email, $quote_data) {
        $subject = 'Your Travel Quote is Ready';

        $template_path = OKSIA_PLUGIN_DIR . 'templates/emails/quote-shared.php';
        $message = $this->load_template($template_path, $quote_data);

        return $this->send($to_email, $subject, $message);
    }

    public function send_agency_welcome($to_email, $agency_data) {
        $subject = 'Welcome to OKSIA - Agency Registration';

        $template_path = OKSIA_PLUGIN_DIR . 'templates/emails/agency-welcome.php';
        $message = $this->load_template($template_path, $agency_data);

        return $this->send($to_email, $subject, $message);
    }

    private function load_template($template_path, $data) {
        if (!file_exists($template_path)) {
            return $this->get_fallback_message($data);
        }

        ob_start();
        extract($data);
        include $template_path;
        return ob_get_clean();
    }

    private function send($to, $subject, $message) {
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail($to, $subject, $message, $headers);
    }

    private function get_fallback_message($data) {
        $quote_number = $data['quote_number'] ?? 'N/A';

        return "<html><body>
            <h2>Quote Notification</h2>
            <p>Quote #: {$quote_number}</p>
            <p>Thank you for choosing OKSIA.</p>
        </body></html>";
    }
}
