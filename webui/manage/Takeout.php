<?php

class Takeout implements ManageCard {
    private $name = 'Station Takeout';
    private $links = [];
    private $html = '';
    private $settings = null;
    private $settings_file = '/home/pi/Desktop/settings.json';
    private $plugins_dir = null;
    private $manifest_file = __DIR__ . '/takeout_manifest.json';
    private $status_msg = '';
    private $db_config = [
        'host' => null,
        'user' => null,
        'pass' => null,
        'name' => null
    ];

    public function priority() {
        return 190;
    }

    public function name() {
        return $this->name;
    }

    public function links() {
        return $this->links;
    }

    public function html() {
        return $this->html;
    }

    public function setSettings($json_settings) {
        $this->settings = $json_settings;
        $this->loadConfigFromSettings($json_settings);
    }

    private function loadConfigFromSettings($cfg) {
        if (empty($cfg)) {
            return;
        }

        $root = isset($cfg[0]) && is_array($cfg[0]) ? $cfg[0] : $cfg;
        $this->plugins_dir = $root['plugins directory'] ?? null;

        $dbInfo = $root['web-ui']['database_info'] ?? [];
        $this->db_config['host'] = $dbInfo['host'] ?? null;
        $this->db_config['user'] = $dbInfo['username'] ?? null;
        $this->db_config['pass'] = $dbInfo['password'] ?? null;
        $this->db_config['name'] = $dbInfo['database_name'] ?? null;
    }

    private function getManifestEntries(): array {
        if (!file_exists($this->manifest_file)) {
            $defaultManifest = [
                ["path" => "/home/pi/Desktop/settings.json", "archive_target" => "settings.json"],
                ["path" => "$/plugins directory",            "archive_target" => "plugins"],
                ["path" => "/var/www/html/schedule.json",   "archive_target" => "schedule.json"],
                ["path" => "/var/www/html/docs",            "archive_target" => "docs"],
                ["path" => "/var/www/html/templates",       "archive_target" => "templates"]
            ];
            file_put_contents($this->manifest_file, json_encode($defaultManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            @chmod($this->manifest_file, 0777);
            return $defaultManifest;
        }

        $raw = file_get_contents($this->manifest_file);
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : [];
    }

    private function resolveDynamicPath(string $path) {
        // Detect setting traversal prefix: $/
        if (substr($path, 0, 2) === '$/') {
            if (empty($this->settings)) {
                return null;
            }

            // Strip the leading '$/' and explode hierarchy by '/'
            $subPath = substr($path, 2);
            $parts = explode('/', $subPath);

            // Normalize base settings array ($cfg or $cfg[0])
            $curr = isset($this->settings[0]) && is_array($this->settings[0]) ? $this->settings[0] : $this->settings;

            foreach ($parts as $segment) {
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }

                if (is_array($curr) && array_key_exists($segment, $curr)) {
                    $curr = $curr[$segment];
                } else {
                    // Setting branch not found
                    return null;
                }
            }

            // Return path string if found
            return is_string($curr) ? rtrim($curr, '/') : null;
        }

        return $path;
    }

    public function __construct() {
        global $json_response;

        if (isset($json_response)) {
            $this->setSettings($json_response);
        } elseif (file_exists($this->settings_file)) {
            $raw = file_get_contents($this->settings_file);
            $parsed = json_decode($raw, true);
            if ($parsed) {
                $this->setSettings($parsed);
            }
        }

        $this->handleActions();
    }

    private function handleActions() {
        if (isset($_GET['takeout_action']) && $_GET['takeout_action'] === 'export') {
            $this->generateExportZip();
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['takeout_archive'])) {
            $this->processUploadStage();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_takeout_import'])) {
            $this->executeImport();
        }

        $this->renderUi();
    }

    private function generateExportZip() {
        if (ob_get_level()) {
            ob_end_clean();
        }

        $tmpDir = sys_get_temp_dir() . '/takeout_' . uniqid();
        @mkdir($tmpDir, 0777, true);

        $sqlFile = $tmpDir . '/database_backup.sql';
        if (!empty($this->db_config['user']) && !empty($this->db_config['name'])) {
            $hostParam = !empty($this->db_config['host']) ? (' -h ' . escapeshellarg($this->db_config['host'])) : '';
            $passParam = ($this->db_config['pass'] !== null && $this->db_config['pass'] !== '') ? (' -p' . escapeshellarg($this->db_config['pass'])) : '';

            $dumpCmd = sprintf(
                'mysqldump%s -u %s%s --skip-extended-insert --complete-insert --order-by-primary --skip-compact %s > %s',
                $hostParam,
                escapeshellarg($this->db_config['user']),
                $passParam,
                escapeshellarg($this->db_config['name']),
                escapeshellarg($sqlFile)
            );
            exec($dumpCmd);
        }

        $zipPath = $tmpDir . '/station_takeout_' . date('Y-m-d_His') . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            die('Error: Unable to build takeout archive.');
        }

        if (file_exists($sqlFile) && filesize($sqlFile) > 0) {
            $zip->addFile($sqlFile, 'database_backup.sql');
        }

        $manifestEntries = $this->getManifestEntries();
        $storedManifest = [];

        foreach ($manifestEntries as $entry) {
            $rawPath = $entry['path'] ?? '';
            $resolvedPath = $this->resolveDynamicPath($rawPath);
            $target = trim($entry['archive_target'] ?? basename($rawPath), '/');

            if (!$resolvedPath || !file_exists($resolvedPath) || empty($target)) {
                continue;
            }

            if (is_dir($resolvedPath)) {
                $this->addFolderToZip($resolvedPath, $target, $zip);
                $storedManifest[] = [
                    'type' => 'dir',
                    'original_path' => $rawPath,
                    'archive_target' => $target
                ];
            } elseif (is_file($resolvedPath)) {
                $zip->addFile($resolvedPath, $target);
                $storedManifest[] = [
                    'type' => 'file',
                    'original_path' => $rawPath,
                    'archive_target' => $target
                ];
            }
        }

        $zip->addFromString('manifest.json', json_encode($storedManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        if (file_exists($zipPath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);

            @unlink($sqlFile);
            @unlink($zipPath);
            @rmdir($tmpDir);
            exit;
        }
    }

    private function addFolderToZip(string $folder, string $zipSubFolder, ZipArchive $zip) {
        $folder = rtrim($folder, '/');
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($folder) + 1);
            $zipDestPath = rtrim($zipSubFolder, '/') . '/' . $relativePath;

            if ($file->isDir()) {
                $zip->addEmptyDir($zipDestPath);
            } elseif ($file->isFile()) {
                $zip->addFile($filePath, $zipDestPath);
            }
        }
    }

    private function getHostMountPoints(): array {
        $drives = [];
        $searchBases = ['/media/pi', '/media', '/mnt'];

        foreach ($searchBases as $base) {
            if (is_dir($base)) {
                $dirs = glob($base . '/*', GLOB_ONLYDIR);
                if ($dirs) {
                    foreach ($dirs as $d) {
                        $drives[] = $d;
                    }
                }
            }
        }
        return array_unique($drives);
    }

    private function processUploadStage() {
        $file = $_FILES['takeout_archive'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->status_msg = '<span style="color:#ff6b6b;">Upload failed with error code: ' . $file['error'] . '</span>';
            return;
        }

        $stageDir = sys_get_temp_dir() . '/takeout_stage_' . uniqid();
        if (!mkdir($stageDir, 0777, true)) {
            $this->status_msg = '<span style="color:#ff6b6b;">Failed to create staging directory.</span>';
            return;
        }

        $destArchive = $stageDir . '/staged.zip';
        if (!move_uploaded_file($file['tmp_name'], $destArchive)) {
            $this->status_msg = '<span style="color:#ff6b6b;">Failed to store staged archive.</span>';
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($destArchive) !== true) {
            $this->status_msg = '<span style="color:#ff6b6b;">Invalid or corrupted ZIP archive.</span>';
            return;
        }

        $zip->extractTo($stageDir);
        $zip->close();

        $sqlPath = $stageDir . '/database_backup.sql';
        $detectedOldMounts = [];

        if (file_exists($sqlPath)) {
            $handle = @fopen($sqlPath, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    if (preg_match_all('#/(?:media(?:/pi)?|mnt)/[^/\'"\s;,]+#', $line, $matches)) {
                        foreach ($matches[0] as $match) {
                            $detectedOldMounts[$match] = true;
                        }
                    }
                }
                fclose($handle);
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stageDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $path => $fileObj) {
            if ($fileObj->isFile() && in_array($fileObj->getExtension(), ['json', 'txt', 'conf', 'cfg', 'ini'])) {
                $rawText = @file_get_contents($path);
                if ($rawText && preg_match_all('#/(?:media(?:/pi)?|mnt)/[^/\'"\s;,]+#', $rawText, $matches)) {
                    foreach ($matches[0] as $match) {
                        $detectedOldMounts[$match] = true;
                    }
                }
            }
        }

        $availableCurrentDrives = $this->getHostMountPoints();
        $this->renderDriveMappingForm($stageDir, array_keys($detectedOldMounts), $availableCurrentDrives);
    }

    private function executeImport() {
        $stageDir = $_POST['stage_dir'] ?? '';
        if (empty($stageDir) || !is_dir($stageDir)) {
            $this->status_msg = '<span style="color:#ff6b6b;">Staging session expired. Please re-upload.</span>';
            return;
        }

        $remaps = $_POST['remap'] ?? [];
        $replacements = [];
        foreach ($remaps as $old => $new) {
            $oldTrim = trim($old);
            $newTrim = trim($new);
            if (!empty($oldTrim) && !empty($newTrim) && $oldTrim !== $newTrim) {
                $replacements[$oldTrim] = $newTrim;
            }
        }

        $manifestPath = $stageDir . '/manifest.json';
        $archiveManifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;

        // Prioritize settings.json to bootstrap credentials and paths
        $settingsArchiveItem = null;
        if (is_array($archiveManifest)) {
            foreach ($archiveManifest as $item) {
                if (strpos($item['original_path'], 'settings.json') !== false || $item['archive_target'] === 'settings.json') {
                    $settingsArchiveItem = $item;
                    break;
                }
            }
        }

        $stagedSettings = $settingsArchiveItem ? ($stageDir . '/' . $settingsArchiveItem['archive_target']) : ($stageDir . '/settings.json');
        if (file_exists($stagedSettings)) {
            $settingsContent = file_get_contents($stagedSettings);
            if (!empty($replacements)) {
                $settingsContent = str_replace(array_keys($replacements), array_values($replacements), $settingsContent);
            }

            $targetSettingsPath = $settingsArchiveItem ? $this->resolveDynamicPath($settingsArchiveItem['original_path']) : $this->settings_file;
            @mkdir(dirname($targetSettingsPath), 0775, true);
            file_put_contents($targetSettingsPath, $settingsContent);
            @chmod($targetSettingsPath, 0777);
            @exec('sudo chown pi:pi ' . escapeshellarg($targetSettingsPath));
            @exec('sudo chmod 0777 ' . escapeshellarg($targetSettingsPath));

            $newSettings = json_decode($settingsContent, true);
            if ($newSettings) {
                $this->setSettings($newSettings);
            }
        }

        // Restore Database
        $sqlPath = $stageDir . '/database_backup.sql';
        if (file_exists($sqlPath)) {
            if (!empty($replacements)) {
                $rewrittenSql = $sqlPath . '.rewritten';
                $in = fopen($sqlPath, 'r');
                $out = fopen($rewrittenSql, 'w');

                while (($line = fgets($in)) !== false) {
                    $line = str_replace(array_keys($replacements), array_values($replacements), $line);
                    fwrite($out, $line);
                }
                fclose($in);
                fclose($out);
                unlink($sqlPath);
                rename($rewrittenSql, $sqlPath);
            }

            if (!empty($this->db_config['user']) && !empty($this->db_config['name'])) {
                $hostParam = !empty($this->db_config['host']) ? (' -h ' . escapeshellarg($this->db_config['host'])) : '';
                $passParam = ($this->db_config['pass'] !== null && $this->db_config['pass'] !== '') ? (' -p' . escapeshellarg($this->db_config['pass'])) : '';

                $importCmd = sprintf(
                    'mysql%s -u %s%s %s < %s',
                    $hostParam,
                    escapeshellarg($this->db_config['user']),
                    $passParam,
                    escapeshellarg($this->db_config['name']),
                    escapeshellarg($sqlPath)
                );
                exec($importCmd);
            }
        }

        // Restore remaining manifest files/directories dynamically
        if (is_array($archiveManifest)) {
            foreach ($archiveManifest as $item) {
                if ($item === $settingsArchiveItem) {
                    continue;
                }

                $stagedItemPath = $stageDir . '/' . $item['archive_target'];
                $destinationPath = $this->resolveDynamicPath($item['original_path']);

                if (!$destinationPath || !file_exists($stagedItemPath)) {
                    continue;
                }

                if ($item['type'] === 'dir') {
                    if (!is_dir($destinationPath)) {
                        @mkdir($destinationPath, 0777, true);
                    }
                    $this->copyDirectory($stagedItemPath, $destinationPath);
                    @exec('sudo chmod -R 0777 ' . escapeshellarg($destinationPath));
                } elseif ($item['type'] === 'file') {
                    @mkdir(dirname($destinationPath), 0777, true);
                    copy($stagedItemPath, $destinationPath);
                    @chmod($destinationPath, 0777);
                    @exec('sudo chmod 0777 ' . escapeshellarg($destinationPath));
                }
            }
        }

        exec('rm -rf ' . escapeshellarg($stageDir));
        $this->status_msg = '<span style="color:#4ec9b0;">Takeout successfully restored and drives re-mapped!</span>';
    }

    private function copyDirectory(string $src, string $dst) {
        $dir = opendir($src);
        @mkdir($dst, 0777, true);
        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                if (is_dir($src . '/' . $file)) {
                    $this->copyDirectory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                    @chmod($dst . '/' . $file, 0777);
                }
            }
        }
        closedir($dir);
    }

    private function renderDriveMappingForm(string $stageDir, array $oldDrives, array $systemDrives) {
        $out = '<div style="background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); border-radius: 4px; padding: 12px; margin-top: 10px;">';
        $out .= '<h4 style="margin: 0 0 10px 0; color: #e0bb6b;">Remap Storage Mounts</h4>';
        $out .= '<p style="font-size: 0.8rem; opacity: 0.8; margin-bottom: 12px;">The uploaded backup references the following storage mount paths. Map them to current mounts on this Raspberry Pi:</p>';

        $out .= '<form method="POST">';
        $out .= '<input type="hidden" name="execute_takeout_import" value="1">';
        $out .= '<input type="hidden" name="stage_dir" value="' . htmlspecialchars($stageDir) . '">';

        if (empty($oldDrives)) {
            $out .= '<p style="font-size: 0.8rem; color: #888;">No drive mount patterns (/media/...) were identified in the backup.</p>';
        } else {
            $out .= '<table style="width: 100%; font-size: 0.8rem; margin-bottom: 10px; border-collapse: collapse;">';
            $out .= '<tr style="text-align: left; opacity: 0.6; border-bottom: 1px solid rgba(255,255,255,0.1);"><th style="padding: 4px;">Detected in Backup</th><th style="padding: 4px;">Map to New Mount Point</th></tr>';

            foreach ($oldDrives as $old) {
                $out .= '<tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">';
                $out .= '<td style="padding: 6px; font-family: monospace; color: #4ec9b0;">' . htmlspecialchars($old) . '</td>';
                $out .= '<td style="padding: 6px;">';
                $out .= '<input type="text" name="remap[' . htmlspecialchars($old) . ']" list="system_drives" value="' . htmlspecialchars($old) . '" style="width: 90%; background: #1a1a1a; border: 1px solid #444; color: #fff; padding: 3px 6px; border-radius: 3px;">';
                $out .= '</td>';
                $out .= '</tr>';
            }
            $out .= '</table>';

            $out .= '<datalist id="system_drives">';
            foreach ($systemDrives as $drv) {
                $out .= '<option value="' . htmlspecialchars($drv) . '">';
            }
            $out .= '</datalist>';
        }

        $out .= '<div style="display:flex; gap: 8px; margin-top: 10px;">';
        $out .= '<button type="submit" style="background: rgba(78,201,176,0.2); border: 1px solid #4ec9b0; color: #4ec9b0; padding: 5px 12px; border-radius: 3px; cursor: pointer;">Finalize & Restore Station</button>';
        $out .= '<a href="/?takeout_cancel=1" style="font-size: 0.8rem; align-self: center; color: #ff6b6b; text-decoration: none;">Cancel</a>';
        $out .= '</div>';
        $out .= '</form></div>';

        $this->html = $out;
    }

    private function renderUi() {
        if (!empty($this->html)) {
            return;
        }

        $this->links = [
            [
                'label'  => 'Download Full Station Takeout (.zip)',
                'url'    => '/?takeout_action=export',
                'style'  => 'color: #4ec9b0; font-weight: bold;',
                'action' => null
            ]
        ];

        $out = '<div style="font-size: 0.82rem; padding: 5px 0;">';
        if (!empty($this->status_msg)) {
            $out .= '<div style="margin-bottom: 8px;">' . $this->status_msg . '</div>';
        }

        $out .= '
        <div style="background: rgba(0,0,0,0.25); border: 1px dashed rgba(255,255,255,0.15); border-radius: 4px; padding: 10px; margin-top: 6px;">
            <div style="font-weight: 600; margin-bottom: 6px;">Restore Station Takeout:</div>
            <form method="POST" enctype="multipart/form-data" style="margin: 0; display: flex; flex-direction: column; gap: 8px;">
                <input type="file" name="takeout_archive" accept=".zip" required style="font-size: 0.75rem; color: #aaa;">
                <div>
                    <button type="submit" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 3px 10px; border-radius: 3px; cursor: pointer;" onclick="return confirm(\'Warning: Importing a takeout archive will overwrite existing station configurations and tables. Continue?\');">Upload & Inspect Takeout</button>
                </div>
            </form>
        </div>
        </div>';

        $this->html = $out;
    }
}
?>