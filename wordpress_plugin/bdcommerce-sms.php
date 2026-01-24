<?php
/**
 * Plugin Name: BdCommerce SMS Manager
 * Plugin URI:  https://bdcommerce.com
 * Description: A complete SMS & Customer Management solution. Sync customers and send Bulk SMS by relaying requests through your Main Dashboard. Includes Live Capture & Fraud Check in Orders.
 * Version:     2.2.0
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

        $message = "Your Verification Code is: " . $otp . ". Please do not share this code with anyone.";
        $api_base = $this->get_api_base_url();
        
        if(!$api_base) wp_send_json_error("SMS API not configured");

        // Format Phone
        $formatted_phone = preg_replace('/[^0-9]/', '', $phone);
        if ( strlen( $formatted_phone ) == 11 && substr( $formatted_phone, 0, 2 ) == '01' ) $formatted_phone = '88' . $formatted_phone;

        // Send SMS Request to Dashboard
        $response = wp_remote_post( $api_base . '/send_sms.php', array(
            'body' => json_encode(array("contacts" => $formatted_phone, "msg" => $message, "type" => 'text')),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 15, 'sslverify' => false
        ));

        if(is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success("OTP Sent");
        }
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

        // Loose comparison for phone to handle formatting differences if needed, but session usually stores what was sent
        if( $stored_otp && $code == $stored_otp ) {
            // Optional: Check phone match strictly if critical
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
                
                // Professional Modal CSS
                $custom_css = "
                    .bdc-modal { position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0,0,0,0.7); backdrop-filter: blur(2px); display: none; align-items:center; justify-content:center; }
                    .bdc-modal-content { background-color: #ffffff; margin: auto; padding: 30px; border-radius: 16px; width: 90%; max-width: 420px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.3); position: relative; font-family: -apple-system, system-ui, sans-serif; animation: bdcSlideUp 0.3s ease-out; }
                    @keyframes bdcSlideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
                    .bdc-icon-wrap { width: 60px; height: 60px; background: #fff7ed; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
                    .bdc-icon { font-size: 24px; }
                    .bdc-title { margin: 0 0 10px; color: #1e293b; font-size: 20px; font-weight: 700; }
                    .bdc-desc { font-size: 14px; color: #64748b; margin-bottom: 25px; line-height: 1.5; }
                    .bdc-otp-input { width: 100%; padding: 15px; margin-bottom: 20px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 24px; font-weight: 700; letter-spacing: 8px; text-align: center; box-sizing: border-box; outline: none; transition: border 0.2s; color: #334155; }
                    .bdc-otp-input:focus { border-color: #ea580c; }
                    .bdc-btn { background-color: #ea580c; color: white; padding: 15px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; width: 100%; transition: transform 0.1s, background 0.2s; box-shadow: 0 4px 6px -1px rgba(234, 88, 12, 0.2); }
                    .bdc-btn:hover { background-color: #c2410c; }
                    .bdc-btn:active { transform: scale(0.98); }
                    .bdc-btn:disabled { opacity: 0.7; cursor: not-allowed; }
                    .bdc-resend { margin-top: 20px; font-size: 13px; color: #64748b; background: none; border: none; padding: 0; cursor: default; }
                    .bdc-resend-link { color: #ea580c; font-weight: 600; cursor: pointer; text-decoration: none; }
                    .bdc-resend-link:hover { text-decoration: underline; }
                    .bdc-error { color: #ef4444; font-size: 13px; margin-top: 10px; display: none; font-weight: 500; background: #fef2f2; padding: 8px; border-radius: 6px; }
                    .bdc-close { position: absolute; right: 20px; top: 20px; color: #94a3b8; font-size: 24px; cursor: pointer; transition: color 0.2s; line-height: 1; }
                    .bdc-close:hover { color: #ef4444; }
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
                        var isChecking = false;

                        // Add Verified Field to Form
                        $("form.checkout").append("<input type=\"hidden\" name=\"bdc_otp_verified\" id=\"bdc_otp_verified\" value=\"false\">");

                        // Add Modal HTML
                        $("body").append(`
                            <div id="bdc-otp-modal" class="bdc-modal">
                                <div class="bdc-modal-content">
                                    <span class="bdc-close">&times;</span>
                                    <div class="bdc-icon-wrap">
                                        <span class="dashicons dashicons-smartphone bdc-icon" style="color:#ea580c;">📱</span>
                                    </div>
                                    <h3 class="bdc-title">Verification Required</h3>
                                    <p class="bdc-desc">To ensure secure delivery, we have sent a <strong>4-digit code</strong> to your mobile number.</p>
                                    
                                    <input type="tel" id="bdc-otp-code" class="bdc-otp-input" placeholder="0000" maxlength="4" autocomplete="one-time-code">
                                    
                                    <button type="button" id="bdc-verify-btn" class="bdc-btn">Verify & Place Order</button>
                                    
                                    <div id="bdc-otp-error" class="bdc-error">Invalid Code entered. Please try again.</div>
                                    
                                    <p class="bdc-resend">
                                        Didn\'t receive code? <span id="bdc-resend-btn" class="bdc-resend-link">Resend SMS</span>
                                    </p>
                                </div>
                            </div>
                        `);

                        // Modal UI Events
                        $(".bdc-close").click(function() { 
                            $("#bdc-otp-modal").fadeOut(); 
                            $("form.checkout").removeClass("processing").unblock(); 
                        });
                        
                        // Prevent non-numeric input
                        $("#bdc-otp-code").on("input", function() {
                            this.value = this.value.replace(/[^0-9]/g, "");
                        });

                        $("#bdc-resend-btn").click(function() {
                            var phone = $("#billing_phone").val();
                            if(!phone) return;
                            
                            var btn = $(this);
                            btn.text("Sending...").css("opacity", "0.5").css("pointer-events", "none");
                            
                            sendOtp(phone, function() {
                                btn.text("Sent! Wait 60s").css("color", "#16a34a");
                                setTimeout(function(){
                                    btn.text("Resend SMS").css("opacity", "1").css("pointer-events", "auto").css("color", "#ea580c");
                                }, 60000);
                            });
                        });

                        $("#bdc-verify-btn").click(function() {
                            verifyOtp();
                        });

                        function sendOtp(phone, callback) {
                            $.post("'.admin_url('admin-ajax.php').'", {
                                action: "bdc_send_otp",
                                phone: phone
                            }, function(res) {
                                if(res.success) {
                                    if(callback) callback();
                                } else {
                                    alert("Failed to send SMS. Please check phone number.");
                                }
                            });
                        }

                        function verifyOtp() {
                            var code = $("#bdc-otp-code").val();
                            var phone = $("#billing_phone").val();
                            
                            if(code.length !== 4) {
                                $("#bdc-otp-error").text("Please enter 4 digits").slideDown();
                                return;
                            }
                            
                            $("#bdc-verify-btn").text("Verifying...").prop("disabled", true);
                            $("#bdc-otp-error").slideUp();
                            
                            $.post("'.admin_url('admin-ajax.php').'", {
                                action: "bdc_verify_otp",
                                code: code,
                                phone: phone
                            }, function(res) {
                                if(res.success) {
                                    $("#bdc-verify-btn").text("Verified! Redirecting...");
                                    isOtpVerified = true;
                                    $("#bdc_otp_verified").val("true");
                                    $("#bdc-otp-modal").fadeOut(200);
                                    
                                    // Trigger Place Order again
                                    $("form.checkout").submit(); 
                                } else {
                                    $("#bdc-verify-btn").text("Verify & Place Order").prop("disabled", false);
                                    $("#bdc-otp-error").text(res.data).slideDown();
                                }
                            });
                        }

                        // Main Checkout Interception
                        $("form.checkout").on("checkout_place_order", function() {
                            if(fraudConfig.check !== "yes") return true;
                            if(isOtpVerified) return true; // Already verified, proceed
                            if(isChecking) return false; // Wait for async check

                            var phone = $("#billing_phone").val().replace(/[^0-9]/g, "");
                            var paymentMethod = $("input[name=\"payment_method\"]:checked").val();

                            // 1. Basic Length Check
                            if(phone.length < 11) return true; 

                            // 2. Check if we already have a status in cache
                            if(checkedPhones[phone]) {
                                var status = checkedPhones[phone];
                                if(status === "risk") {
                                    if(fraudConfig.disableCod === "yes" && paymentMethod === "cod") {
                                        alert("⚠️ Warning: Cash on Delivery is unavailable for your account history. Please pay via bKash or Card.");
                                        return false; 
                                    }
                                    if(fraudConfig.enableOtp === "yes") {
                                        $("#bdc-otp-modal").css("display", "flex");
                                        sendOtp(phone);
                                        return false; 
                                    }
                                }
                                return true; // Safe to proceed
                            }

                            // 3. No cache? Perform Async Check
                            isChecking = true;
                            $("form.checkout").addClass("processing"); // Show WC Loader

                            $.ajax({
                                url: fraudConfig.apiUrl,
                                data: { phone: phone },
                                dataType: "json",
                                success: function(data) {
                                    isChecking = false;
                                    $("form.checkout").removeClass("processing");

                                    var status = "safe";
                                    if(data.success_rate && data.total_orders > 0 && parseFloat(data.success_rate) < fraudConfig.minRate) {
                                        status = "risk";
                                    }
                                    checkedPhones[phone] = status;

                                    // Re-trigger submission logic
                                    if(status === "risk") {
                                        if(fraudConfig.disableCod === "yes" && paymentMethod === "cod") {
                                            alert("⚠️ Warning: Cash on Delivery is unavailable for your account history. Please pay via bKash or Card.");
                                        } else if(fraudConfig.enableOtp === "yes") {
                                            $("#bdc-otp-modal").css("display", "flex");
                                            sendOtp(phone);
                                        } else {
                                            $("form.checkout").submit(); // Risk but no action configured
                                        }
                                    } else {
                                        $("form.checkout").submit(); // Safe
                                    }
                                },
                                error: function() { 
                                    // Fail safe: Allow order if API fails
                                    isChecking = false;
                                    checkedPhones[phone] = "safe";
                                    $("form.checkout").removeClass("processing");
                                    $("form.checkout").submit(); 
                                }
                            });

                            return false; // Stop initial submission to wait for AJAX
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
?>