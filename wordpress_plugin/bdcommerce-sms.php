<?php
/**
 * Plugin Name: BdCommerce SMS Manager
 * Plugin URI:  https://bdcommerce.com
 * Description: A complete SMS & Customer Management solution. Sync customers and send Bulk SMS by relaying requests through your Main Dashboard. Includes Live Capture & Fraud Check in Orders.
 * Version:     2.1.0
 * Author:      BdCommerce
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class BDC_SMS_Manager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'bdc_customers';

        // Declare HPOS Compatibility
        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            }
        } );

        // Hooks
        register_activation_hook( __FILE__, array( $this, 'create_tables' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // AJAX Handlers
        add_action( 'wp_ajax_bdc_sync_customers', array( $this, 'ajax_sync_customers' ) );
        add_action( 'wp_ajax_bdc_send_sms', array( $this, 'ajax_send_sms' ) );
        
        // OTP AJAX
        add_action( 'wp_ajax_bdc_send_otp', array( $this, 'ajax_send_otp' ) );
        add_action( 'wp_ajax_nopriv_bdc_send_otp', array( $this, 'ajax_send_otp' ) );
        add_action( 'wp_ajax_bdc_verify_otp', array( $this, 'ajax_verify_otp' ) );
        add_action( 'wp_ajax_nopriv_bdc_verify_otp', array( $this, 'ajax_verify_otp' ) );

        // Live Capture Injection
        add_action( 'wp_footer', array( $this, 'inject_live_capture_script' ) );
        
        // Remove Lead on Successful Order
        add_action( 'woocommerce_new_order', array( $this, 'remove_lead_on_order_success' ), 10, 2 );

        // WooCommerce Order Column Hooks (Legacy & HPOS)
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_fraud_check_column' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_fraud_check_column_legacy' ), 10, 2 );
        
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_fraud_check_column' ) );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_fraud_check_column_hpos' ), 10, 2 );

        // Checkout Validation (Server Side Backup)
        add_action( 'woocommerce_checkout_process', array( $this, 'execute_fraud_guard_checks' ) );
    }

    /**
     * Create Database Table for Customers
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            email varchar(100) DEFAULT '',
            total_spent decimal(10,2) DEFAULT 0,
            order_count int DEFAULT 0,
            last_order_date datetime DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY phone (phone)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Add Menu Page
     */
    public function add_admin_menu() {
        add_menu_page(
            'SMS Manager',
            'SMS Manager',
            'manage_options',
            'bdc-sms-manager',
            array( $this, 'render_dashboard' ),
            'dashicons-smartphone',
            56
        );
    }

    /**
     * Register Settings
     */
    public function register_settings() {
        // Main Config
        register_setting( 'bdc_sms_group', 'bdc_dashboard_url' );
        
        // Fraud Guard Settings
        register_setting( 'bdc_fraud_group', 'bdc_fraud_phone_validation' ); // 11 Digit Check
        register_setting( 'bdc_fraud_group', 'bdc_fraud_history_check' ); // Enable History Check
        register_setting( 'bdc_fraud_group', 'bdc_fraud_min_rate' ); // Min % Threshold
        register_setting( 'bdc_fraud_group', 'bdc_fraud_disable_cod' ); // Disable COD for high risk
        register_setting( 'bdc_fraud_group', 'bdc_fraud_enable_otp' ); // Enable OTP for high risk
    }

    /**
     * FRAUD GUARD: Server Side Validation
     */
    public function execute_fraud_guard_checks() {
        $billing_phone = isset( $_POST['billing_phone'] ) ? $_POST['billing_phone'] : '';
        $clean_phone = preg_replace( '/[^0-9]/', '', $billing_phone );

        // 1. Validate 11-Digit Length
        if ( get_option( 'bdc_fraud_phone_validation' ) ) {
            if ( strlen( $clean_phone ) < 11 ) {
                wc_add_notice( __( '<strong>Mobile Number Error:</strong> Please enter a valid 11-digit mobile number.', 'bdc-sms' ), 'error' );
                return;
            }
        }

        // If verified by OTP on frontend, skip backend check
        if ( isset($_POST['bdc_otp_verified']) && $_POST['bdc_otp_verified'] === 'true' ) {
            return;
        }

        // 2. Validate Delivery History & Payment Method
        if ( get_option( 'bdc_fraud_history_check' ) ) {
            $api_base = $this->get_api_base_url();
            if ( ! $api_base ) return;

            $response = wp_remote_get( $api_base . '/check_fraud.php?phone=' . $clean_phone, array( 'timeout' => 15, 'sslverify' => false ) );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body, true );

                if ( isset( $data['success_rate'] ) && isset( $data['total_orders'] ) ) {
                    $total_orders = intval( $data['total_orders'] );
                    $success_rate = floatval( $data['success_rate'] );
                    $min_rate = intval( get_option( 'bdc_fraud_min_rate', 50 ) );

                    if ( $total_orders > 0 && $success_rate < $min_rate ) {
                        // Check COD Restriction
                        if ( get_option('bdc_fraud_disable_cod') ) {
                            $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
                            if ( $payment_method === 'cod' ) {
                                wc_add_notice( __( '<strong>Order Restricted:</strong> Due to delivery history, Cash on Delivery is unavailable. Please select an online payment method.', 'bdc-sms' ), 'error' );
                            }
                        }
                        
                        // OTP check is mainly frontend, but if enabled and not verified, block here as fallback
                        if ( get_option('bdc_fraud_enable_otp') && (!isset($_POST['bdc_otp_verified']) || $_POST['bdc_otp_verified'] !== 'true') ) {
                             wc_add_notice( __( '<strong>Verification Required:</strong> Please complete the SMS verification to place this order.', 'bdc-sms' ), 'error' );
                        }
                    }
                }
            }
        }
    }

    /**
     * AJAX: Send OTP
     */
    public function ajax_send_otp() {
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        if(!$phone) wp_send_json_error("Phone number required");

        $otp = rand(1000, 9999);
        
        // Store in Session
        if ( !WC()->session ) WC()->session = new WC_Session_Handler();
        WC()->session->set( 'bdc_otp_code', $otp );
        WC()->session->set( 'bdc_otp_phone', $phone );

        $message = "Your verification code is: " . $otp;
        $api_base = $this->get_api_base_url();
        
        if(!$api_base) wp_send_json_error("SMS API not configured");

        $formatted_phone = $phone;
        if ( strlen( $formatted_phone ) == 11 && substr( $formatted_phone, 0, 2 ) == '01' ) $formatted_phone = '88' . $formatted_phone;

        // Send SMS
        wp_remote_post( $api_base . '/send_sms.php', array(
            'body' => json_encode(array("contacts" => $formatted_phone, "msg" => $message, "type" => 'text')),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 15, 'sslverify' => false
        ));

        wp_send_json_success("OTP Sent");
    }

    /**
     * AJAX: Verify OTP
     */
    public function ajax_verify_otp() {
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

        if ( !WC()->session ) WC()->session = new WC_Session_Handler();
        $stored_otp = WC()->session->get( 'bdc_otp_code' );
        $stored_phone = WC()->session->get( 'bdc_otp_phone' );

        if( $stored_otp && $code == $stored_otp && $phone == $stored_phone ) {
            wp_send_json_success("Verified");
        } else {
            wp_send_json_error("Invalid Code");
        }
    }

    /**
     * Enqueue Scripts and Styles
     */
    public function enqueue_assets( $hook ) {
        // Enqueue Front-end Checkout Script
        if ( is_checkout() ) {
            $api_base = $this->get_api_base_url();
            if($api_base) {
                $check_history = get_option('bdc_fraud_history_check') ? 'yes' : 'no';
                $disable_cod = get_option('bdc_fraud_disable_cod') ? 'yes' : 'no';
                $enable_otp = get_option('bdc_fraud_enable_otp') ? 'yes' : 'no';
                $min_rate = get_option('bdc_fraud_min_rate', 50);

                wp_enqueue_script('jquery');
                
                // Add Inline OTP CSS
                $custom_css = "
                    .bdc-otp-wrapper {
                        background: #f8fafc;
                        border: 1px solid #e2e8f0;
                        border-radius: 6px;
                        padding: 15px;
                        margin-top: 10px;
                        margin-bottom: 10px;
                        display: none;
                        animation: fadeIn 0.3s ease;
                    }
                    .bdc-otp-msg {
                        font-size: 13px;
                        color: #dc2626;
                        margin-bottom: 10px;
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        gap: 5px;
                    }
                    .bdc-otp-row { display: flex; gap: 10px; }
                    .bdc-otp-input { 
                        flex: 1; 
                        padding: 8px; 
                        text-align: center; 
                        letter-spacing: 2px; 
                        border: 1px solid #cbd5e1; 
                        border-radius: 4px;
                    }
                    .bdc-btn-otp {
                        background: #ea580c;
                        color: white;
                        border: none;
                        padding: 8px 15px;
                        border-radius: 4px;
                        font-size: 12px;
                        cursor: pointer;
                        font-weight: 600;
                    }
                    .bdc-btn-otp:hover { background: #c2410c; }
                    .bdc-btn-otp:disabled { opacity: 0.7; cursor: not-allowed; }
                    .bdc-verified-badge {
                        background: #dcfce7;
                        color: #166534;
                        padding: 8px;
                        border-radius: 6px;
                        text-align: center;
                        font-weight: bold;
                        border: 1px solid #bbf7d0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 6px;
                        margin-top: 5px;
                    }
                    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
                ";
                wp_add_inline_style('woocommerce-general', $custom_css);

                // Add Checkout JS
                wp_add_inline_script('jquery', '
                    jQuery(document).ready(function($) {
                        var fraudConfig = {
                            check: "'.$check_history.'",
                            disableCod: "'.$disable_cod.'",
                            enableOtp: "'.$enable_otp.'",
                            minRate: '.$min_rate.',
                            apiUrl: "'.esc_url($api_base . '/check_fraud.php').'"
                        };
                        var isOtpVerified = false;
                        var checkedPhones = {};
                        var lastCheckedPhone = "";

                        // Inject Hidden verified field
                        $("form.checkout").append("<input type=\"hidden\" name=\"bdc_otp_verified\" id=\"bdc_otp_verified\" value=\"false\">");

                        // Monitor Phone Field
                        $("#billing_phone").on("change blur", function() {
                            var phone = $(this).val().replace(/[^0-9]/g, "");
                            
                            // Reset verification if number changes
                            if(lastCheckedPhone && lastCheckedPhone !== phone) {
                                isOtpVerified = false;
                                $("#bdc_otp_verified").val("false");
                                $("#bdc-inline-otp").remove();
                                $("form.checkout button[type=\"submit\"]").prop("disabled", false);
                            }

                            if(phone.length >= 11 && fraudConfig.check === "yes") {
                                lastCheckedPhone = phone;
                                checkFraudStatus(phone);
                            }
                        });

                        function checkFraudStatus(phone) {
                            if(checkedPhones[phone]) {
                                handleRisk(checkedPhones[phone], phone);
                                return;
                            }

                            // Show loading indicator? Maybe not to disturb UI too much
                            
                            $.ajax({
                                url: fraudConfig.apiUrl,
                                data: { phone: phone },
                                dataType: "json",
                                success: function(data) {
                                    var status = "safe";
                                    if(data.success_rate && data.total_orders > 0 && parseFloat(data.success_rate) < fraudConfig.minRate) {
                                        status = "risk";
                                    }
                                    checkedPhones[phone] = status;
                                    handleRisk(status, phone);
                                }
                            });
                        }

                        function handleRisk(status, phone) {
                            // Clear previous
                            $("#bdc-inline-otp").remove();

                            if(status === "risk" && fraudConfig.enableOtp === "yes") {
                                // Inject OTP Field
                                var otpHtml = `
                                    <div id="bdc-inline-otp" class="bdc-otp-wrapper">
                                        <div class="bdc-otp-msg">
                                            <span class="dashicons dashicons-warning"></span>
                                            Delivery History Check Failed. Verification Required.
                                        </div>
                                        <div id="bdc-otp-step-1">
                                            <p style="font-size:11px; margin-bottom:5px; color:#64748b;">We need to verify your phone number to proceed with this order.</p>
                                            <button type="button" id="bdc-send-btn" class="bdc-btn-otp" style="width:100%;">Send OTP via SMS</button>
                                        </div>
                                        <div id="bdc-otp-step-2" style="display:none;">
                                            <p style="font-size:11px; margin-bottom:5px; color:#64748b;">Enter code sent to ` + phone + `</p>
                                            <div class="bdc-otp-row">
                                                <input type="text" id="bdc-otp-input" class="bdc-otp-input" placeholder="XXXX" maxlength="4">
                                                <button type="button" id="bdc-verify-btn" class="bdc-btn-otp">Verify</button>
                                            </div>
                                            <div style="margin-top:5px; display:flex; justify-content:space-between;">
                                                <small id="bdc-otp-error" style="color:red; display:none;">Invalid Code</small>
                                                <button type="button" id="bdc-resend-link" style="background:none; border:none; color:#2563eb; font-size:10px; cursor:pointer; text-decoration:underline;">Resend?</button>
                                            </div>
                                        </div>
                                        <div id="bdc-otp-step-3" style="display:none;">
                                            <div class="bdc-verified-badge">
                                                <span class="dashicons dashicons-yes"></span> Verified Successfully
                                            </div>
                                        </div>
                                    </div>
                                `;
                                
                                $("#billing_phone_field").append(otpHtml);
                                $("#bdc-inline-otp").show();
                                
                                // Disable Place Order until verified
                                $("form.checkout button[type=\"submit\"]").prop("disabled", true);

                                // Attach Events
                                $("#bdc-send-btn, #bdc-resend-link").click(function() {
                                    var btn = $(this);
                                    btn.text("Sending...");
                                    $.post("'.admin_url('admin-ajax.php').'", {
                                        action: "bdc_send_otp",
                                        phone: phone
                                    }, function(res) {
                                        if(res.success) {
                                            $("#bdc-otp-step-1").hide();
                                            $("#bdc-otp-step-2").show();
                                            $("#bdc-otp-input").focus();
                                        } else {
                                            alert("Failed to send OTP.");
                                            btn.text("Send OTP via SMS");
                                        }
                                    });
                                });

                                $("#bdc-verify-btn").click(function() {
                                    var code = $("#bdc-otp-input").val();
                                    var btn = $(this);
                                    btn.text("...");
                                    
                                    $.post("'.admin_url('admin-ajax.php').'", {
                                        action: "bdc_verify_otp",
                                        code: code,
                                        phone: phone
                                    }, function(res) {
                                        if(res.success) {
                                            $("#bdc-otp-step-2").hide();
                                            $("#bdc-otp-step-3").show();
                                            
                                            // Unlock Order
                                            isOtpVerified = true;
                                            $("#bdc_otp_verified").val("true");
                                            $("form.checkout button[type=\"submit\"]").prop("disabled", false);
                                        } else {
                                            btn.text("Verify");
                                            $("#bdc-otp-error").show();
                                        }
                                    });
                                });
                            }
                        }

                        // Also block submission if risk detected and not verified (double safety)
                        $("form.checkout").on("checkout_place_order", function() {
                            var phone = $("#billing_phone").val().replace(/[^0-9]/g, "");
                            var status = checkedPhones[phone];
                            
                            if(status === "risk" && fraudConfig.enableOtp === "yes" && !isOtpVerified) {
                                $("html, body").animate({
                                    scrollTop: $("#billing_phone").offset().top - 100
                                }, 500);
                                if($("#bdc-inline-otp").length === 0) {
                                    handleRisk("risk", phone);
                                }
                                return false;
                            }
                            return true;
                        });
                    });
                ');
            }
        }

        // Admin Scripts (Existing)
        $screen = get_current_screen();
        $is_order_page = ( 
            ( $hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order' ) || 
            ( $screen && $screen->id === 'woocommerce_page_wc-orders' ) 
        );

        if ( 'toplevel_page_bdc-sms-manager' === $hook || $is_order_page ) {
            
            // Script for Fraud Check Column (Only on order list page)
            if( $is_order_page ) {
                // Add Professional Styles
                wp_register_style( 'bdc-admin-styles', false );
                wp_enqueue_style( 'bdc-admin-styles' );
                $custom_css = "
                    /* Fix Column Width to prevent overlap */
                    .column-bdc_fraud_check { 
                        width: 260px !important; 
                        min-width: 260px !important; 
                    }

                    .bdc-fraud-wrapper { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
                    
                    .bdc-fraud-result-card {
                        background: #ffffff;
                        border: 1px solid #e2e8f0;
                        border-radius: 8px;
                        padding: 12px;
                        width: 100%;
                        max-width: 240px;
                        box-sizing: border-box;
                        box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.05);
                        margin-top: 5px;
                        margin-bottom: 5px;
                        position: relative;
                        z-index: 10;
                    }
                    
                    .bdc-fraud-top {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        margin-bottom: 12px;
                    }
                    
                    .bdc-rate-group {
                        display: flex;
                        flex-direction: column;
                    }
                    
                    .bdc-rate-label {
                        font-size: 10px;
                        font-weight: 700;
                        text-transform: uppercase;
                        color: #64748b;
                        letter-spacing: 0.5px;
                        margin-bottom: 2px;
                    }
                    
                    .bdc-rate-val {
                        font-size: 20px;
                        font-weight: 800;
                        line-height: 1;
                        display: flex;
                        align-items: center;
                        gap: 4px;
                    }
                    
                    .bdc-total-orders {
                        font-size: 10px;
                        font-weight: 700;
                        color: #1e293b;
                        background: #f1f5f9;
                        padding: 3px 6px;
                        border-radius: 4px;
                        white-space: nowrap;
                    }
                    
                    .bdc-fraud-bottom {
                        display: flex;
                        gap: 6px;
                    }
                    
                    .bdc-stat-box {
                        flex: 1;
                        padding: 6px;
                        border-radius: 6px;
                        text-align: center;
                    }
                    
                    .bdc-stat-box.delivered {
                        background: #dcfce7; /* Green 100 */
                        border: 1px solid #bbf7d0;
                    }
                    
                    .bdc-stat-box.cancelled {
                        background: #fee2e2; /* Red 100 */
                        border: 1px solid #fecaca;
                    }
                    
                    .bdc-stat-title {
                        font-size: 8px;
                        font-weight: 800;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        margin-bottom: 2px;
                        display: block;
                    }
                    
                    .bdc-stat-num {
                        font-size: 14px;
                        font-weight: 800;
                        display: block;
                    }
                    
                    .bdc-stat-box.delivered .bdc-stat-title { color: #166534; }
                    .bdc-stat-box.delivered .bdc-stat-num { color: #15803d; }
                    
                    .bdc-stat-box.cancelled .bdc-stat-title { color: #991b1b; }
                    .bdc-stat-box.cancelled .bdc-stat-num { color: #b91c1c; }
                    
                    .bdc-spin { animation: bdc-spin 1s infinite linear; }
                    @keyframes bdc-spin { 100% { transform: rotate(360deg); } }
                    
                    /* New Autoload Styling */
                    .bdc-fraud-loading {
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 6px 12px;
                        background: #f8fafc;
                        border: 1px solid #e2e8f0;
                        border-radius: 6px;
                        font-size: 12px;
                        color: #64748b;
                        font-weight: 600;
                    }
                ";
                wp_add_inline_style( 'bdc-admin-styles', $custom_css );

                $api_base = $this->get_api_base_url();
                if($api_base) {
                    $api_url = esc_url($api_base . '/check_fraud.php');
                    
                    wp_add_inline_script('jquery', '
                        jQuery(document).ready(function($) {
                            
                            function loadFraudData(container) {
                                var phone = container.data("phone");
                                if(!phone) return;

                                console.log("BDC: Checking fraud for " + phone);

                                $.ajax({
                                    url: "' . $api_url . '",
                                    data: { phone: phone },
                                    dataType: "json",
                                    timeout: 20000, // 20s timeout for JS as well
                                    success: function(data) {
                                        if(data.error) {
                                            container.html("<span style=\"color:#d63638; font-weight:bold; font-size:11px;\">⚠️ " + data.error + "</span>");
                                            return;
                                        }
                                        
                                        var rate = parseFloat(data.success_rate);
                                        var rateColor = rate >= 80 ? "#16a34a" : (rate < 50 ? "#dc2626" : "#ca8a04");
                                        var shieldIcon = rate >= 80 ? "dashicons-shield" : (rate < 50 ? "dashicons-warning" : "dashicons-shield-alt");

                                        var html = "<div class=\"bdc-fraud-result-card\">";
                                        
                                        // Top Row
                                        html += "<div class=\"bdc-fraud-top\">";
                                        html += "  <div class=\"bdc-rate-group\">";
                                        html += "    <span class=\"bdc-rate-label\">Success Rate</span>";
                                        html += "    <span class=\"bdc-rate-val\" style=\"color: " + rateColor + "\"><span class=\"dashicons " + shieldIcon + "\"></span> " + rate + "%</span>";
                                        html += "  </div>";
                                        html += "  <div class=\"bdc-total-orders\">" + data.total_orders + " Orders</div>";
                                        html += "</div>";
                                        
                                        // Bottom Row
                                        html += "<div class=\"bdc-fraud-bottom\">";
                                        
                                        // Delivered Box
                                        html += "  <div class=\"bdc-stat-box delivered\">";
                                        html += "    <span class=\"bdc-stat-title\">Delivered</span>";
                                        html += "    <span class=\"bdc-stat-num\">" + data.delivered + "</span>";
                                        html += "  </div>";
                                        
                                        // Cancelled Box
                                        html += "  <div class=\"bdc-stat-box cancelled\">";
                                        html += "    <span class=\"bdc-stat-title\">Cancelled</span>";
                                        html += "    <span class=\"bdc-stat-num\">" + data.cancelled + "</span>";
                                        html += "  </div>";
                                        
                                        html += "</div>"; // End Bottom
                                        html += "</div>"; // End Card
                                        
                                        container.html(html);
                                    },
                                    error: function(xhr, status, error) {
                                        console.error("BDC Fraud Check Error:", status, error);
                                        var msg = status === "timeout" ? "Timeout" : "Network Error";
                                        container.html("<div style=\"display:flex; flex-direction:column; gap:5px;\"><span style=\"color:#d63638; font-size:10px;\">⚠️ " + msg + "</span><button type=\"button\" class=\"button button-small bdc-retry-btn\">Retry</button></div>");
                                        
                                        container.find(".bdc-retry-btn").click(function(e){
                                            e.preventDefault();
                                            container.html("<div class=\"bdc-fraud-loading\"><span class=\"dashicons dashicons-update bdc-spin\"></span> Retry...</div>");
                                            loadFraudData(container);
                                        });
                                    }
                                });
                            }

                            // Auto load on page ready
                            $(".bdc-fraud-autoload").each(function() {
                                loadFraudData($(this));
                            });
                        });
                    ');
                }
            }
        }
    }

    /**
     * Add Custom Column to Orders
     */
    public function add_fraud_check_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $column ) {
            $new_columns[$key] = $column;
            // Insert after Order Status
            if ( 'order_status' === $key ) {
                $new_columns['bdc_fraud_check'] = __( 'Fraud Check', 'bdc-sms' );
            }
        }
        if(!isset($new_columns['bdc_fraud_check'])) {
             $new_columns['bdc_fraud_check'] = __( 'Fraud Check', 'bdc-sms' );
        }
        return $new_columns;
    }

    /**
     * Render Custom Column Data (Legacy)
     */
    public function render_fraud_check_column_legacy( $column, $post_id ) {
        if ( 'bdc_fraud_check' === $column ) {
            $order = wc_get_order( $post_id );
            $this->render_fraud_content( $order );
        }
    }

    /**
     * Render Custom Column Data (HPOS)
     */
    public function render_fraud_check_column_hpos( $column, $order ) {
        if ( 'bdc_fraud_check' === $column ) {
            $this->render_fraud_content( $order );
        }
    }

    /**
     * Shared Content Renderer
     */
    private function render_fraud_content( $order ) {
        if ( ! $order ) return;
        $phone = $order->get_billing_phone();
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Changed: Removed Button, Added Auto-load Container
        echo '<div class="bdc-fraud-wrapper bdc-fraud-autoload" data-phone="'.esc_attr($clean_phone).'">';
        if($clean_phone) {
            echo '<div class="bdc-fraud-loading">';
            echo '<span class="dashicons dashicons-update bdc-spin"></span> Checking...';
            echo '</div>';
        } else {
            echo '<span class="description" style="color:#aaa;">-</span>';
        }
        echo '</div>';
    }

    /**
     * Helper: Normalize Dashboard URL
     */
    private function get_api_base_url() {
        $dashboard_url = get_option( 'bdc_dashboard_url' );
        if ( empty( $dashboard_url ) ) return null;

        $clean_url = preg_replace('/\/[a-zA-Z0-9_-]+\.php$/', '', $dashboard_url);
        $base_url = rtrim( $clean_url, '/' );
        
        if ( substr( $base_url, -3 ) === 'api' ) {
             return $base_url;
        } else {
             return $base_url . '/api';
        }
    }

    /**
     * Fetch Feature Flags from Dashboard API
     */
    private function get_remote_features( $force_refresh = false ) {
        $api_base = $this->get_api_base_url();
        if ( ! $api_base ) return [];

        if ( ! $force_refresh ) {
            $cached_features = get_transient('bdc_remote_features');
            if ( false !== $cached_features ) {
                return $cached_features;
            }
        }

        $response = wp_remote_get( $api_base . '/features.php', array( 'timeout' => 5, 'sslverify' => false ) );
        
        if ( is_wp_error( $response ) ) {
            if ( $force_refresh ) {
                $cached = get_transient('bdc_remote_features');
                return $cached !== false ? $cached : [];
            }
            return [];
        }

        $body = wp_remote_retrieve_body( $response );
        $features = json_decode( $body, true );

        if ( is_array( $features ) ) {
            set_transient( 'bdc_remote_features', $features, 5 * MINUTE_IN_SECONDS );
            return $features;
        }

        return [];
    }

    /**
     * Remove Lead on Order Success
     */
    public function remove_lead_on_order_success( $order_id, $order = null ) {
        if ( !$order ) {
            $order = wc_get_order( $order_id );
        }
        if ( !$order ) return;

        $api_base = $this->get_api_base_url();
        if ( ! $api_base ) return;

        $phone = $order->get_billing_phone();
        if ( empty($phone) ) return;

        // Send delete request to API
        wp_remote_post( $api_base . '/live_capture.php', array(
            'body' => json_encode(array(
                "action" => "delete",
                "phone" => $phone
            )),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 5, 
            'blocking' => false, 
            'sslverify' => false
        ));
    }

    /**
     * Inject Live Capture Script on Checkout
     */
    public function inject_live_capture_script() {
        if ( ! is_checkout() || is_order_received_page() ) return;

        $api_base = $this->get_api_base_url();
        if ( ! $api_base ) return;

        // Use cached features for frontend performance
        $features = $this->get_remote_features( false );
        if ( empty($features['live_capture']) || $features['live_capture'] !== true ) {
            return;
        }

        $cart_items = [];
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                $product = $cart_item['data'];
                $cart_items[] = [
                    'product_id' => $cart_item['product_id'],
                    'name' => $product->get_name(),
                    'quantity' => $cart_item['quantity'],
                    'total' => $cart_item['line_total']
                ];
            }
        }
        $cart_total = WC()->cart ? WC()->cart->total : 0;
        $session_id = WC()->session ? WC()->session->get_customer_id() : uniqid('guest_', true);

        ?>
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                const apiEndpoint = "<?php echo esc_url($api_base . '/live_capture.php'); ?>";
                const sessionId = "<?php echo esc_js($session_id); ?>";
                const cartItems = <?php echo json_encode($cart_items); ?>;
                const cartTotal = <?php echo esc_js($cart_total); ?>;
                
                let debounceTimer;

                function captureData() {
                    const phone = $('#billing_phone').val();
                    const name = $('#billing_first_name').val() + ' ' + $('#billing_last_name').val();
                    const email = $('#billing_email').val();
                    const address = $('#billing_address_1').val() + ', ' + $('#billing_city').val();

                    if(phone && phone.length > 5) {
                        $.ajax({
                            url: apiEndpoint,
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({
                                session_id: sessionId,
                                phone: phone,
                                name: name,
                                email: email,
                                address: address,
                                cart_items: cartItems,
                                cart_total: cartTotal
                            }),
                            success: function(res) {
                                // console.log('Lead captured', res);
                            }
                        });
                    }
                }

                $('form.checkout').on('input', 'input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(captureData, 1000); // 1s debounce
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    private function check_connection() {
        $api_base = $this->get_api_base_url();
        if (!$api_base) return false;
        $url = $api_base . '/settings.php?key=check_connection';
        $response = wp_remote_get( $url, array( 'timeout' => 5, 'sslverify' => false ) );
        if ( is_wp_error( $response ) ) return false;
        return wp_remote_retrieve_response_code($response) === 200;
    }

    public function ajax_sync_customers() {
        check_ajax_referer( 'bdc_sms_nonce', 'nonce' );
        if ( ! class_exists( 'WooCommerce' ) ) wp_send_json_error( 'WooCommerce is not installed.' );
        global $wpdb;
        $orders = wc_get_orders( array('limit' => -1, 'status' => array('wc-completed', 'wc-processing', 'wc-on-hold')) );
        $count = 0;
        foreach ( $orders as $order ) {
            $phone = preg_replace( '/[^0-9]/', '', $order->get_billing_phone() );
            if ( empty( $phone ) ) continue;
            
            $exists = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE phone = %s", $phone ) );
            if ( $exists ) {
                $wpdb->update($this->table_name, array('name' => $order->get_billing_first_name().' '.$order->get_billing_last_name(), 'email' => $order->get_billing_email(), 'last_order_date' => $order->get_date_created()->date('Y-m-d H:i:s')), array('id' => $exists->id));
            } else {
                $wpdb->insert($this->table_name, array('name' => $order->get_billing_first_name().' '.$order->get_billing_last_name(), 'phone' => $phone, 'email' => $order->get_billing_email(), 'total_spent' => $order->get_total(), 'order_count' => 1, 'last_order_date' => $order->get_date_created()->date('Y-m-d H:i:s')));
                $count++;
            }
        }
        
        wp_send_json_success( "$count new customers imported." );
    }

    public function ajax_send_sms() {
        check_ajax_referer( 'bdc_sms_nonce', 'nonce' );
        $numbers = $_POST['numbers'] ?? [];
        $message = $_POST['message'] ?? '';
        $api_base = $this->get_api_base_url();
        if ( !$api_base ) wp_send_json_error( 'Dashboard URL missing.' );
        if ( empty( $numbers ) || empty( $message ) ) wp_send_json_error( 'Inputs empty.' );

        $formatted_numbers = [];
        foreach ( $numbers as $phone ) {
            $p = $phone; 
            if ( strlen( $p ) == 11 && substr( $p, 0, 2 ) == '01' ) $p = '88' . $p;
            $formatted_numbers[] = $p;
        }
        $contacts_csv = implode(',', $formatted_numbers);
        $type = (mb_strlen($message) != strlen($message)) ? 'unicode' : 'text';

        $response = wp_remote_post( $api_base . '/send_sms.php', array(
            'body' => json_encode(array("contacts" => $contacts_csv, "msg" => $message, "type" => $type)),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 25, 'sslverify' => false
        ));

        if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );
        wp_send_json_success( "Sent successfully." );
    }

    public function render_dashboard() {
        global $wpdb;
        $customers = $wpdb->get_results( "SELECT * FROM $this->table_name ORDER BY id DESC" );
        $is_connected = $this->check_connection();
        $features = $this->get_remote_features( true );
        include(plugin_dir_path(__FILE__) . 'admin-view.php');
    }
}

new BDC_SMS_Manager();