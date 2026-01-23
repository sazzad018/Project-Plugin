<?php
/**
 * Pathao Webhook Handler
 * This file handles real-time order status updates from Pathao Courier.
 * Returns 202 Accepted to satisfy Pathao's requirement.
 */

// ১. পাঠাও-এর রিকোয়ারমেন্ট অনুযায়ী প্রথমেই ২০২ স্ট্যাটাস কোড পাঠানো হচ্ছে
http_response_code(202);

// ২. কন্টেন্ট টাইপ এবং ভেরিফিকেশন সিক্রেট সেট করা
header('Content-Type: application/json');

// আপনার ভেরিফিকেশন সিক্রেটটি এখানে দিন (এটি সেটিংস থেকে ডায়নামিক করা যেতে পারে)
$webhookSecret = "f3992ecc-59da-4cbe-a049-a13da2018d51"; 

// পাঠাও এই হেডারটি রেসপন্সে আশা করে
header("X-Pathao-Merchant-Webhook-Integration-Secret: " . $webhookSecret);

// ৩. ইনকামিং ডাটা গ্রহণ করা
$json = file_get_contents('php://input');
$payload = json_decode($json, true);

if ($payload) {
    // ডাটা থেকে প্রয়োজনীয় তথ্য বের করা
    $orderId = $payload['merchant_order_id'] ?? null;
    $trackingCode = $payload['consignment_id'] ?? null;
    $event = $payload['event'] ?? 'unknown';

    if ($orderId && $trackingCode) {
        // লোকাল ট্র্যাকিং ফাইল আপডেট করা
        $localTrackingFile = 'local_tracking.json'; 
        $trackingData = [];
        
        if (file_exists($localTrackingFile)) {
            $trackingData = json_decode(file_get_contents($localTrackingFile), true) ?: [];
        }

        $found = false;
        foreach ($trackingData as &$item) {
            if ((string)$item['id'] === (string)$orderId) {
                $item['courier_status'] = $event;
                $item['courier_tracking_code'] = $trackingCode;
                $item['courier_name'] = 'Pathao';
                $found = true;
                break;
            }
        }

        if (!$found) {
            $trackingData[] = [
                'id' => $orderId,
                'courier_tracking_code' => $trackingCode,
                'courier_status' => $event,
                'courier_name' => 'Pathao'
            ];
        }

        // ফাইল সেভ করা
        file_put_contents($localTrackingFile, json_encode($trackingData, JSON_PRETTY_PRINT));
    }
}

// সাকসেস মেসেজ প্রিন্ট করা (যদিও ২০২ অলরেডি চলে গেছে)
echo json_encode(["status" => "success", "message" => "Webhook received"]);
exit;