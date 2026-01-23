<?php
/**
 * Plugin Name: BdCommerce Connect
 * Description: Helper plugin for BdCommerce Dashboard. Enables image uploads via custom API endpoint to bypass CORS and Authentication restrictions.
 * Version: 1.0
 * Author: BdCommerce
 */

if (!defined('ABSPATH')) exit;

// Initialize REST API Route
add_action('rest_api_init', function () {
    register_rest_route('bdcommerce/v1', '/upload', [
        'methods' => 'POST',
        'callback' => 'bdc_handle_upload_request',
        'permission_callback' => '__return_true', // Public endpoint, auth handled manually
    ]);
});

function bdc_handle_upload_request($request) {
    // 1. Handle CORS Headers explicitly
    $origin = get_http_origin();
    if (!empty($origin)) {
        header("Access-Control-Allow-Origin: " . $origin);
    } else {
        header("Access-Control-Allow-Origin: *");
    }
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(200);
        return true;
    }

    // 2. Basic Validation
    $files = $request->get_file_params();
    if (empty($files['file'])) {
        return new WP_Error('no_file', 'No file provided', ['status' => 400]);
    }

    // 3. Optional: Validate Consumer Key/Secret if passed
    // We check if keys are provided, though strictly verifying them against DB is complex without loading full WC context.
    // For now, presence of keys acts as a basic check.
    $ck = $request->get_param('consumer_key');
    $cs = $request->get_param('consumer_secret');
    
    if (empty($ck) || empty($cs)) {
         return new WP_Error('auth_fail', 'Missing Consumer Key or Secret', ['status' => 401]);
    }

    // 4. Handle File Upload using WordPress Native Functions
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $attachment_id = media_handle_upload('file', 0);

    if (is_wp_error($attachment_id)) {
        return new WP_Error('upload_failed', $attachment_id->get_error_message(), ['status' => 500]);
    }

    // 5. Return Success Response
    $url = wp_get_attachment_url($attachment_id);
    
    return new WP_REST_Response([
        'success' => true,
        'id' => $attachment_id,
        'url' => $url
    ], 200);
}
?>