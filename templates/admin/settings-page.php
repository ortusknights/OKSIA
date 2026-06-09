<div class="wrap">
    <h1>OKSIA Settings</h1>

    <form method="post" action="options.php">
        <?php settings_fields('oksia_settings'); ?>

        <div class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active">General</a>
            <a href="#openai" class="nav-tab">OpenAI</a>
            <a href="#pdf" class="nav-tab">PDF Settings</a>
            <a href="#smtp" class="nav-tab">SMTP</a>
        </div>

        <div id="general" class="settings-tab active">
            <table class="form-table">
                <tr>
                    <th>Base Currency</th>
                    <td>
                        <input type="text" name="oksia_base_currency" value="<?php echo esc_attr(get_option('oksia_base_currency', 'INR')); ?>" class="regular-text">
                        <p class="description">Example: USD, EUR, INR, GBP</p>
                    </td>
                </tr>
                <tr>
                    <th>Allow Public Registration</th>
                    <td>
                        <input type="checkbox" name="oksia_allow_public_registration" value="1" <?php checked(get_option('oksia_allow_public_registration', 1)); ?>>
                        <label>Allow agencies to register publicly</label>
                    </td>
                </tr>
            </table>
        </div>

        <div id="openai" class="settings-tab" style="display:none">
            <table class="form-table">
                <tr>
                    <th>OpenAI API Key</th>
                    <td>
                        <input type="password" name="oksia_openai_api_key" value="<?php echo esc_attr(get_option('oksia_openai_api_key', '')); ?>" class="regular-text">
                        <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p>
                    </td>
                </tr>
                <tr>
                    <th>AI Model</th>
                    <td>
                        <select name="oksia_openai_model">
                            <option value="gpt-4.1-mini" <?php selected(get_option('oksia_openai_model'), 'gpt-4.1-mini'); ?>>GPT-4.1 Mini</option>
                            <option value="gpt-4o" <?php selected(get_option('oksia_openai_model'), 'gpt-4o'); ?>>GPT-4o</option>
                            <option value="gpt-4-turbo" <?php selected(get_option('oksia_openai_model'), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div id="pdf" class="settings-tab" style="display:none">
            <table class="form-table">
                <tr>
                    <th>PDF Generation Method</th>
                    <td>
                        <select name="oksia_pdf_method">
                            <option value="dompdf" <?php selected(get_option('oksia_pdf_method'), 'dompdf'); ?>>DOMPDF (PHP)</option>
                            <option value="chrome" <?php selected(get_option('oksia_pdf_method'), 'chrome'); ?>>Chrome/Chromium (Headless)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Chrome Path (if using Chrome)</th>
                    <td>
                        <input type="text" name="oksia_chrome_path" value="<?php echo esc_attr(get_option('oksia_chrome_path', '')); ?>" class="regular-text">
                        <p class="description">Example: /usr/bin/google-chrome or C:\Program Files\Google\Chrome\Application\chrome.exe</p>
                    </td>
                </tr>
                <tr>
                    <th>PDF Page Size</th>
                    <td>
                        <select name="oksia_pdf_page_size">
                            <option value="A4" <?php selected(get_option('oksia_pdf_page_size'), 'A4'); ?>>A4</option>
                            <option value="Letter" <?php selected(get_option('oksia_pdf_page_size'), 'Letter'); ?>>Letter</option>
                            <option value="Legal" <?php selected(get_option('oksia_pdf_page_size'), 'Legal'); ?>>Legal</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div id="smtp" class="settings-tab" style="display:none">
            <table class="form-table">
                <tr>
                    <th>Enable SMTP</th>
                    <td>
                        <input type="checkbox" name="oksia_enable_smtp" value="1" <?php checked(get_option('oksia_enable_smtp', 0)); ?>>
                        <label>Use SMTP for email delivery</label>
                    </td>
                </tr>
                <tr>
                    <th>SMTP Host</th>
                    <td><input type="text" name="oksia_smtp_host" value="<?php echo esc_attr(get_option('oksia_smtp_host', '')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>SMTP Port</th>
                    <td><input type="text" name="oksia_smtp_port" value="<?php echo esc_attr(get_option('oksia_smtp_port', 587)); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Encryption</th>
                    <td>
                        <select name="oksia_smtp_encryption">
                            <option value="tls" <?php selected(get_option('oksia_smtp_encryption'), 'tls'); ?>>TLS</option>
                            <option value="ssl" <?php selected(get_option('oksia_smtp_encryption'), 'ssl'); ?>>SSL</option>
                            <option value="none" <?php selected(get_option('oksia_smtp_encryption'), 'none'); ?>>None</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>SMTP Username</th>
                    <td><input type="text" name="oksia_smtp_username" value="<?php echo esc_attr(get_option('oksia_smtp_username', '')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>SMTP Password</th>
                    <td><input type="password" name="oksia_smtp_password" value="<?php echo esc_attr(get_option('oksia_smtp_password', '')); ?>" class="regular-text"></td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href').substring(1);
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.settings-tab').hide();
        $('#' + target).show();
    });
});
</script>
