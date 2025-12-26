<?php
/**
 * Queue Management
 */

if (!defined('ABSPATH')) exit;

class WA_Queue {
    
    private static $instance = null;
    private static $table = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        self::$table = $wpdb->prefix . 'wa_queue';
        
        // Schedule processor
        if (!wp_next_scheduled('wa_process_queue')) {
            wp_schedule_event(time(), 'wa_interval', 'wa_process_queue');
        }
        
        add_action('wa_process_queue', array($this, 'process'));
        add_filter('cron_schedules', array($this, 'add_interval'));
    }
    
    /**
     * Activate - Create table
     */
    public static function activate() {
        global $wpdb;
        
        $table   = $wpdb->prefix . 'wa_queue';
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            priority VARCHAR(10) DEFAULT 'normal',
            status VARCHAR(20) DEFAULT 'pending',
            attempts INT DEFAULT 0,
            metadata TEXT,
            error TEXT,
            created_at DATETIME NOT NULL,
            sent_at DATETIME,
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) $charset;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Default settings
        if (!get_option('wa_simple_settings')) {
            update_option('wa_simple_settings', array(
                'api_key'        => '',
                'interval'       => 6,
                'retention_days' => 30,
            ));
        }
    }
    
    /**
     * Add custom cron interval
     */
    public function add_interval($schedules) {
        $interval = (int) WA_Simple::option('interval', 6);
        
        $schedules['wa_interval'] = array(
            'interval' => $interval,
            'display'  => 'Every ' . $interval . ' seconds',
        );
        
        return $schedules;
    }
    
    /**
     * Add message to queue (for normal phone numbers)
     */
    public static function add($phone, $message, $options = array()) {
        global $wpdb;
        
        // Clean phone
        $phone = self::format_phone($phone);
        if (!$phone) {
            return false;
        }
        
        // Prepare data
        $data = array(
            'phone'      => $phone,
            'message'    => sanitize_textarea_field($message),
            'priority'   => isset($options['priority']) ? $options['priority'] : 'normal',
            'status'     => 'pending',
            'metadata'   => isset($options['metadata']) ? wp_json_encode($options['metadata']) : '',
            'created_at' => current_time('mysql'),
        );
        
        $result = $wpdb->insert(self::$table, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }

    /**
     * Add message to queue without phone formatting (for group IDs, etc.)
     */
    public static function add_raw($recipient, $message, $options = array()) {
        global $wpdb;

        error_log('WA_Queue::add_raw called with recipient=' . $recipient);

        $data = array(
            'phone'      => $recipient,
            'message'    => sanitize_textarea_field($message),
            'priority'   => isset($options['priority']) ? $options['priority'] : 'normal',
            'status'     => 'pending',
            'metadata'   => isset($options['metadata']) ? wp_json_encode($options['metadata']) : '',
            'created_at' => current_time('mysql'),
        );

        $result = $wpdb->insert(self::$table, $data);

        if ($result) {
            error_log('WA_Queue::add_raw inserted id ' . $wpdb->insert_id);
            return $wpdb->insert_id;
        }

        error_log('WA_Queue::add_raw insert failed: ' . $wpdb->last_error);
        return false;
    }

    /**
     * Get message by ID
     */
    public static function get($id) {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$table . " WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if ($row && !empty($row['metadata'])) {
            $row['metadata'] = json_decode($row['metadata'], true);
        }
        
        return $row;
    }

    /**
     * Process queue - Process ALL pending messages with rate limiting
     */
    public function process() {
        global $wpdb;
        
        $interval      = (int) WA_Simple::option('interval', 6);
        $start_time    = time();
        $max_execution = 50; // Max 50 seconds (before next cron)
        $processed     = 0;

        // Processor lock to prevent parallel runs
        $lock_key = 'wa_processing_lock';
        if ( get_transient($lock_key) ) {
            error_log('WA Queue: Processor locked, exiting');
            return array('processed' => 0, 'time' => 0);
        }
        set_transient($lock_key, 1, $max_execution + 5);
        
        error_log('WA Queue: Starting batch processor');
        
        while (true) {
            // Check execution time limit
            if ((time() - $start_time) >= $max_execution) {
                error_log('WA Queue: Time limit reached, stopping');
                break;
            }
            
            // Check rate limit
            $last_send = (int) get_transient('wa_last_send');
            if ($last_send) {
                $elapsed = time() - $last_send;
                if ($elapsed < $interval) {
                    $wait = $interval - $elapsed;
                    error_log("WA Queue: Rate limit - sleeping {$wait}s");
                    sleep($wait);
                }
            }
            
            // Get next pending message
            $message = $wpdb->get_row(
                "SELECT * FROM " . self::$table . " 
                WHERE status = 'pending' 
                AND attempts < 3 
                ORDER BY 
                    FIELD(priority, 'urgent', 'normal', 'low'),
                    created_at ASC 
                LIMIT 1",
                ARRAY_A
            );
            
            if (!$message) {
                error_log('WA Queue: No more pending messages');
                break; // No more messages
            }

            // Atomically claim the message (set to processing)
            $claimed = $wpdb->update(
                self::$table,
                array('status' => 'processing'),
                array('id' => $message['id'], 'status' => 'pending'),
                array('%s'),
                array('%d', '%s')
            );
            if ( ! $claimed ) {
                // Another worker took it
                continue;
            }
            
            error_log("WA Queue: Processing message #{$message['id']}");
            
            // Try to send
            $result = WA_Sender::send($message['phone'], $message['message']);
            
            // Update attempts (increment by 1)
            $wpdb->query($wpdb->prepare(
                "UPDATE " . self::$table . " SET attempts = attempts + 1 WHERE id = %d",
                $message['id']
            ));
            $new_attempts = (int) $message['attempts'] + 1;
            
            if (!empty($result['success'])) {
                // Success
                $wpdb->update(
                    self::$table,
                    array(
                        'status'  => 'sent',
                        'sent_at' => current_time('mysql'),
                        'error'   => ''
                    ),
                    array('id' => $message['id']),
                    array('%s','%s','%s'),
                    array('%d')
                );
                
                set_transient('wa_last_send', time(), 60);
                
                do_action('wa_message_sent', $message['id'], $message);
                
                $processed++;
                error_log("WA Queue: Message #{$message['id']} sent successfully");
                
            } else {
                // Failed
                $error_msg   = isset($result['error']) ? $result['error'] : '';
                $retry_after = isset($result['retry_after']) ? (int) $result['retry_after'] : 0;

                if ($new_attempts >= 3) {
                    // Max attempts reached
                    $wpdb->update(
                        self::$table,
                        array(
                            'status' => 'failed',
                            'error'  => $error_msg
                        ),
                        array('id' => $message['id']),
                        array('%s','%s'),
                        array('%d')
                    );

                    error_log("WA Queue: Message #{$message['id']} FAILED after 3 attempts. Error: " . $error_msg);

                    do_action('wa_message_failed', $message['id'], $message);

                } else {
                    // Will retry: set back to pending and store error
                    $wpdb->update(
                        self::$table,
                        array(
                            'status' => 'pending',
                            'error'  => $error_msg
                        ),
                        array('id' => $message['id']),
                        array('%s','%s'),
                        array('%d')
                    );

                    error_log("WA Queue: Message #{$message['id']} failed, will retry. Error: " . $error_msg);

                    // Respect provider retry_after if present (e.g., HTTP 429)
                    if ($retry_after > 0) {
                        error_log("WA Queue: Respecting retry_after={$retry_after}s");
                        sleep($retry_after);
                    }
                }
            }
        }
        
        $total_time = time() - $start_time;

        // Release processor lock
        delete_transient($lock_key);

        error_log("WA Queue: Batch complete - Processed: {$processed} in {$total_time}s");
        
        return array(
            'processed' => $processed,
            'time'      => $total_time,
        );
    }

    /**
     * Get statistics
     */
    public static function get_stats() {
        global $wpdb;
        
        return $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM " . self::$table,
            ARRAY_A
        );
    }

    public static function cleanup() {

        check_admin_referer('wa_cleanup');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $type    = isset($_GET['type']) ? $_GET['type'] : 'old';
        $deleted = 0;
        
        switch ($type) {
            case 'old':
                // Clean old messages (original behavior)
                $deleted = WA_Queue::cleanup();
                break;
                
            case 'sent':
                // Clean all sent messages
                $deleted = WA_Queue::cleanup_sent();
                break;
                
            case 'failed':
                // Clean all failed messages
                $deleted = WA_Queue::cleanup_failed();
                break;
                
            case 'all':
                // Clean everything
                $deleted = WA_Queue::cleanup_all();
                break;
        }
        
        wp_redirect(add_query_arg(array(
            'page'    => 'wa-queue',
            'cleaned' => $deleted,
            'type'    => $type,
        ), admin_url('admin.php')));
        exit;
    }
    
    /**
     * Format phone number
     */
    private static function format_phone($phone) {
        // Remove everything except digits and +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Must start with +
        if (substr($phone, 0, 1) !== '+') {
            // Try to add +20 if starts with 0
            if (substr($phone, 0, 1) === '0') {
                $phone = '+20' . substr($phone, 1);
            } else {
                return false;
            }
        }
        
        // Validate length
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            return false;
        }
        
        return $phone;
    }
}
