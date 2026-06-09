<?php
namespace OKSIA\Frontend;

class QuoteViewer {

    public function render_viewer($token, $version = null) {
        $sharing = new \OKSIA\Quote\QuoteSharing();
        $quote = $sharing->get_shared_quote($token);

        if (!$quote) {
            return '<p>Invalid or expired quote link.</p>';
        }

        if ($version && $version != $quote->version) {
            $quote_repo = new \OKSIA\Quote\QuoteRepository();
            $quote = $quote_repo->get_by_number($quote->quote_number);

            if ($quote && $quote->version != $version) {
                // Redirect to correct version
                wp_redirect(home_url("/quote/{$token}/v{$quote->version}"));
                exit;
            }
        }

        ob_start();
        ?>
        <div class="oksia-quote-viewer">
            <div class="quote-header">
                <h1>Travel Quote</h1>
                <p>Quote #: <?php echo esc_html($quote->quote_number); ?></p>
                <p>Version: <?php echo esc_html($quote->version); ?></p>
                <p>Status: <?php echo esc_html($quote->status); ?></p>
            </div>

            <div class="client-details">
                <h2>Client Details</h2>
                <?php $client = (array)$quote->client_data; ?>
                <p><strong>Name:</strong> <?php echo esc_html($client['client_name'] ?? ''); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($client['email'] ?? ''); ?></p>
                <p><strong>Phone:</strong> <?php echo esc_html($client['phone'] ?? ''); ?></p>
                <p><strong>Destination:</strong> <?php echo esc_html($client['destination'] ?? ''); ?></p>
                <p><strong>Trip Dates:</strong> <?php echo esc_html($client['start_date'] ?? ''); ?> to <?php echo esc_html($client['end_date'] ?? ''); ?></p>
            </div>

            <?php if (!empty($quote->itinerary_data)): ?>
                <div class="itinerary">
                    <h2>Itinerary</h2>
                    <?php $itinerary = (array)$quote->itinerary_data; ?>

                    <?php if (!empty($itinerary['summary'])): ?>
                        <div class="summary">
                            <h3>Trip Summary</h3>
                            <p><?php echo esc_html($itinerary['summary']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($itinerary['important_notes'])): ?>
                        <div class="notes">
                            <h3>Important Notes</h3>
                            <p><?php echo esc_html($itinerary['important_notes']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($itinerary['days'])): ?>
                        <?php foreach ($itinerary['days'] as $day): ?>
                            <div class="day">
                                <h3>Day <?php echo esc_html($day['day']); ?>: <?php echo esc_html($day['title']); ?></h3>
                                <p><strong>Location:</strong> <?php echo esc_html($day['location']); ?></p>
                                <p><?php echo esc_html($day['description']); ?></p>
                                <p><strong>Logistics:</strong> <?php echo esc_html($day['logistics']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($itinerary['inclusions'])): ?>
                        <div class="inclusions">
                            <h3>Inclusions</h3>
                            <ul>
                                <?php foreach ($itinerary['inclusions'] as $item): ?>
                                    <li><?php echo esc_html($item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($itinerary['exclusions'])): ?>
                        <div class="exclusions">
                            <h3>Exclusions</h3>
                            <ul>
                                <?php foreach ($itinerary['exclusions'] as $item): ?>
                                    <li><?php echo esc_html($item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p>Itinerary is being prepared. Please check back later.</p>
            <?php endif; ?>

            <div class="quote-footer">
                <p>Generated on: <?php echo esc_html($quote->created_at); ?></p>
                <button onclick="window.print()" class="print-btn">Print Quote</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_confirm_action() {
        if (!isset($_POST['confirm_quote']) || !isset($_POST['quote_id'])) {
            return;
        }

        $quote_id = intval($_POST['quote_id']);
        $nonce = $_POST['nonce'] ?? '';

        if (!wp_verify_nonce($nonce, 'oksia_confirm_quote_' . $quote_id)) {
            return;
        }

        $status_manager = new \OKSIA\Quote\QuoteStatus();
        $result = $status_manager->transition($quote_id, 'confirmed');

        if ($result['success']) {
            echo '<div class="success-message">Quote confirmed successfully!</div>';
        } else {
            echo '<div class="error-message">' . esc_html($result['message']) . '</div>';
        }
    }
}
