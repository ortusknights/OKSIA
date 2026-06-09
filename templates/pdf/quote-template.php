<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Travel Quote - <?php echo esc_html($quote->quote_number ?? 'N/A'); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            font-size: 12px;
        }

        .container {
            max-width: 100%;
            padding: 30px;
        }

        /* Header */
        .header {
            text-align: center;
            border-bottom: 3px solid #3498db;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .quote-number {
            font-size: 14px;
            color: #7f8c8d;
        }

        /* Sections */
        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #3498db;
            border-left: 4px solid #3498db;
            padding-left: 10px;
            margin-bottom: 15px;
        }

        /* Client Info */
        .client-info-grid {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .info-row {
            display: flex;
        }

        .info-label {
            font-weight: bold;
            width: 100px;
        }

        .info-value {
            flex: 1;
        }

        /* Day Cards */
        .day-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        .day-title {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .day-location {
            color: #3498db;
            font-size: 11px;
            margin-bottom: 10px;
        }

        .day-description {
            margin-bottom: 10px;
        }

        .day-logistics {
            font-size: 11px;
            color: #7f8c8d;
            font-style: italic;
        }

        /* Lists */
        .inclusions-list, .exclusions-list {
            margin: 10px 0;
            padding-left: 20px;
        }

        .inclusions-list li {
            color: #27ae60;
        }

        .exclusions-list li {
            color: #e74c3c;
        }

        /* Important Notes */
        .important-notes {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #999;
        }

        /* Page Break */
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Travel Itinerary & Quote</h1>
            <div class="quote-number">Quote #: <?php echo esc_html($quote->quote_number ?? 'N/A'); ?></div>
            <div class="quote-number">Version: <?php echo esc_html($quote->version ?? 1); ?></div>
        </div>

        <!-- Client Information -->
        <div class="section">
            <div class="section-title">Client Information</div>
            <div class="client-info-grid">
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo esc_html($client->client_name ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo esc_html($client->email ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo esc_html($client->phone ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Destination:</span>
                    <span class="info-value"><?php echo esc_html($client->destination ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Trip Type:</span>
                    <span class="info-value"><?php echo esc_html($client->trip_type ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Travelers:</span>
                    <span class="info-value">
                        <?php
                        $adults = $client->adults ?? 0;
                        $children = $client->children ?? 0;
                        $infants = $client->infants ?? 0;
                        echo "{$adults} Adults";
                        if ($children) echo ", {$children} Children";
                        if ($infants) echo ", {$infants} Infants";
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Dates:</span>
                    <span class="info-value"><?php echo esc_html($client->start_date ?? ''); ?> to <?php echo esc_html($client->end_date ?? ''); ?></span>
                </div>
            </div>
        </div>

        <!-- Trip Summary -->
        <?php if (!empty($itinerary->summary)): ?>
        <div class="section">
            <div class="section-title">Trip Summary</div>
            <p><?php echo nl2br(esc_html($itinerary->summary)); ?></p>
        </div>
        <?php endif; ?>

        <!-- Itinerary Days -->
        <?php if (!empty($itinerary->days)): ?>
        <div class="section">
            <div class="section-title">Detailed Itinerary</div>
            <?php foreach ($itinerary->days as $day): ?>
            <div class="day-card">
                <div class="day-title">Day <?php echo esc_html($day['day']); ?>: <?php echo esc_html($day['title']); ?></div>
                <div class="day-location">📍 <?php echo esc_html($day['location']); ?></div>
                <div class="day-description"><?php echo nl2br(esc_html($day['description'])); ?></div>
                <div class="day-logistics">🚗 Logistics: <?php echo esc_html($day['logistics']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Inclusions & Exclusions -->
        <div class="section">
            <div class="section-title">What's Included & Excluded</div>

            <?php if (!empty($itinerary->inclusions)): ?>
            <div style="margin-bottom: 20px;">
                <strong style="color: #27ae60;">✓ Included:</strong>
                <ul class="inclusions-list">
                    <?php foreach ($itinerary->inclusions as $item): ?>
                    <li><?php echo esc_html($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($itinerary->exclusions)): ?>
            <div>
                <strong style="color: #e74c3c;">✗ Excluded:</strong>
                <ul class="exclusions-list">
                    <?php foreach ($itinerary->exclusions as $item): ?>
                    <li><?php echo esc_html($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- Important Notes -->
        <?php if (!empty($itinerary->important_notes)): ?>
        <div class="important-notes">
            <strong>📋 Important Notes:</strong><br>
            <?php echo nl2br(esc_html($itinerary->important_notes)); ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <p>Generated on: <?php echo date('d/m/Y H:i'); ?></p>
            <p>This is a computer-generated document. Terms and conditions apply.</p>
            <p>&copy; <?php echo date('Y'); ?> OKSIA - Ortus Knights Structured Itinerary Agent</p>
        </div>
    </div>
</body>
</html>
