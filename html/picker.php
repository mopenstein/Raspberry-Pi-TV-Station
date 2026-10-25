<?php
$settingsPath = '/home/pi/Desktop/settings.json';
$schedulePath = '/var/www/html/schedule.json';

$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = trim($_POST['mode'] ?? 'show');
    $category = trim($_POST['category'] ?? '');
    $hexColor = strtoupper(ltrim(trim($_POST['color'] ?? ''), '#'));

    // Select which target JSON block inside "web-ui" to manipulate
    $blockKey = ($mode === 'commercial') ? 'commercial_type_colors' : 'show_type_colors';

    if (!empty($category) && preg_match('/^[A-F0-9]{6}$/', $hexColor)) {
        $raw = @file_get_contents($settingsPath);

        if ($raw !== false) {
            $pattern = '/("' . preg_quote($blockKey, '/') . '"\s*:\s*\{)([^}]*?)(\})/s';

            if (preg_match($pattern, $raw, $matches)) {
                $colorsBlock = $matches[2];
                $escapedCat = preg_quote($category, '/');
                $keyPattern = '/"' . $escapedCat . '"\s*:\s*"[A-Fa-f0-9]{6}"/';

                if (preg_match($keyPattern, $colorsBlock)) {
                    // Update existing entry
                    $updatedBlock = preg_replace($keyPattern, '"' . addcslashes($category, '"\\') . '": "' . $hexColor . '"', $colorsBlock);
                } else {
                    // Append new category while maintaining single-line style
                    $trimmed = rtrim($colorsBlock);
                    $hasEntries = preg_match('/"[^"]+"\s*:/', $trimmed);
                    $separator = $hasEntries ? ', ' : ' ';
                    $updatedBlock = $trimmed . $separator . '"' . addcslashes($category, '"\\') . '": "' . $hexColor . '" ';
                }

                // Inject the updated block back into the raw text
                $newRaw = preg_replace($pattern, '${1}' . $updatedBlock . '${3}', $raw, 1);

                // --- NEW API SAVE LOGIC ---
                $postData = http_build_query([
                    'api_save' => 1,
                    'payload'  => $newRaw
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
                        $label = ($mode === 'commercial') ? 'Commercial Category' : 'Show Category';
                        $message = "Updated {$label} '{$category}' to #{$hexColor}";
                        $messageClass = 'success';
                    } else {
                        $errorMsg = $response['error'] ?? 'Unknown API error.';
                        $message = "API Error: " . $errorMsg;
                        $messageClass = 'error';
                    }
                } else {
                    $message = "Error: Could not connect to local settings.php API.";
                    $messageClass = 'error';
                }
                // --- END API SAVE LOGIC ---

            } else {
                $message = "Error: Could not locate '{$blockKey}' block in settings.json.";
                $messageClass = 'error';
            }
        } else {
            $message = "Error: Unable to read {$settingsPath}.";
            $messageClass = 'error';
        }
    } else {
        $message = 'Error: Invalid category name or hex color format.';
        $messageClass = 'error';
    }
}

// Load Show Categories from schedule.json
$showCategories = [];
$showCategoriesSorted = [];
if (file_exists($schedulePath)) {
    $scheduleData = json_decode(file_get_contents($schedulePath), true);
    if (is_array($scheduleData)) {
        $showCategories = array_keys($scheduleData);
		$showCategoriesSorted = $showCategories;
		sort($showCategoriesSorted, SORT_NATURAL | SORT_FLAG_CASE);
    }
}

// Load defined colors from settings.json
$showColors = [];
$commColors = [];
if (file_exists($settingsPath)) {
    $settingsData = json_decode(file_get_contents($settingsPath), true);
    $showColors = $settingsData['web-ui']['show_type_colors'] ?? [];
    $commColors = $settingsData['web-ui']['commercial_type_colors'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Color Manager</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #121417; color: #e6edf3; padding: 2rem 1rem; }
        .container { max-width: 480px; margin: auto; background: #1a1e24; padding: 1.5rem; border-radius: 8px; border: 1px solid #2b313a; }
        h2 { font-size: 1.15rem; margin-bottom: 1.2rem; }
        
        .tab-bar { display: flex; gap: 8px; margin-bottom: 1.25rem; border-bottom: 1px solid #2b313a; padding-bottom: 10px; }
        .tab-btn { flex: 1; padding: 8px; background: transparent; border: 1px solid #2b313a; color: #8b949e; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; }
        .tab-btn.active { background: #2f81f7; border-color: #2f81f7; color: #fff; }

        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.45rem; font-size: 0.85rem; font-weight: 600; color: #8b949e; }
        select, input[type="text"], button[type="submit"] { width: 100%; padding: 0.65rem; border-radius: 6px; border: 1px solid #2b313a; background: #0d1117; color: #fff; font-size: 0.9rem; outline: none; }
        select:focus, input[type="text"]:focus { border-color: #2f81f7; }
        
        .color-row { display: flex; align-items: center; gap: 0.75rem; background: #0d1117; border: 1px solid #2b313a; padding: 6px 10px; border-radius: 6px; }
        input[type="color"] { width: 44px; height: 36px; border: none; cursor: pointer; background: transparent; }
        #hex-display { font-family: monospace; font-size: 0.95rem; font-weight: 600; }

        button[type="submit"] { background: #2f81f7; border: none; font-weight: 600; cursor: pointer; margin-top: 0.5rem; }
        button[type="submit"]:hover { background: #1f6feb; }

        .notice { padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem; }
        .notice.success { background: #1b4721; color: #85e89d; border: 1px solid #238636; }
        .notice.error { background: #3c1e20; color: #ffa1a1; border: 1px solid #da3633; }
    </style>
</head>
<body>
<div class="container">
    <h2>Schedule & Commercial Colors</h2>

    <?php if (!empty($message)): ?>
        <div class="notice <?= $messageClass ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="tab-bar">
        <button type="button" class="tab-btn active" id="tabShow" onclick="switchMode('show')">Show Categories</button>
        <button type="button" class="tab-btn" id="tabComm" onclick="switchMode('commercial')">Commercials</button>
    </div>

    <form method="POST" id="colorForm">
        <input type="hidden" name="mode" id="modeInput" value="show">
        <input type="hidden" name="category" id="categoryFinal">

        <!-- Show Category Selection -->
        <div class="form-group" id="groupShowCat">
            <label for="showCategorySelect">Show Category</label>
            <select id="showCategorySelect" onchange="syncCategorySelection(this.value)">
                <?php foreach ($showCategoriesSorted as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Commercial Category Selection / Entry -->
        <div id="groupCommCat" style="display:none;">
            <div class="form-group">
                <label for="commCategorySelect">Existing Commercial Categories</label>
                <select id="commCategorySelect" onchange="handleCommSelectChange(this.value)">
                    <?php foreach (array_keys($commColors) as $cCat): ?>
                        <option value="<?= htmlspecialchars($cCat) ?>"><?= htmlspecialchars($cCat) ?></option>
                    <?php endforeach; ?>
                    <option value="__NEW__">+ Enter New Folder / Category...</option>
                </select>
            </div>

            <div class="form-group" id="customCommBox" style="display:none;">
                <label for="customCommInput">Folder / Category Name</label>
                <input type="text" id="customCommInput" placeholder="e.g. 80s_toys, local_promos" oninput="syncCustomInput(this.value)">
            </div>
        </div>

        <div class="form-group">
            <label for="color">Hex Color</label>
            <div class="color-row">
                <input type="color" id="color" name="color" value="#000000">
                <span id="hex-display">#000000</span>
            </div>
        </div>

        <button type="submit">Save Color</button>
    </form>
</div>

<script>
    let currentMode = 'show';
    const showColors = <?= json_encode($showColors) ?>;
    const commColors = <?= json_encode($commColors) ?>;

    const colorInput = document.getElementById('color');
    const hexDisplay = document.getElementById('hex-display');
    const modeInput = document.getElementById('modeInput');
    const categoryFinal = document.getElementById('categoryFinal');

    const groupShowCat = document.getElementById('groupShowCat');
    const showSelect = document.getElementById('showCategorySelect');

    const groupCommCat = document.getElementById('groupCommCat');
    const commSelect = document.getElementById('commCategorySelect');
    const customCommBox = document.getElementById('customCommBox');
    const customCommInput = document.getElementById('customCommInput');

    function switchMode(mode) {
        currentMode = mode;
        modeInput.value = mode;

        document.getElementById('tabShow').classList.toggle('active', mode === 'show');
        document.getElementById('tabComm').classList.toggle('active', mode === 'commercial');

        if (mode === 'show') {
            groupShowCat.style.display = 'block';
            groupCommCat.style.display = 'none';
            if (showSelect.value) syncCategorySelection(showSelect.value);
        } else {
            groupShowCat.style.display = 'none';
            groupCommCat.style.display = 'block';
            handleCommSelectChange(commSelect.value);
        }
    }

    function setColor(hex) {
        if (!hex) hex = '#FFFFFF';
        if (!hex.startsWith('#')) hex = '#' + hex;
        colorInput.value = hex;
        hexDisplay.textContent = hex.toUpperCase();
    }

    function syncCategorySelection(cat) {
        categoryFinal.value = cat;
        const map = (currentMode === 'show') ? showColors : commColors;
        setColor(map[cat] || '#FFFFFF');
    }

    function handleCommSelectChange(val) {
        if (val === '__NEW__' || !commSelect.options.length) {
            customCommBox.style.display = 'block';
            customCommInput.focus();
            categoryFinal.value = customCommInput.value.trim();
            setColor('#FFFFFF');
        } else {
            customCommBox.style.display = 'none';
            syncCategorySelection(val);
        }
    }

    function syncCustomInput(val) {
        categoryFinal.value = val.trim();
    }

    colorInput.addEventListener('input', (e) => {
        hexDisplay.textContent = e.target.value.toUpperCase();
    });

    document.getElementById('colorForm').addEventListener('submit', (e) => {
        if (!categoryFinal.value.trim()) {
            e.preventDefault();
            alert('Please select or specify a category name.');
            if (currentMode === 'commercial' && customCommBox.style.display !== 'none') {
                customCommInput.focus();
            }
        }
    });

    // Initialize with existing show category or empty commercial state
    if (showSelect.options.length > 0) {
        syncCategorySelection(showSelect.value);
    } else if (commSelect.options.length > 1) {
        switchMode('commercial');
    } else {
        handleCommSelectChange('__NEW__');
    }
</script>
</body>
</html>