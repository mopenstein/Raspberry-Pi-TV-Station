<?php

class Plugins implements ManageCard {
    private $name = 'Plugins';
    private $links = [];
    private $html = '';
    private $settings_file = "/home/pi/Desktop/settings.json";
    private $plugins_dir;
    private $dir_configured = false;
    private $status_msg = '';

    function getPluginMetadata(string $filepath): array {
        $metadata = [];
        $handle = @fopen($filepath, 'r');
        if (!$handle) {
            return $metadata;
        }

        $inBlock = false;
        $linesRead = 0;
        $currentKey = null;
        $lineBreakPending = false;

        while (($line = fgets($handle)) !== false) {
            $linesRead++;
            $trimmed = trim($line);

            if ($linesRead > 60) {
                break;
            }

            if (strcasecmp($trimmed, '# MetaData') === 0) {
                $inBlock = true;
                continue;
            }

            if (strcasecmp($trimmed, '# EndMetaData') === 0) {
                break;
            }

            if ($inBlock && $trimmed !== '' && $trimmed[0] === '#') {
                $rawContent = ltrim($trimmed, "# \t");

                if ($rawContent === '') {
                    continue;
                }

                $hasContinuation = (substr(rtrim($rawContent), -1) === '\\');
                if ($hasContinuation) {
                    $rawContent = rtrim(substr(rtrim($rawContent), 0, -1));
                }

                if (strpos($rawContent, ':') !== false && !$lineBreakPending) {
                    list($key, $val) = explode(':', $rawContent, 2);
                    $currentKey = strtolower(trim($key));
                    $metadata[$currentKey] = trim($val);
                } elseif ($currentKey !== null) {
                    $separator = $lineBreakPending ? "\n" : " ";
                    $metadata[$currentKey] .= $separator . trim($rawContent);
                }

                $lineBreakPending = $hasContinuation;
            }
        }

        fclose($handle);
        return $metadata;
    }

    private function initPluginsDirectory(): bool {
        if (empty($this->plugins_dir)) {
            $this->dir_configured = false;
            return false;
        }

        $this->dir_configured = true;

        if (!is_dir($this->plugins_dir)) {
            @mkdir($this->plugins_dir, 0775, true);

            if (!is_dir($this->plugins_dir)) {
                @exec('sudo mkdir -p ' . escapeshellarg($this->plugins_dir));
            }

            @chown($this->plugins_dir, 'pi');
            @chgrp($this->plugins_dir, 'pi');
            @exec('sudo chown -R pi:pi ' . escapeshellarg($this->plugins_dir));

            @chmod($this->plugins_dir, 0777);
            @exec('sudo chmod 0777 ' . escapeshellarg($this->plugins_dir));
            clearstatcache(true, $this->plugins_dir);
        }

        return is_dir($this->plugins_dir);
    }

    private function ensureDirectoryWritable(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }

        if (!is_writable($dir)) {
            @chmod($dir, 0777);
            clearstatcache(true, $dir);

            if (!is_writable($dir)) {
                @exec('sudo chmod 0777 ' . escapeshellarg($dir));
                @exec('sudo chown -R pi:pi ' . escapeshellarg($dir));
                clearstatcache(true, $dir);
            }
        }

        return is_writable($dir);
    }

    private function handlePackageUpload(string $zipPath, string $origFilename) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->status_msg = 'Error: Failed to open archive ' . htmlspecialchars($origFilename);
            return;
        }

        // Map base filenames to archive index positions
        $pyFiles = [];
        $docFiles = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $base = basename($name);

            // Skip directories and hidden / metadata files
            if (substr($name, -1) === '/' || $base[0] === '.' || strpos($name, '__MACOSX') !== false) {
                continue;
            }

            if (substr($base, -3) === '.py') {
                $rootName = substr($base, 0, -3);
                $pyFiles[$rootName] = [
                    'archive_index' => $i,
                    'filename' => $base
                ];
            } elseif (substr($base, -12) === '.plugin.html') {
                $rootName = substr($base, 0, -12);
                $docFiles[$rootName] = [
                    'archive_index' => $i,
                    'filename' => $base
                ];
            }
        }

        if (empty($pyFiles)) {
            $zip->close();
            $this->status_msg = 'Error: Package contained no valid .py plugin files.';
            return;
        }

        $docsDir = '/var/www/html/docs';
        if (!is_dir($docsDir)) {
            @mkdir($docsDir, 0775, true);
            @exec('sudo mkdir -p ' . escapeshellarg($docsDir));
            @exec('sudo chown -R pi:www-data ' . escapeshellarg($docsDir));
            @exec('sudo chmod 0777 ' . escapeshellarg($docsDir));
        }

        $installed = [];

        foreach ($pyFiles as $rootName => $info) {
            // 1. Extract Python plugin
            $pyDest = rtrim($this->plugins_dir, '/') . '/' . $info['filename'];
            $disabledTarget = $pyDest . '.disabled';
            if (file_exists($disabledTarget)) {
                $pyDest = $disabledTarget;
            }

            $stream = $zip->getStream($zip->getNameIndex($info['archive_index']));
            if ($stream) {
                file_put_contents($pyDest, stream_get_contents($stream));
                fclose($stream);

                @chmod($pyDest, 0755);
                @chown($pyDest, 'pi');
                @chgrp($pyDest, 'pi');
                @exec('sudo chown pi:pi ' . escapeshellarg($pyDest));
                @exec('sudo chmod 0777 ' . escapeshellarg($pyDest));
            }

            // 2. Extract paired documentation file if present
            $docAdded = false;
            if (isset($docFiles[$rootName])) {
                $docInfo = $docFiles[$rootName];
                $docDest = rtrim($docsDir, '/') . '/' . $docInfo['filename'];
                $docStream = $zip->getStream($zip->getNameIndex($docInfo['archive_index']));

                if ($docStream) {
                    file_put_contents($docDest, stream_get_contents($docStream));
                    fclose($docStream);

                    @chmod($docDest, 0777);
                    @exec('sudo chmod 0777 ' . escapeshellarg($docDest));
                    @exec('sudo chown pi:www-data ' . escapeshellarg($docDest));
                    $docAdded = true;
                }
            }

            $installed[] = htmlspecialchars($info['filename']) . ($docAdded ? ' (+docs)' : '');
        }

        $zip->close();
        $this->status_msg = 'Successfully installed package: ' . implode(', ', $installed);
    }

    private function handleUpload() {
        if (!$this->dir_configured || empty($this->plugins_dir) || !is_dir($this->plugins_dir)) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['plugin_file'])) {
            if (!$this->ensureDirectoryWritable($this->plugins_dir)) {
                $this->status_msg = 'Error: Cannot write to plugins directory (permissions lock).';
                return;
            }

            $file = $_FILES['plugin_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->status_msg = 'Upload failed with code: ' . $file['error'];
                return;
            }

            $rawFilename = basename($file['name']);
            $lowerFilename = strtolower($rawFilename);

            // Handle .tvplugin and .zip packages
            if (substr($lowerFilename, -9) === '.tvplugin' || substr($lowerFilename, -4) === '.zip') {
                $this->handlePackageUpload($file['tmp_name'], $rawFilename);
                return;
            }

            // Handle standalone .py files
            if (substr($rawFilename, -3) !== '.py') {
                $this->status_msg = 'Error: Only .tvplugin packages, .zip archives, or .py files are permitted.';
                return;
            }

            $dest = rtrim($this->plugins_dir, '/') . '/' . $rawFilename;
            $disabledTarget = $dest . '.disabled';

            if (file_exists($disabledTarget)) {
                $dest = $disabledTarget;
            }

            if (file_exists($dest) && !is_writable($dest)) {
                @chmod($dest, 0777);
                @exec('sudo chmod 0777 ' . escapeshellarg($dest));
            }

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                @chmod($dest, 0777);
                @chown($dest, 'pi');
                @chgrp($dest, 'pi');
                @exec('sudo chown pi:pi ' . escapeshellarg($dest));
                @exec('sudo chmod 0777 ' . escapeshellarg($dest));
                $this->status_msg = 'Successfully installed/updated: ' . htmlspecialchars($rawFilename);
            } else {
                $this->status_msg = 'Error: Failed to save file to ' . htmlspecialchars($dest);
            }
        }
    }

    private function handleToggleAction() {
        if (!$this->dir_configured || empty($this->plugins_dir) || !is_dir($this->plugins_dir)) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_plugin'])) {
            $filename = basename($_POST['toggle_plugin']);
            $currentPath = rtrim($this->plugins_dir, '/') . '/' . $filename;

            if (file_exists($currentPath)) {
                if (substr($filename, -9) === '.disabled') {
                    $newPath = substr($currentPath, 0, -9);
                    if (@rename($currentPath, $newPath)) {
                        @chmod($newPath, 0777);
                        @exec('chmod +x ' . escapeshellarg($newPath));
                    }
                } elseif (substr($filename, -3) === '.py') {
                    $newPath = $currentPath . '.disabled';
                    @rename($currentPath, $newPath);
                }
            }
        }
    }

    public function __construct() {
        global $json_response;
        $this->plugins_dir = $json_response[0]["plugins directory"] ?? null;

        $dirReady = $this->initPluginsDirectory();

        if ($dirReady) {
            $this->handleUpload();
            $this->handleToggleAction();
        }

        $plugins = [];

        if ($dirReady) {
            $files = glob($this->plugins_dir . '/*.{py,disabled}', GLOB_BRACE);
            if ($files) {
                foreach ($files as $file) {
                    if (substr($file, -3) !== '.py' && substr($file, -9) !== '.disabled') {
                        continue;
                    }

                    $meta = $this->getPluginMetadata($file);
                    $base = basename($file);

                    // Extract base name without .py or .py.disabled
                    $cleanName = preg_replace('/(\.py)?(\.disabled)?$/', '', $base);
                    $docFilename = $cleanName . '.plugin.html';
                    $docFsPath = '/var/www/html/docs/' . $docFilename;

                    $meta['filename'] = $base;
                    $meta['is_enabled'] = (substr($base, -9) !== '.disabled');
                    $meta['doc_url'] = file_exists($docFsPath) ? ('/docs/' . rawurlencode($docFilename)) : null;

                    $plugins[] = $meta;
                }
            }
        }

        $this->html = $this->renderPluginCards($plugins);
    }

    private function renderPluginCards(array $plugins): string {
        $out = '
        <style type="text/css">
            .plugin-stack {
                display: flex;
                flex-direction: column;
                gap: 8px;
                padding: 4px 0;
                font-family: inherit;
            }

            .plugin-warning-box {
                background: rgba(220, 160, 20, 0.12);
                border: 1px solid rgba(220, 160, 20, 0.35);
                border-radius: 4px;
                padding: 10px 14px;
                margin-bottom: 8px;
                font-size: 0.8rem;
                color: #e0bb6b;
                line-height: 1.4;
            }

            .plugin-upload-bar {
                background: rgba(0, 0, 0, 0.35);
                border: 1px dashed rgba(255, 255, 255, 0.15);
                border-radius: 4px;
                padding: 0px 10px 8px 10px;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                flex-wrap: wrap;
            }

            .plugin-upload-bar form {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0;
                width: 100%;
            }

            .plugin-upload-bar input[type="file"] {
                font-size: 0.75rem;
                color: #aaa;
                max-width: 240px;
            }

            .plugin-status-msg {
                font-size: 0.75rem;
                color: #4ec9b0;
                margin: 6px;
                font-family: monospace;
            }

            .plugin-row {
                background: rgba(0, 0, 0, 0.25);
                border: 1px solid rgba(255, 255, 255, 0.07);
                box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.4);
                border-radius: 4px;
                overflow: hidden;
                transition: border-color 0.15s ease;
            }

            .plugin-row:hover {
                border-color: rgba(255, 255, 255, 0.14);
            }

            .plugin-row.is-disabled {
                opacity: 0.5;
            }

            .plugin-summary {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 10px;
                cursor: pointer;
                user-select: none;
                list-style: none;
            }

            .plugin-summary::-webkit-details-marker {
                display: none;
            }

            .plugin-lead {
                display: flex;
                align-items: center;
                gap: 8px;
                min-width: 0;
            }

            .plugin-arrow {
                display: inline-block;
                width: 12px;
                height: 12px;
                opacity: 0.45;
                transition: transform 0.2s ease, opacity 0.2s ease;
                flex-shrink: 0;
            }

            .plugin-row[open] .plugin-arrow {
                transform: rotate(90deg);
                opacity: 0.85;
            }

            .plugin-row:hover .plugin-arrow {
                opacity: 0.8;
            }

            .plugin-name {
                font-size: 0.85rem;
                font-weight: 600;
                letter-spacing: 0.2px;
                color: #ffffff;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .plugin-controls {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }

            .plugin-badge {
                font-size: 0.72rem;
                font-family: monospace;
                opacity: 0.6;
            }

            .plugin-btn {
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.15);
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
                color: #dcdce0;
                padding: 2px 7px;
                font-size: 0.72rem;
                border-radius: 3px;
                cursor: pointer;
                font-family: inherit;
            }

            .plugin-btn:hover {
                background: rgba(255, 255, 255, 0.16);
                color: #ffffff;
                border-color: rgba(255, 255, 255, 0.25);
            }

            .plugin-drawer {
                padding: 8px 10px 10px 30px;
                border-top: 1px solid rgba(255, 255, 255, 0.05);
                font-size: 0.78rem;
                color: rgba(255, 255, 255, 0.75);
            }

            .plugin-subline {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-family: monospace;
                font-size: 0.72rem;
                opacity: 0.6;
                margin-bottom: 6px;
            }

            .plugin-doc-link {
                color: #5294e2;
                text-decoration: none;
                margin-left: 8px;
            }

            .plugin-doc-link:hover {
                text-decoration: underline;
                color: #73a9eb;
            }

            .plugin-desc {
                line-height: 1.4;
                white-space: pre-wrap;
                font-size: 0.76rem;
                opacity: 0.85;
            }
        </style>
        <div class="plugin-stack">';

        if (!$this->dir_configured) {
            $out .= '
            <div class="plugin-warning-box">
                <strong>No Plugin Directory Configured</strong><br>
                Please set the <code>"plugins directory"</code> path in your <code>settings.json</code> file to enable plugin management.
            </div>
            </div>';
            return $out;
        }

        if (!empty($this->status_msg)) {
            $out .= '<div class="plugin-status-msg">' . $this->status_msg . '</div>';
        }

        if (empty($plugins)) {
            $out .= '<div style="padding: 12px; opacity: 0.6; font-size: 0.85rem;">No plugins detected in directory.</div>';
        } else {
            foreach ($plugins as $plugin) {
                $name      = htmlspecialchars($plugin['name'] ?? $plugin['filename']);
                $version   = htmlspecialchars($plugin['version'] ?? '—');
                $date      = htmlspecialchars($plugin['version date'] ?? '');
                $file      = htmlspecialchars($plugin['filename']);
                $desc      = nl2br(htmlspecialchars($plugin['description'] ?? 'No description provided.'));
                $isEnabled = $plugin['is_enabled'];
                $docUrl    = $plugin['doc_url'] ?? null;

                $statusClass = $isEnabled ? '' : 'is-disabled';
                $btnLabel    = $isEnabled ? 'Disable' : 'Enable';

                $docLinkHtml = '';
                if ($docUrl !== null) {
                    $docLinkHtml = '<a class="plugin-doc-link" href="' . htmlspecialchars($docUrl) . '" target="_blank" rel="noopener noreferrer">[Docs]</a>';
                }

                $out .= '
                <details class="plugin-row ' . $statusClass . '">
                    <summary class="plugin-summary">
                        <div class="plugin-lead">
                            <svg class="plugin-arrow" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
                            </svg>
                            <span class="plugin-name">' . $name . '</span>
                        </div>
                        <div class="plugin-controls">
                            <span class="plugin-badge">v' . $version . '</span>
                            <form method="POST" style="display:inline; margin:0; padding:0;" onclick="event.stopPropagation();">
                                <input type="hidden" name="toggle_plugin" value="' . $file . '">
                                <button type="submit" class="plugin-btn">' . $btnLabel . '</button>
                            </form>
                        </div>
                    </summary>
                    <div class="plugin-drawer">
                        <div class="plugin-subline">
                            <div>
                                <span>' . $file . '</span>
                                ' . $docLinkHtml . '
                            </div>
                            ' . ($date ? '<span>' . $date . '</span>' : '') . '
                        </div>
                        <div class="plugin-desc">' . $desc . '</div>
                    </div>
                </details>';
            }
        }

        $out .= '</div>';

        $out .= '
        <div class="plugin-upload-bar">
            <div style="width:100%; font-size:75%; border-bottom: 1px dashed rgba(255, 255, 255, 0.15); padding: 5px;">Install/update plugin (.tvplugin, .zip, or .py):</div>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="plugin_file" accept=".tvplugin,.zip,.py" required>
                <button type="submit" class="plugin-btn">Install</button>
            </form>
        </div>';
        return $out;
    }

    public function name() { return $this->name; }
    public function links() { return $this->links; }
    public function html() { return $this->html; }
}
?>