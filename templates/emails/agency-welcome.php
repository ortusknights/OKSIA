<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to OKSIA</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; padding: 20px; background: #2c3e50; border-radius: 10px 10px 0 0;">
            <h1 style="color: white; margin: 0;">Welcome to OKSIA</h1>
        </div>

        <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
            <p>Dear <?php echo esc_html($admin_name ?? 'Agency Admin'); ?>,</p>

            <p>Your agency <strong><?php echo esc_html($agency_name ?? ''); ?></strong> has been successfully registered with OKSIA.</p>

            <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Agency Details:</h3>
                <p><strong>Agency Code:</strong> <?php echo esc_html($agency_code ?? ''); ?></p>
                <p><strong>Login Email:</strong> <?php echo esc_html($admin_email ?? ''); ?></p>
                <p><strong>Dashboard URL:</strong> <a href="<?php echo home_url('/agency-dashboard'); ?>"><?php echo home_url('/agency-dashboard'); ?></a></p>
            </div>

            <p>Our team will review your registration and activate your account shortly.</p>

            <p>If you have any questions, please contact our support team.</p>

            <p>Best regards,<br>OKSIA Team</p>
        </div>

        <div style="text-align: center; padding: 20px; font-size: 12px; color: #999;">
            <p>&copy; <?php echo date('Y'); ?> OKSIA - Ortus Knights Structured Itinerary Agent</p>
        </div>
    </div>
</body>
</html>
