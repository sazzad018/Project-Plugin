<?php 
// Allow access to variables from render_dashboard
$is_connected = isset($is_connected) ? $is_connected : false;
$is_licensed = isset($is_licensed) ? $is_licensed : false;
$dashboard_url = get_option( 'bdc_dashboard_url' );
$license_key = get_option( 'bdc_license_key' );
$mask_key = $license_key ? substr($license_key, 0, 8) . '••••••' . substr($license_key, -8) : '';

// Get Balance (This would ideally come from an API call or transient)
$balance = 0; // Default
// Assuming balance is fetched via JS or stored locally in future updates
?>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* Reset & Base */
    .bdc-wrap {
        font-family: 'Inter', sans-serif;
        background-color: #F8F9FB;
        color: #334155;
        margin: 0 -20px 0 -20px; /* Counteract WP padding */
        padding: 40px;
        min-height: 100vh;
        box-sizing: border-box;
    }
    .bdc-wrap * { box-sizing: border-box; }

    /* Top Navigation Tabs */
    .bdc-nav {
        display: flex;
        gap: 30px;
        margin-bottom: 40px;
        background: #fff;
        padding: 15px 30px;
        border-radius: 12px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        border: 1px solid #e2e8f0;
        overflow-x: auto;
    }
    .bdc-nav-item {
        text-decoration: none;
        color: #64748b;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        padding-bottom: 2px;
        white-space: nowrap;
        transition: all 0.2s;
    }
    .bdc-nav-item:hover { color: #2563eb; }
    .bdc-nav-item.active {
        color: #2563eb;
        font-weight: 600;
        border-bottom: 2px solid #2563eb;
    }

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
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 25px;
        display: block;
    }

    /* API Status Block */
    .bdc-status-block {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-bottom: 25px;
    }
    .bdc-check-icon {
        width: 40px;
        height: 40px;
        background: #22c55e;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        flex-shrink: 0;
    }
    .bdc-status-info h3 { margin: 0 0 5px; font-size: 16px; font-weight: 700; color: #1e293b; }
    .bdc-status-info p { margin: 0; font-size: 13px; color: #64748b; }
    
    .bdc-badge-active {
        background: #dcfce7;
        color: #15803d;
        font-size: 10px;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 6px;
        text-transform: uppercase;
        margin-left: auto;
    }
    .bdc-badge-inactive {
        background: #fee2e2;
        color: #991b1b;
        font-size: 10px;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 6px;
        text-transform: uppercase;
        margin-left: auto;
    }

    /* Inputs */
    .bdc-input-wrap {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 20px;
        font-family: monospace;
        color: #334155;
        font-size: 14px;
        display: flex;
        align-items: center;
    }
    .bdc-input-field {
        width: 100%;
        background: transparent;
        border: none;
        outline: none;
        font-family: inherit;
        color: inherit;
    }

    /* Buttons */
    .bdc-btn {
        width: 100%;
        padding: 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
    }
    .bdc-btn-dark { background: #334155; color: white; }
    .bdc-btn-dark:hover { background: #1e293b; }
    .bdc-btn-primary { background: #6366f1; color: white; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); }
    .bdc-btn-primary:hover { opacity: 0.9; }

    /* Promo Box */
    .bdc-promo {
        background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
        border-radius: 12px;
        padding: 25px;
        color: white;
        margin-top: 30px;
        text-align: left;
    }
    .bdc-promo h4 { margin: 0 0 10px; font-size: 16px; font-weight: 700; }
    .bdc-promo p { margin: 0 0 20px; font-size: 13px; opacity: 0.9; line-height: 1.5; }
    .bdc-btn-promo {
        background: rgba(255,255,255,0.2);
        color: white;
        width: auto;
        padding: 10px 20px;
        border: 1px solid rgba(255,255,255,0.3);
    }
    .bdc-btn-promo:hover { background: rgba(255,255,255,0.3); }

    /* List Items */
    .bdc-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        background: #f8fafc;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .bdc-list-left { display: flex; align-items: center; gap: 12px; }
    .bdc-icon-box {
        width: 32px; height: 32px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; font-size: 14px;
    }
    .icon-blue { background: #eff6ff; color: #3b82f6; }
    .icon-orange { background: #fff7ed; color: #f97316; }
    .icon-green { background: #f0fdf4; color: #22c55e; }
    .icon-gray { background: #f1f5f9; color: #64748b; }
    
    .bdc-list-label { font-size: 13px; font-weight: 500; color: #64748b; }
    .bdc-list-value { font-size: 13px; font-weight: 600; color: #334155; }
    
    .bdc-add-btn {
        font-size: 11px; font-weight: 700; color: #0f172a; cursor: pointer; text-decoration: none;
        display: flex; align-items: center; gap: 4px;
    }

    /* Modal */
    .bdc-modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); z-index: 99999;
        display: none; align-items: center; justify-content: center;
    }
    .bdc-modal-wrap {
        background: white; width: 400px; padding: 30px; border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        position: relative;
    }
    .bdc-close-modal { position: absolute; top: 15px; right: 15px; cursor: pointer; font-size: 20px; color: #94a3b8; }
    
    /* Footer Button */
    .bdc-footer-btn {
        margin-top: 40px;
        text-align: left;
    }
    .wp-core-ui .button-primary.bdc-save-btn {
        background: #4f46e5;
        border-color: #4f46e5;
        padding: 8px 25px;
        font-size: 14px;
        font-weight: 600;
        border-radius: 6px;
        height: auto;
    }
</style>

<div class="bdc-wrap">
    
    <!-- Title -->
    <h1 class="bdc-section-title">API Key Settings</h1>

    <!-- Tabs -->
    <div class="bdc-nav">
        <a href="#api" class="bdc-nav-item active">API Settings</a>
        <a href="#phone" class="bdc-nav-item">Phone Validation</a>
        <a href="#otp" class="bdc-nav-item">OTP Settings</a>
        <a href="#courier" class="bdc-nav-item">Courier Report</a>
        <a href="#filter" class="bdc-nav-item">Smart Order Filter</a>
        <a href="#vpn" class="bdc-nav-item">VPN Block</a>
        <a href="#fraud" class="bdc-nav-item">Fraud Detection</a>
        <a href="#incomplete" class="bdc-nav-item">Incomplete Orders</a>
    </div>

    <div class="bdc-grid">
        
        <!-- Left Card: API Connection -->
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

            <form method="post" action="options.php">
                <?php settings_fields( 'bdc_sms_group' ); ?>
                
                <div class="bdc-input-wrap">
                    <input type="text" name="bdc_license_key" value="<?php echo esc_attr( get_option( 'bdc_license_key' ) ); ?>" class="bdc-input-field" placeholder="BDC-xxxxxxxx" id="licenseField">
                </div>
                
                <!-- Hidden Fields required for settings to save properly if not edited -->
                <input type="hidden" name="bdc_dashboard_url" value="<?php echo esc_attr( get_option( 'bdc_dashboard_url' ) ); ?>">

                <button type="submit" class="bdc-btn bdc-btn-dark">
                    <span class="dashicons dashicons-edit"></span> <?php echo $is_licensed ? 'Change API Key' : 'Connect API Key'; ?>
                </button>
            </form>

            <div class="bdc-promo">
                <h4>Thanks for Updating!</h4>
                <p>We appreciate you for keeping your plugin up to date. 🎉 Join our community group to get exclusive coupon codes and stay connected.</p>
                <a href="https://www.facebook.com/groups/bdcommerce" target="_blank" class="bdc-btn bdc-btn-promo">Join Group</a>
            </div>
        </div>

        <!-- Right Card: Subscription Status -->
        <div class="bdc-card">
            <h3 style="font-size:16px; font-weight:700; margin:0 0 20px 0;">Subscription Status</h3>
            
            <div class="bdc-list-item">
                <div class="bdc-list-left">
                    <div class="bdc-icon-box icon-blue"><span class="dashicons dashicons-admin-site"></span></div>
                    <span class="bdc-list-label">Domain</span>
                </div>
                <span class="bdc-list-value"><?php echo parse_url(site_url(), PHP_URL_HOST); ?></span>
            </div>

            <div class="bdc-list-item" style="background:#fff7ed;">
                <div class="bdc-list-left">
                    <div class="bdc-icon-box icon-orange"><span class="dashicons dashicons-clock"></span></div>
                    <span class="bdc-list-label">Time Remaining</span>
                </div>
                <span class="bdc-list-value">Unlimited <span class="dashicons dashicons-info" style="font-size:12px; color:#f97316;"></span></span>
            </div>

            <div class="bdc-list-item" style="background:#f0fdf4;">
                <div class="bdc-list-left">
                    <div class="bdc-icon-box icon-green"><span class="dashicons dashicons-email"></span></div>
                    <span class="bdc-list-label">SMS Balance</span>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="bdc-list-value" style="color:#16a34a; font-size:16px;" id="sms-balance-display">...</span>
                    <a href="<?php echo $dashboard_url ? rtrim($dashboard_url, '/') . '/buy-sms' : '#'; ?>" target="_blank" class="bdc-add-btn"><span class="dashicons dashicons-plus"></span> Add</a>
                </div>
            </div>

            <div class="bdc-list-item">
                <div class="bdc-list-left">
                    <div class="bdc-icon-box icon-gray"><span class="dashicons dashicons-backup"></span></div>
                    <span class="bdc-list-label">Last Updated</span>
                </div>
                <span class="bdc-list-value"><?php echo date("M d, Y h:i A"); ?></span>
            </div>

            <div style="margin-top:auto;">
                <button class="bdc-btn bdc-btn-primary" onclick="window.location.reload()">
                    <span class="dashicons dashicons-update"></span> Renew / Refresh
                </button>
            </div>
        </div>

    </div>

    <!-- Hidden Settings Modal (To allow configuring URL if needed) -->
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
                <input type="hidden" name="bdc_license_key" value="<?php echo esc_attr( get_option( 'bdc_license_key' ) ); ?>">
                <button type="submit" class="bdc-btn bdc-btn-dark">Save URL</button>
            </form>
        </div>
    </div>

</div>

<!-- Scripts to handle dynamic data -->
<script>
jQuery(document).ready(function($) {
    // Fetch SMS Balance dynamically via AJAX if API is connected
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