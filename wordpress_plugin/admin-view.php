<?php 
// Allow access to variables from render_dashboard
$is_connected = isset($is_connected) ? $is_connected : false;
$is_licensed = isset($is_licensed) ? $is_licensed : false;
$dashboard_url = get_option( 'bdc_dashboard_url' );
$license_key = get_option( 'bdc_license_key' );

// Automation Settings
$automation_settings = get_option( 'bdc_sms_automation_settings', array() );
$statuses = array(
    'pending'    => 'Pending Payment',
    'processing' => 'Processing',
    'on-hold'    => 'On Hold',
    'completed'  => 'Completed',
    'cancelled'  => 'Cancelled',
    'refunded'   => 'Refunded',
    'failed'     => 'Failed'
);
?>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* Reset & Base */
    .bdc-wrap {
        font-family: 'Inter', sans-serif;
        background-color: #F8F9FB;
        color: #334155;
        margin: 0 -20px 0 -20px;
        padding: 40px;
        min-height: 100vh;
        box-sizing: border-box;
    }
    .bdc-wrap * { box-sizing: border-box; }

    /* Top Navigation Tabs */
    .bdc-nav {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        background: #fff;
        padding: 10px 20px;
        border-radius: 12px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        border: 1px solid #e2e8f0;
        overflow-x: auto;
        white-space: nowrap;
    }
    .bdc-nav-item {
        text-decoration: none;
        color: #64748b;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        padding: 10px 15px;
        border-radius: 8px;
        transition: all 0.2s;
        display: inline-block;
    }
    .bdc-nav-item:hover { background-color: #f1f5f9; color: #0f172a; }
    .bdc-nav-item.active {
        background-color: #eff6ff;
        color: #2563eb;
        font-weight: 600;
    }

    /* Tab Content Logic */
    .bdc-tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
    .bdc-tab-content.active { display: block !important; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    /* Grid Layout */
    .bdc-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        max-width: 1200px;
    }
    @media (max-width: 1000px) { .bdc-grid { grid-template-columns: 1fr; } }

    /* Cards */
    .bdc-card {
        background: #fff;
        border-radius: 16px;
        padding: 30px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    /* Section Title */
    .bdc-section-title {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 20px;
        display: block;
    }

    /* Status Block */
    .bdc-status-block { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 25px; }
    .bdc-check-icon { width: 40px; height: 40px; background: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; flex-shrink: 0; }
    .bdc-status-info h3 { margin: 0 0 5px; font-size: 16px; font-weight: 700; color: #1e293b; }
    .bdc-status-info p { margin: 0; font-size: 13px; color: #64748b; }
    .bdc-badge-active { background: #dcfce7; color: #15803d; font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 6px; text-transform: uppercase; margin-left: auto; }
    .bdc-badge-inactive { background: #fee2e2; color: #991b1b; font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 6px; text-transform: uppercase; margin-left: auto; }

    /* Forms */
    .bdc-input-wrap { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 15px; margin-bottom: 20px; display: flex; align-items: center; }
    .bdc-input-field { width: 100%; background: transparent; border: none; outline: none; font-family: monospace; color: #334155; }
    
    .bdc-setting-row { display: flex; align-items: center; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f1f5f9; }
    .bdc-setting-row:last-child { border-bottom: none; }
    .bdc-setting-label h4 { margin: 0 0 5px; font-size: 14px; font-weight: 600; color: #1e293b; }
    .bdc-setting-label p { margin: 0; font-size: 12px; color: #64748b; }
    
    /* Toggle Switch */
    .bdc-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .bdc-switch input { opacity: 0; width: 0; height: 0; }
    .bdc-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px; }
    .bdc-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .bdc-slider { background-color: #2563eb; }
    input:checked + .bdc-slider:before { transform: translateX(20px); }

    /* Buttons */
    .bdc-btn { padding: 12px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; border: none; transition: all 0.2s; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
    .bdc-btn-dark { background: #334155; color: white; width: 100%; }
    .bdc-btn-primary { background: #2563eb; color: white; border: none; }
    .bdc-btn-primary:hover { background: #1d4ed8; }

    /* List Items */
    .bdc-list-item { display: flex; align-items: center; justify-content: space-between; padding: 15px; background: #f8fafc; border-radius: 8px; margin-bottom: 15px; }
    .bdc-list-left { display: flex; align-items: center; gap: 12px; }
    .bdc-icon-box { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    .icon-blue { background: #eff6ff; color: #3b82f6; }
    .icon-green { background: #f0fdf4; color: #22c55e; }
    
    .bdc-list-label { font-size: 13px; font-weight: 500; color: #64748b; }
    .bdc-list-value { font-size: 13px; font-weight: 600; color: #334155; }

    /* Automation Specific */
    .bdc-automation-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
    .bdc-automation-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .bdc-automation-title { font-weight: 700; font-size: 15px; color: #1e293b; display: flex; align-items: center; gap: 8px; }
    .bdc-status-dot { width: 8px; height: 8px; border-radius: 50%; background: #cbd5e1; }
    .bdc-status-dot.active { background: #22c55e; box-shadow: 0 0 0 2px #dcfce7; }
    .bdc-msg-box { width: 100%; min-height: 80px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; line-height: 1.5; color: #334155; margin-bottom: 10px; font-family: inherit; resize: vertical; }
    .bdc-msg-box:focus { border-color: #3b82f6; outline: none; }
    .bdc-shortcodes { font-size: 11px; color: #64748b; background: #f8fafc; padding: 8px; border-radius: 6px; display: inline-block; }
    .bdc-code { font-family: monospace; background: #e2e8f0; padding: 2px 4px; border-radius: 4px; color: #475569; font-weight: 600; margin: 0 2px; }
</style>

<div class="bdc-wrap">
    
    <h1 class="bdc-section-title">Plugin Settings</h1>

    <!-- TABS Navigation -->
    <div class="bdc-nav">
        <div class="bdc-nav-item active" data-target="api">API Settings</div>
        <div class="bdc-nav-item" data-target="phone">Phone Validation</div>
        <div class="bdc-nav-item" data-target="courier">Courier Report</div>
        <div class="bdc-nav-item" data-target="filter">Smart Order Filter</div>
        <div class="bdc-nav-item" data-target="vpn">VPN Block</div>
        <div class="bdc-nav-item" data-target="fraud">Fraud Detection</div>
        <div class="bdc-nav-item" data-target="phonesearch">Phone Search</div>
        <div class="bdc-nav-item" data-target="automation">SMS Automation</div>
        <div class="bdc-nav-item" data-target="incomplete">Incomplete Orders</div>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'bdc_sms_group' ); ?>

        <!-- TAB 1: API Settings -->
        <div id="content-api" class="bdc-tab-content active">
            <div class="bdc-grid">
                <!-- API Connection Card -->
                <div class="bdc-card">
                    <div class="bdc-status-block">
                        <?php if ($is_licensed): ?>
                            <div class="bdc-check-icon"><span class="dashicons dashicons-yes"></span></div>
                            <div class="bdc-status-info">
                                <h3>API Key Connected</h3>
                                <p>Your API key is connected and working properly</p>
                            </div>
                            <span class="bdc-badge-active">ACTIVE</span>
                        <?php else: ?>
                            <div class="bdc-check-icon" style="background:#ef4444;"><span class="dashicons dashicons-no"></span></div>
                            <div class="bdc-status-info">
                                <h3>Not Connected</h3>
                                <p>Please enter your license key below.</p>
                            </div>
                            <span class="bdc-badge-inactive">INACTIVE</span>
                        <?php endif; ?>
                    </div>

                    <div class="bdc-input-wrap">
                        <input type="text" name="bdc_license_key" value="<?php echo esc_attr( get_option( 'bdc_license_key' ) ); ?>" class="bdc-input-field" placeholder="BDC-xxxxxxxx">
                    </div>
                    
                    <input type="hidden" name="bdc_dashboard_url" value="<?php echo esc_attr( get_option( 'bdc_dashboard_url' ) ); ?>">

                    <button type="submit" class="bdc-btn bdc-btn-dark">
                        <span class="dashicons dashicons-saved"></span> Save Configuration
                    </button>
                </div>

                <!-- Subscription Status Card -->
                <div class="bdc-card">
                    <h3 style="font-size:16px; font-weight:700; margin:0 0 20px 0;">Subscription Status</h3>
                    
                    <div class="bdc-list-item">
                        <div class="bdc-list-left">
                            <div class="bdc-icon-box icon-blue"><span class="dashicons dashicons-admin-site"></span></div>
                            <span class="bdc-list-label">Domain</span>
                        </div>
                        <span class="bdc-list-value"><?php echo parse_url(site_url(), PHP_URL_HOST); ?></span>
                    </div>

                    <div class="bdc-list-item" style="background:#f0fdf4;">
                        <div class="bdc-list-left">
                            <div class="bdc-icon-box icon-green"><span class="dashicons dashicons-email"></span></div>
                            <span class="bdc-list-label">SMS Balance</span>
                        </div>
                        <span class="bdc-list-value" id="sms-balance-display">Loading...</span>
                    </div>

                    <div style="margin-top:auto;">
                        <a href="<?php echo $dashboard_url ? rtrim($dashboard_url, '/') . '/buy-sms' : '#'; ?>" target="_blank" class="bdc-btn bdc-btn-primary" style="width:100%;">
                            <span class="dashicons dashicons-cart"></span> Recharge SMS
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: Phone Validation -->
        <div id="content-phone" class="bdc-tab-content">
            <div class="bdc-card" style="max-width: 800px;">
                <h3 style="font-size:16px; font-weight:700; margin-bottom:20px;">Phone Number Validation</h3>
                
                <div class="bdc-setting-row">
                    <div class="bdc-setting-label">
                        <h4>Validate 11 Digits</h4>
                        <p>Prevents users from entering phone numbers less or more than 11 digits at checkout.</p>
                    </div>
                    <label class="bdc-switch">
                        <input type="checkbox" name="bdc_fraud_phone_validation" value="1" <?php checked(1, get_option('bdc_fraud_phone_validation'), true); ?>>
                        <span class="bdc-slider"></span>
                    </label>
                </div>

                <div class="bdc-setting-row" style="display:block; border-bottom:none; padding-top:0;">
                    <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:8px; display:block;">Custom Error Message (Optional)</label>
                    <input type="text" name="bdc_fraud_phone_error_msg" value="<?php echo esc_attr( get_option( 'bdc_fraud_phone_error_msg' ) ); ?>" class="bdc-input-field" placeholder="Mobile Number Error: Please enter a valid 11-digit mobile number." style="background:#fff; border:1px solid #e2e8f0; padding:10px; border-radius:6px; width:100%;">
                    <p style="font-size:11px; color:#94a3b8; margin-top:5px;">If left empty, the default message will be shown.</p>
                </div>
                
                <div style="margin-top:20px;">
                    <button type="submit" class="bdc-btn bdc-btn-primary">Save Settings</button>
                </div>
            </div>
        </div>

        <!-- TAB 4: Courier Report (Placeholder) -->
        <div id="content-courier" class="bdc-tab-content">
            <div class="bdc-card">
                <div style="text-align:center; padding: 40px;">
                    <span class="dashicons dashicons-chart-bar" style="font-size:40px; color:#cbd5e1;"></span>
                    <h3 style="margin-top:10px; color:#64748b;">Courier Reporting</h3>
                    <p style="color:#94a3b8;">This feature allows you to see delivery success rates directly in your order list.</p>
                    <a href="<?php echo $dashboard_url; ?>" target="_blank" class="bdc-btn bdc-btn-primary" style="margin-top:20px; display:inline-flex;">View Full Report in Dashboard</a>
                </div>
            </div>
        </div>

        <!-- TAB 5: Smart Order Filter (Placeholder) -->
        <div id="content-filter" class="bdc-tab-content">
            <div class="bdc-card">
                <h3 style="font-size:16px; font-weight:700;">Smart Order Filter</h3>
                <p style="font-size:13px; color:#64748b; margin-top:10px;">This module runs automatically to flag duplicate orders and incomplete addresses.</p>
            </div>
        </div>

        <!-- TAB 6: VPN Block -->
        <div id="content-vpn" class="bdc-tab-content">
            <div class="bdc-card" style="max-width: 800px;">
                <h3 style="font-size:16px; font-weight:700; margin-bottom:20px;">VPN & Proxy Blocker</h3>
                
                <div class="bdc-setting-row">
                    <div class="bdc-setting-label">
                        <h4>Enable VPN/Proxy Blocking</h4>
                        <p>Automatically detects and restricts visitors using VPNs or Proxies from accessing your site or placing orders.</p>
                    </div>
                    <label class="bdc-switch">
                        <input type="checkbox" name="bdc_vpn_block_enabled" value="1" <?php checked(1, get_option('bdc_vpn_block_enabled'), true); ?>>
                        <span class="bdc-slider"></span>
                    </label>
                </div>
                
                <div style="margin-top:20px;">
                    <button type="submit" class="bdc-btn bdc-btn-primary">Save Settings</button>
                </div>
            </div>
        </div>

        <!-- TAB 7: Fraud Detection -->
        <div id="content-fraud" class="bdc-tab-content">
            <div class="bdc-card" style="max-width: 800px;">
                <h3 style="font-size:16px; font-weight:700; margin-bottom:20px;">Fraud Detection Settings</h3>
                
                <div class="bdc-setting-row">
                    <div class="bdc-setting-label">
                        <h4>Check Delivery History</h4>
                        <p>Check customer's previous delivery success rate from global database.</p>
                    </div>
                    <label class="bdc-switch">
                        <input type="checkbox" name="bdc_fraud_history_check" value="1" <?php checked(1, get_option('bdc_fraud_history_check'), true); ?>>
                        <span class="bdc-slider"></span>
                    </label>
                </div>

                <div class="bdc-setting-row">
                    <div class="bdc-setting-label">
                        <h4>Disable COD for Low Success Rate</h4>
                        <p>If delivery success rate is below threshold, hide Cash on Delivery.</p>
                    </div>
                    <label class="bdc-switch">
                        <input type="checkbox" name="bdc_fraud_disable_cod" value="1" <?php checked(1, get_option('bdc_fraud_disable_cod'), true); ?>>
                        <span class="bdc-slider"></span>
                    </label>
                </div>

                <!-- OTP Verification Toggle (Moved Here) -->
                <div class="bdc-setting-row">
                    <div class="bdc-setting-label">
                        <h4>Enable OTP Verification</h4>
                        <p>Require SMS verification for customers with low success rate (High Risk).</p>
                    </div>
                    <label class="bdc-switch">
                        <input type="checkbox" name="bdc_fraud_enable_otp" value="1" <?php checked(1, get_option('bdc_fraud_enable_otp'), true); ?>>
                        <span class="bdc-slider"></span>
                    </label>
                </div>

                <div class="bdc-setting-row">
                    <div class="bdc-setting-label">
                        <h4>Minimum Success Rate (%)</h4>
                        <p>Threshold to consider a customer "Safe". Default: 50%</p>
                    </div>
                    <input type="number" name="bdc_fraud_min_rate" value="<?php echo esc_attr(get_option('bdc_fraud_min_rate', 50)); ?>" style="width:80px; padding:5px; border:1px solid #e2e8f0; border-radius:6px;">
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="bdc-btn bdc-btn-primary">Save Settings</button>
                </div>
            </div>
        </div>

        <!-- TAB: Phone Search -->
        <div id="content-phonesearch" class="bdc-tab-content">
            <div class="bdc-card" style="max-width: 800px;">
                <h3 style="font-size:16px; font-weight:700; margin-bottom:20px;">Global Phone Search</h3>
                <p style="font-size:13px; color:#64748b; margin-bottom:20px;">Check delivery history for any number from the global database.</p>

                <div style="display:flex; gap:10px; margin-bottom:30px;">
                    <input type="text" id="bdc-phone-search-input" class="bdc-input-field" placeholder="Enter Phone Number (017xxxxxxxx)" style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; width:100%; font-size:14px;">
                    <button type="button" id="bdc-phone-search-btn" class="bdc-btn bdc-btn-primary" style="white-space:nowrap;">
                        <span class="dashicons dashicons-search"></span> Check
                    </button>
                </div>

                <div id="bdc-phone-result" style="display:none;"></div>
            </div>
        </div>

        <!-- TAB 9: SMS Automation (New) -->
        <div id="content-automation" class="bdc-tab-content">
            <div class="bdc-card" style="max-width: 900px;">
                <h3 style="font-size:16px; font-weight:700; margin-bottom:20px;">SMS Automation Rules</h3>
                <p style="font-size:13px; color:#64748b; margin-bottom:25px;">Configure automatic SMS messages based on order status changes. Toggle the switch to enable/disable specific notifications.</p>
                
                <?php foreach ($statuses as $slug => $label): 
                    $isEnabled = isset($automation_settings[$slug]['enabled']) ? $automation_settings[$slug]['enabled'] : 0;
                    $template = isset($automation_settings[$slug]['template']) ? $automation_settings[$slug]['template'] : "Hi [name], your order #[order_id] is now $label.";
                ?>
                <div class="bdc-automation-card">
                    <div class="bdc-automation-header">
                        <div class="bdc-automation-title">
                            <div class="bdc-status-dot <?php echo $isEnabled ? 'active' : ''; ?>"></div>
                            <?php echo esc_html($label); ?> Status
                        </div>
                        <label class="bdc-switch">
                            <input type="checkbox" name="bdc_sms_automation_settings[<?php echo esc_attr($slug); ?>][enabled]" value="1" <?php checked(1, $isEnabled, true); ?>>
                            <span class="bdc-slider"></span>
                        </label>
                    </div>
                    
                    <textarea name="bdc_sms_automation_settings[<?php echo esc_attr($slug); ?>][template]" class="bdc-msg-box" placeholder="Type your message here..."><?php echo esc_textarea($template); ?></textarea>
                    
                    <div class="bdc-shortcodes">
                        Available Shortcodes: 
                        <span class="bdc-code">[name]</span>
                        <span class="bdc-code">[order_id]</span>
                        <span class="bdc-code">[amount]</span>
                        <span class="bdc-code">[status]</span>
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top:20px;">
                    <button type="submit" class="bdc-btn bdc-btn-primary">Save Automation Settings</button>
                </div>
            </div>
        </div>

        <!-- TAB 8: Incomplete Orders (Placeholder) -->
        <div id="content-incomplete" class="bdc-tab-content">
            <div class="bdc-card">
                <div style="text-align:center; padding: 40px;">
                    <span class="dashicons dashicons-cart" style="font-size:40px; color:#cbd5e1;"></span>
                    <h3 style="margin-top:10px; color:#64748b;">Live Incomplete Orders</h3>
                    <p style="color:#94a3b8;">See customers who filled out the checkout form but didn't click "Place Order". Recover lost sales.</p>
                    <a href="<?php echo $dashboard_url; ?>" target="_blank" class="bdc-btn bdc-btn-primary" style="margin-top:20px; display:inline-flex;">View Leads in Dashboard</a>
                </div>
            </div>
        </div>

    </form>

    <!-- Hidden Settings Modal (To allow configuring URL manually if needed) -->
    <div style="margin-top:40px; text-align:left;">
        <p style="font-size:12px; color:#94a3b8; cursor:pointer;" onclick="document.getElementById('adv-settings').style.display='block'">Advanced Configuration</p>
    </div>

    <div id="adv-settings" class="bdc-modal-overlay">
        <div class="bdc-modal-wrap">
            <span class="bdc-close-modal" onclick="document.getElementById('adv-settings').style.display='none'">&times;</span>
            <h3 style="margin-top:0;">Advanced Settings</h3>
            <form method="post" action="options.php">
                <?php settings_fields( 'bdc_sms_group' ); ?>
                <div style="margin-bottom:15px;">
                    <label style="font-size:12px; font-weight:bold;">Dashboard URL</label>
                    <input type="text" name="bdc_dashboard_url" value="<?php echo esc_attr( get_option( 'bdc_dashboard_url' ) ); ?>" class="bdc-input-field" style="border:1px solid #ddd; padding:8px; border-radius:4px;">
                </div>
                <!-- Hidden License Key field to prevent overwriting with empty if only URL is saved here -->
                <input type="hidden" name="bdc_license_key" value="<?php echo esc_attr( get_option( 'bdc_license_key' ) ); ?>">
                <button type="submit" class="bdc-btn bdc-btn-dark">Save URL</button>
            </form>
        </div>
    </div>

</div>

<!-- Scripts for Tabs & Data -->
<script>
jQuery(document).ready(function($) {
    // 1. Restore Active Tab from LocalStorage
    var savedTab = localStorage.getItem('bdc_active_tab');
    if(savedTab && $('#content-' + savedTab).length > 0) {
        $('.bdc-nav-item').removeClass('active');
        $('.bdc-tab-content').removeClass('active');
        
        $('.bdc-nav-item[data-target="' + savedTab + '"]').addClass('active');
        $('#content-' + savedTab).addClass('active');
    }

    // 2. Tab Switching Logic
    $('.bdc-nav-item').on('click', function(e) {
        e.preventDefault(); // Prevent default just in case
        var target = $(this).data('target');
        
        // Remove active class from all nav items and contents
        $('.bdc-nav-item').removeClass('active');
        $('.bdc-tab-content').removeClass('active');
        
        // Add active class to clicked item and target content
        $(this).addClass('active');
        $('#content-' + target).addClass('active');
        
        // Save to LocalStorage
        localStorage.setItem('bdc_active_tab', target);
    });

    // Fetch SMS Balance
    <?php if ($is_licensed && isset($api_base) && $api_base): ?>
    $.ajax({
        url: "<?php echo esc_url($api_base . '/manage_sms_balance.php'); ?>",
        dataType: 'json',
        success: function(data) {
            if(data.balance !== undefined) {
                $('#sms-balance-display').text(data.balance);
            } else {
                $('#sms-balance-display').text('0');
            }
        },
        error: function() {
            $('#sms-balance-display').text('N/A');
        }
    });
    <?php else: ?>
        $('#sms-balance-display').text('0');
    <?php endif; ?>

    // Phone Search Logic
    $('#bdc-phone-search-btn').on('click', function() {
        var phone = $('#bdc-phone-search-input').val();
        var container = $('#bdc-phone-result');
        var btn = $(this);
        
        if(phone.length < 10) { alert('Invalid Phone Number'); return; }

        btn.prop('disabled', true).text('Checking...');
        container.hide().html('');

        // Construct API URL based on PHP variable
        var dashboardUrl = "<?php echo rtrim($dashboard_url, '/'); ?>"; 
        var apiUrl = dashboardUrl.includes('/api') ? dashboardUrl : dashboardUrl + '/api';

        $.ajax({
            url: apiUrl + '/check_fraud.php',
            data: { phone: phone, refresh: 'true' }, // Force refresh for manual check
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Check');
                
                if(res.error) {
                    container.html('<div style="color:red; padding:20px; text-align:center;">' + res.error + '</div>').show();
                    return;
                }

                var rate = parseFloat(res.success_rate);
                var color = rate >= 80 ? '#16a34a' : (rate >= 50 ? '#ea580c' : '#dc2626');
                var bg = rate >= 80 ? '#f0fdf4' : (rate >= 50 ? '#fff7ed' : '#fef2f2');
                
                var html = '<div style="background:'+bg+'; border:1px solid '+color+'; border-radius:12px; padding:30px; text-align:center;">';
                html += '<div style="font-size:48px; font-weight:900; color:'+color+'; line-height:1;">' + rate + '%</div>';
                html += '<div style="font-size:12px; font-weight:700; color:'+color+'; text-transform:uppercase; margin-top:5px; margin-bottom:20px;">Success Rate</div>';
                
                html += '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; border-top:1px solid rgba(0,0,0,0.05); padding-top:20px;">';
                    html += '<div><div style="font-size:20px; font-weight:800; color:#334155;">' + res.total_orders + '</div><div style="font-size:10px; color:#64748b; text-transform:uppercase;">Total</div></div>';
                    html += '<div><div style="font-size:20px; font-weight:800; color:#16a34a;">' + res.delivered + '</div><div style="font-size:10px; color:#16a34a; text-transform:uppercase;">Delivered</div></div>';
                    html += '<div><div style="font-size:20px; font-weight:800; color:#dc2626;">' + res.cancelled + '</div><div style="font-size:10px; color:#dc2626; text-transform:uppercase;">Cancelled</div></div>';
                html += '</div>';
                html += '</div>';

                if(res.details && res.details.length > 0) {
                    html += '<div style="margin-top:20px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden;">';
                    html += '<div style="padding:10px 15px; background:#f8fafc; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">History Breakdown</div>';
                    res.details.forEach(function(item) {
                        html += '<div style="padding:10px 15px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; font-size:13px;">';
                        html += '<span style="font-weight:600; color:#334155;">' + item.courier + '</span>';
                        html += '<span style="color:#64748b;">' + item.status + '</span>';
                        html += '</div>';
                    });
                    html += '</div>';
                }

                container.html(html).show();
            },
            error: function() {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Check');
                container.html('<div style="color:red; padding:20px; text-align:center;">Failed to connect to API</div>').show();
            }
        });
    });
});
</script>