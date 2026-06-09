<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Quote Created</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; padding: 20px; background: #3498db; border-radius: 10px 10px 0 0;">
            <h1 style="color: white; margin: 0;">New Quote Created</h1>
        </div>

        <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
            <p>A new travel quote has been created.</p>

            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Quote Details:</h3>
                <p><strong>Quote #:</strong> <?php echo esc_html($quote_number ?? 'N/A'); ?></p>
                <p><strong>Client Name:</strong> <?php echo esc_html($client_name ?? 'N/A'); ?></p>
                <p><strong>Destination:</strong> <?php echo esc_html($destination ?? 'N/A'); ?></p>
                <p><strong>Trip Dates:</strong> <?php echo esc_html($start_date ?? ''); ?> to <?php echo esc_html($end_date ?? ''); ?></p>
            </div>

            <p>
                <a href="<?php echo home_url("/quote/{$share_token}"); ?>" style="display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;">
                    View Quote
                </a>
            </p>

            <p>Best regards,<br>OKSIA Team</p>
        </div>

        <div style="text-align: center; padding: 20px; font-size: 12px; color: #999;">
            <p>&copy; <?php echo date('Y'); ?> OKSIA</p>
        </div>
    </div>
</body>
</html>
