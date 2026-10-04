<?php

class ThemeManager implements ManageCard {
    private $name = 'UI Theme Manager';
    private $links = [];
    private $html = '';
    private $settings_file = "/home/pi/Desktop/settings.json";
    private $template_dir = "templates";
    private $status_msg = '';
    private $status_is_error = false;
    private $updateProcessed = false;

    public function __construct() {
        $this->ensureTemplateDirectory();
        $this->handleActions();
        $this->loadThemeData();
    }

    private function ensureTemplateDirectory(): bool {
        if (!is_dir($this->template_dir)) {
            @mkdir($this->template_dir, 0775, true);
            @exec('sudo mkdir -p ' . escapeshellarg($this->template_dir));
            @exec('sudo chown -R pi:www-data ' . escapeshellarg($this->template_dir));
            @exec('sudo chmod 0777 ' . escapeshellarg($this->template_dir));
        }
        return is_dir($this->template_dir);
    }

    private function ensureDirectoryWritable(string $dir): bool {
        if (!is_dir($dir)) return false;
        if (!is_writable($dir)) {
            @chmod($dir, 0777);
            clearstatcache(true, $dir);
            if (!is_writable($dir)) {
                @exec('sudo chmod 0777 ' . escapeshellarg($dir));
                @exec('sudo chown -R pi:www-data ' . escapeshellarg($dir));
                clearstatcache(true, $dir);
            }
        }
        return is_writable($dir);
    }

    private function getCurrentActiveTheme(): string {
        if (file_exists($this->settings_file)) {
            $json = json_decode(file_get_contents($this->settings_file), true);
            return $json["web-ui"]["theme"] ?? "modern";
        }
        return "modern";
    }

    private function handleActions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // 1. Activate Theme
        if (isset($_POST["new_theme"])) {
            $newTheme = basename($_POST['new_theme']);
            if (is_file($this->template_dir . '/' . $newTheme . '.php')) {
                $content = file_get_contents($this->settings_file);
                $pattern = '/("theme"\s*:\s*")([^"]*)(")/';
                $replacement = '$1' . $newTheme . '$3';
                $newContent = preg_replace($pattern, $replacement, $content);

                if ($newContent !== null) {
                    file_put_contents($this->settings_file, $newContent);
                    $this->updateProcessed = true;
                    $this->status_msg = 'Activated theme "' . htmlspecialchars($newTheme) . '". Reloading...';
                }
            }
            return;
        }

        // 2. Delete Theme
        if (isset($_POST["delete_theme"])) {
            $targetTheme = basename($_POST['delete_theme']);
            $activeTheme = $this->getCurrentActiveTheme();

            if ($targetTheme === $activeTheme) {
                $this->status_msg = 'Error: Cannot delete the currently active theme.';
                $this->status_is_error = true;
                return;
            }

            $themePhp = rtrim($this->template_dir, '/') . '/' . $targetTheme . '.php';
            $previewPng = rtrim($this->template_dir, '/') . '/' . $targetTheme . '.preview.png';

            $deleted = false;
            if (file_exists($themePhp)) {
                @unlink($themePhp);
                $deleted = true;
            }
            if (file_exists($previewPng)) {
                @unlink($previewPng);
            }

            if ($deleted) {
                $this->status_msg = 'Successfully removed theme "' . htmlspecialchars($targetTheme) . '".';
            } else {
                $this->status_msg = 'Error: Target theme file does not exist.';
                $this->status_is_error = true;
            }
            return;
        }

        // 3. Upload Theme (.php, .tvtheme, or .zip)
        if (isset($_FILES['theme_file'])) {
            if (!$this->ensureDirectoryWritable($this->template_dir)) {
                $this->status_msg = 'Error: Templates directory is not writable.';
                $this->status_is_error = true;
                return;
            }

            $file = $_FILES['theme_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->status_msg = 'Upload failed with code: ' . (int)$file['error'];
                $this->status_is_error = true;
                return;
            }

            $rawFilename = basename($file['name']);
            $lowerFilename = strtolower($rawFilename);

            if (substr($lowerFilename, -8) === '.tvtheme' || substr($lowerFilename, -4) === '.zip') {
                $this->handlePackageUpload($file['tmp_name'], $rawFilename);
            } elseif (substr($lowerFilename, -4) === '.php') {
                $dest = rtrim($this->template_dir, '/') . '/' . $rawFilename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    @chmod($dest, 0777);
                    @exec('sudo chmod 0777 ' . escapeshellarg($dest));
                    $this->status_msg = 'Successfully uploaded theme: ' . htmlspecialchars($rawFilename);
                } else {
                    $this->status_msg = 'Error: Failed to move uploaded PHP theme file.';
                    $this->status_is_error = true;
                }
            } else {
                $this->status_msg = 'Error: Invalid file format. Allowed types: .php, .tvtheme, .zip';
                $this->status_is_error = true;
            }
        }
    }

    private function handlePackageUpload(string $archivePath, string $origFilename) {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            $this->status_msg = 'Error: Could not open package archive ' . htmlspecialchars($origFilename);
            $this->status_is_error = true;
            return;
        }

        $rootPhpIndex = null;
        $rootPhpName = null;
        $totalRootPhpCount = 0;
        $previewIndex = null;
        $previewName = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            $base = basename($entry);

            if ($base[0] === '.' || strpos($entry, '__MACOSX') !== false || substr($entry, -1) === '/') {
                continue;
            }

            if (strtolower(substr($base, -4)) === '.php') {
                if (strpos(trim($entry, '/'), '/') === false) {
                    $totalRootPhpCount++;
                    $rootPhpIndex = $i;
                    $rootPhpName = $base;
                }
            }

            if (strtolower(substr($base, -12)) === '.preview.png') {
                $previewIndex = $i;
                $previewName = $base;
            }
        }

        if ($totalRootPhpCount !== 1) {
            $zip->close();
            $this->status_msg = 'Verification Error: Package must contain exactly one root PHP theme file (Found: ' . $totalRootPhpCount . ').';
            $this->status_is_error = true;
            return;
        }

        $themeBaseName = pathinfo($rootPhpName, PATHINFO_FILENAME);

        if ($previewIndex !== null) {
            $expectedPreview = $themeBaseName . '.preview.png';
            if (strtolower($previewName) !== strtolower($expectedPreview)) {
                $zip->close();
                $this->status_msg = 'Verification Error: Preview image "' . htmlspecialchars($previewName) . '" does not match root theme "' . htmlspecialchars($expectedPreview) . '".';
                $this->status_is_error = true;
                return;
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            $base = basename($entry);

            if ($base[0] === '.' || strpos($entry, '__MACOSX') !== false || substr($entry, -1) === '/') {
                continue;
            }

            if ($i === $rootPhpIndex) {
                $destPath = rtrim($this->template_dir, '/') . '/' . $rootPhpName;
            } elseif ($i === $previewIndex) {
                $destPath = rtrim($this->template_dir, '/') . '/' . $themeBaseName . '.preview.png';
            } else {
                $destPath = rtrim($this->template_dir, '/') . '/' . ltrim($entry, '/');
                $parentDir = dirname($destPath);
                if (!is_dir($parentDir)) {
                    @mkdir($parentDir, 0777, true);
                    @exec('sudo chmod 0777 ' . escapeshellarg($parentDir));
                }
            }

            $stream = $zip->getStream($entry);
            if ($stream) {
                file_put_contents($destPath, stream_get_contents($stream));
                fclose($stream);
                @chmod($destPath, 0777);
                @exec('sudo chmod 0777 ' . escapeshellarg($destPath));
            }
        }

        $zip->close();
        $this->status_msg = 'Successfully installed theme: ' . htmlspecialchars($themeBaseName) . ($previewIndex !== null ? ' (+preview)' : '');
    }

    private function loadThemeData() {
        $themeFiles = array_filter(glob($this->template_dir . '/*.php'));
        $activeTheme = $this->getCurrentActiveTheme();

        $themes = [];
        foreach ($themeFiles as $path) {
            $name = pathinfo(basename($path), PATHINFO_FILENAME);
            $previewPath = $this->template_dir . '/' . $name . '.preview.png';
            $themes[] = [
                'name' => $name,
                'path' => $path,
                'is_active' => ($name === $activeTheme),
                'has_preview' => file_exists($previewPath),
                'preview_url' => file_exists($previewPath) ? ('/templates/' . rawurlencode($name) . '.preview.png') : null,
                'size_kb' => round(filesize($path) / 1024, 1),
                'modified' => date("Y-m-d H:i", filemtime($path))
            ];
        }

        $this->html = $this->renderThemeCard($themes);
    }

    private function renderThemeCard(array $themes): string {
        $out = '
        <style type="text/css">
            .tm-stack {
                display: flex;
                flex-direction: column;
                gap: 8px;
                padding: 4px 0;
                font-family: inherit;
            }

            .tm-status-msg {
                font-size: 0.75rem;
                padding: 6px 10px;
                border-radius: 4px;
                margin-bottom: 8px;
                font-family: monospace;
            }
            .tm-status-msg.success {
                background: rgba(78, 201, 176, 0.12);
                border: 1px solid rgba(78, 201, 176, 0.35);
                color: #4ec9b0;
            }
            .tm-status-msg.error {
                background: rgba(220, 50, 50, 0.12);
                border: 1px solid rgba(220, 50, 50, 0.35);
                color: #ff8080;
            }

            .tm-row {
                background: rgba(0, 0, 0, 0.25);
                border: 1px solid rgba(255, 255, 255, 0.07);
                border-radius: 4px;
                overflow: hidden;
            }
            .tm-row:hover {
                border-color: rgba(255, 255, 255, 0.14);
            }
            .tm-row.is-active {
                border-left: 3px solid #e5a93b;
            }

            .tm-summary {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 10px;
                cursor: pointer;
                user-select: none;
                list-style: none;
            }
            .tm-summary::-webkit-details-marker { display: none; }

            .tm-lead {
                display: flex;
                align-items: center;
                gap: 8px;
                min-width: 0;
            }

            .tm-arrow {
                display: inline-block;
                width: 12px;
                height: 12px;
                opacity: 0.45;
                transition: transform 0.2s ease;
                flex-shrink: 0;
            }
            .tm-row[open] .tm-arrow {
                transform: rotate(90deg);
                opacity: 0.85;
            }

            .tm-name {
                font-size: 0.85rem;
                font-weight: 600;
                color: #ffffff;
            }

            .tm-controls {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-shrink: 0;
            }

            .tm-badge {
                font-size: 0.68rem;
                font-family: monospace;
                padding: 1px 5px;
                border-radius: 2px;
                text-transform: uppercase;
                background: rgba(255, 255, 255, 0.08);
                color: #aaa;
            }
            .tm-badge.active {
                background: rgba(229, 169, 59, 0.2);
                border: 1px solid #e5a93b;
                color: #e5a93b;
            }

            .tm-btn {
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.15);
                color: #dcdce0;
                padding: 2px 7px;
                font-size: 0.72rem;
                border-radius: 3px;
                cursor: pointer;
            }
            .tm-btn:hover {
                background: rgba(255, 255, 255, 0.16);
                color: #fff;
            }
            .tm-btn.danger {
                border-color: rgba(248, 81, 73, 0.4);
                color: #ff9994;
            }
            .tm-btn.danger:hover {
                background: #f85149;
                color: #000;
            }

            .tm-drawer {
                padding: 10px;
                border-top: 1px solid rgba(255, 255, 255, 0.05);
                font-size: 0.75rem;
                color: rgba(255, 255, 255, 0.7);
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .tm-drawer-preview {
                max-width: 100%;
                height: auto;
                border-radius: 3px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                background: #000;
            }

            .tm-upload-bar {
                background: rgba(0, 0, 0, 0.35);
                border: 1px dashed rgba(255, 255, 255, 0.15);
                border-radius: 4px;
                padding: 8px 10px;
                margin-top: 4px;
                display: flex;
                flex-direction: column;
                gap: 6px;
            }

            .tm-upload-bar form {
                display: flex;
                align-items: center;
                gap: 8px;
                width: 100%;
                flex-wrap: wrap;
            }

            .tm-upload-bar input[type="file"] {
                font-size: 0.75rem;
                color: #aaa;
                flex: 1;
            }
        </style>

        <div class="tm-stack">';

        if (!empty($this->status_msg)) {
            $msgType = $this->status_is_error ? 'error' : 'success';
            $out .= '<div class="tm-status-msg ' . $msgType . '">' . $this->status_msg . '</div>';
        }

        if ($this->updateProcessed) {
            $out .= '<script>setTimeout(function() { location.reload(); }, 1000);</script>';
        }

        if (empty($themes)) {
            $out .= '<div style="padding: 10px; color: #888;">No themes available in templates directory.</div>';
        } else {
            foreach ($themes as $t) {
                $statusBadge = $t['is_active'] ? '<span class="tm-badge active">ON-AIR</span>' : '<span class="tm-badge">INSTALLED</span>';

                $out .= '
                <details class="tm-row ' . ($t['is_active'] ? 'is-active' : '') . '">
                    <summary class="tm-summary">
                        <div class="tm-lead">
                            <svg class="tm-arrow" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
                            </svg>
                            <span class="tm-name">' . htmlspecialchars($t['name']) . '</span>
                        </div>
                        <div class="tm-controls">
                            ' . $statusBadge . '
                            ' . (!$t['is_active'] ? '
                            <form method="POST" style="display:inline; margin:0;" onclick="event.stopPropagation();">
                                <input type="hidden" name="new_theme" value="' . htmlspecialchars($t['name']) . '">
                                <button type="submit" class="tm-btn">Activate</button>
                            </form>
                            <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm(\'Delete theme ' . htmlspecialchars($t['name']) . '?\');" onclick="event.stopPropagation();">
                                <input type="hidden" name="delete_theme" value="' . htmlspecialchars($t['name']) . '">
                                <button type="submit" class="tm-btn danger">Delete</button>
                            </form>
                            ' : '') . '
                        </div>
                    </summary>
                    <div class="tm-drawer">
                        <div style="display: flex; justify-content: space-between; font-family: monospace; font-size: 0.7rem; color: #888;">
                            <span>templates/' . htmlspecialchars($t['name']) . '.php (' . $t['size_kb'] . ' KB)</span>
                            <span>Modified: ' . $t['modified'] . '</span>
                        </div>';

                if ($t['has_preview']) {
                    $out .= '<img class="tm-drawer-preview" src="' . htmlspecialchars($t['preview_url']) . '" alt="Preview" />';
                } else {
                    $out .= '<div style="font-style: italic; color: #666;">No preview asset found (' . htmlspecialchars($t['name']) . '.preview.png).</div>';
                }

                $out .= '
                    </div>
                </details>';
            }
        }

        $out .= '
        <div class="tm-upload-bar">
            <div style="font-size: 0.72rem; opacity: 0.8;">Upload Theme (.php, .tvtheme, or .zip):</div>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="theme_file" accept=".php,.tvtheme,.zip" required>
                <button type="submit" class="tm-btn">Install</button>
            </form>
        </div>
        </div>';

        return $out;
    }

    public function name() { return $this->name; }
    public function links() { return $this->links; }
    public function html() { return $this->html; }
}