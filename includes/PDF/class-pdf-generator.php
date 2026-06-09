<?php
namespace OKSIA\PDF;

class PDFGenerator {

    private $method;
    private $template_engine;

    public function __construct() {
        $this->method = get_option('oksia_pdf_method', 'chrome');
        $this->template_engine = new TemplateEngine();

        // Load DOMPDF if method is dompdf
        if ($this->method === 'dompdf' && class_exists('Dompdf\Dompdf')) {
            require_once OKSIA_PLUGIN_DIR . 'vendor/autoload.php';
        }
    }

    public function generate($quote_data, $output = 'inline') {
        do_action('oksia_before_pdf_generate', $quote_data['id'] ?? null);

        $html = $this->template_engine->render($quote_data);

        if ($this->method === 'chrome') {
            $pdf_content = $this->generate_with_chrome($html);
        } else {
            $pdf_content = $this->generate_with_dompdf($html);
        }

        if (!$pdf_content) {
            return false;
        }

        $filename = $this->get_filename($quote_data);

        do_action('oksia_after_pdf_generate', $quote_data['id'] ?? null, $filename);

        return $this->output_pdf($pdf_content, $filename, $output);
    }

    private function generate_with_dompdf($html) {
        if (!class_exists('Dompdf\Dompdf')) {
            $this->install_dompdf();
            return false;
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper(get_option('oksia_pdf_page_size', 'A4'), 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function generate_with_chrome($html) {
        $chrome_path = get_option('oksia_chrome_path', '');

        if (empty($chrome_path)) {
            $chrome_path = $this->detect_chrome_path();
        }

        if (!file_exists($chrome_path)) {
            return $this->generate_with_dompdf($html);
        }

        $temp_html = OKSIA_UPLOAD_DIR . 'temp_' . uniqid() . '.html';
        $temp_pdf = OKSIA_UPLOAD_DIR . 'temp_' . uniqid() . '.pdf';

        file_put_contents($temp_html, $html);

        $command = escapeshellcmd($chrome_path) . ' --headless --disable-gpu --print-to-pdf=' . escapeshellarg($temp_pdf) . ' ' . escapeshellarg($temp_html);

        exec($command);

        $pdf_content = file_exists($temp_pdf) ? file_get_contents($temp_pdf) : false;

        @unlink($temp_html);
        @unlink($temp_pdf);

        return $pdf_content;
    }

    private function detect_chrome_path() {
        $paths = [
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe'
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return '';
    }

    private function install_dompdf() {
        $composer_path = OKSIA_PLUGIN_DIR . 'composer.json';
        if (!file_exists($composer_path)) {
            $composer_content = '{"require":{"dompdf/dompdf":"^2.0"}}';
            file_put_contents($composer_path, $composer_content);
        }
    }

    private function get_filename($quote_data) {
        $client_name = $quote_data['client_data']['client_name'] ?? 'quote';
        $quote_number = $quote_data['quote_number'] ?? 'unknown';

        return sanitize_title($client_name) . '-' . $quote_number . '.pdf';
    }

    private function output_pdf($content, $filename, $output) {
        header('Content-Type: application/pdf');

        if ($output === 'attachment') {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        }

        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    }
}
