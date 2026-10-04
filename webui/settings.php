<?php

/*
	Updated: 2026-10
	Name: settings.php
*/

function validateJsonReferences(string $jsonString): array {
    $data = json_decode($jsonString, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['valid' => false, 'errors' => []];
    }

    $errors = [];
    $vars = $data['vars'] ?? [];

    $getPositionData = function($search, $content) {
        $pos = strpos($content, '"' . $search . '"'); 
        if ($pos === false) return null;
        
        $targetPos = $pos + 1;
        $before = substr($content, 0, $targetPos);
        $line = substr_count($before, "\n") + 1;
        
        $lastNewline = strrpos($before, "\n");
        $column = ($lastNewline === false) ? ($targetPos + 1) : ($targetPos - $lastNewline);
        
        return [
            'line' => $line,
            'col' => $column,
            'offset' => $targetPos,
            'length' => strlen($search)
        ];
    };

    $checkRefs = function($item) use (&$checkRefs, &$errors, $vars, $jsonString, $getPositionData) {
		if (is_array($item)) {
			foreach ($item as $value) {
				$checkRefs($value);
			}
		} elseif (is_string($item) && strpos($item, '$ref/') === 0) {
			if (preg_match('/%[^%]+%/', $item)) {
				return; 
			}

			$refPath = substr($item, 5);
			$parts = explode('/', $refPath);
			$current = $vars;
			$missing = false;
			$traversed = "vars";

			foreach ($parts as $part) {
				if (!isset($current[$part])) {
                    $missing = true;
					break;
				}
				$current = $current[$part];
				$traversed .= " > " . $part;
			}

			if ($missing) {
                $errors[] = [
					'label' => "Reference '{$item}' not found in [{$traversed}]",
					'pos' => $getPositionData($item, $jsonString)
				];
			}
		}
	};

    if (isset($data['times'])) $checkRefs($data['times'], 'times');
    if (isset($data['commercial_times'])) $checkRefs($data['commercial_times'], 'commercial_times');

    return ['valid' => empty($errors), 'errors' => $errors];
}

function json_error_to_string($id) {
    switch ($id) {
        case JSON_ERROR_NONE: return "No errors";
        case JSON_ERROR_DEPTH: return "Maximum stack depth exceeded";
        case JSON_ERROR_STATE_MISMATCH: return "Invalid or malformed JSON";
        case JSON_ERROR_CTRL_CHAR: return "Control character error";
        case JSON_ERROR_SYNTAX: return "Syntax error";
        case JSON_ERROR_UTF8: return "Malformed UTF-8 characters";
        default: return "Unknown error";
    }
}

function json_validator($data=NULL) {
    if (!empty($data)) {
        @json_decode($data);
        return [(json_last_error() === JSON_ERROR_NONE), json_error_to_string(json_last_error())];
    }
    return [false, "Empty data"];
}

$settings_file = "/home/pi/Desktop/settings.json";
$backup_dir = "/home/pi/settings_backups/";
$base_name = basename($settings_file);
$is_writable = is_writable($settings_file);

// Helper: Ensure pi user retains full read/write access
function ensure_permissions($file) {
    if (file_exists($file)) {
        @chmod($file, 0666);
        @chown($file, 'pi');
        @chgrp($file, 'pi');
    }
}

// Helper: create rotating backup
function create_backup($settings_file, $backup_dir, $base_name) {
    if (file_exists($settings_file)) {
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        $timestamp = date('Ymd_His');
        $backup_file = $backup_dir . '/' . $base_name . '.bak_' . $timestamp;
        copy($settings_file, $backup_file);
        ensure_permissions($backup_file);

        $backups = glob($backup_dir . '/' . $base_name . '.bak_*');
        usort($backups, function($a, $b) { return filemtime($b) - filemtime($a); });
        if (count($backups) > 10) {
            foreach (array_slice($backups, 10) as $old) unlink($old);
        }
    }
}

// 0. AJAX Preview backup endpoint
if (isset($_GET['action']) && $_GET['action'] === 'preview_backup' && !empty($_GET['file'])) {
    $requested_file = basename($_GET['file']);
    $full_backup_path = $backup_dir . '/' . $requested_file;

    if (file_exists($full_backup_path) && strpos($requested_file, $base_name . '.bak_') === 0) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo file_get_contents($full_backup_path);
        exit;
    } else {
        http_response_code(404);
        echo "Error: Backup file not found or inaccessible.";
        exit;
    }
}

// 1. Export settings.json
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    if (file_exists($settings_file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="settings_' . date('Ymd_His') . '.json"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($settings_file));
        readfile($settings_file);
        exit;
    } else {
        die("Error: settings.json does not exist to export.");
    }
}

// 2. Restore from existing server backup
if (isset($_POST["restore_server_backup"]) && !empty($_POST["backup_filename"])) {
    $requested_file = basename($_POST["backup_filename"]);
    $full_backup_path = $backup_dir . '/' .$requested_file;

    if (file_exists($full_backup_path) && strpos($requested_file,$base_name . '.bak_') === 0) {
        $content = file_get_contents($full_backup_path);
        $val = json_validator($content);
        if ($val[0]) {
            create_backup($settings_file, $backup_dir,$base_name);
            file_put_contents($settings_file,$content);
            ensure_permissions($settings_file);
            header("Location: settings.php?msg=restored");
            exit;
        } else {
            die("Backup corrupted: " . $val[1]);
        }
    } else {
        die("Invalid backup target.");
    }
}

// 3. Upload & Restore custom backup
if (isset($_POST["upload_backup"]) && isset($_FILES["backup_file"])) {
    if ($_FILES["backup_file"]["error"] === UPLOAD_ERR_OK) {
        $uploaded_content = file_get_contents($_FILES["backup_file"]["tmp_name"]);
        $val = json_validator($uploaded_content);
        if ($val[0]) {
            create_backup($settings_file, $backup_dir,$base_name);
            file_put_contents($settings_file,$uploaded_content);
            ensure_permissions($settings_file);
            header("Location: settings.php?msg=uploaded");
            exit;
        } else {
            die('<div style="background:#5a1d1d;color:#ffbaba;padding:20px;font-family:sans-serif;">
                    <h3>Invalid JSON File</h3>
                    <p>'.$val[1].'</p>
                    <button onclick="window.history.back()" style="padding:10px;cursor:pointer;">Go Back</button>
                 </div>');
        }
    } else {
        die("Upload failed with error code: " . $_FILES["backup_file"]["error"]);
    }
}

// 4. Handle Save
if (isset($_POST["settings"]) && isset($_POST["save"])) {
    $raw_json = $_POST["settings"];
    $json_valid = json_validator($raw_json);
    
    if (!$json_valid[0]) {
        die('<div style="background:#5a1d1d;color:#ffbaba;margin:10px;padding:20px;border-radius:4px;font-family:sans-serif;">
                <h3>JSON Syntax Error</h3>
                <p>'.$json_valid[1].'</p>
                <button onclick="window.history.back()" style="padding:10px;cursor:pointer;">Go Back and Fix</button>
             </div>');
    }

    create_backup($settings_file, $backup_dir,$base_name);

    $temp_file = $settings_file . '.tmp';
    if (file_put_contents($temp_file, $raw_json) !== false) {
        rename($temp_file, $settings_file);
        ensure_permissions($settings_file);
        header("Location: settings.php?saved=yes");
        exit();
    } else {
        die("Fatal Error: Could not write to disk.");
    }
}

// Gather server backups for UI
$server_backups = glob($backup_dir . '/' .$base_name . '.bak_*');
usort($server_backups, function($a,$b) { return filemtime($b) - filemtime($a); });

$json_data = file_exists($settings_file) ? file_get_contents($settings_file) : "{}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Settings Editor</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            background-color: #1a1a1a; 
            color: #eee; 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            margin: 12px; 
            line-height: 1.4; 
        }

        .error-box { background: #4e1414; color: #ffbaba; border-left: 4px solid #ff5c5c; margin-bottom: 15px; padding: 12px; border-radius: 4px; font-size: 13px; }
        .warning-box { background: #4e3e14; color: #ffebba; border-left: 4px solid #ffcc5c; margin-bottom: 15px; padding: 12px; border-radius: 4px; font-size: 13px; }
        
        .button-container { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            margin-bottom: 12px; 
            flex-wrap: wrap; 
        }
        
        .btn { 
            padding: 9px 13px; 
            border: none; 
            border-radius: 4px; 
            font-weight: 600; 
            cursor: pointer; 
            font-size: 12px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            transition: background 0.15s ease, opacity 0.15s;
            text-decoration: none; 
            color: #eee; 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
        }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-save { background-color: #007acc; color: #fff; }
        .btn-save:hover:not(:disabled) { background-color: #0062a3; }
        .btn-action { background-color: #2e2e2e; color: #ddd; border: 1px solid #444; }
        .btn-action:hover { background-color: #3e3e3e; }
        .btn-danger { background-color: #a63434; color: white; }
        .btn-danger:hover { background-color: #c93b3b; }
        .btn-preview { background-color: #245b41; color: #e0f2e9; }
        .btn-preview:hover { background-color: #317856; }

        #saved-span { 
            color: #4ec9b0; 
            font-size: 12px; 
            font-weight: bold; 
            padding: 4px 8px;
            background: rgba(78, 201, 176, 0.1);
            border-radius: 3px;
            animation: fadeOut 3.5s forwards; 
        }
        @keyframes fadeOut { 0%, 70% { opacity: 1; } 100% { opacity: 0; } }

        #status-bar { 
            background: #252525; 
            color: #999; 
            padding: 6px 12px; 
            font-size: 11px; 
            border: 1px solid #333; 
            border-bottom: none;
            display: flex; 
            justify-content: space-between; 
            font-family: Consolas, Monaco, monospace;
            border-radius: 4px 4px 0 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            gap: 10px;
        }
        #file-info { overflow: hidden; text-overflow: ellipsis; direction: rtl; text-align: left; }
        
        #editor {
            background-color: #121212; 
            color: #d4d4d4; 
            border: 1px solid #333;
            font-family: Consolas, 'Fira Code', Menlo, Monaco, monospace; 
            font-size: 13px; 
            line-height: 1.5;
            padding: 12px; 
            outline: none; 
            width: 100%; 
            height: 70vh; 
            resize: none; 
            caret-color: #007acc;
            border-radius: 0 0 4px 4px;
            display: block;
        }

        /* Modal styling */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 100; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            backdrop-filter: blur(2px);
            padding: 10px;
            overflow-y: auto;
        }
        .modal-content {
            background-color: #212121; 
            margin: 20px auto; 
            padding: 20px; 
            border: 1px solid #3c3c3c;
            width: 820px; 
            max-width: 100%; 
            border-radius: 6px; 
            box-shadow: 0 8px 30px rgba(0,0,0,0.7);
        }
        .modal-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #333; 
            padding-bottom: 10px; 
            margin-bottom: 16px; 
        }
        .modal-header h3 { margin: 0; font-size: 17px; color: #fff; }
        .modal-close { 
            cursor: pointer; 
            font-size: 24px; 
            line-height: 24px;
            font-weight: bold; 
            color: #888; 
            padding: 4px 8px;
        }
        .modal-close:hover { color: #fff; }

        /* Responsive Backups List */
        .backup-list-container {
            max-height: 52vh;
            overflow-y: auto;
            border: 1px solid #303030;
            border-radius: 4px;
            background: #171717;
            margin-bottom: 15px;
        }
        .backup-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .backup-table th, .backup-table td { padding: 9px 12px; text-align: left; border-bottom: 1px solid #282828; }
        .backup-table th { background-color: #1e1e1e; color: #888; position: sticky; top: 0; z-index: 1; }
        .table-actions { display: flex; gap: 6px; justify-content: flex-end; }

        .upload-section { 
            background: #181818; 
            padding: 14px; 
            border-radius: 4px; 
            border: 1px dashed #3a3a3a; 
        }
        .upload-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .upload-form input[type="file"] { font-size: 12px; color: #aaa; flex: 1 1 200px; }

        #previewArea {
            background-color: #121212; 
            color: #d4d4d4; 
            border: 1px solid #333;
            font-family: Consolas, 'Fira Code', Monaco, monospace; 
            font-size: 12px; 
            line-height: 1.45;
            padding: 12px; 
            width: 100%; 
            height: 55vh; 
            resize: vertical; 
            white-space: pre; 
            overflow: auto; 
            border-radius: 4px;
        }

        /* Hide mobile card view by default on desktop */
        /* Hide mobile card view by default on desktop */
        .mobile-backup-cards {
            display: none;
        }

        /* Mobile Adjustments */
        @media (max-width: 680px) {
            body { margin: 8px; }
            .button-container { gap: 6px; }
            .button-container .btn { flex: 1 1 calc(50% - 6px); font-size: 11px; padding: 8px 6px; }
            .button-container .btn-save { flex: 1 1 100%; font-size: 13px; }
            .modal-content { margin: 8px auto; padding: 14px; }
            #editor { height: 64vh; font-size: 12px; }

            /* Switch Table to Stacked Cards */
            .backup-table, .backup-table thead, .backup-table tbody, .backup-table th { display: none; }
            .mobile-backup-cards { display: block; }
            .backup-card {
                display: flex;
                flex-direction: column;
                gap: 8px;
                padding: 12px;
                border-bottom: 1px solid #2a2a2a;
                background: #1a1a1a;
            }
            .backup-card:nth-child(even) { background: #171717; }
            .backup-card-meta { display: flex; justify-content: space-between; font-size: 12px; }
            .backup-card-filename {
                font-family: Consolas, Monaco, monospace;
                font-size: 11px;
                color: #8fa1b3;
                word-break: break-all;
            }
            .backup-card-actions {
                display: flex;
                gap: 8px;
                margin-top: 4px;
            }
            .backup-card-actions form { flex: 1; display: flex; }
            .backup-card-actions .btn {
                flex: 1;
                padding: 8px 10px;
                font-size: 11px;
                justify-content: center;
            }
            .upload-form .btn { width: 100%; }
        }
    </style>
</head>
<body>

    <?php if (!$is_writable): ?>
        <div class="warning-box"><strong>FILE SYSTEM LOCK:</strong> PHP does not have permission to write to <code>settings.json</code>. Changes cannot be saved.</div>
    <?php endif; ?>

    <?php 
    $val = json_validator($json_data);
    if (!$val[0]) {
        echo '<div class="error-box"><strong>JSON Syntax Error:</strong> '.htmlspecialchars($val[1]).'</div>';
    } else {
        $ref = validateJsonReferences($json_data);
        if (!$ref['valid']) {
            echo '<div class="error-box"><strong>Reference Validation Failed:</strong><ul style="margin: 5px 0 0 16px; padding: 0;">';
            foreach ($ref['errors'] as$err) {
                $pos =$err['pos'];
				if ($pos) {
					echo "<li><span style='color:#ff5c5c; cursor:pointer; text-decoration:underline;' onclick='goToPos({$pos['offset']}, {$pos['length']})'>[Line {$pos['line']}, Col {$pos['col']}]</span> ".htmlspecialchars($err['label'])."</li>";
				} else {
					echo "<li>".htmlspecialchars($err['label'])."</li>";
				}
            }
            echo '</ul></div>';
        }
    }
    ?>

    <form method="POST" id="settingsForm">
        <div class="button-container">
            <button type="submit" name="save" class="btn btn-save" <?php echo !$is_writable ? 'disabled' : ''; ?>>Save Changes</button>
            <button type="button" class="btn btn-action" onclick="openRestoreModal()">Backups & Restore</button>
            <a class="btn btn-action" href="settings.php?action=export">Export JSON</a>
            <a class="btn btn-action" href="/docs/settings.html" target="_blank">Docs</a>
            <button type="button" class="btn btn-action" onclick="testSettings()">Live Test</button>
            
            <?php if(isset($_GET["saved"])): ?>
                <span id="saved-span">Changes saved.</span>
            <?php elseif(isset($_GET["msg"]) && $_GET["msg"] === 'restored'): ?>
                <span id="saved-span">Backup restored.</span>
            <?php elseif(isset($_GET["msg"]) && $_GET["msg"] === 'uploaded'): ?>
                <span id="saved-span">JSON uploaded & applied.</span>
            <?php endif; ?>
        </div>

        <div id="status-bar">
        	<span id="cursor-info">Line 1, Col 1</span>    
			<span id="file-info"><?php echo htmlspecialchars($settings_file); ?></span>
        </div>
        <textarea id="editor" name="settings" spellcheck="false" wrap="off"><?php echo htmlspecialchars($json_data); ?></textarea>
    </form>

    <div style="margin-top: 10px; font-size: 11px; color: #666; font-style: italic;">
        * Rotating backups are preserved automatically (up to 10 stored locally).
    </div>

    <!-- Backup & Restore Modal -->
    <div id="restoreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Backups & Recovery</h3>
                <span class="modal-close" onclick="closeRestoreModal()">&times;</span>
            </div>

            <div style="margin-bottom: 10px; font-size: 12px; font-weight: bold; color: #aaa;">Stored Local Backups:</div>
            
            <div class="backup-list-container">
                <?php if (empty($server_backups)): ?>
                    <div style="padding: 15px; font-size: 12px; color: #777;">No automatic backups found on disk.</div>
                <?php else: ?>
                    <!-- Standard desktop table -->
                    <table class="backup-table">
                        <thead>
                            <tr>
                                <th>Date & Time Modified</th>
                                <th>Filename</th>
                                <th>Size</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($server_backups as $file):$mtime = filemtime($file);$formatted_date = date("M j, Y — H:i:s", $mtime);
                                $filename = basename($file);
                                $size = round(filesize($file) / 1024, 2) . ' KB';
                            ?>
                            <tr>
                                <td><strong><?php echo $formatted_date; ?></strong></td>
                                <td style="font-family: monospace; color: #8fa1b3;"><?php echo htmlspecialchars($filename); ?></td>
                                <td><?php echo $size; ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button type="button" class="btn btn-preview" onclick="previewBackup('<?php echo htmlspecialchars($filename); ?>')">Preview</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Restore this version? Current file will be backed up.');">
                                            <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($filename); ?>">
                                            <button type="submit" name="restore_server_backup" class="btn btn-danger">Restore</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Mobile stacked card layout (auto-toggled via CSS) -->
                    <div class="mobile-backup-cards">
                        <?php foreach ($server_backups as $file):$mtime = filemtime($file);$formatted_date = date("M j, Y — H:i:s", $mtime);
                            $filename = basename($file);
                            $size = round(filesize($file) / 1024, 2) . ' KB';
                        ?>
                        <div class="backup-card">
                            <div class="backup-card-meta">
                                <strong><?php echo $formatted_date; ?></strong>
                                <span style="color:#888;"><?php echo $size; ?></span>
                            </div>
                            <div class="backup-card-filename"><?php echo htmlspecialchars($filename); ?></div>
                            <div class="backup-card-actions">
                                <button type="button" class="btn btn-preview" onclick="previewBackup('<?php echo htmlspecialchars($filename); ?>')">Preview</button>
                                <form method="POST" onsubmit="return confirm('Restore this version? Current file will be backed up.');">
                                    <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($filename); ?>">
                                    <button type="submit" name="restore_server_backup" class="btn btn-danger" style="width: 100%;">Restore</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="upload-section">
                <div style="font-size: 12px; font-weight: bold; margin-bottom: 8px; color: #ccc;">Upload & Apply External Backup (.json)</div>
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <input type="file" name="backup_file" accept=".json,text/plain" required>
                    <button type="submit" name="upload_backup" class="btn btn-action" onclick="return confirm('Upload and apply this JSON file?');">Upload & Apply</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal" style="z-index: 105;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 85%;">Preview: <span id="previewFilename" style="font-family: monospace; font-size: 13px; color: #8fa1b3;"></span></h3>
                <span class="modal-close" onclick="closePreviewModal()">&times;</span>
            </div>
            <textarea id="previewArea" readonly spellcheck="false"></textarea>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; flex-wrap: wrap;">
                <button type="button" class="btn btn-action" onclick="closePreviewModal()" style="flex: 1 1 120px;">Back</button>
                <form method="POST" id="previewRestoreForm" style="flex: 1 1 160px; display: flex;" onsubmit="return confirm('Restore this version? Current file will be backed up.');">
                    <input type="hidden" name="backup_filename" id="previewRestoreTarget" value="">
                    <button type="submit" name="restore_server_backup" class="btn btn-danger" style="width: 100%;">Restore This Version</button>
                </form>
            </div>
        </div>
    </div>

<script>
const editor = document.getElementById("editor");
const cursorInfo = document.getElementById("cursor-info");
const modal = document.getElementById("restoreModal");
const previewModal = document.getElementById("previewModal");
const previewArea = document.getElementById("previewArea");
const previewFilename = document.getElementById("previewFilename");
const previewRestoreTarget = document.getElementById("previewRestoreTarget");

function updateCaret() {
    const textBefore = editor.value.substring(0, editor.selectionStart);
    const lines = textBefore.split("\n");
    const currentLine = lines.length;
    const currentCol = lines[lines.length - 1].length + 1;
    cursorInfo.innerText = `Line ${currentLine}, Col ${currentCol}`;
}

function goToPos(offset, length) {
    editor.focus();
    editor.setSelectionRange(offset, offset + length);
    const lineHeight = 19.5; 
    const textBefore = editor.value.substring(0, offset);
    const lineNum = textBefore.split("\n").length;
    editor.scrollTop = Math.max(0, (lineNum - 4) * lineHeight); 
    updateCaret();
}

function openRestoreModal() { modal.style.display = "block"; }
function closeRestoreModal() { modal.style.display = "none"; }

function previewBackup(filename) {
    previewFilename.innerText = filename;
    previewRestoreTarget.value = filename;
    previewArea.value = "Loading backup file...";
    previewModal.style.display = "block";

    fetch(`settings.php?action=preview_backup&file=${encodeURIComponent(filename)}`)
        .then(response => {
            if (!response.ok) throw new Error("Could not retrieve backup file.");
            return response.text();
        })
        .then(data => {
            previewArea.value = data;
        })
        .catch(err => {
            previewArea.value = "Error: " + err.message;
        });
}

function closePreviewModal() {
    previewModal.style.display = "none";
}

window.onclick = function(e) { 
    if (e.target === modal) closeRestoreModal(); 
    if (e.target === previewModal) closePreviewModal(); 
}

function testSettings() {
    if(confirm('Warning: This triggers a live station test. Continue?')) {
        location.href='index.php?test_settings=1&t=' + Date.now();
    }
}

editor.addEventListener('keyup', updateCaret);
editor.addEventListener('click', updateCaret);
editor.addEventListener('input', updateCaret);
editor.addEventListener('keydown', function(e) {
    if(e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.substring(0, start) + "\t" + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 1;
        updateCaret();
    }
});

window.onload = updateCaret;
</script>
</body>
</html>