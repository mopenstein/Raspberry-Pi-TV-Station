<?php

class SystemUpdater implements ManageCard {
    private $name = 'Software Updater';
    private $links = [];
    private $html = '';
    private $updater_script = '/home/pi/Desktop/update_station.sh';
    private $version_file = '/home/pi/Desktop/.manifest_version';
    private $remote_manifest_url = 'https://raw.githubusercontent.com/mopenstein/raspberry_pi_tv_station/main/assets/manifest.txt';
    private $backup_dir = '/home/pi/station_backups';

    public function __construct() {
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'run_station_update') {
                $this->streamUpdateProcess();
                exit;
            } elseif ($_GET['action'] === 'run_station_restore') {
                $this->streamRestoreProcess();
                exit;
            }
        }

        $this->renderCard();
    }

    private function getInstalledDate() {
        if (file_exists($this->version_file)) {
            $date = trim(file_get_contents($this->version_file));
            return !empty($date) ? htmlspecialchars($date) : 'Unknown';
        }
        return 'Not Recorded';
    }

    private function getRemoteDate() {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 2,
                'header'  => "User-Agent: PiTV-Updater\r\n"
            ]
        ]);

        $remote_head = @file_get_contents($this->remote_manifest_url, false, $ctx, 0, 512);
        if ($remote_head !== false) {
            if (preg_match('/^date:\s*(.+)$/m', $remote_head, $matches)) {
                return trim($matches[1]);
            }
        }
        return null;
    }

    private function getBackupsList() {
        $backups = [];
        if (is_dir($this->backup_dir)) {
            $files = glob($this->backup_dir . '/backup_*.tar.gz');
            if ($files) {
                // Sort newest to oldest
                rsort($files);
                foreach ($files as $filepath) {
                    $filename = basename($filepath);
                    // Match timestamp format: backup_YYYYMMDD_HHMMSS.tar.gz
                    $label = $filename;
                    if (preg_match('/^backup_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})\.tar\.gz$/', $filename, $m)) {
                        $label = "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";
                    }
                    $size_kb = round(filesize($filepath) / 1024, 1);
                    $backups[] = [
                        'filename' => $filename,
                        'label'    => "{$label} ({$size_kb} KB)"
                    ];
                }
            }
        }
        return $backups;
    }

    private function renderCard() {
        $installed_date = $this->getInstalledDate();
        $remote_date = $this->getRemoteDate();
        $backups = $this->getBackupsList();

        $status_badge = '';
        if ($remote_date !== null) {
            if ($installed_date !== $remote_date) {
                $status_badge = '<span style="background: #f59e0b; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 6px;">Update Available: ' . htmlspecialchars($remote_date) . '</span>';
            } else {
                $status_badge = '<span style="background: #22c55e; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 6px;">Up to Date</span>';
            }
        }

        // Build backup section HTML
        $restore_html = '';
        if (!empty($backups)) {
            $options = '';
            foreach ($backups as $b) {
                $options .= '<option value="' . htmlspecialchars($b['filename']) . '">' . htmlspecialchars($b['label']) . '</option>';
            }

            $restore_html = '
            <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #334155;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #cbd5e1; margin-bottom: 6px;">
                    Restore From Safety Backup:
                </label>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <select id="backupFileSelect" style="background: #1e293b; color: #f8fafc; border: 1px solid #475569; padding: 6px 10px; border-radius: 4px; font-size: 12px; flex: 1; min-width: 220px;">
                        ' . $options . '
                    </select>
                    <button id="btnStartRestore" class="btn" style="padding: 6px 14px; cursor: pointer; background: #e11d48; color: #fff; border: none; border-radius: 4px; font-size: 12px; font-weight: 600;" onclick="executeStationRestore()">
                        Restore Selected
                    </button>
                </div>
            </div>';
        }

        $this->html = '
        <div style="margin:5px; padding:10px;">
            <div style="display: flex; justify-content: space-between; align-items: baseline;">
                <strong>Check & Install Station Updates</strong>
                ' . $status_badge . '
            </div>
            
            <p style="margin: 6px 0 12px 0; color: #94a3b8; font-size: 12px;">
                Installed Version: <strong>' . $installed_date . '</strong>
            </p>

            <button id="btnStartUpdate" class="btn" style="padding: 6px 14px; cursor: pointer;" onclick="executeStationUpdate()">
                Run Station Update
            </button>

            ' . $restore_html . '

            <div id="updateTerminalContainer" style="display:none; margin-top: 14px;">
                <div style="background: #1e1e1e; color: #eee; font-family: monospace; font-size: 12px; padding: 6px 10px; border-radius: 4px 4px 0 0; display:flex; justify-content: space-between;">
                    <span id="terminalTitle">Console Output</span>
                    <span id="updateStatusText" style="color: #bbb;">Running...</span>
                </div>
                <pre id="updateTerminal" style="margin:0; background: #000; color: #39ff14; font-family: monospace; font-size: 12px; padding: 12px; height: 260px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; border: 1px solid #333; border-top: none;"></pre>
            </div>
        </div>

        <script>
        async function runStreamOperation(url, confirmMsg, initialStatus, titleText) {
            if (!confirm(confirmMsg)) return;

            const btnUp = document.getElementById("btnStartUpdate");
            const btnRes = document.getElementById("btnStartRestore");
            const box = document.getElementById("updateTerminalContainer");
            const term = document.getElementById("updateTerminal");
            const status = document.getElementById("updateStatusText");
            const title = document.getElementById("terminalTitle");

            if (btnUp) { btnUp.disabled = true; btnUp.style.opacity = "0.5"; }
            if (btnRes) { btnRes.disabled = true; btnRes.style.opacity = "0.5"; }

            box.style.display = "block";
            term.textContent = "";
            title.textContent = titleText;
            status.textContent = initialStatus;
            status.style.color = "#bbb";

            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error("HTTP error " + response.status);

                const reader = response.body.getReader();
                const decoder = new TextDecoder("utf-8");

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    term.textContent += decoder.decode(value, { stream: true });
                    term.scrollTop = term.scrollHeight;
                }

                status.textContent = "Finished";
                status.style.color = "#39ff14";
            } catch (err) {
                term.textContent += "\\n[Operation Error: " + err.message + "]";
                status.textContent = "Failed";
                status.style.color = "#ff4444";
            } finally {
                if (btnUp) { btnUp.disabled = false; btnUp.style.opacity = "1"; }
                if (btnRes) { btnRes.disabled = false; btnRes.style.opacity = "1"; }
            }
        }

        function executeStationUpdate() {
            const url = window.location.pathname + "?action=run_station_update";
            runStreamOperation(url, "Start the update process now?", "Connecting to GitHub...", "Update Console");
        }

        function executeStationRestore() {
            const sel = document.getElementById("backupFileSelect");
            if (!sel || !sel.value) return;
            const file = encodeURIComponent(sel.value);
            const url = window.location.pathname + "?action=run_station_restore&file=" + file;
            runStreamOperation(url, "Restore from backup " + sel.value + "? Existing files will be rolled back.", "Restoring Archive...", "Restore Console");
        }
        </script>
        ';
    }

    private function streamRestoreProcess() {
        set_time_limit(180);

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        @ini_set('implicit_flush', true);
        ob_implicit_flush(true);

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');

        echo str_repeat(" ", 1024) . "\n";
        echo "=== Initializing Station Rollback ===\n";
        flush();

        $file = basename($_GET['file'] ?? '');
        $filepath = $this->backup_dir . '/' . $file;

        if (empty($file) || !preg_match('/^backup_\d{8}_\d{6}\.tar\.gz$/', $file) || !file_exists($filepath)) {
            echo "Error: Specified backup archive does not exist or has an invalid name.\n";
            return;
        }

        echo "Extracting backup: {$file}...\n";
        flush();

        // Extract files back into root directory with verbose output
        $cmd = 'sudo /bin/tar -xzvf ' . escapeshellarg($filepath) . ' -C / 2>&1';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]);

            while (!feof($pipes[1])) {
                $chunk = fgets($pipes[1]);
                if ($chunk !== false) {
                    echo $chunk;
                    flush();
                }
            }

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exit_code = proc_close($process);
            echo "\n===================================\n";
            if ($exit_code === 0) {
                echo "Rollback successful! Files restored to previous state.\n";
                echo "Please reboot or restart station services if scripts were active.\n";
            } else {
                echo "Rollback failed (Exit {$exit_code}).\n";
            }
            flush();
        } else {
            echo "Error: Failed to spawn extraction process.\n";
        }
    }

    private function streamUpdateProcess() {
        set_time_limit(300);

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        @ini_set('implicit_flush', true);
        ob_implicit_flush(true);

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');

        echo str_repeat(" ", 1024) . "\n";
        echo "=== Initializing Station Update ===\n";
        flush();

        if (!file_exists($this->updater_script)) {
            echo "Error: Updater script not found at " . $this->updater_script . "\n";
            return;
        }

        $cmd = 'sudo ' . escapeshellarg($this->updater_script) . ' 2>&1';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]);

            while (!feof($pipes[1])) {
                $chunk = fgets($pipes[1]);
                if ($chunk !== false) {
                    echo $chunk;
                    flush();
                }
            }

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exit_code = proc_close($process);
            echo "\n===================================\n";
            if ($exit_code === 0) {
                echo "Result: Complete (Exit 0)\n";
            } else {
                echo "Result: Failed (Exit " . $exit_code . ")\n";
            }
            flush();
        } else {
            echo "Error: Unable to fork update process.\n";
        }
    }

    public function name() { return $this->name; }
    public function links() { return $this->links; }
    public function html() { return $this->html; }
}
?>