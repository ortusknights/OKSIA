<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your Travel Quote</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; padding: 20px; background: #9b59b6; border-radius: 10px 10px 0 0;">
            <h1 style="color: white; margin: 0;">Your Travel Quote is Ready</h1>
        </div>

        <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
            <p>Dear <?php echo esc_html($client_name ?? 'Valued Customer'); ?>,</p>

            <p>Your personalized travel itinerary is now ready for review.</p>

            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Trip Summary:</h3>
                <p><strong>Destination:</strong> <?php echo esc_html($destination ?? 'TBD'); ?></p>
                <p><strong>Duration:</strong> <?php echo esc_html($duration ?? 'TBD'); ?> days</p>
                <p><strong>Travelers:</strong> <?php echo esc_html($adults ?? 0); ?> Adults</p>
            </div>

            <p>
                <a href="<?php echo esc_url($quote_url ?? '#'); ?>" style="display: inline-block; padding: 12px 25px; background: #9b59b6; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                    View Your Quote
                </a>
            </p>

            <p>To confirm your booking or request changes, please reply to this email.</p>

            <p>Thank you for choosing us for your travel needs!</p>

            <p>Best regards,<br>Travel Team</p>
        </div>

        <div style="text-align: center; padding: 20px; font-size: 12px; color: #999;">
            <p>&copy; <?php echo date('Y'); ?> OKSIA</p>
        </div>
    </div>
</body>
</html>
