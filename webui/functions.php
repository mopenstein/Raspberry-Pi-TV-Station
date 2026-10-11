<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);


//######################### settings
require_once("settings.class.inc");

$Settings = new Settings();
$json_response = $Settings->load("/home/pi/Desktop/settings.json");
$settings_equalize = $json_settings["equalize playcount"] ?? false;

// 1. Validation Logic
if (($json_settings = $json_response[0]) === null) {
	$error_msg = htmlspecialchars($json_response[1] ?? 'Unknown Error');
	die("<div style='background:red;color:white;margin:10px;padding:10px;'>
		JSON VALIDATION ERROR :: CHECK 'settings.json' FILE! <br /><br />
		Error: $error_msg - <a href='https://duckduckgo.com/?q=json+validator'>Validate</a>
	</div>");
}

// 2. Data Extraction with Null Coalescing
$drive_loc     = $json_settings["drive"] ?? '';
$database_info = $json_settings["web-ui"]["database_info"] ?? [];
$settings_name = $json_settings["name"] ?? "";

// 3. Channel Mapping
$channels      = $json_settings["channels"]["names"] ?? [];
$channel_file  = $json_settings["channels"]["file"] ?? '';

//######################### /settings

require_once("db_manage_extx.inc");
$db_manage_ext = new DBMANAGEEXT();
$db_manage_ext->load($database_info["host"], $database_info["username"], $database_info["password"], $database_info["database_name"]);
$mysqli = new mysqli($database_info["host"], $database_info["username"], $database_info["password"], $database_info["database_name"]);

if(isset($_GET["work"])) {
	if(!isset($json_settings["workers"])) die("0|workers not defined in settings file");
	$worker_scripts = $json_settings["workers"];
	foreach($worker_scripts as $script) {
		if(file_exists($script)) {
			require_once($script);
		} else {
			die("0|worker script not found: $script");
		}
	}
	die("1|successfully ran workers");
}

if(isset($_GET["test_settings"])) {
	header("Location: /test_settings.php");
	exit();
}

function getCurrentChannel() {
	global $channel_file;
	if(!file_exists($channel_file)) return null;
	$cchannel = file_get_contents($channel_file);
	return $cchannel;
}

if(isset($_GET["backup"])) {
	$result = exec('sudo mysqldump --extended-insert=FALSE shows played | gzip > shows.sql.gz');
	while(!file_exists('shows.sql.gz')) {
		sleep(1);
	}
	//header('Content-type: text/plain');
	header("Cache-Control: public");
	header("Content-Description: File Transfer");
	header("Content-Disposition: attachment; filename=backup".date("Y-m-d.H.i") .".sql.gz");
	header("Content-Type: application/zip");
	header("Content-Transfer-Encoding: binary");
	readfile('shows.sql.gz');
	unlink('shows.sql.gz');
	die();
}

if(isset($_GET["get_commercials"]) && isset($_GET["showId"])) {
	$fullPath = realpath($_GET["get_commercials"] . ".commercials");
	// verify path is valid and within allowed drives
	if(!file_exists($fullPath) || !is_numeric($_GET["showId"])) {
		die("Commercials file not found.");	
	}
	$commercials = file_get_contents($fullPath);
	if($commercials === false) {
		die("Failed to read commercials file.");
	}
	$lines = explode("\n", $commercials);
	$ret = "";
	foreach($lines as $line) {
		if(is_numeric(trim($line))) {
			$hhmmss = gmdate("H:i:s", trim($line));
			$ret .= $hhmmss . "\n";
		}
	}
	die($ret . "|" . $_GET["showId"]);
}

if(isset($_GET["insert"]) && isset($_GET["file"]) && isset($_GET["times"])) {
	if(!file_exists(urldecode($_GET["file"]))) die("File does not exist. Confirm path and try again. ".$_GET["file"]);
	for($i=0;$i<urldecode($_GET["times"]) * 1;$i++) {
		$mysqli->real_query("INSERT INTO played (short_name, name, played) VALUES ('" . $mysqli->real_escape_string(urldecode($_GET["insert"])) . "', '" . $mysqli->real_escape_string(urldecode($_GET["file"])) . "', 0)");
		echo ($i+1) . ") INSERT INTO played (short_name, name, played) VALUES ('" . $mysqli->real_escape_string(urldecode($_GET["insert"])) . "', '" . $mysqli->real_escape_string(urldecode($_GET["file"])) . "', 0)<br />\n";
	}
	
	die(urldecode($_GET["insert"]) . " - " . urldecode($_GET["file"]) . " - " . urldecode($_GET["times"]));
}

if(isset($_GET["showstats"])) {
	if(isset($_GET["id"])) {
		if(!is_numeric($_GET["id"])) die("Invalid ID");
	}
	$res = $mysqli->query("SELECT * FROM played WHERE short_name=\"".addslashes($_GET["showstats"])."\"") or die($mysqli->error);
	$cnt = array();

	while ($brow = $res->fetch_assoc()) {
		$show = preg_replace(["/_NA_/", "/%T.(.*)\)%/"], ["", ""], pathinfo($brow["name"])["filename"]);
		echo date('M d, Y h:i A', $brow["played"]) . " @ $show\n";
	}
	
	die("|". $_GET["id"]);
}

if(isset($_GET["flag_video"]) && isset($_GET["id"])) {
	if(is_numeric($_GET["flag_video"])) {
		$res = $mysqli->query("UPDATE played SET flag=1 WHERE id=".addslashes($_GET["flag_video"])) or die($mysqli->error);
		die("flag|".$_GET["id"]."|". $_GET["flag_video"]);
	}
}

if(isset($_GET["unflag_video"]) && isset($_GET["id"])) {
	if(is_numeric($_GET["unflag_video"])) {
		$res = $mysqli->query("UPDATE played SET flag=0 WHERE id=".addslashes($_GET["unflag_video"])) or die($mysqli->error);
		die("unflag|".$_GET["id"]."|". $_GET["unflag_video"]);
	}
}

if(isset($_GET["flag_comm"]) && isset($_GET["id"])) {
	if(is_numeric($_GET["flag_comm"])) {
		$res = $mysqli->query("UPDATE commercials SET flag=1 WHERE id=".addslashes($_GET["flag_comm"])) or die($mysqli->error);
		die("flagc|".$_GET["id"]."|". $_GET["flag_comm"]);
	}
}

if(isset($_GET["unflag_comm"]) && isset($_GET["id"])) {
	if(is_numeric($_GET["unflag_comm"])) {
		$res = $mysqli->query("UPDATE commercials SET flag=0 WHERE id=".addslashes($_GET["unflag_comm"])) or die($mysqli->error);
		die("unflagc|".$_GET["id"]."|". $_GET["unflag_comm"]);
	}
}

function isDriveAllowed(string $path, array $drives): bool {
	foreach ($drives as $drive) {
		$resolvedDrive = realpath($drive);
		if ($resolvedDrive && strpos($path, $resolvedDrive) === 0) {
			return true;
		}
	}
	return false;
}

if (isset($_GET['get_live_status'])) {
    header('Content-Type: application/json');
    
    $status_file = '/dev/shm/tv_status.json';
    
    if (file_exists($status_file)) {
        die(file_get_contents($status_file));
    } else {
        die(json_encode([
            "state" => "offline",
            "file" => "",
            "position" => 0
        ]));
    }
}

if(isset($_GET['get_last_played'])) {
	$result = $mysqli->query("SELECT * FROM played ORDER BY id DESC LIMIT 1") or die($mysqli->error);
	$last_played = null;
	if ($result->num_rows > 0) {
		$last_played = $result->fetch_assoc();
	}
	
	if (!$last_played) {
		die(json_encode([
			"name" => "ERROR: NOT FOUND",
			"short_name" => "ERROR",
			"played" => -1,
			"id" => -1,
			"len" => -1
		]));
	}

		// make this a json string instead of pipe delimited for better parsing on the client side
		die(json_encode([
			"name" => rawurlencode($last_played["name"]),
			"short_name" => rawurlencode($last_played["short_name"]),
			"played" => (int)$last_played["played"],
			"id" => (int)$last_played["id"],
			"len" => (int)substr($last_played["name"], strpos($last_played["name"], "%T(") + 3, strpos($last_played["name"], ")%") - strpos($last_played["name"], "%T(") - 3)
		]));

}

if(isset($_GET['get_last_comm_played'])) {
    $result = $mysqli->query("SELECT * FROM commercials ORDER BY id DESC LIMIT 1") or die($mysqli->error);
    $last_played = null;
    if ($result->num_rows > 0) {
        $last_played = $result->fetch_assoc();
    }
    
    if (!$last_played) {
        die(json_encode([
            "name" => "ERROR: NOT FOUND",
            "short_name" => "ERROR",
            "played" => -1,
            "id" => -1,
            "len" => -1
        ]));
    }

    $response = [
        "name" => rawurlencode($last_played["name"]),
        "short_name" => null,
        "played" => (int)$last_played["played"],
        "id" => (int)$last_played["id"],
        "len" => (int)substr($last_played["name"], strpos($last_played["name"], "%T(") + 3, strpos($last_played["name"], ")%") - strpos($last_played["name"], "%T(") - 3)
    ];

    // Optional 'since' parameter: appends every commercial aired after the supplied unixtime
    if (isset($_GET['since']) && is_numeric($_GET['since'])) {
        $since = (int)$_GET['since'];
        $history_result = $mysqli->query("SELECT id, name, played FROM commercials WHERE played > {$since} ORDER BY played ASC") or die($mysqli->error);

        $history = [];
        while ($row = $history_result->fetch_assoc()) {
            $history[] = [
                "id" => (int)$row["id"],
                "name" => rawurlencode($row["name"]),
                "played" => (int)$row["played"],
                "len" => (int)substr($row["name"], strpos($row["name"], "%T(") + 3, strpos($row["name"], ")%") - strpos($row["name"], "%T(") - 3)
            ];
        }
        $response["commercials"] = $history;
    }

    die(json_encode($response));
}

if (isset($_GET['rename_video'], $_GET['to'])) {
	$oldInput = urldecode($_GET['rename_video']);
	$newInput = urldecode($_GET['to']);

	$oldFile = realpath($oldInput);
	$newDir  = realpath(dirname($newInput));
	$newFile = $newDir ? $newDir . DIRECTORY_SEPARATOR . basename($newInput) : false;

	if (!$oldFile || !file_exists($oldFile)) {
		exit("Error: Source file does not exist.\n\n" . htmlspecialchars($oldInput));
	}

	if (!$newFile) {
		exit("Error: Destination path is invalid.\n\n" . htmlspecialchars($newInput));
	}

	if (file_exists($newFile)) {
		exit("Error: Destination file already exists. Choose a different name.");
	}

	if (!isDriveAllowed($oldFile, $json_settings['drive'])) {
		exit("Error: Source file is outside of allowed drive(s).");
	}

	if (!isDriveAllowed($newFile, $json_settings['drive'])) {
		exit("Error: Destination path is outside of allowed drive(s).");
	}

	if (!rename($oldFile, $newFile)) {
		exit("Error: Failed to rename file. Check permissions and paths.");
	}

	echo "Renamed:\n\n" . htmlspecialchars($oldInput) . "\n\nto\n\n" . htmlspecialchars($newInput) . "<br />\n";
	exit;
}

if(isset($_GET["channel"]) && count($_GET)==1) {
	if(!isset($channel_file)) die("channel file not defined in settings file");
	
	foreach($channels as $c) {
		if($c==null || $c=="") $c = "default";
		if($_GET["channel"]==$c) {
			if($c=="default") $c="";
			file_put_contents($channel_file, $c);
			$result = exec('sudo ./kill.sh');
			die("Channel set to: ".$c);
			break;
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reboot') {
    // 1. Send clean HTTP headers and clear buffer
    ignore_user_abort(true);
    set_time_limit(0);

    // 2. Render holding UI to the browser
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Rebooting...</title>
        <style>
            body { font-family: monospace; background: #111; color: #0f0; padding: 40px; text-align: center; }
            .box { border: 1px solid #333; display: inline-block; padding: 20px 40px; border-radius: 6px; }
        </style>
        <script>
            // Poll the root page every 5 seconds until the Pi comes back up, then reload cleanly
            function checkServer() {
                fetch('/', { method: 'HEAD', cache: 'no-store' })
                    .then(response => {
                        if (response.ok) window.location.href = '/';
                        else setTimeout(checkServer, 5000);
                    })
                    .catch(() => setTimeout(checkServer, 5000));
            }
            // Start polling after 20 seconds (give it time to actually shut down)
            setTimeout(checkServer, 20000);
        </script>
    </head>
    <body>
        <div class="box">
            <h2>Rebooting System</h2>
            <p>Station reboot initiated. This page will automatically reload once the Pi is back online.</p>
        </div>
    </body>
    </html>
    <?php

    // 3. Force output to browser and close connection
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();

    // FastCGI / Apache hook to close client socket immediately
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    // 4. Trigger reboot detached in the background
    exec('sleep 1 && sudo /sbin/reboot >/dev/null 2>&1 &');
    exit;
}

if(isset($_GET["rebootz"])) {
	header("Location: /\n\n");
	exec('sudo reboot');
}

if(isset($_GET["start"])) {
	header("Location: /\n\n");
	exec('python /home/pi/Desktop/_rnd80s.py');
}


if(isset($_GET["skip"])) {
	
	$last_played = "_not_found_";
	$result = $mysqli->query("SELECT * FROM played ORDER BY id DESC") or die($mysqli->error);

	if ($result->num_rows > 0) {
		$last_played = $result->fetch_assoc()["name"];
	}
	
	$mysqli->real_query("INSERT INTO errors (name, played) VALUES ('SKIPPED|File currently playing was skipped|".addslashes($last_played)."', ". time() . ")");

	$result = exec('sudo ./kill.sh');
	header("Location: /?skipped=yes\n\n");
}

if(isset($_GET["restart"])) {
	//header("Location: /\n\n");
	//$result = exec('sudo ./home/pi/Desktop/restart.sh > /dev/null 2>&1 &');
	echo 'sudo /home/pi/Desktop/brestart.sh';
	$result = shell_exec('sudo /home/pi/Desktop/brestart.sh');
	die($result);
}

if(isset($_GET["video"])) {
	if(file_exists($_GET["video"])) {
		include("videostream.inc");
		header('Content-type: video/mp4');
		$stream = new VideoStream($_GET["video"]);
		$stream->start();
		die();
	} else {
		die("file not found");
	}
}

if(isset($_GET["delete"])) {
	if(file_exists($_GET["delete"])) {
		unlink($_GET["delete"]);
		die("video deleted");
	} else {
		die("file not found");
	}
}

if(isset($_GET["db_manage_ext"])) {
	$dbexe = $db_manage_ext->handle($_GET);
	if($dbexe) {
		die($dbexe);
	} else {
		header("location: /\n\n");
		die();
	}
}

function checkShowPlayAmount($sname, array $showList) {
	global $mysqli;
	if(isset($_GET["test"])) echo "$sname\n";

	$res = $mysqli->query("SELECT *, COUNT(`short_name`) AS `value_occurrence` FROM `played` GROUP BY `short_name` ORDER BY `value_occurrence` DESC") or die($mysqli->error);
	$shows = [];
	while ($brow = $res->fetch_assoc()) {
		$shows[$brow["short_name"]] = $brow["value_occurrence"]*1;
		if(isset($_GET["test"])) echo $brow["short_name"] ." = ". $brow["value_occurrence"] ."\n";
	}

	$narr = [];
	$selected = null;

	foreach($showList as $catKey => $items) {
		$narr[$catKey] = [];
		foreach($items as $entry) {
			$conv_short_name = $entry;
			if(strpos("S".$conv_short_name, "=")>0) {
				$conv_short_name = substr($conv_short_name, strpos($conv_short_name, "=") + 1);
			}

			$cnt = isset($shows[$conv_short_name]) ? $shows[$conv_short_name] : 0;
			$narr[$catKey][] = [$conv_short_name, $cnt];

			if($conv_short_name == $sname) {
				$selected = $catKey;
			}
		}
	}

	if(isset($_GET["test"])) echo "///////////////////////////////narr\n";
	if(isset($_GET["test"])) var_dump($narr);
	$highest = -1;
	$lowest = 999999;
	$lowshow = "";
	$list = [];
	if($selected!=null && isset($narr[$selected])) {
		for($i=0;$i<count($narr[$selected]);$i++) {
			if($narr[$selected][$i][1] >= $highest) {
				$highest = $narr[$selected][$i][1];
				array_unshift($list, $narr[$selected][$i]);
			} else {
				array_push($list, $narr[$selected][$i]);
			}
			
			if($narr[$selected][$i][1] < $lowest) {
				$lowest = $narr[$selected][$i][1];
				$lowshow = $narr[$selected][$i][0];
			}
		}
	}

	if(isset($_GET["test"])) echo "///////////////////////////////list\n";
	if(isset($_GET["test"])) var_dump($list);
	if(isset($_GET["test"])) echo $lowest."\n";
	if(isset($_GET["test"])) echo $highest."\n";

	$exit=true;

	for($i=0;$i<count($list);$i++) {
		if($list[$i][1] != $highest) { 
			$exit=false;
			break;
		}
	}
	
	if($exit) return true;

	for($i=0;$i<count($list);$i++) {
		if($list[$i][1] != $lowest) { 
			if($sname==$list[$i][0]) {
				return false;
			}
		}
	}

	return true;
}

function getTvShowName($filename, $showList) {
	if ($showList == null || count($showList) <= 0) return basename($filename);

	if (strrpos($filename, "%T(") !== false) {
		$filename = substr($filename, 0, strrpos($filename, "%T("));
	}
	$filename = basename($filename);

	$cleanFilename = strtolower(preg_replace("~[_\W\s]~", '', $filename));

	$bestMatch = '';
	$bestLength = 0;

	foreach ($showList as $entries) {
		foreach ($entries as $entry) {
			$parts = explode("=", $entry, 2);
			$rawName = $parts[0];
			$normalized = strtolower(preg_replace("~[_\W\s]~", '', $rawName));

			if (!$normalized) continue;

			if (strpos($cleanFilename, $normalized) !== false) {
				if (strlen($normalized) > $bestLength) {
					$bestMatch = (count($parts) == 1 ? $entry : $parts[1]);
					$bestLength = strlen($normalized);
				}
			}
		}
	}

	return $bestMatch ?: $filename;
}

function parseCSVToArray($csv, $verbose = null) {
	$lines = preg_split('/\r\n|\r|\n/', trim($csv));
	if (empty($lines)) return [];

	$rows = array_map('str_getcsv', $lines);
	$categoryData = [];
	$headers = $rows[0];

	// Track shows globally during parse to catch duplicates across categories
	$seenShows = [];

	for ($i = 0; $i < count($headers); $i++) {
		$catName = trim($headers[$i]);
		if ($catName !== '') {
			$categoryData[$catName] = [];
			if ($verbose !== null) echo "<div style='color:#555;margin-top:10px;'>Category: <b>{$catName}</b></div>\n";
		}
	}

	$headerKeys = array_keys($categoryData);
	for ($i = 1; $i < count($rows); $i++) {
		for ($j = 0; $j < count($rows[$i]); $j++) {
			if (!isset($headerKeys[$j])) continue;
			
			$val = trim($rows[$i][$j]);
			$stripped = preg_replace('/[^\da-z]/i', '', $val);

			if ($stripped !== '') {
				$categoryName = $headerKeys[$j];
				$categoryData[$categoryName][] = $val;

				if ($verbose !== null) {
					// 1. Check for Alias syntax (Alias=Real Name)
					$aliasNotice = "";
					$checkTarget = $val;
					if (strpos($val, "=") !== false) {
						$parts = explode("=", $val, 2);
						$aliasNotice = " <span style='color:#0066cc;'>(Alias Target: <b>'{$parts[0]}'</b> &rarr; Display: <b>'{$parts[1]}'</b>)</span>";
						$checkTarget = $parts[0];
					}

					// 2. Check for Short Strings (High collision risk)
					$shortWarning = "";
					$cleanTarget = preg_replace('/[^\da-z]/i', '', $checkTarget);
					if (strlen($cleanTarget) <= 3) {
						$shortWarning = " <span style='background:#fff3cd;color:#856404;padding:2px 5px;border-radius:3px;font-weight:bold;'>&Delta; SHORT NAME ({$cleanTarget}): High collision risk!</span>";
					}

					// 3. Check for Global Duplicates across categories
					$dupWarning = "";
					$cleanLower = strtolower($cleanTarget);
					if (isset($seenShows[$cleanLower])) {
						$prevCat = $seenShows[$cleanLower];
						$dupWarning = " <span style='background:#f8d7da;color:#721c24;padding:2px 5px;border-radius:3px;font-weight:bold;'>&times; DUPLICATE: Also in '{$prevCat}'!</span>";
					} else {
						$seenShows[$cleanLower] = $categoryName;
					}

					echo "<div>&bull; Added <b>'{$val}'</b> to <i>{$categoryName}</i> <small style='color:#777;'>[match: '{$stripped}']</small>{$aliasNotice}{$shortWarning}{$dupWarning}</div>\n";
				}
			} else {
				if ($val !== '') {
					echo "<div style='background:#f8d7da;color:#721c24;padding:6px;margin:4px 0;font-weight:bold;'>Check your spreadsheet. Category '{$headerKeys[$j]}' contains an all-whitespace entry!</div>\n";
				}
			}
		}
	}

	return $categoryData;
}

function getShowType($sname, $showList) {
	if($showList==null) return "none";
	foreach($showList as $k=>$v) {
		for($i=0;$i<count($v);$i++) {
			$names = explode("=",$v[$i],2);
			if(count($names)>1) {
				if($sname==$names[1]) return $k;
			}
			if($sname==$names[0]) return $k;
		}
	}
	
	return "none";
}

function getShowNamesz($url, $force = false, $chan = null, $verbose = null) {
	if ($chan === null) {
		$chan = "";
	} else {
		$chan = "." . $chan;
	}

	$jsonFile = "schedule" . $chan . ".json";

	if (!$force && file_exists($jsonFile)) {
		$cachedData = json_decode(file_get_contents($jsonFile), true);
		if (is_array($cachedData)) {
			return $cachedData;
		}
	}

	$csv = @file_get_contents($url);

	if ($csv !== false && strlen(trim($csv)) > 0) {
		$parsed = parseCSVToArray($csv, $verbose);
		file_put_contents($jsonFile, json_encode($parsed, JSON_PRETTY_PRINT));
		return $parsed;
	}

	if (file_exists($jsonFile)) {
		$cachedData = json_decode(file_get_contents($jsonFile), true);
		return is_array($cachedData) ? $cachedData : [];
	}

	return [];
}

function getShowNames($url, $force = false, $chan = null, $verbose = null) {
	if ($chan === null) {
		$chan = "";
	} else {
		$chan = "." . $chan;
	}

	$jsonFile = "schedule" . $chan . ".json";

	if (file_exists($jsonFile)) {
		$cachedData = json_decode(file_get_contents($jsonFile), true);
		return is_array($cachedData) ? $cachedData : [];
	}

	return [];
}

if(isset($_GET["clear_cache"])) {
	//$sched_link = $json_settings["web-ui"]["tv_schedule_link"] ?? null;
	//if(!$sched_link) die("no schedule link defined");
	//$fetchUrl = rtrim($sched_link, "/") . '/export?format=csv';
	
	echo "<h2>Refreshing Schedule Cache...</h2>";
	$wasted = getShowNames("", true, null, true);
	echo "<br /><b>Done. Loaded " . count($wasted) . " categories into JSON cache.</b>";
	var_dump($wasted);	
	die();
}

$parsedShows = null;
//$sched_link = $json_settings["web-ui"]["tv_schedule_link"] ?? null;
//if($sched_link) {
//	$fetchUrl = rtrim($sched_link, "/") . '/export?format=csv';
$parsedShows = getShowNames("", false);
//}

function getPathFromShortName(string $shortname) {
	global $mysqli;
	$res = $mysqli->query("SELECT name FROM `played` WHERE short_name='".addslashes($shortname)."' LIMIT 1") or die($mysqli->error);
	$brow = $res->fetch_assoc();
	if(!$brow) return null;
	return dirname($brow["name"]);
}

function cleanupAndResetCategory(string $sname, array $showList, int $limit): bool
{
	global $mysqli;

	if (!isset($showList[$sname]) || empty($showList[$sname])) {
		return false;
	}

	$targetShows = [];
	foreach ($showList[$sname] as $shortName) {
		$targetShows[] = preg_replace('/^[^=]+=/', '', $shortName);
	}

	if (empty($targetShows)) return false;

	$placeholders = implode(',', array_fill(0, count($targetShows), '?'));
	
	$sql_count = "
		SELECT 
			short_name, 
			COUNT(id) AS play_count
		FROM played
		WHERE short_name IN ($placeholders)
		GROUP BY short_name
	";

	$stmt_count = $mysqli->prepare($sql_count);
	$types = str_repeat('s', count($targetShows));
	$stmt_count->bind_param($types, ...$targetShows);
	$stmt_count->execute();
	$result_count = $stmt_count->get_result();

	$showsAtCapCount = 0;
	$totalTargetShows = count($targetShows);

	while ($row = $result_count->fetch_assoc()) {
		if ($row['play_count'] >= $limit) {
			$showsAtCapCount++;
		} else {
			$stmt_count->close();
			return false;
		}
	}
	$stmt_count->close();

	if ($showsAtCapCount !== $totalTargetShows) {
		return false;
	}

	$resetOccurred = false;

	foreach ($targetShows as $show_name) {
		$sql_delete = "
			DELETE FROM played 
			WHERE id = (
				SELECT id 
				FROM (
					SELECT id 
					FROM played 
					WHERE short_name = ? 
					ORDER BY played ASC 
					LIMIT 1
				) AS oldest_entry
			)
		";

		$stmt_delete = $mysqli->prepare($sql_delete);
		$stmt_delete->bind_param("s", $show_name);
		
		if ($stmt_delete->execute() && $mysqli->affected_rows > 0) {
			$resetOccurred = true;
		}
		$stmt_delete->close();
	}
	
	return $resetOccurred;
}

function getAvailableShows($sname, $sdir, array $showList) {
	global $mysqli;
	
	$dirs_in_dir = glob($sdir . '/*' , GLOB_ONLYDIR);
	if(isset($_GET["test"])) var_dump($dirs_in_dir);
	if(isset($_GET["test"])) echo "$sname\n";

	$res = $mysqli->query("SELECT *, COUNT(`short_name`) AS `value_occurrence` FROM `played` GROUP BY `short_name` ORDER BY `value_occurrence` DESC") or die($mysqli->error);
	$shows = [];
	while ($brow = $res->fetch_assoc()) {
		$shows[$brow["short_name"]] = $brow["value_occurrence"]*1;
		if(isset($_GET["test"])) var_dump($brow);
	}

	$narr = [];
	foreach($showList as $catKey => $items) {
		$narr[$catKey] = [];
		foreach($items as $entry) {
			$conv_short_name = $entry;
			if(strpos("S".$conv_short_name, "=")>0) {
				$conv_short_name = substr($conv_short_name, strpos($conv_short_name, "=") + 1);
			}

			$cnt = isset($shows[$conv_short_name]) ? $shows[$conv_short_name] : 0;
			$narr[$catKey][] = [$conv_short_name, $cnt];
		}
	}
	
	$selected = $sname;
	
	if(isset($_GET["testx"])) echo "///////////////////////////////narr\n";
	if(isset($_GET["testx"])) var_dump($narr);
	if(isset($_GET["testx"])) echo "///////////////////////////////selected\n";
	if(isset($_GET["testx"])) var_dump($selected);
	$highest = -1;
	$lowest = 999999;
	$lowshow = "";
	$list = [];
	if($selected!=null) {
		if(!in_array($selected, array_keys($narr))) die('0');

		for($i=0;$i<count($narr[$selected]);$i++) {
			if($narr[$selected][$i][1] >= $highest) {
				$highest = $narr[$selected][$i][1];
				array_unshift($list, $narr[$selected][$i]);
			} else {
				array_push($list, $narr[$selected][$i]);
			}
			
			if($narr[$selected][$i][1] < $lowest) {
				$lowest = $narr[$selected][$i][1];
				$lowshow = $narr[$selected][$i][0];
			}
		}
	}

	for($i=0;$i<count($list);$i++) {
		if(!isset($list[$i][2])) {
			$dirshort = getPathFromShortName($list[$i][0]);
			if($dirshort) {
				$list[$i][2] = $dirshort;
			} else {
				$list[$i][2] = "";
			}
		}
	}

	foreach($dirs_in_dir as $d) {
		$d = str_replace("//", "/", $d);
		
		$skip = true;
		for($i=0;$i<count($list);$i++) {
			if($d == $list[$i][2]) {
				$skip = true;
				break;
			} else {
				$skip = false;
			}
		}
		
		if(isset($_GET["test"])) echo "$d & " . ($skip ? "true" : "false") . "\n";
		
		if(!$skip) {
			array_push($list, [-1, "test", $d]);
			$lowest=0;
		}
	}

	if(isset($_GET["test"])) echo "///////////////////////////////list\n";
	if(isset($_GET["test"])) var_dump($list);
	if(isset($_GET["test"])) echo $lowest."\n";
	if(isset($_GET["test"])) echo $highest."\n";

	$exit=true;
	$retlist = [];

	for($i=0;$i<count($list);$i++) {
		if(!file_exists($list[$i][2])) {
			if(isset($_GET["test"])) {
				echo "@@@@@@@@@ Directory does not exist for " . $list[$i][0] . " !!!!!!!!!!!!!!!!!!!!!!!\n";
				addMessageToDatabase("SERVER|GET_AVAILABLE|Directory does not exist: " . $list[$i][2] . "|Short Name: " . $list[$i][0] . "|!- IT WILL BE IGNORED BUT YOU SHOULD CHECK YOUR SCHEDULE. MAKE SURE THE DIRECTORY EXISTS AND PLAYABLE FILES ARE THERE. -!");
				echo "Highest: " . $highest . " - Directory: " . $list[$i][2] . " - Short Name: " . $list[$i][0] . " - Play Count: " . $list[$i][1] . "\n";
			}
			continue;
		}
		array_push($retlist, $list[$i][2]);
		if($list[$i][1] != $highest && $highest!=$lowest) { 
			$exit = false;
		}
	}

	if($exit) {
		return array_filter($retlist);
	}

	$retlist = [];
	if(isset($_GET["test"])) {
		echo "///////////////////////////////list\n";
	}
	$recheck = false;
	for($i=0;$i<count($list);$i++) {
		if(!file_exists($list[$i][2])) {
			if(isset($_GET["test"])) {
				echo "!!!!!!!!!!!!!!!! Directory does not exist for " . $list[$i][0] . " !!!!!!!!!!!!!!!!!!!!!!!\n";
				addMessageToDatabase("SERVER|GET_AVAILABLE|Directory does not exist: " . $list[$i][2] . "|Short Name: " . $list[$i][0] . "|ALERT! IT WILL BE IGNORED BUT YOU SHOULD CHECK YOUR SCHEDULE, MAKE SURE THE DIRECTORY EXISTS, AND PLAYABLE FILES ARE THERE.");
				echo "Highest: " . $highest . " - Directory: " . $list[$i][2] . " - Short Name: " . $list[$i][0] . " - Play Count: " . $list[$i][1] . "\n";
			}
			$recheck = true;
			continue;
		}

		if(isset($_GET["test"])) {
			echo "Highest: " . $highest . " - Directory: " . $list[$i][2] . " - Short Name: " . $list[$i][0] . " - Play Count: " . $list[$i][1] . "\n";
		}
		if($list[$i][1] != $highest && $list[$i][1]!=-1) { 
			array_push($retlist, $list[$i][2]);
		}
	}

	if(isset($_GET["test"])) {
		echo "///////////////////////////////retlist\n";
		var_dump($retlist);
	}
	return array_filter($retlist);
}

if(isset($_GET["getavailable"]) && isset($_GET["dir"])) {
	$dir = urldecode($_GET["dir"]);
	$getavailable = urldecode($_GET["getavailable"]);
	if(!file_exists($getavailable)) {
		addMessageToDatabase("SERVER|GET_AVAILABLE|File does not exist: " . $getavailable);
		if(isset($_GET["h"])) { 
			echo "File does not exist: " . $getavailable;
		}
	}
	if(!is_dir($dir)) {
		if(isset($_GET["h"])) echo "Directory does not exist: " . $dir;
		die('0');
	}

	if(isset($_GET["h"])) echo "Directory exists: " . $dir;

	$shortname=getTvShowName($getavailable, $parsedShows);
	$showType=getShowType($shortname, $parsedShows);
	
	if(isset($_GET["h"])) {
		echo "Human readable output:\n";
		function highlightDiff($string1, $string2) {
			for($i=0;$i<strlen($string1);$i++) {
				if(substr($string1,$i,1) != substr($string2,$i,1)) break;
			}
			return "<b>" . substr($string2,0,$i) . "</b><i>" . substr($string2,$i) . "</i>";
		}
		
		$avh = getAvailableShows($showType, $dir, $parsedShows);
		echo "<h1>$shortname $showType</h1>\n";
		$lshow = "";
		foreach($avh as $show) {
			if($lshow=="") {
				echo "<i>$show</i><br />\n";
			} else {
				echo highlightDiff($lshow,$show) . "<br />\n";
			}
			$lshow=$show;
		}
		die('0');
	} else {
		die(implode("\n", (getAvailableShows($showType, $dir, $parsedShows))));
	}
}

if(isset($_GET["getshowname"])) {

	if(strpos(strtolower($_GET["getshowname"]), '/commercials/') > 0) {
		die("commercial|0");
	}

	$shortname=getTvShowName($_GET["getshowname"], $parsedShows);
	if(isset($_GET["short"])) die($shortname);
	
	$showType=getShowType($shortname, $parsedShows);
	
	$row=0;
	$remote_diff_set = false;

	$time_diff = 0;
	$row = 0;

	if(isset($_GET["min_time"])) {
		if(is_numeric($_GET["min_time"])) {
			if($_GET["min_time"]*1!=0) {
				$time_diff = $_GET["min_time"]*1;
				$remote_diff_set = true;
			}
		}
	}
		
	$res = $mysqli->query("SELECT played FROM played WHERE short_name='" . addslashes($shortname) . "' AND played>=" . (time()-$time_diff) ." AND played<=" . (time()-1) . "  LIMIT 1") or die($mysqli->error);
	if(isset($_GET["test"])) echo "SELECT played FROM played WHERE short_name='" . addslashes($shortname) . "' AND played>=" . (time()-$time_diff) ." AND played<=" . (time()-1) . "  LIMIT 1\n\n";
	$row = $res->fetch_row()[0]*1;
	if(isset($_GET["test"])) var_dump($row);
	
	if($remote_diff_set && $row>0) {
		die("$shortname|$row|$showType|remote diff triggered|played within the last $time_diff seconds");
	}
	
	if(checkShowPlayAmount($shortname, $parsedShows)) {
		if(isset($_GET["test"])) echo "not played too much\n";
		die("$shortname|0|$showType|has not been played too much|$row|$remote_diff_set");
	} else {
		if(isset($_GET["test"])) echo "has not been played too much\n";
		die("$shortname|".time()."|$showType|has been played enough|$row|$remote_diff_set");
	}		
	
	die("$shortname|$row|$showType");
}

if(isset($_GET["get_next_episode"])) {

	function getVideoFiles($dir, $exts = ['mp4', 'avi', 'webm', 'mpeg', 'm4v', 'mkv', 'mov', 'flv', 'wmv']) {
		$dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		$all_cases = [];
		foreach ($exts as $e) {
			$all_cases[] = strtolower($e);
			$all_cases[] = strtoupper($e);
		}
		
		$pattern = $dir . '*.' . '{' . implode(',', array_unique($all_cases)) . '}';
		$files = glob($pattern, GLOB_BRACE);

		return $files ?: [];
	}

	$nv = urldecode($_GET["get_next_episode"] ?? '');
	$clean_nv = rtrim($nv, "/");

	$dir_name = is_dir($clean_nv) ? $clean_nv : dirname($clean_nv);

	if (!is_dir($dir_name)) {
		die("|0|directory does not exist|$dir_name|$nv|1");
	}

	$escaped_dir = $mysqli->real_escape_string($dir_name);
	$sql = "SELECT * FROM played WHERE name LIKE '$escaped_dir%' ORDER BY played DESC LIMIT 1";
	$res = $mysqli->query($sql) or die($mysqli->error);

	if (isset($_GET["dump"]) || isset($_GET["h"])) {
		$shortname = getTvShowName($nv, $parsedShows);
		if (isset($_GET["dump"])) {
			echo "Looking up episodes for: <i>$dir_name</i> <b>$shortname</b> SQL: $sql<br />";
		}
	}

	$filter = ['mp4', 'mkv', 'avi', 'mpeg', 'mpg', 'mov', 'webm', 'm4v', 'flv', 'wmv'];
	if (!empty($_GET["filter"])) {
		$filter = explode(",", $_GET["filter"]);
	}

	$files = getVideoFiles($dir_name . '/', $filter);

	if (empty($files)) {
		die("|0|no files exist matching " . implode(",", $filter) . "|$dir_name|$nv|3");
	}

	if (mysqli_num_rows($res) == 0) {
		$should_random = $json_settings["random video on first play"] ?? false;

		if (isset($_GET['random'])) {
			$should_random = ($_GET['random'] === '1');
		}

		if ($should_random && !empty($files)) {
			$rand_index = array_rand($files);
			die($files[$rand_index] . "|1|play random episode per override/settings");
		}

		if (!empty($files[0])) {
			die($files[0] . "|1|play first episode");
		}

		die("error|0|No episodes found");
	}

	$row = $res->fetch_row();
	$last_played = $row[2] ?? '';

	if (!file_exists($last_played)) {
		die("|0|last played file does not exist (deleted?)|$nv");
	}

	$current_index = array_search($last_played, $files);

	if ($current_index === false) {
		die("|0|last played file no longer matches current filters|$nv");
	}

	$next_index = $current_index + 1;
	if ($next_index >= count($files)) {
		$next_index = 0;
	}

	$next_file = $files[$next_index];

	if (isset($_GET["h"])) {
		$bn = basename($next_file);
		$dn = dirname($next_file);
		die("<h1>$shortname</h1>\nNext: $bn<br />\nPath: $dn");
	}

	die("$next_file|1|next episode");
}

function logPlayback($mysqli, $current_video, $parsedShows, $channel = null, $debug = false, $timestamp_override = null) {

	if ($channel === null) {
		$channel = getCurrentChannel();
	}

	if ($channel !== null) {
		$channel = formatChannelName(trim($channel));
	}

	$show_name = getTvShowName($current_video, $parsedShows);
	$timestamp = ($timestamp_override !== null) ? $timestamp_override : time();

	if ($debug) {
		echo "Show Name: '$show_name' <br>\n";
		echo "Channel: '" . ($channel === null ? "null" : $channel) . "' <br>\n";
		echo "Timestamp: $timestamp <br>\n";
	}

	$stmt_insert = $mysqli->prepare("
		INSERT INTO played (short_name, name, played, channel)
		VALUES (?, ?, ?, ?)
	");
	$stmt_insert->bind_param("ssis", $show_name, $current_video, $timestamp, $channel);
	$stmt_insert->execute();
	$stmt_insert->close();
}

function addMessageToDatabase($message) {
	global $mysqli;

	if (strpos($message, 'UPTIME|') === 0) {
		$mysqli->query("DELETE FROM `errors` WHERE `name` LIKE 'UPTIME|%'");
	}

	$escaped = $mysqli->real_escape_string($message);
	$time = time();

	$mysqli->query("INSERT INTO errors (name, played) VALUES ('$escaped', $time)");
}

function handleRandomVideoByCount($episode, $filter) {
	global $mysqli;
	$max_attempts = 50;
	$attempts = 0;

	if (isset($_GET["dump"])) {
		echo "episode: $episode\nfilter: $filter\n";
	}

	do {
		$return = $filter ? getRandomVideoByCount($episode, $filter) : getRandomVideoByCount($episode);
		
		if (isset($_GET["dump"])) {
			echo str_repeat('$', 40) . "\n";
			var_dump($return);
		}

		list($file, $status, $msg, $dir) = $return;

		if (!$file || $status === 0) {
			return "$file|$status|$msg|$dir";
		}

		$exists = file_exists($file);
		if (!$exists) {
			$escaped = $mysqli->real_escape_string($file);
			$mysqli->query("DELETE FROM played WHERE name='$escaped'");
			$attempts++;
		}

	} while (!$exists && $attempts < $max_attempts);

	if (!$exists) {
		return "|0|max attempts reached, no valid file found|$dir";
	}

	return "$file|$status|$msg|$dir";
}

function isStationRunning() {
    // Specifically match python executing _station.py to prevent false positives
    $output = shell_exec('pgrep -f "^python.*_station\.py" 2>/dev/null');
    return !empty(trim($output));
}

// Place in functions.php
if (isset($_GET['station_status'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode([
        'running' => isStationRunning(),
        'timestamp' => time()
    ]);
    exit;
}

function getRandomVideoByCount($directory, $filter = "mp4,mkv,avi,mpeg,mpg,mov,webm,m4v,flv,wmv") {
	global $mysqli;
	global $parsedShows;
	global $settings_equalize;
	
	$dir_name = rtrim($directory, '/') . '/';
	if (!is_dir($dir_name)) {
		return [$directory, 0, "video directory does not exist", $dir_name];
	}

	$files = glob($dir_name . "*.{" . $filter . "}", GLOB_BRACE);
	if (empty($files)) {
		return [$directory, 0, "no files exist in directory", $dir_name];
	}

	$escaped_dir = $mysqli->real_escape_string($dir_name);
	$sql = "SELECT name FROM played WHERE name LIKE '$escaped_dir%'";
	$res = $mysqli->query($sql) or die($mysqli->error);

	$videos_played = array_fill_keys($files, 0);

	while ($brow = $res->fetch_assoc()) {
		$name = $brow["name"];
		if (isset($videos_played[$name])) {
			$videos_played[$name]++;
		} else {
			if (!file_exists($name)) {
				$escaped_ghost = $mysqli->real_escape_string($name);
				$mysqli->query("DELETE FROM played WHERE name='$escaped_ghost'");
				if (isset($_GET["dump"])) echo "Scrubbed ghost file: $name<br />\n";
			}
		}
	}

	if (empty($videos_played)) {
		return [$files[array_rand($files)], 1, "fallback to random (empty map)", $dir_name];
	}

	$counts = array_values($videos_played);
	$min = min($counts);
	$max = max($counts);

	$pool = [];
	foreach ($videos_played as $f => $c) {
		if ($c === $min) $pool[] = $f;
	}

	if ($min === $max && count($pool) > 1) {
		$recent_sql = "SELECT name FROM played WHERE name LIKE '$escaped_dir%' ORDER BY played DESC LIMIT 1";
		$recent_res = $mysqli->query($recent_sql);
		if ($recent_res && $row = $recent_res->fetch_assoc()) {
			$last_played = $row['name'];
			$key = array_search($last_played, $pool);
			if ($key !== false) {
				unset($pool[$key]);
				$pool = array_values($pool);
				if (isset($_GET["dump"])) echo "Excluded last played: $last_played<br />\n";
			}
		}
	}

	$selected = $pool[array_rand($pool)];

	if ($settings_equalize) {
		$predicted = $videos_played[$selected] + 1;
		if ($predicted < $max) {
			$diff = $max - $predicted;
			$escaped_sel = $mysqli->real_escape_string($selected);
			for ($i = 0; $i < $diff; $i++) {
				logPlayback($mysqli, $escaped_sel, $parsedShows, null, isset($_GET["dump"]), 0);
			}
			addMessageToDatabase("Equalized:|'$selected'|Added:|$diff dummy plays");
		}
	}

	return [$selected, 1, "random video from least played pool", $dir_name];
}

if (isset($_GET["get_next_rnd_episode"])) {
	$episode = urldecode($_GET["get_next_rnd_episode"]);
	$filter = isset($_GET["filter"]) ? urldecode($_GET["filter"]) : null;

	
	die(handleRandomVideoByCount($episode, $filter));
}

if (isset($_GET["get_next_rnd_episode_from_dir"])) {
	$dirs = [];
	$i = 1;

	while (isset($_GET["f$i"])) {
		$dir_name = rtrim(urldecode($_GET["f$i"]), '/') . '/';
		
		if (file_exists($dir_name)) {
			$escaped_dir = $mysqli->real_escape_string($dir_name);
			$sql = "SELECT COUNT(*) AS t FROM played WHERE name LIKE '$escaped_dir%'";
			$res = $mysqli->query($sql) or die($mysqli->error);
			$brow = $res->fetch_assoc();

			$play_count = (int)$brow["t"];
			$file_count = iterator_count(new FilesystemIterator($dir_name, FilesystemIterator::SKIP_DOTS));
			
			$weighted = $file_count > 0 ? ($play_count / $file_count) : 999999;
			$dirs[] = [$weighted, $dir_name];
		}
		$i++;
	}

	if (empty($dirs)) {
		die("|0|no valid directories provided");
	}

	$weights = array_column($dirs, 0);
	$low = min($weights);

	$pool = array_filter($dirs, function($d) use ($low) {
		return $d[0] <= $low;
	});

	if (isset($_GET["dump"])) {
		echo "Low Weight: $low<br>\nPool Size: " . count($pool) . "<br>\n";
		var_dump($pool);
	}

	$selected_dir = $pool[array_rand($pool)][1];
	
	exit(handleRandomVideoByCount($selected_dir, null));
}

$mntcont = strlen($drive_loc[0]);

function formatChannelName($ch) {
	if ($ch !== "" && $ch !== "default" && strlen($ch) <= 100 && preg_match('/^[\w\-]+$/', $ch)) {
		return $ch;
	}
	return null;
}

if (isset($_GET["current_video"])) {
	$current_video = urldecode($_GET["current_video"]);
	$channel       = $_GET["channel"] ?? null;
	$debug         = isset($_GET["d"]);

	logPlayback($mysqli, $current_video, $parsedShows, $channel, $debug);
	
	exit("1");
}

if(isset($_GET["error"])) {
	addMessageToDatabase(urldecode($_GET["error"]));
	exit(1);
}

if (isset($_GET["current_comm"])) {
	$name      = $_GET["current_comm"];
	$timestamp = time();
	$channel   = isset($_GET["channel"]) ? formatChannelName(trim($_GET["channel"])) : null;

	$stmt = $mysqli->prepare("INSERT INTO commercials (name, played, channel) VALUES (?, ?, ?)");
	$stmt->bind_param("sis", $name, $timestamp, $channel);
	$stmt->execute();
	$stmt->close();
	
	die("1");
}

?>