<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>WA Queue Dashboard</h1>
    
    <?php if (isset($_GET['processed'])): ?>
        <div class="notice notice-success"><p>Queue processed!</p></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['cleaned'])): ?>
        <div class="notice notice-success"><p>Cleaned <?php echo absint($_GET['cleaned']); ?> messages!</p></div>
    <?php endif; ?>
    
    <div class="wa-actions" style="margin: 20px 0;">
    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wa_process_now'), 'wa_process_now'); ?>" 
       class="button button-primary">
        <span class="dashicons dashicons-update"></span> Process Now
    </a>
    
    <!-- Cleanup Dropdown -->
    <div class="button-group" style="display: inline-block; position: relative;">
        <button type="button" class="button" id="cleanup-menu-btn">
            <span class="dashicons dashicons-trash"></span> Cleanup ▼
        </button>
        <div id="cleanup-menu" style="display: none; position: absolute; background: #fff; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 200px;">
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wa_cleanup&type=old'), 'wa_cleanup'); ?>" 
               class="cleanup-option" style="display: block; padding: 10px 15px; text-decoration: none; color: #333;">
                🕐 Old Messages (30+ days)
            </a>
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wa_cleanup&type=sent'), 'wa_cleanup'); ?>" 
               class="cleanup-option" style="display: block; padding: 10px 15px; text-decoration: none; color: #333;"
               onclick="return confirm('Delete all sent messages?')">
                ✅ All Sent Messages
            </a>
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wa_cleanup&type=failed'), 'wa_cleanup'); ?>" 
               class="cleanup-option" style="display: block; padding: 10px 15px; text-decoration: none; color: #333;"
               onclick="return confirm('Delete all failed messages?')">
                ❌ All Failed Messages
            </a>
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wa_cleanup&type=all'), 'wa_cleanup'); ?>" 
               class="cleanup-option" style="display: block; padding: 10px 15px; text-decoration: none; color: #d63638; border-top: 1px solid #ddd;"
               onclick="return confirm('⚠️ DELETE EVERYTHING? This cannot be undone!')">
                🗑️ Everything (Dangerous!)
            </a>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#cleanup-menu-btn').on('click', function(e) {
            e.stopPropagation();
            $('#cleanup-menu').toggle();
        });
        
        $(document).on('click', function() {
            $('#cleanup-menu').hide();
        });
        
        $('.cleanup-option').hover(
            function() { $(this).css('background', '#f0f0f0'); },
            function() { $(this).css('background', '#fff'); }
        );
    });
    </script>
</div>

    
    <div class="wa-stats">
        <div class="wa-stat">
            <h3><?php echo number_format($stats['total']); ?></h3>
            <p>Total</p>
        </div>
        <div class="wa-stat pending">
            <h3><?php echo number_format($stats['pending']); ?></h3>
            <p>Pending</p>
        </div>
        <div class="wa-stat sent">
            <h3><?php echo number_format($stats['sent']); ?></h3>
            <p>Sent</p>
        </div>
        <div class="wa-stat failed">
            <h3><?php echo number_format($stats['failed']); ?></h3>
            <p>Failed</p>
        </div>
    </div>
    
    <h2>Recent Messages</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Phone</th>
                <th>Message</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($messages)): ?>
                <tr><td colspan="5" style="text-align:center">No messages</td></tr>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <tr>
                        <td><?php echo $msg['id']; ?></td>
                        <td><code><?php echo esc_html($msg['phone']); ?></code></td>
                        <td><?php echo esc_html(substr($msg['message'], 0, 50)); ?>...</td>
                        <td><span class="status-<?php echo $msg['status']; ?>"><?php echo $msg['status']; ?></span></td>
                        <td><?php echo $msg['created_at']; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
