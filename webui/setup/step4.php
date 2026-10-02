<?php
$state_file = __DIR__ . '/state.json';
require_once __DIR__ . '/reboot_helper.php';

// Handle reboot countdown and healthcheck ping routing to step 5
handle_reboot_logic('step5.php');

// Gatekeeper check: Ensure user belongs on Step 4
if (!isset($_GET['rebooting'])) {
    if (file_exists($state_file)) {
        $raw_state = file_get_contents($state_file);
        $state = !empty($raw_state) ? json_decode($raw_state, true) : array();
        $active_step = isset($state['step']) ? $state['step'] : 4;
        if ($active_step < 4) {
            header("Location: step{$active_step}.php");
            exit;
        } elseif ($active_step > 4 && $active_step !== 'complete') {
            header("Location: step5.php");
            exit;
        } elseif ($active_step === 'complete') {
            header("Location: complete.php");
            exit;
        }
    }
}

$error = '';

// Check if a wireless radio (wlan0) is physically present
$has_wifi = file_exists('/sys/class/net/wlan0');

// Safe command execution helper
function safe_exec($cmd) {
    $out = @shell_exec($cmd);
    return is_string($out) ? trim($out) : '';
}

// Check current network status
$current_ssid = $has_wifi ? safe_exec('iwgetid -r 2>/dev/null') : '';
$current_ip = safe_exec("hostname -I 2>/dev/null | awk '{print $1}'");

// Scan for nearby Wi-Fi networks using iwlist
function scan_wifi() {
    $networks = array();
    $raw = @shell_exec('sudo /sbin/iwlist wlan0 scan 2>/dev/null');

    if ($raw && is_string($raw)) {
        $cells = explode('Cell ', $raw);
        array_shift($cells);

        foreach ($cells as $cell) {
            if (preg_match('/ESSID:"([^"]+)"/', $cell, $ssid_match)) {
                $ssid = trim($ssid_match[1]);
                if ($ssid === '') continue;

                $signal = 50;
                if (preg_match('/Quality=([0-9]+)\/([0-9]+)/', $cell, $qual_match)) {
                    $denom = (int)$qual_match[2];
                    if ($denom > 0) {
                        $signal = (int) round(((int)$qual_match[1] / $denom) * 100);
                    }
                }

                $secured = (preg_match('/Encryption key:on/i', $cell) === 1);

                if (!isset($networks[$ssid]) || $networks[$ssid]['signal'] < $signal) {
                    $networks[$ssid] = array(
                        'ssid' => $ssid,
                        'signal' => $signal,
                        'secured' => $secured
                    );
                }
            }
        }
    }

    if (!empty($networks)) {
        uasort($networks, function ($a, $b) {
            if ($a['signal'] == $b['signal']) {
                return 0;
            }
            return ($a['signal'] > $b['signal']) ? -1 : 1;
        });
    }

    return $networks;
}

$wifi_list = $has_wifi ? scan_wifi() : array();

// Handle Skip Action (Proceed directly to Step 5 without rebooting)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_step'])) {
    $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : array();
    if (!is_array($state)) $state = array();
    $state['step'] = 5;
    $state['skipped_step4'] = true;
    $state['updated_at'] = time();
    @file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

    header('Location: step5.php');
    exit;
}

// Handle Wi-Fi Save & Connect via wpa_supplicant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_wifi'])) {
    $selected_ssid = isset($_POST['ssid_select']) ? trim($_POST['ssid_select']) : '';
    $custom_ssid = isset($_POST['custom_ssid']) ? trim($_POST['custom_ssid']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $ssid = ($selected_ssid === '__custom__') ? $custom_ssid : $selected_ssid;

    if (empty($ssid)) {
        $error = 'Please select a network or enter an SSID.';
    } else {
        $conf_path = '/etc/wpa_supplicant/wpa_supplicant.conf';
        $existing_conf = safe_exec("sudo /bin/cat {$conf_path} 2>/dev/null");

        $required_header = "ctrl_interface=DIR=/var/run/wpa_supplicant GROUP=netdev\nupdate_config=1\ncountry=US\n";
        
        if (empty($existing_conf) || strpos($existing_conf, 'ctrl_interface') === false) {
            $base_content = $required_header;
        } else {
            $pattern = '/network\s*=\s*\{[^}]*ssid="' . preg_quote($ssid, '/') . '"[^}]*\}\n?/s';
            $base_content = preg_replace($pattern, '', $existing_conf);
        }

        if ($password !== '') {
            $network_block = "\nnetwork={\n    ssid=\"" . addslashes($ssid) . "\"\n    psk=\"" . addslashes($password) . "\"\n    key_mgmt=WPA-PSK\n}\n";
        } else {
            $network_block = "\nnetwork={\n    ssid=\"" . addslashes($ssid) . "\"\n    key_mgmt=NONE\n}\n";
        }

        $final_conf = trim($base_content) . "\n" . $network_block;

        @file_put_contents('/tmp/wpa_supplicant.conf.tmp', $final_conf);
        safe_exec('sudo /bin/cp /tmp/wpa_supplicant.conf.tmp /etc/wpa_supplicant/wpa_supplicant.conf');
        safe_exec('sudo /bin/chmod 600 /etc/wpa_supplicant/wpa_supplicant.conf');
        safe_exec('sudo /bin/chown root:root /etc/wpa_supplicant/wpa_supplicant.conf');
        @unlink('/tmp/wpa_supplicant.conf.tmp');

        safe_exec('sudo /sbin/wpa_cli -i wlan0 reconfigure 2>/dev/null');

        $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : array();
        if (!is_array($state)) $state = array();
        $state['step'] = 5;
        $state['wifi_ssid'] = $ssid;
        $state['updated_at'] = time();
        @file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

        header('Location: step4.php?rebooting=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Step 4: Wi-Fi Setup</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 16px;
            background: #0f1117;
            color: #f0f3f6;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 520px;
            background: #181c24;
            border: 1px solid #28303f;
            border-radius: 16px;
            padding: 32px 24px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.45);
        }
        .badge {
            display: inline-block;
            background: rgba(0, 212, 255, 0.12);
            color: #00d4ff;
            border: 1px solid rgba(0, 212, 255, 0.3);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        h1 {
            font-size: 1.85rem;
            line-height: 1.25;
            margin: 0 0 8px 0;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        p.subtitle {
            color: #8b949e;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0 0 24px 0;
        }
        .notice-card {
            background: rgba(234, 179, 8, 0.08);
            border: 1px solid rgba(234, 179, 8, 0.25);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .notice-icon {
            font-size: 1.3rem;
            line-height: 1;
            flex-shrink: 0;
        }
        .notice-text {
            font-size: 0.88rem;
            line-height: 1.45;
            color: #e2c044;
        }
        .notice-text strong {
            color: #ffd84d;
        }
        .network-status-box {
            background: #202632;
            border: 1px solid #2e3748;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .network-meta-label {
            font-size: 0.82rem;
            color: #8b949e;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .network-meta-val {
            font-size: 0.95rem;
            color: #ffffff;
            font-weight: 600;
            margin-top: 2px;
        }
        .error-card {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 14px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        label.input-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #c9d1d9;
        }
        select, input[type="text"], input[type="password"] {
            width: 100%;
            padding: 14px;
            background: #202632;
            border: 1px solid #364154;
            border-radius: 10px;
            color: #ffffff;
            font-size: 1.05rem;
            margin-bottom: 18px;
        }
        select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238b949e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
        }
        select:focus, input[type="text"]:focus, input[type="password"]:focus {
            border-color: #00d4ff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.15);
        }
        .custom-ssid-field {
            display: none;
        }
        .password-wrapper {
            position: relative;
            width: 100%;
            margin-bottom: 18px;
        }
        .password-wrapper input {
            width: 100%;
            margin-bottom: 0;
            padding-right: 48px;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8b949e;
            transition: color 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .toggle-password:hover {
            color: #00d4ff;
        }
        .toggle-password svg {
            width: 22px;
            height: 22px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .btn-submit {
            display: block;
            width: 100%;
            padding: 18px;
            background: #00d4ff;
            color: #050b14;
            border: none;
            border-radius: 12px;
            font-size: 1.15rem;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            box-shadow: 0 4px 18px rgba(0, 212, 255, 0.3);
            margin-top: 8px;
            transition: transform 0.1s ease, background-color 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-submit:active {
            transform: scale(0.98);
            background: #00bce3;
        }
        .btn-skip {
            display: block;
            width: 100%;
            padding: 14px;
            margin-top: 12px;
            background: transparent;
            color: #8b949e;
            border: 1px solid #30363d;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-skip:hover {
            background: #21262d;
            color: #c9d1d9;
            border-color: #484f58;
        }
        .btn-skip:active {
            transform: scale(0.98);
        }

        /* Reboot Helper View Styles */
        .reboot-view { text-align: center; padding: 16px 0; }
        .spinner { margin: 20px auto 28px; width: 52px; height: 52px; border: 4px solid #232a37; border-top: 4px solid #00d4ff; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .countdown { font-size: 2.8rem; font-weight: 800; color: #00d4ff; margin: 12px 0; font-variant-numeric: tabular-nums; }
        .status-msg { font-size: 1.15rem; font-weight: 600; color: #00d4ff; margin: 14px 0; min-height: 28px; }
        .reboot-meta { font-size: 0.88rem; color: #8b949e; line-height: 1.5; margin-top: 16px; }
        .bailout-card { display: none; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px; padding: 20px 16px; margin-top: 24px; text-align: left; }
        .bailout-card h3 { color: #f87171; margin: 0 0 8px 0; font-size: 1rem; }
        .bailout-card p { font-size: 0.85rem; color: #c9d1d9; line-height: 1.5; margin: 0 0 14px 0; }
    </style>
</head>
<body>

<div class="container">

<?php if (isset($_GET['rebooting'])): ?>
    <?php render_reboot_screen('step5.php'); ?>
<?php else: ?>
    <div class="badge">Step 4 of 5</div>
    <h1>Wi-Fi Connection</h1>
    <p class="subtitle">Join a local wireless network or continue using wired Ethernet.</p>

    <div class="network-status-box">
        <div>
            <div class="network-meta-label">Active Connection</div>
            <div class="network-meta-val">
                <?= htmlspecialchars($current_ssid ?: 'Ethernet / Wired') ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div class="network-meta-label">Current IP</div>
            <div class="network-meta-val" style="font-family: monospace; color: #00d4ff;">
                <?= htmlspecialchars($current_ip ?: 'No IP') ?>
            </div>
        </div>
    </div>

    <?php if (!$has_wifi): ?>
        <!-- NO WIRELESS ADAPTER DETECTED -->
        <div class="notice-card" style="border-color: rgba(234, 179, 8, 0.4); background: rgba(234, 179, 8, 0.08);">
            <div class="notice-icon" style="font-size: 1.4rem;">⚠️</div>
            <div class="notice-text">
                <strong style="color: #ffd84d; font-size: 0.95rem;">No Wi-Fi Interface Detected</strong><br>
                This hardware does not have onboard Wi-Fi. The station will operate via its active wired Ethernet connection.
                <div style="margin-top: 8px; font-size: 0.8rem; color: #8b949e;">
                    If using a USB Wi-Fi adapter, plug it in and refresh this page.
                </div>
            </div>
        </div>

        <form method="POST" style="margin-top: 24px;">
            <button type="submit" name="skip_step" value="1" class="btn-submit" style="background: #22c55e; color: #050b14; box-shadow: 0 4px 18px rgba(34, 197, 94, 0.3);">
                Continue using Wired Network &rarr;
            </button>
        </form>
    <?php else: ?>
        <div class="notice-card">
            <div class="notice-icon">⚡</div>
            <div class="notice-text">
                <strong>Reboot Notice:</strong> Connecting to Wi-Fi reboots the Pi (~30s) to bind services to the wireless address.
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-card"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label class="input-label" for="ssidSelect">Select Wi-Fi Network</label>
            <select id="ssidSelect" name="ssid_select" onchange="toggleCustomSsid(this)">
                <?php if (!empty($wifi_list)): ?>
                    <?php foreach ($wifi_list as $net): ?>
                        <option value="<?= htmlspecialchars($net['ssid']) ?>">
                            <?= htmlspecialchars($net['ssid']) ?> (<?= $net['signal'] ?>%<?= $net['secured'] ? ' 🔒' : '' ?>)
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="">-- No Networks Detected --</option>
                <?php endif; ?>
                <option value="__custom__">+ Enter Hidden / Other SSID</option>
            </select>

            <div id="customSsidGroup" class="custom-ssid-field">
                <label class="input-label" for="customSsid">Network SSID Name</label>
                <input type="text" id="customSsid" name="custom_ssid" placeholder="Enter network name" autocomplete="off">
            </div>

            <label class="input-label" for="wifiPass">Wi-Fi Password</label>
            <div class="password-wrapper">
                <input 
                    type="password" 
                    id="wifiPass" 
                    name="password" 
                    placeholder="Leave empty for open networks" 
                    autocomplete="current-password"
                    autocorrect="off"
                    autocapitalize="none"
                    spellcheck="false"
                >
                <button type="button" class="toggle-password" id="togglePassBtn" aria-label="Toggle password visibility">
                    <svg id="eyeOpen" viewBox="0 0 24 24">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg id="eyeClosed" viewBox="0 0 24 24" style="display: none;">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>

            <button type="submit" name="save_wifi" value="1" class="btn-submit">Connect &amp; Restart to Step 5</button>
            <button type="submit" name="skip_step" value="1" class="btn-skip" formnovalidate>Keep Current Network (Skip)</button>
        </form>

        <script>
            function toggleCustomSsid(el) {
                const customGroup = document.getElementById('customSsidGroup');
                const customInput = document.getElementById('customSsid');
                if (el.value === '__custom__') {
                    customGroup.style.display = 'block';
                    customInput.focus();
                } else {
                    customGroup.style.display = 'none';
                }
            }

            const passInput = document.getElementById('wifiPass');
            const toggleBtn = document.getElementById('togglePassBtn');
            const eyeOpen = document.getElementById('eyeOpen');
            const eyeClosed = document.getElementById('eyeClosed');

            toggleBtn.addEventListener('click', () => {
                const isPassword = passInput.getAttribute('type') === 'password';
                passInput.setAttribute('type', isPassword ? 'text' : 'password');
                eyeOpen.style.display = isPassword ? 'none' : 'block';
                eyeClosed.style.display = isPassword ? 'block' : 'none';
            });
        </script>
    <?php endif; ?>

    <?php render_emergency_reset(); ?>

<?php endif; ?>

</div>

</body>
</html>