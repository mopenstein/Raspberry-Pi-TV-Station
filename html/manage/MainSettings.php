<?php

class MainSettings implements ManageCard {
    private $name = 'Core Settings';
    private $links = [];
    private $html = '';
    private $settings = null;
    private $settingsPath = '/home/pi/Desktop/settings.json';
    private $schemaPath = __DIR__ . '/MainSettings.schema.json';
    private $status_msg = '';

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

    public function display() { 
        return 'wide'; 
    }

    public function setSettings($json_settings) {
        $this->settings = $json_settings;
        $this->processPost();
        $this->generateHtml();
    }

    private function loadSchema() {
        if (!file_exists($this->schemaPath)) {
            return [];
        }
        $raw = @file_get_contents($this->schemaPath);
        $decoded = @json_decode($raw, true);
        return $decoded['items'] ?? [];
    }

    private function saveSettingsInPlace($submittedValues, $schema) {
        $raw = @file_get_contents($this->settingsPath);
        if ($raw === false) {
            $this->status_msg = '<div class="settings-notice error">Error reading settings.json from disk.</div>';
            return;
        }

        $updatedRaw = $raw;

        foreach ($schema as $item) {
            $key = $item['key'];
            $type = $item['type'] ?? 'text';
            $nullable = !empty($item['nullable']);

            $is_null = $nullable && isset($submittedValues['null_flags'][$key]);
            $formattedVal = null;

            if ($is_null) {
                $formattedVal = 'null';
            } elseif ($type === 'bool') {
                $formattedVal = (isset($submittedValues[$key]) && ($submittedValues[$key] === 'true' || $submittedValues[$key] === '1')) ? 'true' : 'false';
            } elseif ($type === 'number') {
                $val = trim($submittedValues[$key] ?? '');
                $formattedVal = ($val === '' && $nullable) ? 'null' : (string)(int)$val;
            } elseif ($type === 'float') {
                $val = trim($submittedValues[$key] ?? '');
                $formattedVal = ($val === '' && $nullable) ? 'null' : (string)(float)$val;
            } elseif ($type === 'dropdown') {
                $val = trim($submittedValues[$key] ?? '');
                if (is_numeric($val)) {
                    $formattedVal = (string)$val;
                } else {
                    $formattedVal = json_encode($val, JSON_UNESCAPED_SLASHES);
                }
            } elseif ($type === 'list') {
                $items = $submittedValues[$key] ?? [];
                if (!is_array($items)) {
                    $items = [];
                }
                $cleanItems = array_values(array_filter(array_map('trim', $items), function($v) {
                    return $v !== '';
                }));
                $encodedList = array_map(function($v) {
                    return json_encode($v, JSON_UNESCAPED_SLASHES);
                }, $cleanItems);
                $formattedVal = '[' . implode(', ', $encodedList) . ']';
            } else {
                $val = $submittedValues[$key] ?? '';
                $formattedVal = json_encode((string)$val, JSON_UNESCAPED_SLASHES);
            }

            $escapedKey = preg_quote($key, '/');
            $pattern = '/("' . $escapedKey . '"\s*:\s*)(?:\[[^\]]*\]|"[^"]*"|true|false|null|-?\d+(?:\.\d+)?)/s';

            if (preg_match($pattern, $updatedRaw)) {
                $updatedRaw = preg_replace($pattern, '${1}' . $formattedVal, $updatedRaw, 1);
            }
        }

        $postData = http_build_query([
            'api_save' => 1,
            'payload'  => $updatedRaw
        ]);

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 5
            ]
        ];

        $context = stream_context_create($options);
        $response_raw = @file_get_contents('http://127.0.0.1/settings.php', false, $context);

        if ($response_raw !== false) {
            $response = json_decode($response_raw, true);
            if ($response && isset($response['success']) && $response['success'] === true) {
                $this->status_msg = '<div class="settings-notice success">Settings updated and backed up successfully!</div>';
                $this->settings = json_decode($updatedRaw, true);
            } else {
                $err = $response['error'] ?? 'API response error';
                $this->status_msg = '<div class="settings-notice error">Save failed: ' . htmlspecialchars($err) . '</div>';
            }
        } else {
            $this->status_msg = '<div class="settings-notice error">Could not connect to settings.php API on 127.0.0.1.</div>';
        }
    }

    private function processPost() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['main_settings_action'])) {
            return;
        }

        if ($_POST['main_settings_action'] === 'save_core') {
            $schema = $this->loadSchema();
            $submitted = $_POST['cfg'] ?? [];
            $this->saveSettingsInPlace($submitted, $schema);
        }
    }

    private function generateHtml() {
        $schema = $this->loadSchema();
        $current = is_array($this->settings) ? $this->settings : [];

        $out = '
        <style type="text/css">
            .ms-stack { display: flex; flex-direction: column; gap: 8px; font-family: inherit; font-size: 13px; }
            .settings-notice { padding: 9px 12px; border-radius: 4px; margin-bottom: 6px; font-size: 0.8rem; }
            .settings-notice.success { background: rgba(78, 201, 176, 0.1); border: 1px solid rgba(78, 201, 176, 0.3); color: #4ec9b0; }
            .settings-notice.error { background: rgba(220, 20, 60, 0.1); border: 1px solid rgba(220, 20, 60, 0.3); color: #ff6b6b; }
            .ms-card { background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.07); box-shadow: inset 0 1px 3px rgba(0,0,0,0.4); border-radius: 4px; padding: 14px; }
            .ms-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
            .ms-group { display: flex; flex-direction: column; gap: 4px; }
            .ms-group.full { grid-column: span 2; }
            .ms-header-row { display: flex; justify-content: space-between; align-items: baseline; }
            .ms-label { font-size: 0.72rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
            .ms-desc { font-size: 0.7rem; color: #777; margin-top: 1px; }
            .ms-warn { font-size: 0.68rem; color: #d19a66; margin-top: 2px; }
            .ms-input-row { display: flex; gap: 6px; align-items: center; }
            .ms-input, .ms-select { 
                width: 100%; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.15); 
                color: #fff; padding: 6px 8px; border-radius: 3px; font-size: 0.8rem; font-family: Consolas, monospace; box-sizing: border-box;
            }
            .ms-input:disabled { opacity: 0.4; cursor: not-allowed; background: rgba(0, 0, 0, 0.5); }
            .ms-null-toggle { font-size: 0.72rem; color: #888; display: flex; align-items: center; gap: 3px; cursor: pointer; white-space: nowrap; }
            .ms-checkbox-wrap { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; margin-top: 18px; color: #eee; font-size: 0.8rem; }
            .ms-list-container { display: flex; flex-direction: column; gap: 5px; }
            .ms-list-row { display: flex; gap: 6px; }
            .ms-btn-small { background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); color: #ddd; padding: 3px 8px; border-radius: 3px; cursor: pointer; font-size: 0.72rem; }
            .ms-btn-small:hover { background: rgba(255, 255, 255, 0.16); color: #fff; }
            .ms-btn-remove:hover { background: rgba(220, 20, 60, 0.4); border-color: rgba(220, 20, 60, 0.6); color: #ffbaba; }
            .ms-btn-save { background: #007acc; border: 1px solid #005f9e; color: #fff; padding: 7px 18px; font-size: 0.75rem; font-weight: bold; border-radius: 3px; cursor: pointer; }
            .ms-btn-save:hover { background: #0062a3; }
        </style>';

        $out .= '<div class="ms-stack">';
        if (!empty($this->status_msg)) {
            $out .= $this->status_msg;
        }

        if (empty($schema)) {
            $out .= '<div class="settings-notice error">Schema file not found or empty: ' . htmlspecialchars(basename($this->schemaPath)) . '</div></div>';
            $this->html = $out;
            return;
        }

        $out .= '<form method="POST" id="mainSettingsForm">';
        $out .= '<input type="hidden" name="main_settings_action" value="save_core">';
        $out .= '<div class="ms-card">';
        $out .= '<div class="ms-grid">';

        foreach ($schema as $idx => $item) {
            $key = $item['key'];
            $label = $item['label'] ?? $key;
            $type = $item['type'] ?? 'text';
            $nullable = !empty($item['nullable']);
            $desc = $item['description'] ?? '';
            $warn = $item['warn'] ?? '';

            $val = array_key_exists($key, $current) ? $current[$key] : null;
            $isNull = ($val === null);

            $isFull = ($type === 'list') || in_array($key, ['name', 'plugins directory', 'cache_path']);
            $colClass = $isFull ? 'ms-group full' : 'ms-group';

            $out .= '<div class="' . $colClass . '">';
            $out .= '<div class="ms-header-row">';
            $out .= '<label class="ms-label">' . htmlspecialchars($label) . '</label>';
            $out .= '</div>';

            $warnAttr = !empty($warn) ? ' data-warn="' . htmlspecialchars($warn) . '" data-original="' . htmlspecialchars((string)$val) . '"' : '';

            if ($type === 'bool') {
                $checked = filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '';
                $out .= '<label class="ms-checkbox-wrap">';
                $out .= '<input type="checkbox" name="cfg[' . htmlspecialchars($key) . ']" value="true" ' . $checked . $warnAttr . '>';
                $out .= '<span>Enable</span>';
                $out .= '</label>';
            } elseif ($type === 'dropdown') {
                $out .= '<div class="ms-input-row">';
                $out .= '<select class="ms-select" name="cfg[' . htmlspecialchars($key) . ']"' . $warnAttr . '>';
                foreach ($item['options'] ?? [] as $opt) {
                    $selected = ((string)$opt === (string)$val) ? 'selected' : '';
                    $out .= '<option value="' . htmlspecialchars((string)$opt) . '" ' . $selected . '>' . htmlspecialchars((string)$opt) . '</option>';
                }
                $out .= '</select>';
                $out .= '</div>';
            } elseif ($type === 'list') {
                $listItems = is_array($val) ? $val : [];
                $out .= '<div class="ms-list-container" id="list_container_' . $idx . '">';
                foreach ($listItems as $rowVal) {
                    $out .= '<div class="ms-list-row">';
                    $out .= '<input type="text" class="ms-input" name="cfg[' . htmlspecialchars($key) . '][]" value="' . htmlspecialchars($rowVal) . '">';
                    $out .= '<button type="button" class="ms-btn-small ms-btn-remove" onclick="this.parentElement.remove();">&times;</button>';
                    $out .= '</div>';
                }
                $out .= '</div>';
                $out .= '<div style="margin-top: 4px;">';
                $out .= '<button type="button" class="ms-btn-small" onclick="addListRow(\'list_container_' . $idx . '\', \'cfg[' . htmlspecialchars($key) . '][]\');">+ Add Item</button>';
                $out .= '</div>';
            } else {
                $inputId = 'input_' . $idx;
                $step = ($type === 'float') ? 'step="any"' : (($type === 'number') ? 'step="1"' : '');
                $min = isset($item['min']) ? 'min="' . (int)$item['min'] . '"' : '';
                $max = isset($item['max']) ? 'max="' . (int)$item['max'] . '"' : '';
                $maxLen = isset($item['max-length']) ? 'maxlength="' . (int)$item['max-length'] . '"' : '';
                $htmlType = ($type === 'text') ? 'text' : 'number';

                $out .= '<div class="ms-input-row">';
                $disabled = $isNull ? 'disabled' : '';
                $displayVal = $isNull ? '' : (string)$val;

                $out .= '<input id="' . $inputId . '" class="ms-input" type="' . $htmlType . '" name="cfg[' . htmlspecialchars($key) . ']" value="' . htmlspecialchars($displayVal) . '" ' . $step . ' ' . $min . ' ' . $max . ' ' . $maxLen . ' ' . $disabled . $warnAttr . '>';

                if ($nullable) {
                    $nullChecked = $isNull ? 'checked' : '';
                    $out .= '<label class="ms-null-toggle">';
                    $out .= '<input type="checkbox" name="cfg[null_flags][' . htmlspecialchars($key) . ']" value="1" ' . $nullChecked . ' onchange="toggleNullField(\'' . $inputId . '\', this.checked)">';
                    $out .= 'null';
                    $out .= '</label>';
                }
                $out .= '</div>';
            }

            if (!empty($desc)) {
                $out .= '<div class="ms-desc">' . htmlspecialchars($desc) . '</div>';
            }
            if (!empty($warn)) {
                $out .= '<div class="ms-warn">⚠ ' . htmlspecialchars($warn) . '</div>';
            }

            $out .= '</div>';
        }

        $out .= '</div>';
        $out .= '<div style="margin-top: 14px; text-align: right;">';
        $out .= '<button type="submit" class="ms-btn-save">Save Core Settings</button>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</form>';

        $out .= '
        <script>
        function toggleNullField(id, isNull) {
            var el = document.getElementById(id);
            if (!el) return;
            el.disabled = isNull;
            if (isNull) {
                el.dataset.prevVal = el.value;
                el.value = "";
            } else if (el.dataset.prevVal !== undefined) {
                el.value = el.dataset.prevVal;
            }
        }

        function addListRow(containerId, fieldName) {
            var container = document.getElementById(containerId);
            if (!container) return;
            var row = document.createElement("div");
            row.className = "ms-list-row";
            row.innerHTML = \'<input type="text" class="ms-input" name="\' + fieldName + \'" value="">\' +
                            \'<button type="button" class="ms-btn-small ms-btn-remove" onclick="this.parentElement.remove();">&times;</button>\';
            container.appendChild(row);
        }

        document.getElementById("mainSettingsForm").addEventListener("submit", function(e) {
            var warned = document.querySelectorAll("[data-warn]");
            for (var i = 0; i < warned.length; i++) {
                var el = warned[i];
                var cur = (el.type === "checkbox") ? (el.checked ? "true" : "false") : el.value;
                if (el.dataset.original !== undefined && cur !== el.dataset.original) {
                    if (!confirm("Warning on " + el.name + ":\\n" + el.dataset.warn + "\\n\\nProceed with save?")) {
                        e.preventDefault();
                        return;
                    }
                }
            }
        });
        </script>';

        $out .= '</div>';
        $this->html = $out;
    }
}