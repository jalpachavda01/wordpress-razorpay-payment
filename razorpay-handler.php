<?php
/**
 * Razorpay Custom Payment Integration
 * Author: JAzz N
 * Description: Handles Razorpay payment verification, PDF receipt generation,
 * email notification, and database storage.
 */

add_action('wp_ajax_razorpay_payment', 'handle_razorpay_payment');
add_action('wp_ajax_nopriv_razorpay_payment', 'handle_razorpay_payment');

function handle_razorpay_payment() {

    // 🔐 Get POST data safely
    $entry_id  = intval($_POST['entry_id'] ?? 0);
    $payment_id = sanitize_text_field($_POST['razorpay_payment_id'] ?? '');
    $order_id   = sanitize_text_field($_POST['razorpay_order_id'] ?? '');
    $signature  = sanitize_text_field($_POST['razorpay_signature'] ?? '');

    if (!$payment_id || !$order_id || !$signature) {
        wp_send_json_error('Missing payment data');
    }

    // 🔐 Use ENV / wp-config.php for secret
    $key_id     = 'YOUR_TEST_KEY_ID';
    $key_secret = 'YOUR_SECRET_KEY';

    // ✅ Verify signature
    $data = $order_id . '|' . $payment_id;
    $generated_signature = hash_hmac('sha256', $data, $key_secret);

    if (!hash_equals($generated_signature, $signature)) {
        wp_send_json_error('Invalid payment signature');
    }

    // ✅ Fetch payment details from Razorpay
    $ch = curl_init("https://api.razorpay.com/v1/payments/$payment_id");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$key_id:$key_secret",
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        wp_send_json_error('Curl error: ' . curl_error($ch));
    }

    curl_close($ch);

    $payment_details = json_decode($response, true);

    if (empty($payment_details['method'])) {
        wp_send_json_error('Unable to fetch payment details');
    }

    $method = $payment_details['method'] ?? '';
    $fee    = $payment_details['fee'] ?? 0;
    $tax    = $payment_details['tax'] ?? 0;

    // ✅ Get stored donor info
    $stored = get_transient('razorpay_order_' . $entry_id);

    if (!$stored) {
        wp_send_json_error('Missing order data');
    }

    $name     = sanitize_text_field($stored['name']);
    $email    = sanitize_email($stored['email']);
    $currency = strtoupper($stored['currency']);
    $amount   = $stored['amount'] / 100;
    $project  = sanitize_text_field($stored['project']);

    $date    = date('d/m/Y');
    $receipt = 'RCPT-' . str_pad($entry_id, 5, '0', STR_PAD_LEFT);

    // ✅ Generate PDF (optional)
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/donation-receipts';

    if (!file_exists($pdf_dir)) {
        mkdir($pdf_dir, 0755, true);
    }

    $file_path = $pdf_dir . "/{$receipt}.pdf";

    try {
        if (class_exists('\Mpdf\Mpdf')) {
            $mpdf = new \Mpdf\Mpdf();
            $mpdf->WriteHTML("<h2>Donation Receipt</h2><p>Thank you {$name}</p>");
            $mpdf->Output($file_path, \Mpdf\Output\Destination::FILE);
        }
    } catch (\Exception $e) {
        // Don't stop payment if PDF fails
    }

    // ✅ Send email
    $subject = 'Donation Received';
    $message = "Thank you {$name}, we received your donation of {$currency} {$amount}.";

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $attachments = file_exists($file_path) ? [$file_path] : [];

    wp_mail($email, $subject, $message, $headers, $attachments);

    // ✅ Save to database
    global $wpdb;
    $table = $wpdb->prefix . 'donation_entries';

    $inserted = $wpdb->insert($table, [
        'name'           => $name,
        'email'          => $email,
        'project'        => $project,
        'amount'         => $amount,
        'currency'       => $currency,
        'payment_id'     => $payment_id,
        'order_id'       => $order_id,
        'signature'      => $signature,
        'payment_method' => $method,
        'fee'            => intval($fee),
        'tax'            => intval($tax),
        'status'         => 'success',
        'created_at'     => current_time('mysql'),
    ]);

    if (!$inserted) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    }

    // ✅ Cleanup
    delete_transient('razorpay_order_' . $entry_id);

    wp_send_json_success('Payment successful');
}
