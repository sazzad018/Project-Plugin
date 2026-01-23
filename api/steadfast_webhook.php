<?php
/**
 * Steadfast Courier Webhook Handler
 * This script handles real-time status updates from Steadfast Courier.
 * Place this file in your /api/ folder.
 */

header('Content-Type: application/json');

// Read the incoming POST data (Steadfast sends data as POST)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// If data is not JSON, check standard POST (Steadfast sometimes sends Form Data)
if (!$data) {
    $data = $_POST;
}

// Check for required fields: 'invoice' and 'status'
if (isset($data['status']) && isset($data['invoice'])) {
    $invoiceId = strval($data['invoice']);
    $newStatus = $data['status'];
    $trackingCode = $data['tracking_code'] ?? '';

    // Path to your local tracking JSON file
    $jsonFilePath = 'local_tracking.json';
    
    if (file_exists($jsonFilePath)) {
        $jsonContent = file_get_contents($jsonFilePath);
        $trackingData = json_decode($jsonContent, true) ?: [];
        
        $updated = false;
        foreach ($trackingData as &$order) {
            // Match our order ID with Steadfast's invoice number
            if (strval($order['id']) === $invoiceId) {
                $order['courier_status'] = $newStatus;
                if (!empty($trackingCode)) {
                    $order['courier_tracking_code'] = $trackingCode;
                }
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            // Save the updated status back to the local tracking file
            file_put_contents($jsonFilePath, json_encode($trackingData, JSON_PRETTY_PRINT));
            
            echo json_encode([
                'status' => 'success', 
                'message' => "Order $invoiceId updated to $newStatus"
            ]);
        } else {
            echo json_encode([
                'status' => 'error', 
                'message' => "Invoice $invoiceId not found in our local tracking record"
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Local tracking data file (local_tracking.json) not found'
        ]);
    }
} else {
    // Bad request if required data is missing
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid request. "status" and "invoice" fields are required.'
    ]);
}
?>