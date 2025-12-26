<?php
/**
 * Plugin Name: WA Simple Queue
 * Description: Simple WhatsApp message queue manager - Easy integration with any plugin
 * Version: 1.0.0
 * Author: Abdulrahman Roston
 * Author URI: https://abdulrahmanroston.com
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('WA_VERSION', '1.0.0');
define('WA_PATH', plugin_dir_path(__FILE__));
define('WA_URL', plugin_dir_url(__FILE__));

/**
 * Main Class
 */
class WA_Simple {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_files();
        $this->init_hooks();
    }
    
    private function load_files() {
        require_once WA_PATH . 'includes/class-queue.php';
        require_once WA_PATH . 'includes/class-sender.php';
        require_once WA_PATH . 'includes/class-wa-order-invoices.php';
        
        if (is_admin()) {
            require_once WA_PATH . 'includes/class-admin.php';
        }
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array('WA_Queue', 'activate'));
        add_action('plugins_loaded', array($this, 'init'));
    }
    // داخل WA_Simple::init_hooks()


    
    public function init() {
        WA_Queue::instance();
        WA_Sender::instance();
        // New: Order invoices
        WA_Order_Invoices::instance();

        if (is_admin()) {
            WA_Admin::instance();
        }
    }
    
    // Helper: Get option
    public static function option($key, $default = '') {
        $options = get_option('wa_simple_settings', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    // Helper: Update option
    public static function update_option($key, $value) {
        $options = get_option('wa_simple_settings', array());
        $options[$key] = $value;
        update_option('wa_simple_settings', $options);
    }
}

// Initialize
WA_Simple::instance();

/**
 * ========================================
 * PUBLIC API - Simple function for integration
 * ========================================
 */

/**
 * Send WhatsApp message (add to queue)
 * 
 * Usage:
 * wa_send('+201234567890', 'Hello World!');
 * 
 * OR with options:
 * wa_send('+201234567890', 'Hello!', array(
 *     'priority' => 'urgent',
 *     'metadata' => array('order_id' => 123)
 * ));
 * 
 * @param string $phone Phone number with country code
 * @param string $message Message text
 * @param array $options Optional settings
 * @return int|false Queue ID or false on failure
 */
function wa_send($phone, $message, $options = array()) {
    return WA_Queue::add($phone, $message, $options);
}

/**
 * Get message status
 * 
 * @param int $queue_id Queue ID
 * @return array|false Message data or false
 */
function wa_get_status($queue_id) {
    return WA_Queue::get($queue_id);
}
