<?php 
// Allow access to variables from render_dashboard
$is_connected = isset($is_connected) ? $is_connected : false;
$is_licensed = isset($is_licensed) ? $is_licensed : false;
$dashboard_url = get_option( 'bdc_dashboard_url' );
$license_key = get_option( 'bdc_license_key' );
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
    .bdc-tab-content.active { display: block; }
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
</style>

<div class="bdc-wrap">
    
    <h1 class="bdc-section-title">Plugin Settings</h1>

    <!-- TABS Navigation -->
    <div class="bdc-nav">
        <div class="bdc-nav-item active" data-target="api">API Settings</div>
        <div class="bdc-nav-item" data-target="phone">Phone Validation</div>
        <div class="bdc-nav-item" data-target="otp">OTP Settings</div>
        <div class="bdc-nav-item" data-target="courier">Courier Report</div>
        <div class="bdc-nav-item" data-target="filter">Smart Order Filter</div>
        <div class="bdc-nav-item" data-target="vpn">VPN Block</div>
        <div class="bdc-nav-item" data-target="fraud">Fraud Detection</div>
        <div class="bdc-nav-item" data-target="incomplete">Incomplete Orders</div>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'bdc_sms_group' ); ?>
        <?php settings_fields( 'bdc_fraud_group' ); ?>

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
                
                <div style="margin-top:20px;">
                    <button type="submit" class="bdc-btn bdc-btn-primary">Save Settings</button>
                </div>
            </div>
        </div>

        <!-- TAB 3: OTP Settings -->
        <div id="content-otp" class="bdc-tab-content">
            <div class="bdc-card" style="max-width: 800px;">
                <h3 style="font-size:16px; font-weight:700; margin-bottom:20px;">OTP Verification</h3>
                
                <div class="bdc-setting-row">
                    <div class="bdc-setting-label">
                        <h4>Enable OTP for COD</h4>
                        <p>Require SMS verification if the user selects Cash on Delivery.</p>
                    </div>
                    <label class="bdc-switch">
                        <input type="checkbox" name="bdc_fraud_enable_otp" value="1" <?php checked(1, get_option('bdc_fraud_enable_otp'), true); ?>>
                        <span class="bdc-slider"></span>
                    </label>
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

        <!-- TAB 6: VPN Block (Placeholder) -->
        <div id="content-vpn" class="bdc-tab-content">
            <div class="bdc-card">
                <h3 style="font-size:16px; font-weight:700;">VPN & Proxy Blocker</h3>
                <p style="font-size:13px; color:#64748b; margin-top:10px;">Automatically detects and restricts orders placed via VPN or Proxy IPs to prevent fraud.</p>
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
    // Tab Switching Logic
    $('.bdc-nav-item').on('click', function() {
        var target = $(this).data('target');
        
        // Remove active class from all nav items and contents
        $('.bdc-nav-item').removeClass('active');
        $('.bdc-tab-content').removeClass('active');
        
        // Add active class to clicked item and target content
        $(this).addClass('active');
        $('#content-' + target).addClass('active');
    });

    // Fetch SMS Balance
    <?php if ($is_licensed && $api_base): ?>
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
});
</script>