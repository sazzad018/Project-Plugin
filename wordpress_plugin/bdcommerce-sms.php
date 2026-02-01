<?php
/**
 * Plugin Name: BdCommerce SMS Manager
 * Plugin URI:  https://bdcommerce.com
 * Description: A complete SMS & Customer Management solution. Sync customers and send Bulk SMS by relaying requests through your Main Dashboard. Includes Live Capture & Fraud Check in Orders.
 * Version:     2.5.3
 * Author:      BdCommerce
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class BDC_SMS_Manager {

    private $table_name;
    private $license_error = '';
    private $runtime_cache = null; // Store status for current request

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
        add_action( 'admin_notices', array( $this, 'license_check_notice' ) );
        
        // Cache Clearing Hooks
        add_action( 'update_option_bdc_license_key', array( $this, 'clear_license_cache' ), 10, 2 );
        add_action( 'update_option_bdc_dashboard_url', array( $this, 'clear_license_cache' ), 10, 2 );

        // AJAX Handlers
        add_action( 'wp_ajax_bdc_sync_customers', array( $this, 'ajax_sync_customers' ) );
        add_action( 'wp_ajax_bdc_send_sms', array( $this, 'ajax_send_sms' ) );
        
        // OTP AJAX
        add_action( 'wp_ajax_bdc_send_otp', array( $this, 'ajax_send_otp' ) );
        add_action( 'wp_ajax_nopriv_bdc_send_otp', array( $this, 'ajax_send_otp' ) );
        add_action( 'wp_ajax_bdc_verify_otp', array( $this, 'ajax_verify_otp' ) );
        add_action( 'wp_ajax_nopriv_bdc_verify_otp', array( $this, 'ajax_verify_otp' ) );

        // VPN AJAX
        add_action( 'wp_ajax_bdc_check_vpn', array( $this, 'ajax_check_vpn' ) );
        add_action( 'wp_ajax_nopriv_bdc_check_vpn', array( $this, 'ajax_check_vpn' ) );

        // Live Capture Injection
        add_action( 'wp_footer', array( $this, 'inject_live_capture_script' ) );
        
        // VPN Script Injection
        add_action( 'wp_footer', array( $this, 'inject_vpn_check_script' ) );
        
        // Remove Lead on Successful Order
        add_action( 'woocommerce_new_order', array( $this, 'remove_lead_on_order_success' ), 10, 2 );

        // WooCommerce Order Column Hooks (Legacy & HPOS)
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_fraud_check_column' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_fraud_check_column_legacy' ), 10, 2 );
        
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_fraud_check_column' ) );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_fraud_check_column_hpos' ), 10, 2 );

        // Checkout Validation (Server Side Backup)
        add_action( 'woocommerce_checkout_process', array( $this, 'execute_fraud_guard_checks' ) );

        // SMS Automation Hook
        add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change_sms' ), 10, 3 );
    }

    /**
     * Clear Cache when settings change
     */
    public function clear_license_cache($old_value, $new_value) {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bdc_license_status_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bdc_license_status_%'" );
    }

    /**
     * Check License Logic
     */
    private function is_license_active($force_check = false) {
        if ($this->runtime_cache !== null && !$force_check) {
            return $this->runtime_cache;
        }

        $license_key = get_option('bdc_license_key');
        $license_key = trim($license_key); 
        if ( empty($license_key) ) return false;

        $cache_key = 'bdc_license_status_' . md5($license_key);
        
        if (!$force_check) {
            $cached_status = get_transient($cache_key);
            if ( false !== $cached_status ) {
                $this->runtime_cache = ($cached_status === 'valid');
                return $this->runtime_cache;
            }
        }

        $api_base = $this->get_api_base_url();
        if ( ! $api_base ) {
            $this->license_error = 'Dashboard URL is missing.';
            return false;
        }

        $response = wp_remote_post( $api_base . '/verify_license.php', array(
            'body' => json_encode(array(
                'license_key' => $license_key,
                'domain' => site_url()
            )),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 10, 
            'sslverify' => false
        ));

        if ( is_wp_error( $response ) ) {
            $this->license_error = 'Connection Error: ' . $response->get_error_message();
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset($data['valid']) && $data['valid'] === true ) {
            set_transient($cache_key, 'valid', 30 * MINUTE_IN_SECONDS);
            $this->runtime_cache = true;
            return true;
        }

        $this->license_error = isset($data['message']) ? $data['message'] : 'Invalid Key Response';
        set_transient($cache_key, 'invalid', 30 * MINUTE_IN_SECONDS);
        $this->runtime_cache = false;
        return false;
    }

    public function license_check_notice() {
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'toplevel_page_bdc-sms-manager' ) {
            if ( ! $this->is_license_active(true) ) {
                $error_msg = $this->license_error ? " ($this->license_error)" : "";
                echo '<div class="notice notice-error"><p><strong>License Inactive:</strong> Please enter a valid License Key in Settings tab to enable features.' . esc_html($error_msg) . '</p></div>';
            }
        }
    }

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

    public function register_settings() {
        register_setting( 'bdc_sms_group', 'bdc_dashboard_url' );
        register_setting( 'bdc_sms_group', 'bdc_license_key' ); 
        register_setting( 'bdc_sms_group', 'bdc_fraud_phone_validation' ); 
        register_setting( 'bdc_sms_group', 'bdc_fraud_phone_error_msg' ); 
        register_setting( 'bdc_sms_group', 'bdc_fraud_history_check' ); 
        register_setting( 'bdc_sms_group', 'bdc_fraud_min_rate' ); 
        register_setting( 'bdc_sms_group', 'bdc_fraud_disable_cod' ); 
        register_setting( 'bdc_sms_group', 'bdc_fraud_enable_otp' ); 
        register_setting( 'bdc_sms_group', 'bdc_vpn_block_enabled' ); 
        register_setting( 'bdc_sms_group', 'bdc_sms_automation_settings' ); 
    }

    public function handle_order_status_change_sms( $order_id, $old_status, $new_status ) {
        if ( ! $this->is_license_active(false) ) return;

        $settings = get_option('bdc_sms_automation_settings', []);
        $clean_status = str_replace('wc-', '', $new_status);

        if ( isset($settings[$clean_status]) && !empty($settings[$clean_status]['enabled']) ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) return;

            $phone = $order->get_billing_phone();
            if ( empty($phone) ) return;

            $template = isset($settings[$clean_status]['template']) ? $settings[$clean_status]['template'] : '';
            if ( empty($template) ) return;

            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            $full_name = trim( $first_name . ' ' . $last_name );
            if(empty($full_name)) $full_name = 'Customer';

            $message = str_replace(
                array('[name]', '[order_id]', '[amount]', '[status]'),
                array($full_name, $order_id, $order->get_total(), wc_get_order_status_name($new_status)),
                $template
            );

            $this->send_remote_sms( $phone, $message );
        }
    }

    private function send_remote_sms( $phone, $message ) {
        $api_base = $this->get_api_base_url();
        if ( ! $api_base ) return;

        $formatted_phone = preg_replace('/[^0-9]/', '', $phone);
        if ( strlen( $formatted_phone ) == 11 && substr( $formatted_phone, 0, 2 ) == '01' ) {
            $formatted_phone = '88' . $formatted_phone;
        }

        $type = (mb_strlen($message) != strlen($message)) ? 'unicode' : 'text';

        wp_remote_post( $api_base . '/send_sms.php', array(
            'body' => json_encode(array(
                "contacts" => $formatted_phone, 
                "msg" => $message, 
                "type" => $type
            )),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 15, 
            'blocking' => false, 
            'sslverify' => false
        ));
    }

    public function execute_fraud_guard_checks() {
        if ( ! $this->is_license_active(false) ) return;

        if ( get_option('bdc_vpn_block_enabled') ) {
            $ip = $this->get_user_ip();
            $license_key = get_option('bdc_license_key');
            
            $vpn_response = wp_remote_get( "https://dash.hoorin.com/api/ip/ip.php?api_key={$license_key}&ip={$ip}", array('timeout' => 5, 'sslverify' => false) );
            
            if ( !is_wp_error($vpn_response) ) {
                $body = wp_remote_retrieve_body($vpn_response);
                $data = json_decode($body, true);
                if ( isset($data['proxy']) && $data['proxy'] === 'yes' ) {
                    wc_add_notice( __( '<strong>Security Alert:</strong> Orders from VPN/Proxy are not allowed. Please disable your VPN.', 'bdc-sms' ), 'error' );
                    return;
                }
            }
        }

        $billing_phone = isset( $_POST['billing_phone'] ) ? $_POST['billing_phone'] : '';
        $clean_phone = preg_replace( '/[^0-9]/', '', $billing_phone );

        if ( get_option( 'bdc_fraud_phone_validation' ) ) {
            if ( strlen( $clean_phone ) < 11 ) {
                $custom_msg = get_option('bdc_fraud_phone_error_msg');
                $error_message = !empty($custom_msg) ? $custom_msg : __( '<strong>Mobile Number Error:</strong> Please enter a valid 11-digit mobile number.', 'bdc-sms' );
                wc_add_notice( $error_message, 'error' );
                return;
            }
        }

        if ( isset($_POST['bdc_otp_verified']) && $_POST['bdc_otp_verified'] === 'true' ) {
            return;
        }

        if ( get_option( 'bdc_fraud_history_check' ) ) {
            $api_base = $this->get_api_base_url();
            if ( ! $api_base ) return;

            $response = wp_remote_get( $api_base . '/check_fraud.php?phone=' . $clean_phone, array( 'timeout' => 10, 'sslverify' => false ) );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body, true );

                if ( isset( $data['success_rate'] ) && isset( $data['total_orders'] ) ) {
                    $total_orders = intval( $data['total_orders'] );
                    $success_rate = floatval( $data['success_rate'] );
                    $min_rate = intval( get_option( 'bdc_fraud_min_rate', 50 ) );

                    if ( $total_orders > 0 && $success_rate < $min_rate ) {
                        if ( get_option('bdc_fraud_disable_cod') ) {
                            $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
                            if ( $payment_method === 'cod' ) {
                                wc_add_notice( __( '<strong>Order Restricted:</strong> Your delivery success rate is too low for Cash on Delivery. Please pay via bKash or Card.', 'bdc-sms' ), 'error' );
                            }
                        }
                        
                        if ( get_option('bdc_fraud_enable_otp') && (!isset($_POST['bdc_otp_verified']) || $_POST['bdc_otp_verified'] !== 'true') ) {
                             wc_add_notice( __( '<strong>Verification Required:</strong> Please complete the SMS verification to place this order.', 'bdc-sms' ), 'error' );
                        }
                    }
                }
            }
        }
    }

    public function ajax_send_otp() {
        if ( ! $this->is_license_active(false) ) wp_send_json_error("License Inactive");

        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        if(!$phone) wp_send_json_error("Phone number required");

        $otp = rand(1000, 9999);
        
        if ( !WC()->session ) WC()->session = new WC_Session_Handler();
        WC()->session->set( 'bdc_otp_code', $otp );
        WC()->session->set( 'bdc_otp_phone', $phone );

        $message = "Your Verification Code is: " . $otp . ". Please do not share this code with anyone.";
        $api_base = $this->get_api_base_url();
        
        if(!$api_base) wp_send_json_error("SMS API not configured");

        $formatted_phone = preg_replace('/[^0-9]/', '', $phone);
        if ( strlen( $formatted_phone ) == 11 && substr( $formatted_phone, 0, 2 ) == '01' ) $formatted_phone = '88' . $formatted_phone;

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

    public function ajax_verify_otp() {
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        if ( !WC()->session ) WC()->session = new WC_Session_Handler();
        $stored_otp = WC()->session->get( 'bdc_otp_code' );

        if( $stored_otp && $code == $stored_otp ) {
            wp_send_json_success("Verified");
        } else {
            wp_send_json_error("Invalid Code");
        }
    }

    private function get_user_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return trim($ip);
    }

    public function ajax_check_vpn() {
        if ( get_option( 'bdc_vpn_block_enabled' ) !== '1' ) {
            wp_send_json_success(array('proxy' => 'no')); 
        }

        $ip = $this->get_user_ip();
        $license_key = get_option('bdc_license_key');
        
        if ( empty($license_key) ) {
            wp_send_json_error("License Key Missing");
        }

        $response = wp_remote_get( "https://dash.hoorin.com/api/ip/ip.php?api_key={$license_key}&ip={$ip}", array('timeout' => 5, 'sslverify' => false) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ( isset($data['proxy']) ) {
            wp_send_json_success( $data );
        } else {
            wp_send_json_error("Invalid API Response");
        }
    }

    public function inject_vpn_check_script() {
        if ( get_option( 'bdc_vpn_block_enabled' ) !== '1' ) return;
        if ( ! $this->is_license_active(false) ) return;
        if ( current_user_can('manage_options') ) return;

        $admin_ajax = admin_url('admin-ajax.php');
        
        $css = "
        #bdc-vpn-modal { display: none; position: fixed; z-index: 9999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.95); align-items: center; justify-content: center; backdrop-filter: blur(10px); }
        .bdc-vpn-content { background: #ffffff; width: 90%; max-width: 450px; padding: 40px; border-radius: 16px; text-align: center; position: relative; border: 1px solid #e5e7eb; }
        .bdc-vpn-btn { background: #dc2626; color: white; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        body.bdc-vpn-blocked { overflow: hidden !important; }
        ";
        
        echo "<style>{$css}</style>";
        
        echo '
        <div id="bdc-vpn-modal">
            <div class="bdc-vpn-content">
                <h2 style="margin-top:0">Access Restricted</h2>
                <p>We have detected that you are using a <strong>VPN or Proxy</strong>. Please disable your VPN to access our store.</p>
                <button onclick="window.location.reload()" class="bdc-vpn-btn">I have disabled VPN</button>
            </div>
        </div>
        ';

        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var vpnStatus = sessionStorage.getItem('bdc_vpn_status');
            
            if (vpnStatus === 'blocked') {
                showVpnBlock();
            } else if (vpnStatus !== 'clean') {
                $.ajax({
                    url: "<?php echo $admin_ajax; ?>",
                    data: { action: "bdc_check_vpn" },
                    method: "POST",
                    success: function(res) {
                        if(res.success && res.data.proxy === 'yes') {
                            sessionStorage.setItem('bdc_vpn_status', 'blocked');
                            showVpnBlock();
                        } else {
                            sessionStorage.setItem('bdc_vpn_status', 'clean');
                        }
                    }
                });
            }

            function showVpnBlock() {
                $('#bdc-vpn-modal').css('display', 'flex');
                $('body').addClass('bdc-vpn-blocked');
            }
        });
        </script>
        <?php
    }

    public function enqueue_assets( $hook ) {
        if ( is_checkout() && $this->is_license_active(false) ) {
            $api_base = $this->get_api_base_url();
            if($api_base) {
                $check_history = get_option('bdc_fraud_history_check') ? 'yes' : 'no';
                $disable_cod = get_option('bdc_fraud_disable_cod') ? 'yes' : 'no';
                $enable_otp = get_option('bdc_fraud_enable_otp') ? 'yes' : 'no';
                $min_rate = get_option('bdc_fraud_min_rate', 50);

                wp_enqueue_script('jquery');
                
                $custom_css = "
                    .bdc-modal { position: fixed; z-index: 2147483647 !important; left: 0; top: 0; width: 100%; height: 100%; overflow: hidden; background-color: rgba(0,0,0,0.85); backdrop-filter: blur(5px); display: none; align-items:center; justify-content:center; }
                    .bdc-modal.show-modal { display: flex !important; }
                    .bdc-modal-content { background-color: #ffffff; margin: auto; padding: 30px; border-radius: 16px; width: 90%; max-width: 420px; text-align: center; }
                    .bdc-otp-input { width: 100%; padding: 15px; margin-bottom: 20px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 24px; font-weight: 700; text-align: center; }
                    .bdc-btn { background-color: #ea580c; color: white; padding: 15px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; width: 100%; }
                    .bdc-close { position: absolute; right: 20px; top: 20px; color: #94a3b8; font-size: 24px; cursor: pointer; }
                ";
                wp_add_inline_style('woocommerce-general', $custom_css);

                $admin_ajax = admin_url('admin-ajax.php');
                
                wp_add_inline_script('jquery', <<<EOD
                    jQuery(document).ready(function($) {
                        var fraudConfig = {
                            check: "{$check_history}",
                            disableCod: "{$disable_cod}",
                            enableOtp: "{$enable_otp}",
                            minRate: {$min_rate},
                            apiUrl: "{$api_base}/check_fraud.php"
                        };
                        var isOtpVerified = false;
                        var checkedPhones = {};
                        var isChecking = false;

                        var modalHtml = `
                            <div id="bdc-otp-modal" class="bdc-modal">
                                <div class="bdc-modal-content">
                                    <span class="bdc-close">&times;</span>
                                    <h3>Verification Required</h3>
                                    <p>Enter the 4-digit code sent to your phone.</p>
                                    <input type="tel" id="bdc-otp-code" class="bdc-otp-input" placeholder="0000" maxlength="4">
                                    <button type="button" id="bdc-verify-btn" class="bdc-btn">Verify</button>
                                </div>
                            </div>
                        `;
                        $('body').append(modalHtml);
                        
                        if ($('#bdc_otp_verified').length === 0) {
                            $('form.checkout').append('<input type="hidden" name="bdc_otp_verified" id="bdc_otp_verified" value="false">');
                        }

                        $(document).on('click', '.bdc-close', function() { 
                            $('#bdc-otp-modal').removeClass('show-modal'); 
                            $('form.checkout').removeClass('processing').unblock(); 
                        });

                        $(document).on('click', '#bdc-verify-btn', function() {
                            verifyOtp();
                        });

                        function sendOtp(phone) {
                            $.post("{$admin_ajax}", { action: "bdc_send_otp", phone: phone });
                        }

                        function verifyOtp() {
                            var code = $('#bdc-otp-code').val();
                            var phone = $('#billing_phone').val();
                            $.post("{$admin_ajax}", { action: "bdc_verify_otp", code: code, phone: phone }, function(res) {
                                if(res.success) {
                                    isOtpVerified = true;
                                    $('#bdc_otp_verified').val('true');
                                    $('#bdc-otp-modal').removeClass('show-modal');
                                    $('form.checkout').submit(); 
                                } else {
                                    alert("Invalid Code");
                                }
                            });
                        }

                        $('form.checkout').on('checkout_place_order', function() {
                            if(fraudConfig.check !== "yes") return true;
                            if(isOtpVerified) return true; 
                            if(isChecking) return false;

                            var phone = $('#billing_phone').val().replace(/[^0-9]/g, '');
                            if(phone.length < 11) return true; 

                            if(checkedPhones[phone]) {
                                var status = checkedPhones[phone];
                                if(status === "risk" && fraudConfig.enableOtp === "yes") {
                                    sendOtp(phone);
                                    $('#bdc-otp-modal').addClass('show-modal');
                                    return false;
                                }
                                return true;
                            }

                            isChecking = true;
                            $('form.checkout').addClass('processing'); 

                            $.ajax({
                                url: fraudConfig.apiUrl,
                                data: { phone: phone },
                                dataType: "json",
                                success: function(data) {
                                    isChecking = false;
                                    $('form.checkout').removeClass('processing');
                                    var status = "safe";
                                    if(data.success_rate && data.total_orders > 0 && parseFloat(data.success_rate) < fraudConfig.minRate) {
                                        status = "risk";
                                    }
                                    checkedPhones[phone] = status;
                                    if(status === "risk" && fraudConfig.enableOtp === "yes") {
                                        sendOtp(phone);
                                        $('#bdc-otp-modal').addClass('show-modal');
                                    } else {
                                        $('form.checkout').submit();
                                    }
                                },
                                error: function() { 
                                    isChecking = false;
                                    $('form.checkout').removeClass('processing');
                                    $('form.checkout').submit(); 
                                }
                            });
                            return false; 
                        });
                    });
EOD
                );
            }
        }

        $screen = get_current_screen();
        $is_order_page = ( 
            ( $hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order' ) || 
            ( $screen && $screen->id === 'woocommerce_page_wc-orders' ) 
        );

        if ( 'toplevel_page_bdc-sms-manager' === $hook || $is_order_page ) {
            if( $is_order_page && $this->is_license_active(false) ) {
                wp_register_style( 'bdc-admin-styles', false );
                wp_enqueue_style( 'bdc-admin-styles' );
                $custom_css = ".column-bdc_fraud_check { width: 260px !important; }";
                wp_add_inline_style( 'bdc-admin-styles', $custom_css );

                $api_base = $this->get_api_base_url();
                if($api_base) {
                    $api_url = esc_url($api_base . '/check_fraud.php');
                    wp_add_inline_script('jquery', '
                        jQuery(document).ready(function($) {
                            function loadFraudData(container) {
                                var phone = container.data("phone");
                                if(!phone) return;
                                $.ajax({
                                    url: "' . $api_url . '",
                                    data: { phone: phone },
                                    dataType: "json",
                                    success: function(data) {
                                        var rate = parseFloat(data.success_rate || 0);
                                        var total = parseInt(data.total_orders || 0);
                                        var del = parseInt(data.delivered || 0);
                                        var can = parseInt(data.cancelled || 0);
                                        
                                        var isGood = rate >= 80;
                                        var isBad = rate < 50;
                                        var colorClass = isGood ? "color:#16a34a;" : (isBad ? "color:#dc2626;" : "color:#f97316;");
                                        var iconClass = isGood ? "dashicons-shield" : "dashicons-warning";
                                        
                                        var html = "<div style=\'width:220px; padding:12px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 1px 2px rgba(0,0,0,0.05);\'>";
                                        
                                        // Header Row
                                        html += "<div style=\'display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;\'>";
                                            html += "<div style=\'display:flex; align-items:center; gap:6px;\'>";
                                                html += "<span class=\'dashicons " + iconClass + "\' style=\'font-size:20px; " + colorClass + "\'></span>";
                                                html += "<div>";
                                                    html += "<div style=\'font-size:9px; color:#94a3b8; font-weight:700; text-transform:uppercase; line-height:1;\'>Success Rate</div>";
                                                    html += "<div style=\'font-size:18px; font-weight:900; " + colorClass + " line-height:1;\'>" + rate + "%</div>";
                                                html += "</div>";
                                            html += "</div>";
                                            html += "<span style=\'font-size:10px; font-weight:700; color:#475569; background:#f8fafc; padding:3px 6px; border-radius:4px; border:1px solid #f1f5f9;\'>" + total + " Orders</span>";
                                        html += "</div>";
                                        
                                        // Boxes Row
                                        html += "<div style=\'display:grid; grid-template-columns:1fr 1fr; gap:6px;\'>";
                                            // Delivered Box
                                            html += "<div style=\'background:#f0fdf4; border:1px solid #dcfce7; border-radius:6px; padding:6px; text-align:center;\'>";
                                                html += "<div style=\'font-size:8px; font-weight:700; color:#4ade80; text-transform:uppercase; margin-bottom:2px;\'>Delivered</div>";
                                                html += "<div style=\'font-size:13px; font-weight:900; color:#15803d; line-height:1;\'>" + del + "</div>";
                                            html += "</div>";
                                            // Cancelled Box
                                            html += "<div style=\'background:#fef2f2; border:1px solid #fee2e2; border-radius:6px; padding:6px; text-align:center;\'>";
                                                html += "<div style=\'font-size:8px; font-weight:700; color:#f87171; text-transform:uppercase; margin-bottom:2px;\'>Cancelled</div>";
                                                html += "<div style=\'font-size:13px; font-weight:900; color:#b91c1c; line-height:1;\'>" + can + "</div>";
                                            html += "</div>";
                                        html += "</div>";
                                        
                                        html += "</div>";
                                        container.html(html);
                                    },
                                    error: function() { container.html("<span style=\'color:#ef4444; font-size:10px;\'>Check Failed</span>"); }
                                });
                            }
                            $(".bdc-fraud-autoload").each(function() { loadFraudData($(this)); });
                        });
                    ');
                }
            }
        }
    }

    public function add_fraud_check_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $column ) {
            $new_columns[$key] = $column;
            if ( 'order_status' === $key ) {
                $new_columns['bdc_fraud_check'] = __( 'Fraud Check', 'bdc-sms' );
            }
        }
        if(!isset($new_columns['bdc_fraud_check'])) $new_columns['bdc_fraud_check'] = __( 'Fraud Check', 'bdc-sms' );
        return $new_columns;
    }

    public function render_fraud_check_column_legacy( $column, $post_id ) {
        if ( 'bdc_fraud_check' === $column ) {
            $order = wc_get_order( $post_id );
            $this->render_fraud_content( $order );
        }
    }

    public function render_fraud_check_column_hpos( $column, $order ) {
        if ( 'bdc_fraud_check' === $column ) {
            $this->render_fraud_content( $order );
        }
    }

    private function render_fraud_content( $order ) {
        if ( ! $order ) return;
        $phone = $order->get_billing_phone();
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        echo '<div class="bdc-fraud-wrapper bdc-fraud-autoload" data-phone="'.esc_attr($clean_phone).'"><span style="font-size:10px; color:#cbd5e1; font-style:italic;">Checking...</span></div>';
    }

    private function get_api_base_url() {
        $dashboard_url = get_option( 'bdc_dashboard_url' );
        if ( empty( $dashboard_url ) ) return null;
        $clean_url = preg_replace('/\/[a-zA-Z0-9_-]+\.php$/', '', $dashboard_url);
        $base_url = rtrim( $clean_url, '/' );
        return ( substr( $base_url, -3 ) === 'api' ) ? $base_url : $base_url . '/api';
    }

    private function get_remote_features( $force_refresh = false ) {
        if ( ! $this->is_license_active(false) ) return [];
        $api_base = $this->get_api_base_url();
        if ( ! $api_base ) return [];

        if ( ! $force_refresh ) {
            $cached = get_transient('bdc_remote_features');
            if ( false !== $cached ) return $cached;
        }

        $response = wp_remote_get( $api_base . '/features.php', array( 'timeout' => 5, 'sslverify' => false ) );
        if ( is_wp_error( $response ) ) return [];

        $body = wp_remote_retrieve_body( $response );
        $features = json_decode( $body, true );
        if ( is_array( $features ) ) {
            set_transient( 'bdc_remote_features', $features, 5 * MINUTE_IN_SECONDS );
            return $features;
        }
        return [];
    }

    public function remove_lead_on_order_success( $order_id, $order = null ) {
        if ( !$order ) $order = wc_get_order( $order_id );
        if ( !$order ) return;
        $api_base = $this->get_api_base_url();
        if ( ! $api_base ) return;
        $phone = $order->get_billing_phone();
        if ( empty($phone) ) return;

        wp_remote_post( $api_base . '/live_capture.php', array(
            'body' => json_encode(array("action" => "delete", "phone" => $phone)),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 5, 'blocking' => false, 'sslverify' => false
        ));
    }

    public function inject_live_capture_script() {
        if ( ! is_checkout() || is_order_received_page() ) return;
        if ( ! $this->is_license_active(false) ) return;
        $api_base = $this->get_api_base_url();
        if ( ! $api_base ) return;

        $features = $this->get_remote_features( false );
        if ( empty($features['live_capture']) || $features['live_capture'] !== true ) return;

        $cart_items = [];
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                $product = $cart_item['data'];
                $cart_items[] = ['product_id' => $cart_item['product_id'], 'name' => $product->get_name(), 'quantity' => $cart_item['quantity'], 'total' => $cart_item['line_total']];
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
                            url: apiEndpoint, type: 'POST', contentType: 'application/json',
                            data: JSON.stringify({ session_id: sessionId, phone: phone, name: name, email: email, address: address, cart_items: cartItems, cart_total: cartTotal }),
                            success: function(res) {}
                        });
                    }
                }
                $('form.checkout').on('input', 'input', function() { clearTimeout(debounceTimer); debounceTimer = setTimeout(captureData, 1000); });
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
        if ( ! $this->is_license_active(true) ) wp_send_json_error("License Inactive");
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
        if ( ! $this->is_license_active(true) ) wp_send_json_error("License Inactive");
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
        $is_licensed = $this->is_license_active(true);
        $features = $this->get_remote_features( true );
        $api_base = $this->get_api_base_url();
        include(plugin_dir_path(__FILE__) . 'admin-view.php');
    }
}

new BDC_SMS_Manager();
