<?php
$state_file = __DIR__ . '/state.json';
require_once __DIR__ . '/reboot_helper.php';

// Handle reboot & ping healthcheck requests
handle_reboot_logic('step3.php');

// Gatekeeper check: Ensure state belongs on Step 2
if (!isset($_GET['rebooting'])) {
    if (file_exists($state_file)) {
        $state = json_decode(file_get_contents($state_file), true);
        $active_step = $state['step'] ?? 2;
        if ($active_step < 2) {
            header('Location: step1.php');
            exit;
        } elseif ($active_step > 2) {
            header("Location: step{$active_step}.php");
            exit;
        }
    }
}

$error = '';
$config_file = '/boot/config.txt';

// Detect current timezone
$current_tz = trim(shell_exec('timedatectl show -p Timezone --value 2>/dev/null'));
if (empty($current_tz)) {
    $current_tz = trim(shell_exec('cat /etc/timezone 2>/dev/null')) ?: 'UTC';
}

// Detect current composite video mode (sdtv_mode in config.txt, default is 0: NTSC)
function get_current_sdtv_mode($path) {
    if (!file_exists($path)) return '0';
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^sdtv_mode=(\d+)/i', $trimmed, $m)) {
            return $m[1];
        }
    }
    return '0';
}
$current_mode = get_current_sdtv_mode($config_file);

// Handle Skip Action (No reboot needed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_step'])) {
    $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
    $state['step'] = 3;
    $state['timezone'] = $current_tz;
    $state['sdtv_mode'] = $current_mode;
    $state['skipped_step2'] = true;
    $state['updated_at'] = time();
    file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

    header('Location: step3.php');
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $tz = trim($_POST['timezone'] ?? '');
    $mode = trim($_POST['sdtv_mode'] ?? '0');
    $sync_clock = isset($_POST['sync_clock']);
    $browser_time = trim($_POST['browser_time'] ?? '');

    $valid_modes = ['0', '1', '2', '3'];
    $valid_timezones = DateTimeZone::listIdentifiers();

    if (!in_array($tz, $valid_timezones)) {
        $error = 'Please select a valid timezone from the list.';
    } elseif (!in_array($mode, $valid_modes)) {
        $error = 'Please select a valid composite video standard.';
    } elseif ($tz === $current_tz && $mode === $current_mode && !$sync_clock) {
        // Nothing changed, skip reboot
        $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
        $state['step'] = 3;
        $state['timezone'] = $tz;
        $state['sdtv_mode'] = $mode;
        $state['updated_at'] = time();
        file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));
        header('Location: step3.php');
        exit;
    } else {
        // 1. Update system timezone
        if ($tz !== $current_tz) {
            shell_exec('sudo /usr/bin/timedatectl set-timezone ' . escapeshellarg($tz));
        }

        // 2. Sync system clock if requested
        if ($sync_clock && !empty($browser_time) && ctype_digit($browser_time)) {
            shell_exec('sudo /bin/date -s "@' . escapeshellarg($browser_time) . '" > /dev/null');
            shell_exec('sudo /sbin/hwclock -w 2>/dev/null');
        }

        // 3. Update /boot/config.txt with sdtv_mode if changed
        if ($mode !== $current_mode && file_exists($config_file)) {
            $content = file_get_contents($config_file);
            if (preg_match('/^#?\s*sdtv_mode=\d+/m', $content)) {
                $new_content = preg_replace('/^#?\s*sdtv_mode=\d+/m', 'sdtv_mode=' . $mode, $content);
            } else {
                $new_content = rtrim($content) . "\nsdtv_mode=" . $mode . "\n";
            }
            file_put_contents('/tmp/config.txt.tmp', $new_content);
            shell_exec('cat /tmp/config.txt.tmp | sudo tee /boot/config.txt > /dev/null');
            @unlink('/tmp/config.txt.tmp');
            shell_exec('sudo /bin/sync');
        }

        // 4. Advance state machine to Step 3
        $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
        $state['step'] = 3;
        $state['timezone'] = $tz;
        $state['sdtv_mode'] = $mode;
        $state['updated_at'] = time();
        file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

        // 5. Trigger reboot cycle
        header('Location: step2.php?rebooting=1');
        exit;
    }
}

$grouped_timezones = [
    'United States & Canada' => [
        'America/New_York' => 'Eastern Time (New York, Toronto, Miami)',
        'America/Detroit' => 'Eastern Time - Michigan',
        'America/Chicago' => 'Central Time (Chicago, Dallas, Winnipeg)',
        'America/Denver' => 'Mountain Time (Denver, Calgary, Salt Lake)',
        'America/Phoenix' => 'Mountain Time - Arizona (No DST)',
        'America/Los_Angeles' => 'Pacific Time (Los Angeles, Vancouver, Seattle)',
        'America/Anchorage' => 'Alaska Time',
        'Pacific/Honolulu' => 'Hawaii Time (No DST)'
    ],
    'Europe & UK' => [
        'Europe/London' => 'London / GMT / BST',
        'Europe/Dublin' => 'Dublin',
        'Europe/Paris' => 'Paris / Berlin / Rome / Madrid (CET)',
        'Europe/Amsterdam' => 'Amsterdam / Brussels / Vienna',
        'Europe/Athens' => 'Athens / Helsinki / Bucharest (EET)'
    ],
    'Australia & Pacific' => [
        'Australia/Sydney' => 'Sydney / Melbourne / Canberra (AEST)',
        'Australia/Brisbane' => 'Brisbane (AEST - No DST)',
        'Australia/Adelaide' => 'Adelaide (ACST)',
        'Australia/Perth' => 'Perth (AWST)',
        'Pacific/Auckland' => 'Auckland / Wellington (NZST)'
    ],
    'Standard / Universal' => [
        'UTC' => 'Coordinated Universal Time (UTC)'
    ]
];

$all_zones = DateTimeZone::listIdentifiers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Step 2: Region & Video Output</title>
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
        .match-card {
            display: none;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            color: #4ade80;
            font-size: 0.88rem;
            line-height: 1.45;
        }
        .match-card strong { color: #86efac; }
        .notice-icon { font-size: 1.3rem; line-height: 1; flex-shrink: 0; }
        .notice-text { font-size: 0.88rem; line-height: 1.45; color: #e2c044; }
        .notice-text strong { color: #ffd84d; }
        .error-card {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 14px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .clock-box {
            background: #202632;
            border: 1px solid #2e3748;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .clock-label {
            font-size: 0.82rem;
            color: #8b949e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .clock-display {
            font-family: monospace;
            font-size: 1.15rem;
            color: #00d4ff;
            font-weight: 700;
        }
        label.input-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #c9d1d9;
        }
        select {
            width: 100%;
            padding: 14px;
            background: #202632;
            border: 1px solid #364154;
            border-radius: 10px;
            color: #ffffff;
            font-size: 1rem;
            margin-bottom: 20px;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238b949e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
        }
        select:focus {
            border-color: #00d4ff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.15);
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #202632;
            border: 1px solid #2e3748;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 24px;
            cursor: pointer;
        }
        .checkbox-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #00d4ff;
            cursor: pointer;
            flex-shrink: 0;
        }
        .checkbox-text {
            font-size: 0.88rem;
            color: #c9d1d9;
            line-height: 1.4;
        }
        .checkbox-text small {
            display: block;
            color: #8b949e;
            font-size: 0.78rem;
            margin-top: 2px;
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
            transition: transform 0.1s ease, background-color 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-submit:active { transform: scale(0.98); background: #00bce3; }
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
        .btn-skip:hover { background: #21262d; color: #c9d1d9; border-color: #484f58; }
        .btn-skip:active { transform: scale(0.98); }
        .btn-skip-recommended {
            border-color: rgba(34, 197, 94, 0.4);
            color: #4ade80;
            background: rgba(34, 197, 94, 0.05);
        }
        .btn-skip-recommended:hover {
            background: rgba(34, 197, 94, 0.12);
            color: #86efac;
            border-color: rgba(34, 197, 94, 0.6);
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
    <?php render_reboot_screen('step3.php'); ?>
<?php else: ?>
    <div class="badge">Step 2 of 5</div>
    <h1>Region &amp; Video Output</h1>
    <p class="subtitle">Set your broadcast timezone and configure the composite TV standard for your CRT.</p>

    <!-- TIMEZONE MATCH ALERT -->
    <div class="match-card" id="matchTzCard">
        ✓ <strong>Timezone Match:</strong> Both your station and browser are set to <span id="matchTzLabel"></span>.
    </div>

    <div class="notice-card" id="noticeCard">
        <div class="notice-icon">⚡</div>
        <div class="notice-text">
            <strong>Reboot Notice:</strong> Updating clock and composite video timing (<code>sdtv_mode</code>) requires a restart (~30s).
        </div>
    </div>

    <div class="clock-box">
        <div>
            <div class="clock-label">Active Pi Timezone</div>
            <div style="font-size: 0.82rem; color: #8b949e;"><?= htmlspecialchars($current_tz) ?></div>
        </div>
        <div class="clock-display">
            <?= date('H:i:s') ?>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-card"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="tzForm">
        <input type="hidden" name="browser_time" id="browserTime" value="">

        <label class="input-label" for="timezone">Choose Timezone</label>
        <select id="timezone" name="timezone" required>
            <?php foreach ($grouped_timezones as $region => $zones): ?>
                <optgroup label="<?= htmlspecialchars($region) ?>">
                    <?php foreach ($zones as $tz_id => $label): ?>
                        <option value="<?= htmlspecialchars($tz_id) ?>" <?= ($tz_id === $current_tz) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>

            <optgroup label="All World Timezones (Alphabetical)">
                <?php foreach ($all_zones as $tz_id): ?>
                    <option value="<?= htmlspecialchars($tz_id) ?>" <?= ($tz_id === $current_tz) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tz_id) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        </select>

		<label class="checkbox-container">
            <input type="checkbox" name="sync_clock" value="1" checked>
            <div class="checkbox-text">
                Sync Pi clock to this device's current time
                <small>Fixes 1970 date reset if the station is currently disconnected from the internet.</small>
            </div>
        </label>

		<!-- VIDEO STANDARD MATCH ALERT -->
		<div class="match-card" id="matchVideoCard">
			✓ <strong>Video Standard Match:</strong> Composite output is already configured for <span id="matchVideoLabel"></span>.
		</div>

        <label class="input-label" for="sdtvMode">Composite Video Standard (CRT Output)</label>
        <select id="sdtvMode" name="sdtv_mode">
            <option value="0" <?= ($current_mode === '0') ? 'selected' : '' ?>>NTSC — North America (480i @ 60Hz)</option>
            <option value="2" <?= ($current_mode === '2') ? 'selected' : '' ?>>PAL — UK, Europe, Australia (576i @ 50Hz)</option>
            <option value="1" <?= ($current_mode === '1') ? 'selected' : '' ?>>NTSC-J — Japan (480i @ 60Hz, 0 IRE)</option>
            <option value="3" <?= ($current_mode === '3') ? 'selected' : '' ?>>PAL-M — Brazil (480i @ 60Hz)</option>
        </select>

        

        <button type="submit" name="save_settings" value="1" class="btn-submit">Save &amp; Restart to Step 3</button>
        <button type="submit" name="skip_step" value="1" id="btnSkip" class="btn-skip" formnovalidate>Keep Current Settings (Skip)</button>
    </form>

    <?php render_emergency_reset(); ?>

    <script>
        document.getElementById('browserTime').value = Math.floor(Date.now() / 1000);

        const currentPiTz = "<?= addslashes($current_tz) ?>";
        const currentPiMode = "<?= addslashes($current_mode) ?>"; // '0', '1', '2', or '3'
        const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        
        const matchTzCard = document.getElementById('matchTzCard');
        const matchVideoCard = document.getElementById('matchVideoCard');
        const noticeCard = document.getElementById('noticeCard');
        const btnSkip = document.getElementById('btnSkip');
        const matchTzLabel = document.getElementById('matchTzLabel');
        const matchVideoLabel = document.getElementById('matchVideoLabel');
        const tzDropdown = document.getElementById('timezone');
        const sdtvDropdown = document.getElementById('sdtvMode');

        const modeLabels = {
            '0': 'NTSC (480i @ 60Hz)',
            '1': 'NTSC-J (480i @ 60Hz)',
            '2': 'PAL (576i @ 50Hz)',
            '3': 'PAL-M (480i @ 60Hz)'
        };

        // Determine expected standard based on browser region on initial page load
        if (browserTz) {
            const isPalRegion = /^(Europe|Australia|Africa|Atlantic|Indian)\//i.test(browserTz) || 
                                browserTz.startsWith('Pacific/Auckland') || 
                                browserTz.startsWith('Asia/Hong_Kong') ||
                                browserTz.startsWith('Asia/Singapore');

            const isJapan = browserTz.startsWith('Asia/Tokyo');

            let suggestedMode = '0';
            if (isPalRegion) {
                suggestedMode = '2';
            } else if (isJapan) {
                suggestedMode = '1';
            }

            sdtvDropdown.value = suggestedMode;
        }

        // Live evaluator function
        function evaluateMatches() {
            const selectedTz = tzDropdown.value;
            const selectedMode = sdtvDropdown.value;

            // Check if current selection on screen matches hardware
            const tzMatches = currentPiTz && (selectedTz.toLowerCase() === currentPiTz.toLowerCase());
            const videoMatches = (selectedMode === currentPiMode);

            // Update Timezone Match Card
            if (tzMatches) {
                matchTzLabel.innerText = currentPiTz;
                matchTzCard.style.display = 'block';
            } else {
                matchTzCard.style.display = 'none';
            }

            // Update Video Standard Match Card
            if (videoMatches) {
                matchVideoLabel.innerText = modeLabels[currentPiMode] || 'Current Setting';
                matchVideoCard.style.display = 'block';
            } else {
                matchVideoCard.style.display = 'none';
            }

            // If BOTH match hardware, a reboot is unnecessary; show recommended skip
            if (tzMatches && videoMatches) {
                noticeCard.style.display = 'none';
                btnSkip.classList.add('btn-skip-recommended');
                btnSkip.innerText = 'Keep Current Settings (Skip Step)';
            } else {
                // User picked something requiring a restart
                noticeCard.style.display = 'flex';
                btnSkip.classList.remove('btn-skip-recommended');
                btnSkip.innerText = 'Keep Current Settings (Skip)';
            }
        }

        // Run once on load
        evaluateMatches();

        // Listen for live dropdown changes
        tzDropdown.addEventListener('change', evaluateMatches);
        sdtvDropdown.addEventListener('change', evaluateMatches);
    </script>
<?php endif; ?>

</div>

</body>
</html>