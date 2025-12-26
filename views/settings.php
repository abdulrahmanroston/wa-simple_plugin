<?php if (!defined('ABSPATH')) exit; 
$settings = get_option('wa_simple_settings', array());
?>

<div class="wrap">
    <h1>WA Queue Settings</h1>
    
    <form method="post">
        <?php wp_nonce_field('wa_settings'); ?>
        
        <table class="form-table">
            <tr>
                <th><label for="api_key">API Key</label></th>
                <td>
                    <input type="text" id="api_key" name="api_key" 
                           value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" 
                           class="regular-text" required>
                    <p class="description">Get from wasenderapi.com</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="interval">Send Interval (seconds)</label></th>
                <td>
                    <input type="number" id="interval" name="interval" 
                           value="<?php echo esc_attr($settings['interval'] ?? 6); ?>" 
                           min="1" max="60" required>
                    <p class="description">Time between messages (recommended: 6)</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="retention_days">Log Retention (days)</label></th>
                <td>
                    <input type="number" id="retention_days" name="retention_days" 
                           value="<?php echo esc_attr($settings['retention_days'] ?? 30); ?>" 
                           min="0" max="365">
                    <p class="description">Delete logs older than this (0 = never)</p>
                </td>
            </tr>
        </table>
        
        <input type="hidden" name="wa_save" value="1">
        <?php submit_button(); ?>
    </form>
</div>
