<?php
/**
 * Admin Interface
 */

if (!defined('ABSPATH')) exit;

class WA_Admin {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Actions
        add_action('admin_post_wa_process_now', array($this, 'process_now'));   // logged-in only
        add_action('admin_post_wa_cleanup',     array($this, 'cleanup'));       // logged-in only
    }
    
    public function add_menu() {
        add_menu_page(
            'WA Queue',
            'WA Queue',
            'manage_options',
            'wa-queue',
            array($this, 'render_dashboard'),
            'dashicons-whatsapp',
            30
        );
        
        add_submenu_page(
            'wa-queue',
            'Settings',
            'Settings',
            'manage_options',
            'wa-settings',
            array($this, 'render_settings')
        );
    }
    
    public function enqueue_assets($hook) {
        if (strpos($hook, 'wa-') === false) return;
        wp_enqueue_style('wa-admin', WA_URL . 'assets/admin.css', array(), WA_VERSION);
    }
    
    public function register_settings() {
        register_setting('wa_simple_settings', 'wa_simple_settings');
    }
    
    public function render_dashboard() {
        global $wpdb;
        $table = $wpdb->prefix . 'wa_queue';

        // Filters and pagination
        $status   = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $q        = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $per_page = isset($_GET['per_page']) ? max(10, min(200, (int) $_GET['per_page'])) : 50;
        $paged    = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $offset   = ($paged - 1) * $per_page;

        $where   = array();
        $params  = array();

        if ($status !== '') {
            $where[]  = 'status = %s';
            $params[] = $status;
        }
        if ($q !== '') {
            // search in phone, message, error
            $where[]  = '(phone LIKE %s OR message LIKE %s OR error LIKE %s)';
            $like     = '%' . $wpdb->esc_like($q) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Total
        $total_sql = "SELECT COUNT(*) FROM $table $where_sql";
        $total     = $params ? (int) $wpdb->get_var($wpdb->prepare($total_sql, $params)) : (int) $wpdb->get_var($total_sql);

        // Rows
        $rows_sql = "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $rows_params = $params;
        $rows_params[] = $per_page;
        $rows_params[] = $offset;

        $messages = $params
            ? $wpdb->get_results($wpdb->prepare($rows_sql, $rows_params), ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($rows_sql, $per_page, $offset), ARRAY_A);

        // Decode metadata for view convenience
        if ($messages) {
            foreach ($messages as &$m) {
                if (!empty($m['metadata'])) {
                    $decoded = json_decode($m['metadata'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $m['metadata_decoded'] = $decoded;
                    } else {
                        $m['metadata_decoded'] = array();
                    }
                } else {
                    $m['metadata_decoded'] = array();
                }
            }
            unset($m);
        }

        // Pass context to view
        $stats = WA_Queue::get_stats();
        $context = array(
            'stats'     => $stats,
            'messages'  => $messages,
            'total'     => $total,
            'per_page'  => $per_page,
            'paged'     => $paged,
            'pages'     => max(1, (int) ceil($total / $per_page)),
            'status'    => $status,
            'q'         => $q,
            'nonce_cleanup' => wp_create_nonce('wa_cleanup'),
            'nonce_process' => wp_create_nonce('wa_process_now'),
        );

        include WA_PATH . 'views/dashboard.php';
    }
    
    public function render_settings() {
        if (isset($_POST['wa_save'])) {
            check_admin_referer('wa_settings');
            
            $settings = array(
                'api_key'        => sanitize_text_field($_POST['api_key']),
                'interval'       => max(1, absint($_POST['interval'])),
                'retention_days' => max(1, absint($_POST['retention_days'])),
            );
            
            update_option('wa_simple_settings', $settings);
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        include WA_PATH . 'views/settings.php';
    }
    
    public function process_now() {
        if ( ! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('wa_process_now');
        
        WA_Queue::instance()->process();
        
        wp_redirect(admin_url('admin.php?page=wa-queue&processed=1'));
        exit;
    }
    
    public function cleanup() {
        if ( ! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('wa_cleanup');

        global $wpdb;
        $table = $wpdb->prefix . 'wa_queue';

        $type = isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : 'old';
        $deleted = 0;

        switch ($type) {
            case 'id':
                // delete single row by id
                $id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
                if ($id > 0) {
                    $deleted = (int) $wpdb->delete($table, array('id' => $id), array('%d'));
                }
                break;

            case 'bulk':
                // bulk delete by ids[]
                $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                    $sql = "DELETE FROM $table WHERE id IN ($placeholders)";
                    $deleted = (int) $wpdb->query($wpdb->prepare($sql, $ids));
                }
                break;

            case 'sent':
                $deleted = (int) $wpdb->query("DELETE FROM $table WHERE status = 'sent'");
                break;

            case 'failed':
                $deleted = (int) $wpdb->query("DELETE FROM $table WHERE status = 'failed'");
                break;

            case 'all':
                $deleted = (int) $wpdb->query("DELETE FROM $table");
                break;

            case 'old':
            default:
                // remove sent/failed older than retention_days
                $settings = get_option('wa_simple_settings', array());
                $days     = isset($settings['retention_days']) ? (int) $settings['retention_days'] : 30;
                $days     = max(1, $days);
                $cutoff   = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

                $sql = "DELETE FROM $table 
                        WHERE created_at < %s
                        AND status IN ('sent','failed')";
                $deleted = (int) $wpdb->query($wpdb->prepare($sql, $cutoff));
                break;
        }

        $args = array(
            'page'    => 'wa-queue',
            'cleaned' => $deleted,
            'type'    => $type,
        );
        wp_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
