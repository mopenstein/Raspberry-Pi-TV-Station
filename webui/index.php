<?php


error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once("settings.class.inc");
require_once("db_manage_extx.inc");
require_once("functions.php");


$setup_state_file = __DIR__ . '/setup/state.json';

// If setup has never run, or is incomplete, redirect to the wizard
if (file_exists($setup_state_file)) {
    $state = json_decode(file_get_contents($setup_state_file), true);
    
    // Check if the wizard is still in progress (not marked complete)
    if (!isset($state['step']) || $state['step'] !== 'complete') {
        header('Location: /setup/');
        exit;
    }
} else {
    // No state file exists at all -> send to setup
    header('Location: /setup/');
    exit;
}

/**
 * Generates the HTML select dropdown for channels based on settings
 */
function generateChannelSelect($channels, $curr_channel_view) {
    if (empty($channels)) {
        return "default";
    }

    $html = '<select name="channel" onchange="window.location=\'/?channelView=\'+this.value;">';
    
    foreach ($channels as $c) {
        if (!is_string($c) && $c !== null) continue;

        // Normalize name for display/value
        $val = ($c === null || $c === "") ? "default" : $c;
        
        // Determine selection state
        $is_selected = ($c === $curr_channel_view || ($curr_channel_view === null && $val === "default"));
        $selected_attr = $is_selected ? ' selected' : '';
        
        $safe_val = htmlspecialchars($val);
        $html .= '<option value="' . $safe_val . '"' . $selected_attr . '>' . $safe_val . '</option>';
    }

    $html .= '</select>';
    return $html;
}

function getLocalTemperature() {
    $f = fopen("/sys/class/thermal/thermal_zone0/temp","r");
    $int_temp = fgets($f);
    fclose($f);
    return $int_temp;
}

function getUptime($mysqli) {
    $res = $mysqli->query("SELECT name FROM errors WHERE name LIKE '%UPTIME%' ORDER BY id DESC LIMIT 1");
    $row = $res->fetch_assoc();
    return ($row) ? gmdate("H:i:s", (int)explode("|", $row["name"])[1]) : "00:00:00";
}

function renderView($filePath, $View) {
    if (!file_exists($filePath)) return "Template not found: $filePath";
    ob_start();
    // This makes the $View array accessible inside your template
    include($filePath);
    return ob_get_clean();
}


function prepareShowRow($row) {
    global $parsedShows; 
    $showType = getShowType($row["short_name"], $parsedShows);

    // Extract length %T(...)%
    $a = strpos($row["name"], "%T(");
    $b = strpos($row["name"], ")%", $a+1);
	$secondsLength = ($a > -1 && $b > -1) ? (int)substr($row["name"], $a + 3, $b-$a - 3) : 0;
    $lengthValue = ($a > -1 && $b > -1) ? gmdate("H:i:s", (int)substr($row["name"], $a + 3, $b-$a - 3)) : "";

    $color = getShowTypeColor($showType);

    // This array acts as the "Contract" with your HTML template
    return [
        'id'    => $row["id"],
        'color' => $color,
        'timestamp'  => $row["played"], 			// Matches $s['timestamp']
        'name'  => $row["short_name"],             // Matches $s['name']
        'url'   => urlencode($row["name"]),       // Matches $s['url']
        'len'   => $lengthValue,                  // Matches $s['len'] - FIXES ERROR
		'slen'   => $secondsLength,                  // Raw seconds for potential use in JS
        'type'  => $showType,                     // Matches $s['type']
        'flag'   => $row["flag"]				  // Matches $s['flag'] for conditional button rendering
    ];
}

function fetchShows($mysqli, $date, $channel) {
    global $parsedShows;
    $start = strtotime($date . ' 00:00:00');
    $end   = strtotime($date . ' 23:59:59');
    $shows = [];
    
    $sql = "SELECT * FROM played WHERE played BETWEEN ? AND ? " . ($channel ? "AND channel = ?" : "AND channel IS NULL") . " ORDER BY id DESC";
    $stmt = $mysqli->prepare($sql);
    
    if ($channel) {
        $stmt->bind_param("iis", $start, $end, $channel);
    } else {
        $stmt->bind_param("ii", $start, $end);
    }
    
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $row['showType'] = getShowType($row["name"], $parsedShows);
        $row['showTypeColor'] = getShowTypeColor($row['showType']);
        $shows[] = prepareShowRow($row);
    }
    return $shows;
}

/**
 * Returns the hex color for a specific show type
 */
function getShowTypeColor($type) {
    global $json_settings;
    return $json_settings["web-ui"]["show_type_colors"][$type] ?? "ffffff";
}

/**
 * Inverts a hex color for text contrast
 */
function invertColor($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) !== 6) return '#000000';
    $new = '';
    for ($i = 0; $i < 3; $i++) {
        $rgbDigits = 255 - hexdec(substr($hex, (2 * $i), 2));
        $hexDigits = ($rgbDigits < 0) ? 0 : dechex($rgbDigits);
        $new .= (strlen($hexDigits) < 2) ? '0' . $hexDigits : $hexDigits;
    }
    return '#' . $new;
}

function getCommercialTypeColor($type) {
	global $json_settings;
	// Deep-traversal fallback using null coalescing
	return $json_settings["web-ui"]["commercial_type_colors"][$type] ?? "ffffff";
}


/**
 * Fetches and processes Commercials for the View
 */
function fetchCommercials($mysqli, $date, $channel, $drives) {
	global $json_settings;

    $content_start_time = strtotime($date . ' 00:00:00');
    $content_end_time   = strtotime($date . ' 23:59:59');
    $comms = [];

$comms_cnt = 0;
$bit = 1;
$curr_channel_view = $channel;

$res = $mysqli->query("SELECT * FROM commercials WHERE played>=" . strtotime($date . ' 00:00:00') . " AND played<=" . strtotime($date . ' 23:59:59') . " ORDER BY id DESC") or die($mysqli->error);
$comms_cnt = mysqli_num_rows($res);

if ($curr_channel_view === null) {
	$stmt = $mysqli->prepare("SELECT * FROM commercials WHERE played BETWEEN ? AND ? AND channel IS NULL ORDER BY id DESC");
	if ($stmt === false) {
    	die("Prepare failed: " . $mysqli->error);
	}
	$stmt->bind_param("ii", $content_start_time, $content_end_time);
} else {
    $stmt = $mysqli->prepare("SELECT * FROM commercials WHERE played BETWEEN ? AND ? AND channel = ? ORDER BY id DESC");
    $stmt->bind_param("iis", $content_start_time, $content_end_time, $curr_channel_view);
}

$stmt->execute();
$res = $stmt->get_result();

$comm_months = [];


while ($row = $res->fetch_assoc()) {
	//if(date("w", $row["played"])!=$td) break;
	$rname = $row["name"];
	foreach($drives as $d) {
		if(substr($rname, 0, strlen($d)) == $d) $rname = substr($rname, strlen($d));
	}
	
	$splits = explode('/', $rname);
	$comm_offset = $json_settings["web-ui"]["commercial_type_colors_offset"] ?? null; // default to null if not set
	$month_offset = 2;
	$comm_type=0;
	$comm_type_emoji = [ "1F4FA", "1F476", "1F37A", "1F46A"];
	$len = "";

	$a = strpos(basename($rname), "%T(");
	$b = strpos(basename($rname), ")%", $a+1);
	if($a>-1 && $b>-1) {
		$len = gmdate("H:i:s", (int)substr(basename($rname), $a + 3, $b-$a - 3));
	}

	if(strpos($rname, "%AM%")>0) $comm_type=1;
	if(strpos($rname, "%PM%")>0) $comm_type=2;
	if(strpos($rname, "%ANY%")>0) $comm_type=3;	

	if(!is_numeric($comm_offset)) { // check if user defined offset is invalid, if so, try to auto-detect
		if($splits[count($splits)-$month_offset]=="any" || $splits[count($splits)-$month_offset]=="am" || $splits[count($splits)-$month_offset]=="pm") {
			$month_offset = 3;
		}	

		if(array_key_exists($splits[count($splits)-$month_offset], $comm_months)==false) { 
			$comm_months[$splits[count($splits)-$month_offset]]=[1, 0, 0, 0];
			$comm_months[$splits[count($splits)-$month_offset]][$comm_type]=1;
		} else { 
			$comm_months[$splits[count($splits)-$month_offset]][0]++;
			if($comm_type) $comm_months[$splits[count($splits)-$month_offset]][$comm_type]++;
		}
		
		// Pre-calculate variables for cleaner mapping
		$currentType = $splits[count($splits) - $month_offset];
	} else {
		$currentType = $splits[count($splits) + $comm_offset];
	}

	$videoPath = stripslashes($rname);
	$videoUrl = urlencode($videoPath);

	$comms[] = [
		'id' 		   => $row["id"],
		'color'        => getCommercialTypeColor($currentType),
		'timestamp'    => $row["played"],		// Matches $s['timestamp']
		'folder'       => $splits[count($splits) - 2],
		'typeLabel'    => $currentType,
		'emoji'        => $comm_type_emoji[$comm_type] ?? '2753', // Default to '?' emoji if missing
		'videoUrl'     => $videoUrl,
		'filename'     => basename($videoPath),
		'length'       => $len,
		'flag' 		   => $row["flag"]
	];
}

    return $comms;
}

/**
 * Handles the repeat detection logic for the Messages tab
 */
function fetchGroupedMessages($mysqli) {
    $res = $mysqli->query("SELECT * FROM errors WHERE name NOT LIKE '%UPTIME%' ORDER BY id DESC");
    $messages = [];
    $lastMsg = null;

    while ($row = $res->fetch_assoc()) {
        $parts = explode("|", $row["name"]);
        $header = $parts[0] ?? 'Unknown Error';
        $details = array_slice($parts, 1);

        if ($header === $lastMsg) {
            // Increment repeat count on the last item
            $messages[count($messages) - 1]['repeats']++;
        } else {
            $messages[] = [
				'timestamp'    => $row["played"],
                'header'  => $header,
                'details' => $details,
                'repeats' => 1
            ];
            $lastMsg = $header;
        }
    }
    return $messages;
}

/**
 * General fetcher for Flagged items (Videos or Commercials)
 */
function fetchFlagged($mysqli, $table) {
    $items = [];
    $res = $mysqli->query("SELECT id, name FROM $table WHERE flag != 0 ORDER BY id DESC");
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'id'   => $row['id'],
            'name' => stripslashes($row['name'])
        ];
    }
    return $items;
}

interface ManageCard {
    public function name();
	public function links();
	public function html();
}

function prepareManageView($json_settings, $db_manage_ext) {
	global $mysqli;
	// Centralized link repository for the Manage tab

	//load manage cards
	$manage_cards = [];

	foreach (glob('manage/*.php') as $card_file) {
		include_once($card_file);
		//class name must match file name for this to work, and must implement ManageCard interface
		$class_name = pathinfo($card_file, PATHINFO_FILENAME);

		if (class_exists($class_name)) {
			if (!is_subclass_of($class_name, 'ManageCard')) {
				echo "<!-- Skipping $class_name because it does not implement ManageCard interface -->\n";
				continue;
			}
			$card_instance = new $class_name();

			if(!$card_instance->name()) {
				echo "<!-- Skipping Card: $class_name because name() is empty -->\n";
				continue;
			}

			if (method_exists($card_instance, 'setMysqli')) {
                $card_instance->setMysqli($mysqli);
            }

			if (method_exists($card_instance, 'setSettings')) {
                $card_instance->setSettings($json_settings);
            }

			$priority = 0;
			if (method_exists($card_instance, 'priority')) {
				$priority = (int)$card_instance->priority();
			}

			if ($card_instance instanceof ManageCard) {
				$manage_cards[$class_name] = [
					'name' => $card_instance->name(),
					'html' => $card_instance->html(),
					'links' => $card_instance->links(),
    				'priority' => $priority
				];
			}
		}
	}

	uasort($manage_cards, function($a, $b) {
		return $b['priority'] <=> $a['priority'];
	});

    // Centralized link repository
    return [
        'cards' 		  => $manage_cards
    ];
}

/**
 * Fetches and processes the Stats tab data
 */
function fetchStats($mysqli) {
    global $parsedShows;
    $stats = [];
    $num = 0;

    $res = $mysqli->query("SELECT id, name, short_name, COUNT(*) as value_occurrence FROM played GROUP BY short_name ORDER BY value_occurrence DESC");
    
    while ($row = $res->fetch_assoc()) {
        $num++;
        $showType = getShowType($row["short_name"], $parsedShows);
        $gavail = getGavailPathFromFile($row["name"]);

        // Pre-calculate URL and JS logic for the template
        $shortNameSafe = addslashes($row["short_name"]);
        $divId         = $row["short_name"] . $row["id"];

        $stats[] = [
			'id'          => $row["id"],
            'num'         => $num,
            'color'       => getShowTypeColor($showType),
            'showType'    => $showType,
            'availUrl'    => urlencode($gavail[0] ?? ''),
            'availDir'    => urlencode($gavail[1] ?? ''),
            'nextEpUrl'   => urlencode($row["name"]),
            'nextRndUrl'  => urlencode(dirname($row["name"])),
            'fullName'    => $row["name"],
            'shortName'   => $row["short_name"],
			'statsJs'	  => "?showstats=" . addslashes(urlencode($row["short_name"])),
            'occurrence'  => $row["value_occurrence"]
        ];
    }
    return $stats;
}

/**
 * Helper to replicate your original Gavail path logic
 */
function getGavailPathFromFile($filename) {
    $dir = dirname($filename);
    $base = basename($filename);
    return [$base, $dir];
}

// --- 1. SETTINGS & DB ---
$Settings = new Settings();
$json_response = $Settings->load("/home/pi/Desktop/settings.json");
if (($json_settings = $json_response[0]) === null) {
    die("JSON ERROR: " . htmlspecialchars($json_response[1]));
}

$db_manage_ext = new DBMANAGEEXT();

$db_info = $json_settings["web-ui"]["database_info"] ?? [];
$mysqli = new mysqli($db_info["host"], $db_info["username"], $db_info["password"], $db_info["database_name"]);

// --- 2. PRELOAD DATA (The $View Object) ---
$the_date = isset($_GET["date"]) ? $_GET["date"] : "today"; // Simplified for this pass
$View = [
    'title' => $json_settings["name"] ?? "Pi Station",
    'the_date' => $the_date,
    'sys' => [
        'uptime' => getUptime($mysqli),
        'temp_f' => (round(((getLocalTemperature()/1000) * (9/5))) + 32),
        'load'   => sys_getloadavg()[0] * 100,
        'disk'   => []
    ],
    'nav' => [
        'days_links' => '<a href="/?date=' . urlencode(date('m/d/y', strtotime($the_date) - 86400)) . '">&lt;&lt;&lt;</a> | <a href="/">' . date("h:i A \o\\n l m/d/Y") . '</a>',
        'channel_select' => generateChannelSelect($json_settings["channels"]["names"] ?? [], $_GET["channelView"] ?? null)
    ],
    'data' => [
        'shows' => [],
        'commercials' => [],
        'comm_stats' => [],
        'messages' => [],
        'flagged_videos' => [],
        'flagged_comms' => [],
        'stats_rows' => [],
        'stats_summary' => []
    ]
];

// Preload Disk Space
$View['sys']['disk'][] = 'Root SD ' . floor(disk_free_space("/.") / (1024**3)) . 'GB';
foreach (($json_settings["drive"] ?? []) as $drive) {
	if (is_dir($drive)) {
    	$View['sys']['disk'][] = floor(disk_free_space($drive) / (1024**3)) . "GB";
	} else {
		$View['sys']['disk'][] = "N/A";
	}
}

// --- 3. FETCH & PROCESS EVERYTHING ---
// (Logic calls to helper functions in functions.php)
$View['timestamp'] = time(); // current timestamp for cache-busting or display purposes
$View['data']['shows']       = fetchShows($mysqli, $the_date, $_GET["channelView"] ?? null);
$View['data']['commercials'] = fetchCommercials($mysqli, $the_date, $_GET["channelView"] ?? null, $json_settings["drive"] ?? []);

$View['data']['messages']    = fetchGroupedMessages($mysqli);
$View['data']['flagged_videos'] = fetchFlagged($mysqli, 'played');
$View['data']['flagged_comms']  = fetchFlagged($mysqli, 'commercials');
$View['data']['stats']     = fetchStats($mysqli);

$View['data']['manage'] = prepareManageView($json_settings, $db_manage_ext);
$View['data']['flagged_videos'] = fetchFlagged($mysqli, 'played');
$View['data']['flagged_comms']  = fetchFlagged($mysqli, 'commercials');

// --- 4. RENDER ---
$theme = $json_settings["web-ui"]["theme"] ?? "modern";
if(!file_exists("templates/{$theme}.php")) {
	die("Theme not found: " . htmlspecialchars($theme));
}
echo renderView("templates/{$theme}.php", $View);

?>