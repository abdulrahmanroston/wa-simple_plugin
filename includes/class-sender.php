<?php
/**
 * API Sender
 */

if (!defined('ABSPATH')) exit;

class WA_Sender {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Send message via API
     */
    public static function send($phone, $message) {
        $api_key = WA_Simple::option('api_key');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'error'   => 'API key not configured',
            );
        }
        
        $url = 'https://wasenderapi.com/api/send-message';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode(array(
                'to'   => $phone,
                'text' => $message,
            )),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            error_log('WA_Sender::send WP_Error: ' . $error);
            return array(
                'success' => false,
                'error'   => $error,
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // 2xx => OK
        if ($code >= 200 && $code < 300) {
            return array('success' => true);
        }

        // خصّص التعامل مع 429: اقرأ Retry-After من الهيدر أو من JSON body
        if ($code === 429) {
            $retry_after = 0;

            // 1) حاول من الهيدر Retry-After
            $headers = wp_remote_retrieve_headers($response);
            if (is_array($headers) && isset($headers['retry-after'])) {
                $retry_after = (int) $headers['retry-after']; // ثوانٍ
            } elseif (is_object($headers) && isset($headers->offsetGet)) {
                $h = $headers->offsetGet('retry-after');
                if ($h !== null) {
                    $retry_after = (int) $h;
                }
            }

            // 2) ولو غير متاح بالهيدر، جرّب من JSON body (كما يفعل بعض المزودين)
            if ($retry_after <= 0 && !empty($body)) {
                $data = json_decode($body, true);
                if (is_array($data) && isset($data['retry_after'])) {
                    $retry_after = (int) $data['retry_after'];
                }
            }

            error_log('WA_Sender::send HTTP 429 retry_after=' . $retry_after . ' body: ' . substr($body, 0, 300));

            return array(
                'success'     => false,
                'error'       => "HTTP 429: $body",
                'retry_after' => max(0, $retry_after),
            );
        }

        // غير ذلك: سجل وأعد الخطأ
        error_log('WA_Sender::send HTTP ' . $code . ' body: ' . substr($body, 0, 300));

        return array(
            'success' => false,
            'error'   => "HTTP $code: $body",
        );
    }
}
