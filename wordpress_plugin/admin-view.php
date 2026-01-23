<?php 
// Allow access to variables from render_dashboard
$is_connected = isset($is_connected) ? $is_connected : false;
$customers = isset($customers) ? $customers : [];
$features = isset($features) ? $features : [];

// Helper to check feature status
function is_feature_active($key, $features) {
    return isset($features[$key]) && $features[$key] == true;
}

// Get Local Settings
$local_fraud_phone_enabled = get_option('bdc_fraud_phone_validation');
$local_fraud_history_enabled = get_option('bdc_fraud_history_check');
$local_min_rate = get_option('bdc_fraud_min_rate', 50);
$local_disable_cod = get_option('bdc_fraud_disable_cod');
$local_enable_otp = get_option('bdc_fraud_enable_otp');
?>

<!-- Custom Dashboard Styles -->
<style>
    .bdc-dashboard {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        background-color: #f0f2f5;
        margin-left: -20px;
        padding: 20px;
        min-height: 100vh;
        box-sizing: border-box;
    }
    .bdc-dashboard * { box-sizing: border-box; }
    
    /* Header */
    .bdc-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .bdc-title h1 {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }
    .bdc-title p { margin: 5px 0 0; color: #64748b; font-size: 13px; }
    
    /* Stats Cards */
    .bdc-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .bdc-stat-card {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .bdc-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    .bdc-stat-info h3 { margin: 0 0 5px; font-size: 24px; font-weight: 800; color: #0f172a; }
    .bdc-stat-info span { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .bg-orange-soft { background: #fff7ed; color: #ea580c; }
    .bg-blue-soft { background: #eff6ff; color: #2563eb; }
    .bg-green-soft { background: #f0fdf4; color: #16a34a; }
    
    /* Main Container */
    .bdc-main-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    /* Tabs */
    .bdc-tabs {
        display: flex;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 0 20px;
    }
    .bdc-tab-btn {
        padding: 16px 20px;
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        transition: all 0.2s;
    }
    .bdc-tab-btn:hover { color: #ea580c; }
    .bdc-tab-btn.active {
        color: #ea580c;
        border-bottom-color: #ea580c;
        background: #fff;
    }
    .bdc-tab-btn .dashicons { font-size: 16px; margin-right: 5px; vertical-align: middle; }
    
    /* Content Area */
    .bdc-content { padding: 30px; display: none; }
    .bdc-content.active { display: block; animation: fadeIn 0.3s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    
    /* Tables */
    .bdc-table-container {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
    }
    .bdc-table {
        width: 100%;
        border-collapse: collapse;
    }
    .bdc-table thead { background: #f8fafc; }
    .bdc-table th {
        text-align: left;
        padding: 12px 20px;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        border-bottom: 1px solid #e2e8f0;
    }
    .bdc-table td {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
        font-size: 14px;
    }
    .bdc-table tr:last-child td { border-bottom: none; }
    .bdc-table tr:hover { background: #f8fafc; }
    
    /* Buttons */
    .bdc-btn {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
        text-decoration: none;
    }
    .bdc-btn-primary { background: #ea580c; color: white; box-shadow: 0 2px 4px rgba(234, 88, 12, 0.2); }
    .bdc-btn-primary:hover { background: #c2410c; color: white; }
    
    /* Inputs */
    .bdc-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 14px;
        outline: none;
        transition: border 0.2s;
    }
    .bdc-input:focus { border-color: #ea580c; ring: 1px solid #ea580c; }
    
    /* Features Grid */
    .bdc-feature-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        transition: all 0.2s;
    }
    .bdc-feature-card.active { background: #fff; border-color: #bbf7d0; }
    .bdc-feature-card.inactive { background: #f8fafc; opacity: 0.8; }
    
    .bdc-badge {
        font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 20px; text-transform: uppercase;
    }
    .bdc-badge-success { background: #dcfce7; color: #166534; }
    .bdc-badge-gray { background: #e2e8f0; color: #475569; }

    /* Switch */
    .bdc-switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 24px;
    }
    .bdc-switch input { opacity: 0; width: 0; height: 0; }
    .bdc-slider {
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1; -webkit-transition: .4s; transition: .4s; border-radius: 34px;
    }
    .bdc-slider:before {
        position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
        background-color: white; -webkit-transition: .4s; transition: .4s; border-radius: 50%;
    }
    input:checked + .bdc-slider { background-color: #ea580c; }
    input:focus + .bdc-slider { box-shadow: 0 0 1px #ea580c; }
    input:checked + .bdc-slider:before { -webkit-transform: translateX(16px); -ms-transform: translateX(16px); transform: translateX(16px); }

    /* Range Slider */
    .bdc-range-wrap { position: relative; padding: 10px 0; }
    .bdc-range { width: 100%; cursor: pointer; }
    .bdc-range-val { font-weight: bold; color: #ea580c; }

    /* Forms */
    .bdc-form-group { margin-bottom: 20px; }
    .bdc-label { display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 13px; }
    
    /* Connection Status */
    .bdc-status-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 12px; border-radius: 20px;
        font-size: 12px; font-weight: 600;
    }
    .status-ok { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .status-err { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    
    /* New Checkbox Styling */
    .bdc-check-group {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
    }
    .bdc-check-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px dashed #e2e8f0;
    }
    .bdc-check-row:last-child { border-bottom: none; }
    .bdc-check-label { font-size: 13px; font-weight: 600; color: #475569; }
</style>

<div class="bdc-dashboard">
    <!-- Header -->
    <div class="bdc-header">
        <div class="bdc-title">
            <h1>SMS Manager</h1>
            <p>Customer Synchronization & Marketing Suite</p>
        </div>
        <div>
            <?php if($is_connected): ?>
                <div class="bdc-status-pill status-ok">
                    <span class="dashicons dashicons-yes"></span> Connected to Dashboard
                </div>
            <?php else: ?>
                <div class="bdc-status-pill status-err">
                    <span class="dashicons dashicons-warning"></span> Not Connected
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="bdc-stats-grid">
        <div class="bdc-stat-card">
            <div class="bdc-stat-icon bg-blue-soft"><span class="dashicons dashicons-admin-users"></span></div>
            <div class="bdc-stat-info">
                <h3><?php echo count($customers); ?></h3>
                <span>Total Customers</span>
            </div>
        </div>
        <div class="bdc-stat-card">
            <div class="bdc-stat-icon bg-orange-soft"><span class="dashicons dashicons-smartphone"></span></div>
            <div class="bdc-stat-info">
                <h3>Active</h3>
                <span>SMS Gateway</span>
            </div>
        </div>
        <div class="bdc-stat-card">
            <div class="bdc-stat-icon bg-green-soft"><span class="dashicons dashicons-shield"></span></div>
            <div class="bdc-stat-info">
                <h3><?php echo is_feature_active('fraud_guard', $features) ? 'ON' : 'OFF'; ?></h3>
                <span>Fraud Guard</span>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="bdc-main-card">
        <!-- Tabs -->
        <div class="bdc-tabs">
            <button onclick="switchTab('customers')" id="tab-customers" class="bdc-tab-btn active">
                <span class="dashicons dashicons-groups"></span> Customers
            </button>
            <button onclick="switchTab('features')" id="tab-features" class="bdc-tab-btn">
                <span class="dashicons dashicons-grid-view"></span> Features
            </button>
            <button onclick="switchTab('send-sms')" id="tab-send-sms" class="bdc-tab-btn">
                <span class="dashicons dashicons-email-alt"></span> Send SMS
            </button>
            <button onclick="switchTab('settings')" id="tab-settings" class="bdc-tab-btn">
                <span class="dashicons dashicons-admin-settings"></span> Settings
            </button>
        </div>

        <!-- Content: Customers -->
        <div id="content-customers" class="bdc-content active">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h2 style="font-size:18px; font-weight:700; color:#1e293b; margin:0;">Database Overview</h2>
                <button id="sync-btn" class="bdc-btn bdc-btn-primary">
                    <span class="dashicons dashicons-update"></span> Sync from WooCommerce
                </button>
            </div>

            <div class="bdc-table-container">
                <table class="bdc-table">
                    <thead>
                        <tr>
                            <th width="50"><input type="checkbox" id="select-all"></th>
                            <th>Customer Name</th>
                            <th>Phone Number</th>
                            <th>Total Spent</th>
                            <th>Orders</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $customers ) ) : ?>
                            <tr><td colspan="5" style="text-align:center; padding: 40px; color: #94a3b8;">No customer data found. Click Sync to import from WooCommerce.</td></tr>
                        <?php else : ?>
                            <?php 
                            // Show max 20 for preview
                            $display_customers = array_slice($customers, 0, 20);
                            foreach ( $display_customers as $customer ) : ?>
                                <tr>
                                    <td><input type="checkbox" name="customer_phone[]" value="<?php echo esc_attr( $customer->phone ); ?>" class="customer-cb"></td>
                                    <td><strong><?php echo esc_html( $customer->name ); ?></strong></td>
                                    <td style="font-family:monospace; color:#64748b;"><?php echo esc_html( $customer->phone ); ?></td>
                                    <td><?php echo wc_price( $customer->total_spent ); ?></td>
                                    <td>
                                        <span class="bdc-badge <?php echo $customer->order_count > 1 ? 'bdc-badge-success' : 'bdc-badge-gray'; ?>">
                                            <?php echo esc_html( $customer->order_count ); ?> Orders
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if(count($customers) > 20): ?>
                <div style="text-align:center; padding-top:15px; color:#64748b; font-size:12px;">Showing recent 20 of <?php echo count($customers); ?> customers</div>
            <?php endif; ?>
        </div>

        <!-- Content: Features -->
        <div id="content-features" class="bdc-content">
            <div style="margin-bottom: 30px;">
                <h2 style="font-size:18px; font-weight:700; color:#1e293b;">Active Modules</h2>
                <p style="color:#64748b; font-size:13px;">Manage these features from the central React Dashboard.</p>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                    <!-- Live Capture -->
                    <div class="bdc-feature-card <?php echo is_feature_active('live_capture', $features) ? 'active' : 'inactive'; ?>">
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                            <div style="display:flex; gap:10px; align-items:center;">
                                <span class="dashicons dashicons-visibility" style="color:#ea580c; font-size:24px; height:24px; width:24px;"></span>
                                <h3 style="margin:0; font-size:16px;">Live Lead Capture</h3>
                            </div>
                            <span class="bdc-badge <?php echo is_feature_active('live_capture', $features) ? 'bdc-badge-success' : 'bdc-badge-gray'; ?>">
                                <?php echo is_feature_active('live_capture', $features) ? 'ACTIVE' : 'INACTIVE'; ?>
                            </span>
                        </div>
                        <p style="color:#64748b; font-size:13px; line-height:1.5;">Captures checkout form data in real-time before submission.</p>
                    </div>

                    <!-- Fraud Guard -->
                    <div class="bdc-feature-card <?php echo is_feature_active('fraud_guard', $features) ? 'active' : 'inactive'; ?>">
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                            <div style="display:flex; gap:10px; align-items:center;">
                                <span class="dashicons dashicons-shield" style="color:#2563eb; font-size:24px; height:24px; width:24px;"></span>
                                <h3 style="margin:0; font-size:16px;">Fraud Guard AI</h3>
                            </div>
                            <span class="bdc-badge <?php echo is_feature_active('fraud_guard', $features) ? 'bdc-badge-success' : 'bdc-badge-gray'; ?>">
                                <?php echo is_feature_active('fraud_guard', $features) ? 'ACTIVE' : 'INACTIVE'; ?>
                            </span>
                        </div>
                        <p style="color:#64748b; font-size:13px; line-height:1.5;">Analyzes delivery history to prevent fake orders.</p>
                    </div>
                </div>
            </div>

            <!-- Fraud Guard Local Settings -->
            <div style="border-top: 1px solid #e2e8f0; padding-top: 30px;">
                <h2 style="font-size:18px; font-weight:700; color:#1e293b; margin-bottom: 20px;">Fraud Guard Settings</h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'bdc_fraud_group' ); ?>
                    
                    <!-- Phone Validation -->
                    <div class="bdc-feature-card active" style="border-left: 4px solid #ea580c; margin-bottom: 20px;">
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <div>
                                <h3 style="margin:0 0 5px; font-size:16px;">11-Digit Phone Validation</h3>
                                <p style="color:#64748b; font-size:13px; margin:0;">Prevent checkout if phone number is less than 11 digits.</p>
                            </div>
                            <label class="bdc-switch">
                                <input type="checkbox" name="bdc_fraud_phone_validation" value="1" <?php checked( 1, $local_fraud_phone_enabled, true ); ?>>
                                <span class="bdc-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Delivery History Check -->
                    <div class="bdc-feature-card active" style="border-left: 4px solid #2563eb;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 15px;">
                            <div>
                                <h3 style="margin:0 0 5px; font-size:16px;">Delivery Success Rate Check</h3>
                                <p style="color:#64748b; font-size:13px; margin:0;">Analyze customer history. If success rate is below threshold, trigger actions.</p>
                            </div>
                            <label class="bdc-switch">
                                <input type="checkbox" name="bdc_fraud_history_check" value="1" <?php checked( 1, $local_fraud_history_enabled, true ); ?>>
                                <span class="bdc-slider"></span>
                            </label>
                        </div>
                        
                        <!-- NEW OPTIONS -->
                        <div class="bdc-check-group">
                            <div class="bdc-check-row">
                                <span class="bdc-check-label">Disable Cash on Delivery (COD) for Low Success Rate?</span>
                                <label class="bdc-switch">
                                    <input type="checkbox" name="bdc_fraud_disable_cod" value="1" <?php checked( 1, $local_disable_cod, true ); ?>>
                                    <span class="bdc-slider"></span>
                                </label>
                            </div>
                            <div class="bdc-check-row">
                                <span class="bdc-check-label">Enable OTP Verification for Low Success Rate?</span>
                                <label class="bdc-switch">
                                    <input type="checkbox" name="bdc_fraud_enable_otp" value="1" <?php checked( 1, $local_enable_otp, true ); ?>>
                                    <span class="bdc-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="bdc-range-wrap" style="background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0; margin-top:15px;">
                            <label class="bdc-label" style="display:flex; justify-content:space-between;">
                                Minimum Success Rate Threshold:
                                <span class="bdc-range-val"><span id="rate-display"><?php echo esc_attr($local_min_rate); ?></span>%</span>
                            </label>
                            <input type="range" class="bdc-range" name="bdc_fraud_min_rate" min="0" max="100" value="<?php echo esc_attr($local_min_rate); ?>" oninput="document.getElementById('rate-display').textContent = this.value">
                            <p style="font-size:11px; color:#94a3b8; margin-top:5px; font-style:italic;">Note: If customer's success rate is BELOW this %, the selected actions (Disable COD / OTP) will trigger.</p>
                        </div>
                    </div>

                    <div style="margin-top: 20px; text-align: right;">
                        <?php submit_button( 'Save Fraud Settings', 'primary', 'submit', false, ['class' => 'bdc-btn bdc-btn-primary'] ); ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Content: Send SMS -->
        <div id="content-send-sms" class="bdc-content">
            <?php if(!$is_connected): ?>
                <div style="background:#fee2e2; border-left:4px solid #ef4444; padding:15px; border-radius:4px; color:#991b1b;">
                    <strong>API Disconnected:</strong> Please configure the Dashboard URL in settings first.
                </div>
            <?php else: ?>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
                    <div>
                        <h3 style="font-size:16px; margin-bottom:15px;">Compose Message</h3>
                        <div class="bdc-form-group">
                            <label class="bdc-label">Message Content</label>
                            <textarea id="sms-message" class="bdc-input" rows="6" placeholder="Type your promotional message here..."></textarea>
                            <p style="font-size:11px; color:#94a3b8; margin-top:5px;">Supports Bangla (Unicode) and English.</p>
                        </div>
                        <button id="send-btn" class="bdc-btn bdc-btn-primary" style="width:100%; justify-content:center;">
                            <span class="dashicons dashicons-paperplane"></span> Send Campaign
                        </button>
                    </div>
                    <div style="background:#f8fafc; padding:20px; border-radius:8px; border:1px solid #e2e8f0;">
                        <h3 style="font-size:14px; text-transform:uppercase; color:#64748b; margin-top:0;">Selected Recipients</h3>
                        <div style="font-size:36px; font-weight:800; color:#ea580c; margin:10px 0;" id="selected-count-display">0</div>
                        <p style="font-size:13px; color:#64748b;">Select customers from the 'Customers' tab to populate this list.</p>
                        <div id="recipient-list" style="background:#fff; border:1px solid #cbd5e1; padding:10px; height:150px; overflow-y:auto; font-family:monospace; font-size:12px; margin-top:10px;">
                            No recipients selected.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Content: Settings -->
        <div id="content-settings" class="bdc-content">
            <form method="post" action="options.php" style="max-width: 500px;">
                <?php settings_fields( 'bdc_sms_group' ); ?>
                <?php do_settings_sections( 'bdc_sms_group' ); ?>
                
                <div class="bdc-form-group">
                    <label class="bdc-label">React Dashboard API URL</label>
                    <input type="url" name="bdc_dashboard_url" value="<?php echo esc_attr( get_option( 'bdc_dashboard_url' ) ); ?>" class="bdc-input" placeholder="https://your-dashboard.com/api">
                    <p style="font-size:12px; color:#64748b; margin-top:5px;">The URL where your main application is hosted.</p>
                </div>

                <?php submit_button( 'Save Configuration', 'primary', 'submit', true, ['class' => 'bdc-btn bdc-btn-primary'] ); ?>
            </form>
        </div>
    </div>
</div>

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.bdc-content').forEach(el => el.classList.remove('active'));
        document.getElementById('content-' + tabId).classList.add('active');
        
        document.querySelectorAll('.bdc-tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
    }

    jQuery(document).ready(function($) {
        $('#select-all').on('change', function() {
            $('.customer-cb').prop('checked', $(this).prop('checked'));
            updateRecipients();
        });

        $(document).on('change', '.customer-cb', function() {
            updateRecipients();
        });

        function updateRecipients() {
            let count = 0;
            let listHtml = '';
            $('.customer-cb:checked').each(function() {
                count++;
                listHtml += '<div>' + $(this).val() + '</div>';
            });
            $('#selected-count-display').text(count);
            $('#recipient-list').html(listHtml || 'No recipients selected.');
        }

        $('#sync-btn').on('click', function() {
            const btn = $(this);
            const originalText = btn.html();
            btn.html('<span class="dashicons dashicons-update" style="animation:spin 2s infinite linear"></span> Syncing...').prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'bdc_sync_customers',
                nonce: '<?php echo wp_create_nonce( "bdc_sms_nonce" ); ?>'
            }, function(res) {
                if(res.success) {
                    alert(res.data);
                    location.reload();
                } else {
                    alert('Error: ' + res.data);
                    btn.html(originalText).prop('disabled', false);
                }
            });
        });

        $('#send-btn').on('click', function() {
            const btn = $(this);
            const msg = $('#sms-message').val();
            let numbers = [];
            $('.customer-cb:checked').each(function() {
                numbers.push($(this).val());
            });

            if(numbers.length === 0) {
                alert('Please select customers from the "Customers" tab first.');
                return;
            }
            if(!msg) {
                alert('Please type a message.');
                return;
            }

            if(!confirm('Are you sure you want to send this SMS to ' + numbers.length + ' customers?')) return;

            btn.text('Sending...').prop('disabled', true);

            $.post(ajaxurl, {
                action: 'bdc_send_sms',
                numbers: numbers,
                message: msg,
                nonce: '<?php echo wp_create_nonce( "bdc_sms_nonce" ); ?>'
            }, function(res) {
                if(res.success) {
                    alert(res.data);
                    $('#sms-message').val('');
                } else {
                    alert('Error: ' + res.data);
                }
                btn.text('Send Campaign').prop('disabled', false);
            });
        });
    });
</script>