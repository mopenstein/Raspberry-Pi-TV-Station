<?php
$state_file = __DIR__ . '/state.json';
require_once __DIR__ . '/reboot_helper.php';

// Load or initialize state early
$state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
if (!is_array($state)) {
    $state = [];
}

// Ensure state tracking keys exist
if (!isset($state['mounted_drives']) || !is_array($state['mounted_drives'])) {
    $state['mounted_drives'] = [];
}
if (!isset($state['step3_phase'])) {
    $state['step3_phase'] = 'mount'; // 'mount' or 'post_mount_choice'
}

// Next page after completing all drive mounting
$next_step_url = 'step4.php';
$reboot_target = ($state['step3_phase'] === 'post_mount_choice') ? 'step3.php' : $next_step_url;

// Handle reboot & ping healthcheck requests
handle_reboot_logic($reboot_target);

// Gatekeeper: Ensure user belongs on Step 3
if (!isset($_GET['rebooting'])) {
    $active_step = $state['step'] ?? 3;
    if ($active_step < 3 || $active_step > 3) {
        header("Location: step{$active_step}.php");
        exit;
    }
}

$error = '';

// Handle Skip / Finish Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['skip_step']) || isset($_POST['finish_step3']))) {
    $state['step'] = 4;
    $state['drives'] = $state['mounted_drives'];
    $state['step3_phase'] = 'mount';
    $state['updated_at'] = time();
    file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

    header('Location: step4.php');
    exit;
}

// Function to scan USB partitions & assign persistent letter targets
function get_usb_partitions($already_mounted_uuids = []) {
    $raw_json = shell_exec('lsblk -J -b -o NAME,SIZE,TYPE,MOUNTPOINT,FSTYPE,LABEL,UUID 2>/dev/null');
    $data = json_decode($raw_json, true);
    $partitions = [];

    if (!empty($data['blockdevices'])) {
        foreach ($data['blockdevices'] as $dev) {
            if (strpos($dev['name'], 'mmcblk') === 0 || strpos($dev['name'], 'loop') === 0 || strpos($dev['name'], 'zram') === 0) {
                continue;
            }

            if (!empty($dev['children'])) {
                foreach ($dev['children'] as $child) {
                    if ($child['type'] === 'part' && !empty($child['uuid'])) {
                        $partitions[] = $child;
                    }
                }
            } elseif ($dev['type'] === 'part' && !empty($dev['uuid'])) {
                $partitions[] = $dev;
            }
        }
    }

    $letters = range('A', 'Z');
    $count = count($already_mounted_uuids);

    foreach ($partitions as &$part) {
        // If partition was already registered, keep assigned target mount
        $matched_existing = null;
        foreach ($already_mounted_uuids as $record) {
            if ($record['uuid'] === $part['uuid']) {
                $matched_existing = $record['mount_point'];
                break;
            }
        }

        if ($matched_existing) {
            $part['target_mount'] = $matched_existing;
            $part['is_registered'] = true;
        } else {
            $part_letter = $letters[$count] ?? ('Z' . $count);
            $part['target_mount'] = "/media/pi/drive_{$part_letter}";
            $part['is_registered'] = false;
        }
    }

    return $partitions;
}

$already_registered = $state['mounted_drives'] ?? [];
$partitions = get_usb_partitions($already_registered);

// Handle Single Drive Mount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mount_single_drive'])) {
    $selected_uuid = trim($_POST['selected_partition'] ?? '');
    $has_more_drives = (isset($_POST['has_more_drives']) && $_POST['has_more_drives'] === '1');

    if (empty($selected_uuid)) {
        $error = 'Please select a drive to mount.';
    } else {
        $selected_part = null;
        foreach ($partitions as $p) {
            if ($p['uuid'] === $selected_uuid) {
                $selected_part = $p;
                break;
            }
        }

        if (!$selected_part) {
            $error = 'Selected partition could not be verified. Please refresh.';
        } else {
            $uuid = $selected_part['uuid'];
            $fstype = $selected_part['fstype'] ?: 'auto';
            $mount_target = $selected_part['target_mount'];

            // 1. Create target directory
            shell_exec('sudo mkdir -p ' . escapeshellarg($mount_target));
            shell_exec('sudo chown -R pi:www-data ' . escapeshellarg($mount_target));
            shell_exec('sudo chmod 775 ' . escapeshellarg($mount_target));

            // 2. Build clean fstab
            $current_fstab = file_get_contents('/etc/fstab');
            $filtered_fstab_lines = [];
            foreach (explode("\n", $current_fstab) as $line) {
                if (strpos($line, $uuid) !== false || strpos($line, $mount_target) !== false) {
                    continue;
                }
                if (trim($line) !== '') {
                    $filtered_fstab_lines[] = $line;
                }
            }

            if (in_array($fstype, ['vfat', 'fat', 'exfat', 'ntfs'])) {
                $fstab_opts = 'defaults,nofail,x-systemd.device-timeout=5,uid=1000,gid=33,umask=0002';
            } else {
                $fstab_opts = 'defaults,nofail,x-systemd.device-timeout=5,noatime';
            }

            $filtered_fstab_lines[] = "UUID={$uuid}  {$mount_target}  {$fstype}  {$fstab_opts}  0  2";

            // 3. Write fstab safely
            file_put_contents('/tmp/fstab.tmp', implode("\n", $filtered_fstab_lines) . "\n");
            shell_exec('cat /tmp/fstab.tmp | sudo tee /etc/fstab > /dev/null');
            @unlink('/tmp/fstab.tmp');

            shell_exec('sudo systemctl daemon-reload');
            shell_exec('sudo mount -a 2>/dev/null');

            // 4. Update session tracking in state.json
            $existing_idx = null;
            foreach ($state['mounted_drives'] as $i => $rec) {
                if ($rec['uuid'] === $uuid) {
                    $existing_idx = $i;
                    break;
                }
            }

            $new_record = [
                'uuid' => $uuid,
                'mount_point' => $mount_target,
                'fstype' => $fstype,
                'label' => $selected_part['label'] ?? ('Drive_' . substr($uuid, 0, 4))
            ];

            if ($existing_idx !== null) {
                $state['mounted_drives'][$existing_idx] = $new_record;
            } else {
                $state['mounted_drives'][] = $new_record;
            }

            $state['updated_at'] = time();

            if ($has_more_drives) {
                $state['step3_phase'] = 'post_mount_choice';
                file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));
                header('Location: step3.php');
                exit;
            } else {
                $state['step'] = 4;
                $state['step3_phase'] = 'mount';
                $state['drives'] = $state['mounted_drives'];
                file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));
                header('Location: step3.php?rebooting=1');
                exit;
            }
        }
    }
}

// Handle Reboot to Next Drive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reboot_for_next_drive'])) {
    $state['step3_phase'] = 'mount';
    $state['updated_at'] = time();
    file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

    header('Location: step3.php?rebooting=1');
    exit;
}

function format_bytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Step 3: Media Storage</title>
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
            max-width: 560px;
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
            margin: 0 0 20px 0;
        }
        .warning-card {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .warning-icon {
            font-size: 1.4rem;
            line-height: 1;
            flex-shrink: 0;
        }
        .warning-text {
            font-size: 0.88rem;
            line-height: 1.45;
            color: #fbbf24;
        }
        .warning-text strong {
            color: #fde68a;
        }
        .info-card {
            background: rgba(0, 212, 255, 0.08);
            border: 1px solid rgba(0, 212, 255, 0.25);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .info-title {
            color: #00d4ff;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .info-card ul {
            margin: 0;
            padding-left: 20px;
            color: #c9d1d9;
            font-size: 0.88rem;
            line-height: 1.5;
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
        .drive-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        .drive-card {
            background: #202632;
            border: 2px solid #2e3748;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .drive-card:hover {
            border-color: #3b475d;
        }
        .drive-card input[type="radio"] {
            width: 20px;
            height: 20px;
            accent-color: #00d4ff;
            margin-top: 3px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .drive-content {
            flex-grow: 1;
        }
        .drive-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
            flex-wrap: wrap;
            gap: 6px;
        }
        .drive-title {
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
        }
        .drive-target {
            font-family: monospace;
            font-size: 0.82rem;
            color: #00d4ff;
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid rgba(0, 212, 255, 0.25);
            padding: 2px 8px;
            border-radius: 6px;
        }
        .drive-sub {
            font-size: 0.82rem;
            color: #8b949e;
            line-height: 1.4;
        }
        .drive-sub code {
            font-family: monospace;
            background: #141720;
            padding: 2px 5px;
            border-radius: 4px;
            color: #79c0ff;
        }
        .configured-card {
            background: #151a23;
            border: 1px solid #242c3b;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .configured-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #56d364;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .configured-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.88rem;
            padding: 6px 0;
            border-bottom: 1px solid #1f2736;
        }
        .configured-row:last-child {
            border-bottom: none;
        }
        .multi-toggle {
            background: #202632;
            border: 1px solid #2e3748;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .multi-toggle label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            color: #f0f3f6;
        }
        .multi-toggle input[type="checkbox"] {
            width: 22px;
            height: 22px;
            accent-color: #00d4ff;
            cursor: pointer;
        }
        .toggle-hint {
            margin: 6px 0 0 34px;
            font-size: 0.82rem;
            color: #8b949e;
            line-height: 1.4;
        }
        .empty-drives {
            text-align: center;
            padding: 28px 16px;
            background: #202632;
            border: 1px dashed #364154;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .btn-submit {
            display: block;
            width: 100%;
            padding: 16px;
            background: #00d4ff;
            color: #050b14;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            box-shadow: 0 4px 18px rgba(0, 212, 255, 0.3);
            transition: transform 0.1s ease, background-color 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-submit:active {
            transform: scale(0.98);
            background: #00bce3;
        }
        .btn-secondary {
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
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .btn-secondary:hover {
            background: #21262d;
            color: #c9d1d9;
            border-color: #484f58;
        }
        .btn-secondary:active {
            transform: scale(0.98);
        }

        /* Reboot View Styles */
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
    <?php render_reboot_screen($reboot_target); ?>

<?php elseif ($state['step3_phase'] === 'post_mount_choice'): ?>
    <!-- Post-Mount State: Prompt user before triggering restart -->
    <div class="badge">Step 3: Setup Loop</div>
    <h1>Drive Mounted</h1>
    <p class="subtitle">Drive recorded to <code>/etc/fstab</code>. Prepare the Pi for the next drive.</p>

    <div class="warning-card">
        <div class="warning-icon">⚠️</div>
        <div class="warning-text">
            <strong>Action Required:</strong> Plug in your next USB SSD now. Do not disconnect the previously mounted drive(s). Once connected, restart the Pi to initialize the hardware and return here to mount it.
        </div>
    </div>

    <?php if (!empty($state['mounted_drives'])): ?>
        <div class="configured-card">
            <div class="configured-title">Configured Drives (<?= count($state['mounted_drives']) ?>)</div>
            <?php foreach ($state['mounted_drives'] as $drv): ?>
                <div class="configured-row">
                    <span><strong><?= htmlspecialchars($drv['label']) ?></strong> (<code><?= htmlspecialchars($drv['fstype']) ?></code>)</span>
                    <span style="color: #00d4ff; font-family: monospace;"><?= htmlspecialchars($drv['mount_point']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <button type="submit" name="reboot_for_next_drive" value="1" class="btn-submit">
            Reboot Pi to Detect Next Drive
        </button>
        <button type="submit" name="finish_step3" value="1" class="btn-secondary">
            Done Adding Drives &bull; Continue to Step 4
        </button>
    </form>

<?php else: ?>
    <!-- Primary Step 3 Mounting Interface -->
    <div class="badge">Step 3 of 5</div>
    <h1>Media Storage</h1>
    <p class="subtitle">Mount external storage partitions one drive at a time.</p>

    <div class="warning-card">
        <div class="warning-icon">⚠️</div>
        <div class="warning-text">
            <strong>USB Bus Warning:</strong> Raspberry Pis can drop USB drives or fail to enumerate partitions when detecting multiple external SSDs at once due to shared USB bus bandwidth and peak power spikes.
        </div>
    </div>

    <div class="info-card">
        <div class="info-title">Multi-Drive Sequential Setup</div>
        <ul>
            <li>If you have <strong>more than 1 drive</strong>, unplug all except the first one and refresh.</li>
            <li>Mount the active drive below.</li>
            <li>Plug in the second drive, reboot, and repeat this process.</li>
        </ul>
    </div>

    <?php if (!empty($state['mounted_drives'])): ?>
        <div class="configured-card">
            <div class="configured-title">Mounted in this setup (<?= count($state['mounted_drives']) ?>)</div>
            <?php foreach ($state['mounted_drives'] as $drv): ?>
                <div class="configured-row">
                    <span><?= htmlspecialchars($drv['label']) ?></span>
                    <span style="color: #56d364; font-family: monospace;">Registered &rarr; <?= htmlspecialchars($drv['mount_point']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="error-card"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php if (!empty($partitions)): ?>
            <div class="drive-list">
                <?php foreach ($partitions as $idx => $part): ?>
                    <?php 
                    $label = !empty($part['label']) ? $part['label'] : 'USB Drive (' . $part['name'] . ')';
                    $size = format_bytes($part['size']);
                    $fs = strtoupper($part['fstype'] ?: 'UNKNOWN');
                    $is_registered = !empty($part['is_registered']);
                    ?>
                    <label class="drive-card" style="<?= $is_registered ? 'opacity: 0.65;' : '' ?>">
                        <input type="radio" name="selected_partition" value="<?= htmlspecialchars($part['uuid']) ?>" <?= (!$is_registered && $idx === 0) ? 'checked' : '' ?>>
                        <div class="drive-content">
                            <div class="drive-header">
                                <span class="drive-title"><?= htmlspecialchars($label) ?> (<?= $size ?>)</span>
                                <span class="drive-target"><?= htmlspecialchars($part['target_mount']) ?></span>
                            </div>
                            <div class="drive-sub">
                                Format: <code><?= htmlspecialchars($fs) ?></code> &bull; 
                                UUID: <code><?= htmlspecialchars(substr($part['uuid'], 0, 13)) ?>...</code>
                            </div>
                            <?php if ($is_registered): ?>
                                <div style="font-size: 0.78rem; color: #56d364; margin-top: 4px;">&check; Already added to fstab</div>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="multi-toggle">
                <label>
                    <input type="checkbox" name="has_more_drives" value="1">
                    I have additional USB drives to plug in and mount
                </label>
                <div class="toggle-hint">
                    Check this if you are mounting multiple drives. After this drive mounts, you will be prompted to insert the next drive and restart.
                </div>
            </div>

            <button type="submit" name="mount_single_drive" value="1" class="btn-submit">
                Mount Drive & Continue
            </button>
        <?php else: ?>
            <div class="empty-drives">
                <div style="font-size: 2rem; margin-bottom: 8px;">🔌</div>
                <strong style="color: #ffffff; display: block; margin-bottom: 6px;">No USB Drives Detected</strong>
                <p style="font-size: 0.85rem; color: #8b949e; margin: 0 0 16px 0;">
                    Plug in one USB drive at a time and refresh the page.
                </p>
                <a href="step3.php" class="btn-secondary" style="display: inline-block; width: auto; padding: 8px 18px;">Refresh List</a>
            </div>
        <?php endif; ?>

        <button type="submit" name="skip_step" value="1" class="btn-secondary" formnovalidate>
            <?= empty($partitions) && empty($state['mounted_drives']) ? 'Skip Storage Setup' : 'Done Adding Drives / Skip Remaining' ?>
        </button>
    </form>

    <?php render_emergency_reset(); ?>
<?php endif; ?>

</div>

</body>
</html>