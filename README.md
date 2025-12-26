# WA Simple Queue - WhatsApp Message Queue Manager

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-red.svg)

Simple, reliable, and powerful WhatsApp message queue system for WordPress with intelligent rate limiting, automatic retries, and seamless integration with any plugin.

---

## 📋 Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage](#-usage)
- [API Reference](#-api-reference)
- [Queue System](#-queue-system)
- [Integration Examples](#-integration-examples)
- [Admin Panel](#-admin-panel)
- [Database Structure](#-database-structure)
- [Hooks & Filters](#-hooks--filters)
- [Advanced Features](#-advanced-features)
- [Troubleshooting](#-troubleshooting)
- [Requirements](#-requirements)
- [Changelog](#-changelog)

---

## 🔍 Overview

**WA Simple Queue** is a lightweight yet powerful WordPress plugin that manages WhatsApp message sending through a reliable queue system. It handles rate limiting, automatic retries, priority management, and provides a clean API for easy integration with any WordPress plugin or theme.

### Why Use This Plugin?

- ✅ **Prevent Rate Limiting**: Automatic message spacing prevents API rate limits
- ✅ **Reliable Delivery**: Smart retry mechanism ensures messages get through
- ✅ **Easy Integration**: Single function call `wa_send()` - that's it!
- ✅ **Priority Management**: Urgent messages sent first
- ✅ **Complete Logging**: Track every message with detailed status
- ✅ **Zero Dependencies**: Works standalone or with any plugin
- ✅ **Admin Interface**: Monitor and manage queue from WordPress admin

---

## ✨ Features

### Core Features

#### 1. Message Queue System

```
┌─────────────────────────────────────────────┐
│         Message Queue Pipeline              │
│                                             │
│  Add Message → Queue → Rate Limit → Send   │
│       ↓          ↓         ↓          ↓     │
│   Database   Pending   Spacing    Delivery  │
└─────────────────────────────────────────────┘
```

- **Automatic Processing**: Background cron job processes queue automatically
- **Rate Limiting**: Configurable delay between messages (default: 6 seconds)
- **Smart Retries**: Up to 3 attempts per message with exponential backoff
- **Priority Queue**: Three priority levels (urgent, normal, low)

#### 2. Message Status Tracking

- `pending`: Waiting to be sent
- `processing`: Currently being sent
- `sent`: Successfully delivered
- `failed`: Exceeded retry limit

#### 3. Advanced API Integration

- **HTTP 429 Handling**: Respects `Retry-After` headers from API
- **Error Logging**: Detailed error messages for debugging
- **Timeout Protection**: 30-second request timeout
- **JSON API**: Clean REST API communication

#### 4. Flexible Phone Number Handling

**Standard Phone Numbers** (auto-formatted):
```php
wa_send('01234567890', 'Hello!');        // Auto adds +20
wa_send('+201234567890', 'Hello!');      // Already formatted
```

**Raw Recipients** (no formatting - for groups, etc.):
```php
WA_Queue::add_raw('120363XXXXX@g.us', 'Group message');
```

#### 5. Metadata Support

Attach custom data to messages for tracking:
```php
wa_send('+201234567890', 'Hello!', [
    'metadata' => [
        'order_id' => 123,
        'customer_id' => 456,
        'campaign' => 'black_friday'
    ]
]);
```

---

## 🚀 Installation

### Method 1: Manual Installation

1. **Download** the plugin files
2. **Upload** to `/wp-content/plugins/wa-simple_plugin` directory
3. **Activate** through WordPress admin panel
4. **Configure** API key in settings

```bash
cd wp-content/plugins/
git clone https://github.com/abdulrahmanroston/wa-simple_plugin.git
```

### Method 2: WordPress Admin

1. Go to **Plugins → Add New**
2. Click **Upload Plugin**
3. Choose the ZIP file
4. Click **Install Now** → **Activate**

### Post-Installation

✅ Database table `wp_wa_queue` created automatically  
✅ Cron job scheduled for queue processing  
✅ Default settings initialized  
✅ Admin menu added under **Tools → WA Queue**  

---

## ⚙️ Configuration

### 1. Get API Key

1. Sign up at [wasenderapi.com](https://wasenderapi.com)
2. Generate your API key from dashboard
3. Copy the key

### 2. Configure Plugin

Go to **WordPress Admin → Tools → WA Queue → Settings**

#### Settings Overview

| Setting | Description | Default | Range |
|---------|-------------|---------|-------|
| **API Key** | Your WaSender API authentication key | *(empty)* | Required |
| **Send Interval** | Seconds between messages (rate limiting) | `6` | 3-60 |
| **Retention Days** | Days to keep old messages before cleanup | `30` | 7-365 |

#### Recommended Settings

**High Volume (>500 msg/day):**
- Interval: 6-8 seconds
- Retention: 7 days

**Medium Volume (100-500 msg/day):**
- Interval: 5-6 seconds
- Retention: 15 days

**Low Volume (<100 msg/day):**
- Interval: 3-5 seconds
- Retention: 30 days

---

## 📖 Usage

### Basic Usage

#### Send Simple Message

```php
// Simplest form
wa_send('+201234567890', 'Hello from WordPress!');
```

#### Send with Priority

```php
// Urgent message (sent first)
wa_send('+201234567890', 'Your OTP: 123456', [
    'priority' => 'urgent'
]);

// Low priority (sent last)
wa_send('+201234567890', 'Marketing message', [
    'priority' => 'low'
]);
```

#### Send with Metadata

```php
$queue_id = wa_send('+201234567890', 'Order shipped!', [
    'priority' => 'normal',
    'metadata' => [
        'order_id' => 5234,
        'customer_name' => 'Ahmed Ali',
        'tracking_number' => 'TRK123456'
    ]
]);

if ($queue_id) {
    echo "Message queued with ID: $queue_id";
}
```

#### Send to WhatsApp Group

```php
// Group ID format: 120363XXXXX@g.us
WA_Queue::add_raw('120363025417863272@g.us', 'Hello Group!');
```

### Check Message Status

```php
$queue_id = wa_send('+201234567890', 'Test message');

// Later, check status
$status = wa_get_status($queue_id);

if ($status) {
    echo "Status: " . $status['status'];
    echo "Attempts: " . $status['attempts'];
    echo "Sent at: " . $status['sent_at'];
    
    if ($status['status'] === 'failed') {
        echo "Error: " . $status['error'];
    }
}
```

---

## 🔧 API Reference

### Functions

#### `wa_send()`

Add message to queue (standard phone numbers)

**Signature:**
```php
wa_send(string $phone, string $message, array $options = []): int|false
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$phone` | `string` | Yes | Phone number with country code (e.g., `+201234567890`) |
| `$message` | `string` | Yes | Message text (max 4096 chars) |
| `$options` | `array` | No | Additional options |

**Options:**

```php
[
    'priority' => 'urgent|normal|low',  // Message priority (default: 'normal')
    'metadata' => [],                   // Custom data array for tracking
]
```

**Returns:**
- `int`: Queue ID on success
- `false`: On failure (invalid phone, database error)

**Example:**
```php
$id = wa_send('+201234567890', 'Hello World!');
if ($id) {
    echo "Queued: #$id";
}
```

---

#### `wa_get_status()`

Get message details and status

**Signature:**
```php
wa_get_status(int $queue_id): array|false
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$queue_id` | `int` | Yes | Queue message ID |

**Returns:**
```php
[
    'id' => 123,
    'phone' => '+201234567890',
    'message' => 'Hello World!',
    'priority' => 'normal',
    'status' => 'sent',
    'attempts' => 1,
    'metadata' => ['order_id' => 456],
    'error' => '',
    'created_at' => '2025-12-26 10:30:00',
    'sent_at' => '2025-12-26 10:30:06'
]
```

---

### Classes

#### `WA_Queue`

Queue management class

**Methods:**

##### `WA_Queue::add()`
Add standard phone number message to queue
```php
WA_Queue::add(string $phone, string $message, array $options = []): int|false
```

##### `WA_Queue::add_raw()`
Add message without phone formatting (for groups)
```php
WA_Queue::add_raw(string $recipient, string $message, array $options = []): int|false
```

**Example:**
```php
// Standard phone
WA_Queue::add('+201234567890', 'Hello!');

// WhatsApp group
WA_Queue::add_raw('120363025417863272@g.us', 'Group message');
```

##### `WA_Queue::get()`
Get message by ID
```php
WA_Queue::get(int $id): array|false
```

##### `WA_Queue::get_stats()`
Get queue statistics
```php
WA_Queue::get_stats(): array
```

**Returns:**
```php
[
    'total' => 150,
    'pending' => 25,
    'sent' => 120,
    'failed' => 5
]
```

##### `WA_Queue::process()`
Manually trigger queue processing (usually automatic via cron)
```php
WA_Queue::instance()->process(): array
```

**Returns:**
```php
[
    'processed' => 15,  // Messages sent
    'time' => 92        // Seconds elapsed
]
```

---

#### `WA_Sender`

API communication class

**Methods:**

##### `WA_Sender::send()`
Send message via WaSender API
```php
WA_Sender::send(string $phone, string $message): array
```

**Returns:**
```php
// Success
['success' => true]

// Failure
[
    'success' => false,
    'error' => 'Error message',
    'retry_after' => 60  // Optional, for rate limit (HTTP 429)
]
```

---

## 🔄 Queue System

### How It Works

#### 1. Message Addition

```
Plugin/Theme Code
       ↓
   wa_send()
       ↓
  WA_Queue::add()
       ↓
   Database Insert
       ↓
  Status: pending
```

#### 2. Automatic Processing

```
WordPress Cron (every X seconds)
       ↓
WA_Queue::process()
       ↓
   Get pending messages
       ↓
   Apply rate limiting
       ↓
  WA_Sender::send()
       ↓
    Update status
```

#### 3. Status Flow Diagram

```
                    ┌──────────┐
                    │ pending  │
                    └────┬─────┘
                         │
                         ↓
                 ┌───────────────┐
                 │  Rate Limit   │
                 │  Check Delay  │
                 └───────┬───────┘
                         │
                         ↓
                 ┌───────────────┐
                 │  processing   │
                 └───────┬───────┘
                         │
          ┌──────────────┴──────────────┐
          │                             │
          ↓                             ↓
    ┌─────────┐                   ┌─────────┐
    │  sent   │                   │  failed │
    │ ✓ Done  │                   │ (retry) │
    └─────────┘                   └────┬────┘
                                       │
                               ┌───────┴────────┐
                               │                │
                    attempts < 3      attempts ≥ 3
                               │                │
                               ↓                ↓
                          ┌─────────┐     ┌─────────┐
                          │ pending │     │ failed  │
                          │ (retry) │     │ (final) │
                          └─────────┘     └─────────┘
```

### Priority System

Messages are processed in priority order:

1. **Urgent** (`urgent`): OTPs, verification codes, critical alerts
2. **Normal** (`normal`): Order updates, notifications (default)
3. **Low** (`low`): Marketing, newsletters, promotional content

**Example:**
```php
// These will be sent in priority order, regardless of creation time
wa_send('+201111111111', 'Marketing email', ['priority' => 'low']);     // #3
wa_send('+202222222222', 'Order shipped', ['priority' => 'normal']);    // #2
wa_send('+203333333333', 'OTP: 123456', ['priority' => 'urgent']);      // #1 (sent first)
```

### Rate Limiting

**How it works:**

1. **Configurable Interval**: Set delay between messages (default: 6 seconds)
2. **Last Send Tracking**: Tracks last message send time
3. **Automatic Waiting**: Sleeps if interval not reached
4. **HTTP 429 Handling**: Respects API `Retry-After` header

**Example Timeline:**
```
10:00:00 → Send Message #1
10:00:06 → Send Message #2 (6 sec delay)
10:00:12 → Send Message #3 (6 sec delay)
10:00:18 → HTTP 429 received, Retry-After: 60
10:01:18 → Retry Message #3 (60 sec delay)
10:01:24 → Send Message #4 (6 sec delay)
```

### Retry Mechanism

**Smart Retry Logic:**

- **Attempt 1**: Immediate send
- **Attempt 2**: After next cron cycle (+ rate limit)
- **Attempt 3**: Final attempt
- **Failed**: Status set to `failed`, error logged

**Retry Triggers:**
- Network errors
- API timeouts
- HTTP 5xx errors
- HTTP 429 (after retry_after period)

**No Retry for:**
- HTTP 4xx (except 429) - Invalid phone, unauthorized, etc.
- Success (HTTP 2xx)

---

## 🔌 Integration Examples

### With WooCommerce

#### Send Order Notification

```php
add_action('woocommerce_order_status_completed', function($order_id) {
    $order = wc_get_order($order_id);
    $phone = $order->get_billing_phone();
    
    $message = sprintf(
        "مرحباً %s،\n\nطلبك رقم #%s قد تم توصيله بنجاح!\n\nشكراً لثقتك.",
        $order->get_billing_first_name(),
        $order->get_order_number()
    );
    
    wa_send($phone, $message, [
        'priority' => 'normal',
        'metadata' => [
            'order_id' => $order_id,
            'customer_id' => $order->get_customer_id()
        ]
    ]);
});
```

#### Send Urgent Payment Reminder

```php
add_action('woocommerce_order_status_pending', function($order_id) {
    $order = wc_get_order($order_id);
    $phone = $order->get_billing_phone();
    
    $message = sprintf(
        "تذكير هام: طلبك #%s بانتظار الدفع.\n\nالمبلغ: %s\n\nيرجى الدفع خلال 24 ساعة.",
        $order->get_order_number(),
        $order->get_formatted_order_total()
    );
    
    wa_send($phone, $message, [
        'priority' => 'urgent',  // Sent immediately
        'metadata' => ['order_id' => $order_id]
    ]);
});
```

---

### With Contact Form 7

```php
add_action('wpcf7_mail_sent', function($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    $data = $submission->get_posted_data();
    
    $phone = isset($data['phone']) ? $data['phone'] : '';
    $name = isset($data['name']) ? $data['name'] : '';
    
    if ($phone) {
        wa_send($phone, "شكراً {$name} على تواصلك! سنرد عليك قريباً.", [
            'metadata' => ['form_id' => $contact_form->id()]
        ]);
    }
});
```

---

### With Custom User Registration

```php
add_action('user_register', function($user_id) {
    $user = get_userdata($user_id);
    $phone = get_user_meta($user_id, 'phone', true);
    
    if ($phone) {
        // Send verification code
        $code = wp_rand(100000, 999999);
        update_user_meta($user_id, 'verification_code', $code);
        
        wa_send($phone, "كود التحقق: {$code}\n\nصالح لمدة 10 دقائق.", [
            'priority' => 'urgent',
            'metadata' => ['user_id' => $user_id]
        ]);
    }
});
```

---

### With Advanced Custom Fields (ACF)

```php
add_action('acf/save_post', function($post_id) {
    if (get_post_type($post_id) === 'appointment') {
        $phone = get_field('customer_phone', $post_id);
        $date = get_field('appointment_date', $post_id);
        $time = get_field('appointment_time', $post_id);
        
        if ($phone && $date && $time) {
            $message = "تم تأكيد موعدك:\n\nالتاريخ: {$date}\nالوقت: {$time}\n\nنتطلع لرؤيتك!";
            
            wa_send($phone, $message, [
                'priority' => 'normal',
                'metadata' => ['appointment_id' => $post_id]
            ]);
        }
    }
});
```

---

### Bulk Sending (Campaign)

```php
function send_campaign_messages() {
    $customers = get_users(['role' => 'customer', 'number' => 100]);
    
    $sent = 0;
    foreach ($customers as $customer) {
        $phone = get_user_meta($customer->ID, 'phone', true);
        
        if ($phone) {
            $message = sprintf(
                "مرحباً %s!\n\nعرض خاص: خصم 20%% على جميع المنتجات!\n\nالعرض ساري حتى نهاية الأسبوع.",
                $customer->display_name
            );
            
            $queued = wa_send($phone, $message, [
                'priority' => 'low',  // Low priority for marketing
                'metadata' => [
                    'campaign' => 'black_friday_2025',
                    'user_id' => $customer->ID
                ]
            ]);
            
            if ($queued) {
                $sent++;
            }
        }
    }
    
    return "Queued {$sent} messages for sending.";
}
```

---

## 🖥️ Admin Panel

### Menu Location

**WordPress Admin → Tools → WA Queue**

### Pages

#### 1. Queue Overview

**Path:** `admin.php?page=wa-queue`

**Features:**
- 📊 Real-time statistics (Total, Pending, Sent, Failed)
- 📋 Recent messages table
- 🔍 Filter by status
- 🔄 Manual process button
- 🗑️ Cleanup options

**Table Columns:**
- ID
- Phone
- Message (truncated)
- Priority
- Status
- Attempts
- Created
- Sent

---

#### 2. Settings

**Path:** `admin.php?page=wa-queue-settings`

**Sections:**

**API Configuration**
- API Key input
- Connection test button

**Queue Settings**
- Send Interval (slider: 3-60 seconds)
- Retention Days (input: 7-365)

**Actions**
- Save Settings button
- Reset to Defaults

---

#### 3. Logs

**Path:** `admin.php?page=wa-queue-logs`

**Features:**
- Detailed message history
- Full error messages
- Metadata display
- Date range filter
- Export to CSV

---

### Cleanup Options

**Available Actions:**

| Action | Description | Query Param |
|--------|-------------|-------------|
| **Clean Old** | Remove messages older than retention period | `?type=old` |
| **Clean Sent** | Remove all successfully sent messages | `?type=sent` |
| **Clean Failed** | Remove all failed messages | `?type=failed` |
| **Clean All** | Remove everything (fresh start) | `?type=all` |

**Usage:**
```
admin.php?page=wa-queue&action=cleanup&type=sent&_wpnonce=xxx
```

---

## 🗄️ Database Structure

### Table: `wp_wa_queue`

```sql
CREATE TABLE wp_wa_queue (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(50) NOT NULL,              -- Recipient (phone or group ID)
    message TEXT NOT NULL,                   -- Message content
    priority VARCHAR(10) DEFAULT 'normal',   -- urgent|normal|low
    status VARCHAR(20) DEFAULT 'pending',    -- pending|processing|sent|failed
    attempts INT DEFAULT 0,                  -- Retry counter
    metadata TEXT,                           -- JSON: custom tracking data
    error TEXT,                              -- Error message if failed
    created_at DATETIME NOT NULL,            -- Queue time
    sent_at DATETIME,                        -- Delivery time
    
    INDEX idx_status (status),               -- Fast status filtering
    INDEX idx_created (created_at)           -- Date range queries
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Field Details

| Field | Type | Description | Example |
|-------|------|-------------|----------|
| `id` | `BIGINT(20)` | Auto-increment primary key | `1523` |
| `phone` | `VARCHAR(50)` | Phone number or group ID | `+201234567890` |
| `message` | `TEXT` | Message content (up to 65,535 chars) | `Hello World!` |
| `priority` | `VARCHAR(10)` | Message priority | `urgent` |
| `status` | `VARCHAR(20)` | Current status | `sent` |
| `attempts` | `INT` | Send attempt counter | `1` |
| `metadata` | `TEXT` | JSON metadata | `{"order_id":123}` |
| `error` | `TEXT` | Last error message | `HTTP 429: ...` |
| `created_at` | `DATETIME` | When queued | `2025-12-26 10:30:00` |
| `sent_at` | `DATETIME` | When delivered | `2025-12-26 10:30:06` |

### Sample Data

```sql
INSERT INTO wp_wa_queue VALUES
(1, '+201234567890', 'Your OTP: 123456', 'urgent', 'sent', 1, 
 '{"user_id":25}', '', '2025-12-26 10:00:00', '2025-12-26 10:00:01'),

(2, '+209876543210', 'Order #5234 shipped!', 'normal', 'sent', 1,
 '{"order_id":5234}', '', '2025-12-26 10:00:05', '2025-12-26 10:00:07'),

(3, '+205555555555', 'Marketing message', 'low', 'pending', 0,
 '{"campaign":"black_friday"}', '', '2025-12-26 10:00:10', NULL),

(4, '+201111111111', 'Failed message', 'normal', 'failed', 3,
 '{}', 'HTTP 401: Invalid API key', '2025-12-26 09:50:00', NULL);
```

---

## 🪝 Hooks & Filters

### Actions

#### `wa_message_sent`

Fired when message successfully sent

**Usage:**
```php
add_action('wa_message_sent', function($queue_id, $message) {
    error_log("Message #{$queue_id} sent to {$message['phone']}");
    
    // Custom logic: update external system, send notification, etc.
    if (isset($message['metadata'])) {
        $meta = json_decode($message['metadata'], true);
        if (isset($meta['order_id'])) {
            update_post_meta($meta['order_id'], '_wa_notification_sent', 'yes');
        }
    }
}, 10, 2);
```

**Parameters:**
- `$queue_id` (int): Queue message ID
- `$message` (array): Full message data from database

---

#### `wa_message_failed`

Fired when message permanently fails (after 3 attempts)

**Usage:**
```php
add_action('wa_message_failed', function($queue_id, $message) {
    // Send alert to admin
    wp_mail(
        get_option('admin_email'),
        'WhatsApp Message Failed',
        "Message #{$queue_id} failed after 3 attempts.\nError: {$message['error']}"
    );
    
    // Log to external monitoring service
    // MyMonitoring::log_error('wa_message_failed', $message);
}, 10, 2);
```

**Parameters:**
- `$queue_id` (int): Queue message ID
- `$message` (array): Full message data including error

---

#### `wa_process_queue`

Cron action that triggers queue processing

**Usage:**
```php
// Add custom pre-processing logic
add_action('wa_process_queue', function() {
    // Run before queue processing
    do_action('my_custom_pre_queue_logic');
}, 5); // Priority 5 = before default processor (priority 10)
```

---

### Filters

#### `cron_schedules`

Modify cron intervals (used internally by plugin)

**Usage:**
```php
add_filter('cron_schedules', function($schedules) {
    // Add custom interval
    $schedules['every_30_seconds'] = [
        'interval' => 30,
        'display' => 'Every 30 Seconds'
    ];
    return $schedules;
});
```

---

### Custom Hooks

You can add your own hooks for specific use cases:

```php
// In your integration code
function my_send_order_notification($order_id) {
    $order = wc_get_order($order_id);
    $phone = $order->get_billing_phone();
    
    // Allow other plugins to modify message
    $message = apply_filters('my_order_notification_message', 
        "طلبك #{$order->get_order_number()} جاهز!", 
        $order
    );
    
    $queue_id = wa_send($phone, $message);
    
    // Trigger custom action
    do_action('my_after_order_notification', $order_id, $queue_id);
}
```

---

## 🚀 Advanced Features

### Manual Queue Processing

By default, the queue is processed automatically via WordPress cron. For more control:

```php
// Manually trigger processing
$result = WA_Queue::instance()->process();

echo "Processed: {$result['processed']} messages";
echo "Time: {$result['time']} seconds";
```

### Disable Auto-Processing

```php
// In wp-config.php or functions.php
define('WA_DISABLE_CRON', true);

// Then use external cron or manual trigger
// Example: curl https://yoursite.com/wp-cron.php?doing_wp_cron
```

### Custom API Endpoint

Integrate with different WhatsApp API provider:

```php
add_filter('wa_api_endpoint', function($url) {
    return 'https://my-custom-api.com/send';
});

add_filter('wa_api_request_body', function($body, $phone, $message) {
    // Modify request format for your API
    return [
        'recipient' => $phone,
        'text' => $message,
        'type' => 'text'
    ];
}, 10, 3);
```

### Query Queue Database

```php
global $wpdb;
$table = $wpdb->prefix . 'wa_queue';

// Get urgent pending messages
$urgent = $wpdb->get_results(
    "SELECT * FROM {$table} 
    WHERE status = 'pending' 
    AND priority = 'urgent' 
    ORDER BY created_at ASC"
);

// Count messages by status today
$stats = $wpdb->get_results(
    "SELECT status, COUNT(*) as count 
    FROM {$table} 
    WHERE DATE(created_at) = CURDATE() 
    GROUP BY status"
);
```

### Webhook Integration

```php
// Send webhook after message sent
add_action('wa_message_sent', function($queue_id, $message) {
    wp_remote_post('https://your-webhook-url.com/wa-callback', [
        'body' => json_encode([
            'event' => 'message_sent',
            'queue_id' => $queue_id,
            'phone' => $message['phone'],
            'sent_at' => $message['sent_at']
        ])
    ]);
}, 10, 2);
```

---

## 🔧 Troubleshooting

### Common Issues

#### 1. Messages Not Sending

**Symptoms:**
- Messages stay in `pending` status
- No messages being processed

**Solutions:**

✅ **Check Cron Status**
```php
// Add to functions.php temporarily
add_action('init', function() {
    $next = wp_next_scheduled('wa_process_queue');
    echo "Next run: " . date('Y-m-d H:i:s', $next);
});
```

✅ **Check API Key**
- Go to Settings
- Verify API key is correct
- Test connection

✅ **Enable Debug Logging**
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check wp-content/debug.log for errors
```

✅ **Manually Trigger**
```php
// Run once to test
WA_Queue::instance()->process();
```

---

#### 2. Rate Limit Errors (HTTP 429)

**Symptoms:**
- Error: "HTTP 429: Too Many Requests"
- Messages failing repeatedly

**Solutions:**

✅ **Increase Interval**
- Go to Settings
- Set interval to 8-10 seconds
- Save changes

✅ **Check Provider Limits**
- Contact WaSender support
- Verify your plan limits
- Upgrade if needed

---

#### 3. Invalid Phone Format

**Symptoms:**
- Messages not queued
- `wa_send()` returns `false`

**Solutions:**

✅ **Correct Format**
```php
// ❌ Wrong
wa_send('01234567890', 'Hello');     // Missing country code
wa_send('1234567890', 'Hello');      // Missing +

// ✅ Correct
wa_send('+201234567890', 'Hello');   // Full format
wa_send('01234567890', 'Hello');     // Auto-converts to +20...
```

✅ **Use Raw for Groups**
```php
WA_Queue::add_raw('120363XXX@g.us', 'Hello Group!');
```

---

#### 4. Database Table Missing

**Symptoms:**
- Error: "Table 'wp_wa_queue' doesn't exist"
- Plugin not working

**Solutions:**

✅ **Reactivate Plugin**
- Deactivate plugin
- Activate again
- Table will be created

✅ **Manual Creation**
```sql
-- Run in phpMyAdmin or Adminer
CREATE TABLE wp_wa_queue (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

#### 5. Cron Not Running

**Symptoms:**
- Queue never processes
- Manual trigger works but cron doesn't

**Solutions:**

✅ **Check WP-Cron**
```php
// Test if WP-Cron is working
wp_schedule_single_event(time() + 10, 'test_cron');
add_action('test_cron', function() {
    error_log('WP-Cron is working!');
});
```

✅ **Use Real Cron**
```bash
# In wp-config.php
define('DISABLE_WP_CRON', true);

# In server crontab
* * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

✅ **Check Hosting**
- Some hosts disable WP-Cron
- Contact support to enable
- Or use external cron service

---

### Debug Mode

```php
// Enable detailed logging
add_filter('wa_debug_mode', '__return_true');

// Logs will appear in wp-content/debug.log
```

---

## 📦 Requirements

### Minimum Requirements

- **WordPress:** 5.0 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher
- **WaSender API Key:** Required for sending

### Recommended

- **PHP:** 8.0 or higher
- **MySQL:** 8.0 or higher (better JSON support)
- **Memory Limit:** 128MB+
- **Max Execution Time:** 60 seconds+
- **SSL Certificate:** For secure API calls

### Server Requirements

- ✅ `wp_remote_post()` enabled (for API calls)
- ✅ WP-Cron enabled (or external cron)
- ✅ File write permissions (for logs)
- ✅ Outbound connections allowed (to wasenderapi.com)

### Optional

- **WooCommerce:** For e-commerce integration
- **ACF / Custom Fields:** For advanced metadata
- **WPML / Polylang:** For multilingual messages

---

## 📝 Changelog

### Version 1.0.0 (December 2025)

#### Added
- ✅ Complete queue management system
- ✅ Rate limiting with configurable intervals
- ✅ Smart retry mechanism (3 attempts)
- ✅ Priority queue (urgent/normal/low)
- ✅ HTTP 429 handling with Retry-After
- ✅ Metadata support for tracking
- ✅ Admin panel with statistics
- ✅ Raw recipient support (groups)
- ✅ Automatic phone formatting
- ✅ Comprehensive logging
- ✅ Cleanup utilities
- ✅ WaSender API integration

#### Features
- 🔄 Automatic background processing via WP-Cron
- 📊 Real-time statistics dashboard
- 🔍 Status tracking per message
- 🪝 Action/filter hooks for extensibility
- 📱 WhatsApp group support
- ⚡ Optimized database queries with indexes
- 🛡️ SQL injection protection
- 🔐 Nonce verification for admin actions

---

## 📄 License

This plugin is licensed under the **GNU General Public License v2.0 or later**.

---

## 👤 Author

**Abdulrahman Roston**

- 🌐 Website: [abdulrahmanroston.com](https://abdulrahmanroston.com)
- 📧 Email: support@abdulrahmanroston.com
- 🐙 GitHub: [@abdulrahmanroston](https://github.com/abdulrahmanroston)

---

## 🤝 Contributing

Contributions are welcome!

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

---

## 📞 Support

- 🐛 Issues: [GitHub Issues](https://github.com/abdulrahmanroston/wa-simple_plugin/issues)
- 📧 Email: support@abdulrahmanroston.com
- 📚 Documentation: [Wiki](https://github.com/abdulrahmanroston/wa-simple_plugin/wiki)

---

## ⭐ Show Your Support

If this plugin helps you:

- ⭐ Star the repository
- 🐛 Report bugs
- 💡 Suggest features
- 📢 Share with others

---

## 🔗 Related Projects

- [Warehouses Manager Plugin](https://github.com/abdulrahmanroston/warehouses_manager_plugin) - Multi-warehouse inventory management
- [SHRMS Plugin](https://github.com/abdulrahmanroston/shrms_plugin) - HR Management System

---

**Made with ❤️ in Egypt 🇪🇬**

---

© 2025 Abdulrahman Roston. All rights reserved.