<?php
/**
 * WooCommerce Order Invoices via WA Simple Queue
 *
 * مسئول عن:
 * - مراقبة إنشاء الطلبات (frontend + REST API)
 * - بناء رسائل الفاتورة (عميل + جروب)
 * - إرسالها إلى Queue باستخدام wa_send()
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Use same WhatsApp group ID as legacy code
if ( ! defined( 'WA_GROUP_ID' ) ) {
    define( 'WA_GROUP_ID', '120363423499532173@g.us' );
}

class WA_Order_Invoices {

    private static $instance = null;

    /**
     * Singleton instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Frontend checkout (يعمل بعد اكتمال الطلب من الواجهة)
        add_action( 'woocommerce_thankyou', array( $this, 'handle_new_order' ), 10, 1 );

        // Orders created via WooCommerce core REST API فقط
        add_action(
            'woocommerce_rest_insert_shop_order_object',
            array( $this, 'handle_rest_order' ),
            10,
            3
        );

        // ملاحظة: تم إلغاء الاعتماد على woocommerce_new_order لتجنب التكرار والرسائل المبكرة
        // add_action( 'woocommerce_new_order', array( $this, 'handle_new_order_generic' ), 10, 2 );
    }

    /**
     * Generic handler for any new order (all sources) - يُستخدم يدويًا من مسار FF فقط
     */
    public function handle_new_order_generic( $order_id, $order ) {
        if ( ! $order_id || ! $order instanceof WC_Order ) {
            return;
        }

        // لا نضع حارس هنا، الحارس مركزي داخل queue_order_invoices
        $this->queue_order_invoices( $order );
    }

    /**
     * Handle frontend orders (after checkout)
     */
    public function handle_new_order( $order_id ) {
        if ( ! $order_id ) {
            return;
        }

        // لا نضع حارس هنا، الحارس مركزي داخل queue_order_invoices
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $this->queue_order_invoices( $order );
    }

    /**
     * Handle orders created via REST API (Woo core)
     */
    public function handle_rest_order( $order, $request, $creating ) {
        error_log(
            'WA_Order_Invoices: handle_rest_order fired, creating=' .
            ( $creating ? '1' : '0' ) .
            ', order=' . $order->get_id()
        );

        // Only new orders, not updates
        if ( ! $creating ) {
            return;
        }

        // تخطّي أوامر FF Warehouses إن تم تمريرها هنا بالخطأ
        if ( $order->get_meta( '_ffw_warehouse_id' ) ) {
            return;
        }

        // لا نضع حارس هنا، الحارس مركزي داخل queue_order_invoices
        $this->queue_order_invoices( $order );
    }

    /**
     * Queue both customer + group invoice messages
     */
    private function queue_order_invoices( WC_Order $order ) {
        $order_id = $order->get_id();

        // حارس Idempotency مركزي: يمنع التكرار من أي مسار
        if ( ! add_post_meta( $order_id, '_wa_invoice_queued', 'yes', true ) ) {
            error_log( 'WA_Order_Invoices: already queued (central guard) for ' . $order_id );
            return;
        }
        update_post_meta( $order_id, '_wa_invoice_queued_time', current_time( 'mysql' ) );

        // قفل خفيف لكل طلب لتفادي السباق في نفس اللحظة (اختياري)
        $lock_key = 'wa_invoice_lock_' . $order_id;
        if ( get_transient( $lock_key ) ) {
            return;
        }
        set_transient( $lock_key, 1, 10 );

        // =========================
        // 1) Queue customer invoice
        // =========================
        $customer_phone    = $this->format_phone( $order->get_billing_phone() );
        $customer_queue_id = false;

        if ( $customer_phone ) {
            $customer_message = $this->build_customer_message( $order );

            $customer_queue_id = wa_send(
                $customer_phone,
                $customer_message,
                array(
                    'priority' => 'normal',
                    'metadata' => array(
                        'order_id' => $order_id,
                        'type'     => 'customer_invoice',
                    ),
                )
            );
        }

        // =========================
        // 2) Queue group invoice (no phone formatting)
        // =========================
        $group_id       = WA_GROUP_ID;
        $group_queue_id = false;

        error_log( 'WA_Order_Invoices: group_id = ' . $group_id );

        if ( ! empty( $group_id ) ) {
            $group_message = $this->build_group_message( $order );

            error_log( 'WA_Order_Invoices: queue group msg for order ' . $order_id );

            $group_queue_id = WA_Queue::add_raw(
                $group_id,
                $group_message,
                array(
                    'priority' => 'urgent',
                    'metadata' => array(
                        'order_id' => $order_id,
                        'type'     => 'group_invoice',
                        'channel'  => 'group',
                    ),
                )
            );

            error_log( 'WA_Order_Invoices: group_queue_id = ' . print_r( $group_queue_id, true ) );
        } else {
            error_log( 'WA_Order_Invoices: group_id is empty, skipping group message' );
        }

        // =========================
        // 3) Trigger async processing (non-blocking via wa-cron.php)
        // =========================
        $cron_url = home_url( '/wa-cron.php' );
        wp_remote_get(
            add_query_arg(
                array( 'manual' => '1' ),
                $cron_url
            ),
            array(
                'timeout'  => 0.01,
                'blocking' => false,
            )
        );
    }

    /**
     * Build customer invoice message
     */
    private function build_customer_message( WC_Order $order ) {
        $order_id = $order->get_id();

        $name = trim(
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
        );

        // Delivery date / time from billing/meta
        $date = $order->get_meta( '_billing_delivery_date' );
        if ( ! $date ) {
            $date = $order->get_meta( '_scl_delivery_date' );
        }
        if ( ! $date ) {
            $date = date( 'Y-m-d', strtotime( '+3 days' ) );
        }

        $time = $order->get_meta( '_billing_delivery_time' );
        if ( ! $time ) {
            $time = $order->get_meta( '_scl_delivery_time' );
        }
        if ( ! $time ) {
            $time = '2:00PM To 4:00PM';
        }

        // Items list
        $items_lines = '';
        foreach ( $order->get_items() as $item ) {
            $qty   = $item->get_quantity();
            $price = 0;

            if ( $qty > 0 ) {
                $price = $item->get_subtotal() / $qty;
            }

            $items_lines .= '• ' . $item->get_name() . '  ' . $qty . ' × ' .
                number_format( (float) $price, 2 ) . " EGP\n\n";
        }

        $subtotal = (float) $order->get_subtotal();
        $discount = (float) $order->get_discount_total();
        $total    = (float) $order->get_total();

        // Delivery fee من الشحن
        $delivery_fee = (float) $order->get_shipping_total();

        $message  = 'Hello ' . $name . ",\n\n";
        $message .= "We're happy to inform you that your order is ready and will be delivered on {$date} at {$time}.\n\n";
        $message .= "-----------\n";
        $message .= "Your Order:\n\n" . $items_lines;
        $message .= "-----------\n";
        $message .= 'Subtotal: ' . number_format( $subtotal, 2 ) . " EGP\n\n";

        if ( $discount > 0 ) {
            $coupons = implode( ', ', $order->get_coupon_codes() );
            if ( ! $coupons ) {
                $coupons = 'discount';
            }

            $message .= 'Discount (' . $coupons . '): -' .
                number_format( $discount, 2 ) . " EGP\n\n";
        }

        if ( $delivery_fee > 0 ) {
            $message .= 'Delivery Fee: ' .
                number_format( $delivery_fee, 2 ) . " EGP\n\n";
        }

        $message .= 'Total: ' . number_format( $total, 2 ) . " EGP\n";
        $message .= "-----------\n\n";
        $message .= "You can explore all our delicious frozen meals anytime at:\n";
        $message .= "https://tenderfrozen.com\n\n";
        $message .= "Thank you for choosing Tender Frozen!";

        return $message;
    }

    /**
     * Build group notification message (تنسيق عربي للجروب)
     */
    private function build_group_message( WC_Order $order ) {
        $order_id = $order->get_id();

        $name    = trim(
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
        );
        $phone   = $order->get_billing_phone();
        $address = $order->get_billing_address_1();

        // بيانات من الـ billing/meta
        $zone            = $order->get_meta( '_billing_zone' );
        $address_label   = $order->get_meta( '_billing_address_name' );
        $location_url    = $order->get_meta( '_billing_location_url' );
        $notes_customer  = $order->get_meta( '_billing_notes_customer' );
        $phone_secondary = $order->get_meta( '_billing_phone_secondary' );

        $date = $order->get_meta( '_billing_delivery_date' ) ?: $order->get_meta( '_scl_delivery_date' );
        $time = $order->get_meta( '_billing_delivery_time' ) ?: $order->get_meta( '_scl_delivery_time' );

        // Warehouse (لو حابب يظهر)
        $warehouse = $order->get_meta( '_selected_warehouse' );
        if ( ! $warehouse ) {
            $warehouse = $order->get_meta( '_ffw_warehouse_id' );
        }

        // Items
        $items_lines = '';
        foreach ( $order->get_items() as $item ) {
            
            $product_id = $item->get_product_id();
            
            $arabic_name = get_post_meta( $product_id, '_arabic_name', true );
            
            $product_name = ! empty( $arabic_name ) ? $arabic_name : $item->get_name();
            
            $items_lines .= '• ' . $product_name . ' × ' .
                $item->get_quantity() . "\n";
        }


        $total     = (float) $order->get_total();
        $payment   = $order->get_payment_method_title();
        $total_egp = number_format( $total, 2 );

        $message = "🔔 *طلب جديد #{$order_id}*\n\n";

        if ( $date || $time ) {
            $message .= "📅  {$date} | {$time}\n\n";
        }

        $message .= "👤 *العميل:* {$name}\n";
        $message .= "📱 *الهاتف:* {$phone}\n";

        if ( $phone_secondary ) {
            $message .= "📱 *هاتف إضافي:* {$phone_secondary}\n";
        }

        if ( $zone ) {
            $message .= "📍 *المنطقة:* {$zone}\n";
        }

        if ( $address_label ) {
            $message .= "🏷 *اسم العنوان:* {$address_label}\n";
        }

        $message .= "🏠 *العنوان:* {$address}\n";

        if ( $location_url ) {
            $message .= "📎 *لوكيشن:* {$location_url}\n";
        }

        if ( $warehouse ) {
            $message .= "\n🏭 *المخزن:* {$warehouse}\n";
        }

        $message .= "\n📦 *المنتجات:*\n{$items_lines}\n";
        $message .= "💰 *الإجمالي:* {$total_egp} جنيه\n";
        $message .= "💳 *الدفع:* {$payment}\n";

        if ( $notes_customer ) {
            $message .= "\n📝 *ملاحظات العميل:* {$notes_customer}\n";
        }

        return $message;
    }

    /**
     * Basic phone formatter – يعتمد على منطق بسيط شبيه بالقديم
     * يستخدم لأرقام العملاء فقط (ليس للجروب)
     */
    private function format_phone( $phone ) {
        $phone = preg_replace( '/[^0-9]/', '', (string) $phone );
        if ( empty( $phone ) ) {
            return false;
        }

        // Egyptian defaults like old code
        if ( strlen( $phone ) === 11 && $phone[0] === '0' ) {
            $phone = '20' . substr( $phone, 1 );
        } elseif ( strlen( $phone ) === 10 ) {
            $phone = '20' . $phone;
        } elseif ( substr( $phone, 0, 2 ) !== '20' ) {
            $phone = '20' . $phone;
        }

        return '+' . $phone;
    }
}
