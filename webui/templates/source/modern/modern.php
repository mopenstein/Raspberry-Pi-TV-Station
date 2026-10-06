<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $View['title'] ?></title>
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --border: #334155;
            --danger: #ef4444;
            --success: #22c55e;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background-color: var(--bg-main); 
            color: var(--text-main); 
            font-family: system-ui, -apple-system, sans-serif;
            line-height: 1.5;
        }

        /* Header & Dashboard */
        header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border);
            padding: 1rem;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        /* On Air Status Indicator Badge */
        .air-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: monospace;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            user-select: none;
            transition: all 0.25s ease;
        }

        .air-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: currentColor;
            display: inline-block;
        }

        /* ON AIR State (Clean Green Signal) */
        .air-status-badge.is-live {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.4);
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.2);
        }

        .air-status-badge.is-live .air-status-dot {
            box-shadow: 0 0 6px var(--success);
            animation: air-dot-pulse 2s infinite ease-in-out;
        }

        /* OFF AIR State (Muted Slate / Inactive) */
        .air-status-badge.is-off {
            background: rgba(148, 163, 184, 0.08);
            color: var(--text-muted);
            border: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: none;
        }

        .air-status-badge.is-off .air-status-dot {
            opacity: 0.4;
            animation: none;
            box-shadow: none;
        }

        @keyframes air-dot-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.45; }
        }

        #player-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 400px;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
        }

        .btn {
            background: #333;
            color: white;
            border: none;
            padding: 0px 4px 1px 6px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            text-decoration: none;
            display: inline-block;
        }
        .btn-play { background-color: #65f89bbb; }

        #vidplayer { width: 100%; display: block; }
        #counter {
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 4px 10px;
            font-family: monospace;
            font-size: 12px;
            text-align: center;
        }
        @media (max-width: 600px) {
            #player-container {
                width: calc(100% - 40px);
                bottom: 10px;
                right: 10px;
            }
            .grid { grid-template-columns: 1fr; }
        }

		.sys-stats {
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem 1rem;
			background: rgba(15, 23, 42, 0.5);
			padding: 0.5rem 1rem;
			border-radius: 0.5rem;
			font-size: 0.75rem;
			font-family: monospace;
			border: 1px solid var(--border);
			justify-content: center;
		}

		.stat-item { 
			display: flex; 
			gap: 0.5rem; 
			white-space: nowrap;
		}

		@media (max-width: 600px) {
			.uptime-border {
				border-left: none !important;
				padding-left: 0 !important;
			}
		}

        .stat-label { color: var(--text-muted); }

        /* Navigation */
        nav {
            max-width: 1200px;
            margin: 1.5rem auto;
            display: flex;
            border-bottom: 1px solid var(--border);
            padding: 0 1rem;
        }

        .tablinks {
            flex: 1 0 0px;
            background: var(--bg-card);
            border: none;
            color: var(--text-main);
            padding: 1rem 0.5rem;
            cursor: pointer;
            font-size: 0.8125rem;
            font-weight: 600;
            transition: all 0.2s;
            text-align: center;
            min-width: 0;
        }

        .tablinks:hover { background: #2d3a4f; color: var(--text-primary); }
        .tablinks.active {
            background: var(--accent);
            color: white;
        }

        /* Content Areas */
        main { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .tabcontent { display: none; }
        .tabcontent.active { display: block; }

        /* Tables & Lists */
        .card-table-wrapper {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            overflow: auto;
        }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { 
            background: rgba(255, 255, 255, 0.03);
            padding: 0.75rem 1rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-main);
            letter-spacing: 0.05em;
        }
        td { padding: 1rem; border-top: 1px solid var(--border); font-size: 0.875rem; }
        tr:hover { background: rgba(255, 255, 255, 0.02); }

        /* Buttons & Badges */
        .btn-skip {
            background: var(--accent);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }

        .type-badge {
            font-size: 0.65rem;
            font-weight: bold;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            background: #334155;
            color: #cbd5e1;
            text-transform: uppercase;
        }

        /* Commercial Grid */
        .comm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
        .comm-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 1rem;
            position: relative;
        }

        .hidden { display: none; }
        a { color: var(--text-main); text-decoration: none; }
        a:hover { text-decoration: underline; }

		.scroll-box {
			background: #334155AA;
			padding: 0.5rem;
			font-family: monospace;
			font-size: 0.7rem;
			border-radius: 0.25rem;
			margin-top: 0.5rem;
			width: 100%;
			box-sizing: border-box;
			overflow-x: auto;
			white-space: nowrap;
		}

        .table-container {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            overflow: auto;
        }
    </style>
    <script>
		function fadeAfterDelay(el, seconds) {		
			if (!el) return;
			el.style.transition = 'opacity 1s ease';
			setTimeout(() => {
				el.style.opacity = '0';
				setTimeout(() => {
					if (el.style.opacity === '0') {
						el.style.display = 'none';
						el.style.opacity = 1;
					}
				}, 1000);
			}, seconds * 1000);
		}

		function prepFlag(id) {
			const commDiv = document.getElementById(id);
			commDiv.innerHTML = 'loading...';
			commDiv.style.display = 'block';
		}		

		function flagCallback(responseText) {
			const [splits, id] = responseText.split('|');
			const element = document.getElementById(id);

			const flagConfig = {
				'unflag':  ['showAVUnFlagIcon_', 'showAVFlagIcon_'],
				'flag':    ['showAVFlagIcon_',   'showAVUnFlagIcon_'],
				'flagc':   ['commAVFlagIcon_',   'commAVUnFlagIcon_'],
				'unflagc': ['commAVUnFlagIcon_', 'commAVFlagIcon_']
			};

			if (flagConfig[splits]) {
				const isNumeric = !isNaN(id) && !isNaN(parseFloat(id));

				if (isNumeric) {
					const [toHide, toShow] = flagConfig[splits];
					document.getElementById(toHide + id).style.display = 'none';
					document.getElementById(toShow + id).style.display = 'inline';
				} else if (element) {
					element.parentElement.removeChild(element);
				}
				return;
			}

			if (element) {
				const listItems = splits.trim()
					.split("\n")
					.map(c => `${c}<br />`)
					.join('');

				element.innerHTML = `<ul style="padding-left:20px;">${listItems}</ul>`;
				fadeAfterDelay(element, 2);
			}
		}

		function flagVideo(vid, id) { ajax("/?flag_video="+vid+"&id="+id, flagCallback); }
		function unflagVideo(vid, id) { ajax("/?unflag_video="+vid+"&id="+id, flagCallback); }
		function flagCommercial(vid, id) { ajax("/?flag_comm="+vid+"&id="+id, flagCallback); }
		function unflagCommercial(vid, id) { ajax("/?unflag_comm="+vid+"&id="+id, flagCallback); }

		function renameVideo(fileName) {
			const toFile = prompt("Rename video file to:", fileName);
			if (!toFile) return;
			const confirmed = confirm(`Are you sure you want to rename "${fileName}" to "${toFile}"?`);
			if (!confirmed) return;

			const url = `/?rename_video=${encodeURIComponent(fileName)}&to=${encodeURIComponent(toFile)}`;
			ajax(url, function(responseText) {
				alert(responseText.trim());
			});
		}

		function ajax(url, callback) {
			const xhr = new XMLHttpRequest();
			xhr.open('GET', url);
			xhr.onload = function() {
				if (xhr.status === 200) {
					callback(xhr.responseText);
				}
			};
			xhr.send();
		}

		function viewCommercials(video, showId) {
			const commDiv = document.getElementById('show' + showId);
			commDiv.innerHTML = 'loading...';
			commDiv.style.display = 'block';

			const url = `/?get_commercials=${video}&showId=${showId}`;
			ajax(url, function(responseText) {
				const [commercials, id] = responseText.split('|');
				const targetDiv = document.getElementById('show' + id);
				const listItems = commercials.trim()
					.split("\n")
					.map(c => `${c}<br />`)
					.join('');

				targetDiv.innerHTML = `<ul style="padding-left:20px;">${listItems}</ul>`;
			});
		}

		function showStats(shortName, id) {
			const commDiv = document.getElementById('stats' + id);
			commDiv.innerHTML = 'loading...';
			commDiv.style.display = 'block';

			const url = `/?showstats=${shortName}&id=${id}`;
			ajax(url, function(responseText) {
				console.log("Stats response:", responseText);
				const [commercials, id] = responseText.split('|');
				const targetDiv = document.getElementById('stats' + id);
				const listItems = commercials.trim()
					.split("\n")
					.map(c => `${c}<br />`)
					.join('');

				targetDiv.innerHTML = listItems;
			});
		}

		function swapTab(tabId) {
			document.querySelectorAll('.tabcontent').forEach(c => c.classList.remove('active'));
			document.querySelectorAll('.tablinks').forEach(b => b.classList.remove('active'));
			
			document.getElementById(tabId).classList.add('active');
			document.getElementById('btn' + tabId).classList.add('active');

			history.pushState(null, null, '#' + tabId);
		}

		function playVideo(url, id) {
			currID = id;
			const container = document.getElementById("player-container");
			const video = document.getElementById("vidplayer");
			container.style.display = "block";
			video.src = url;
			console.log("Playing video:", url);
			video.play();
			startCounter();
			
			document.querySelectorAll('.item').forEach(el => el.style.background = "");
			document.getElementById('row' + id).style.background = "#2e3b2e";
		}

		function closePlayer() {
			const container = document.getElementById("player-container");
			const video = document.getElementById("vidplayer");
			video.pause();
			video.src = "";
			container.style.display = "none";
			document.querySelectorAll('.item').forEach(el => el.style.background = "");
		}

        /* Asynchronous Status Check for Modern Template */
        function updateAirIndicator(isLive) {
            const badge = document.getElementById("modernAirBadge");
            const text = document.getElementById("modernAirText");
            if (!badge || !text) return;

            if (isLive) {
                badge.classList.remove("is-off");
                badge.classList.add("is-live");
                text.textContent = "ON AIR";
            } else {
                badge.classList.remove("is-live");
                badge.classList.add("is-off");
                text.textContent = "OFF AIR";
            }
        }

        async function pollModernAirStatus() {
            try {
                const res = await fetch("/?station_status=1", { cache: "no-store" });
                if (res.ok) {
                    const data = await res.json();
                    updateAirIndicator(data.running);
                }
            } catch (e) {
                // Silently bypass transient connection drops
            }
        }

		window.addEventListener('DOMContentLoaded', () => {
    		const hash = window.location.hash.substring(1);
    		if (hash) {
				const targetTab = document.getElementById(hash);
				if (targetTab) {
					swapTab(hash);
				}
    		}

            // Poll station status every 4 seconds
            setInterval(pollModernAirStatus, 4000);
		});

		window.addEventListener('popstate', () => {
			const hash = window.location.hash.substring(1);
			if (hash) {
				swapTab(hash);
			}
		});
    </script>
</head>
<body>

    <header>
        <div class="header-container">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <?php $isBroadcasting = !empty($View['sys']['is_broadcasting']); ?>
                <span id="modernAirBadge" class="air-status-badge <?= $isBroadcasting ? 'is-live' : 'is-off' ?>">
                    <span class="air-status-dot"></span>
                    <span id="modernAirText"><?= $isBroadcasting ? 'ON AIR' : 'OFF AIR' ?></span>
                </span>
				<div style="color: var(--text-muted); font-size: 0.95rem; font-weight: bold; text-transform: uppercase;">
                    <?= $View['nav']['days_links'] ?>
                </div>
            </div>

            <div class="sys-stats">
                <div class="stat-item">
                    <span class="stat-label">Disk:</span> <?= implode(" | ", $View['sys']['disk']) ?>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Load:</span> 
                    <span style="color: <?= $View['sys']['load'] > 80 ? 'var(--danger)' : 'var(--success)' ?>">
                        <?= $View['sys']['load'] ?>%
                    </span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Temp:</span> <?= $View['sys']['temp_f'] ?>°F
                </div>
                <div class="stat-item uptime-border" style="border-left: 1px solid #334155; padding-left: 0.5rem;">
                    <span class="stat-label">Up:</span> <?= $View['sys']['uptime'] ?>
                </div>
            </div>

            <a href="/?skip=1" class="btn-skip">
                SKIP <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"></polygon><line x1="19" y1="5" x2="19" y2="19"></line></svg>
            </a>
        </div>
    </header>

    <nav>
        <button id="btnShows" class="tablinks active" onclick="swapTab('Shows')">Shows</button>
        <button id="btnCommercials" class="tablinks" onclick="swapTab('Commercials')">Commercials</button>
        <button id="btnMessages" class="tablinks" onclick="swapTab('Messages')">Messages</button>
        <button id="btnManage" class="tablinks" onclick="swapTab('Manage')">Manage</button>
        <button id="btnStats" class="tablinks" onclick="swapTab('Stats')">Stats</button>
    </nav>

    <main>
        <!-- Shows -->
        <section id="Shows" class="tabcontent active">
            <div class="card-table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th width="100">Time</th>
                            <th width="*">Show Name</th>
                            <th style="text-align: right;" width="100">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
						$showCount = 0;
                        foreach ($View['data']['shows'] as $s): 
							$showCount++;
                        ?>
                        <tr style="background-color: #<?= $s['color'] ?>5A;">
                            <td style="font-family: monospace; color: var(--text-main);" valign="top"><?= date("h:i\<\s\m\a\l\l\>:s\<\/\s\m\a\l\l\> A",$s['timestamp']) ?></td>
                            <td>
                                <div style="margin-bottom: 0.5rem;"><a href="/?video=<?= $s['url'] ?>"><?= $s['name'] ?></a> <button class="btn btn-play" id="plus<?= $showCount ?>" onclick="playVideo('/?video=<?= $s['url'] ?>', <?= $showCount ?>)">▶</button></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><span class="type-badge" style="background-color: #FFFFFF88; color:#000; text-shadow: -1px -1px 0 #bbb, 1px -1px 0 #bbb, -1px  1px 0 #bbb, 1px  1px 0 #bbb;"><?= $s['len'] ?></span> <span class="type-badge" style="background-color: #<?= $s['color'] ?>; text-shadow: -1px -1px 0 #888, 1px -1px 0 #888, -1px  1px 0 #888, 1px  1px 0 #888;"><?= $s['type'] ?></span></div>
								<div class="scroll-box" style="display: none; width: 100%;" id="show<?= $showCount ?>"></div>
                            </td>
                            <td style="text-align: right;" valign="top">
                                <a onclick="viewCommercials('<?= $s['url'] ?>', <?= $showCount ?>)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M19 6.5C18 5 16.5 4 14.5 4 10 4 6.5 7.5 6.5 12s3.5 8 8 8c2 0 3.5-1 4.5-2.5" stroke-width="3"></path><path d="M4.5 3c-1.5 2.5-2.5 5-2.5 9s1 6.5 2.5 9"></path></svg></a>
                                    <a id="showAVFlagIcon_<?= $showCount ?>" onclick="flagVideo(<?= $s['id'] ?>, <?= $showCount ?>)" <?php if($s['flag'] == 1) { echo 'style="display: none;"'; } ?> ><svg id="showVFlagIcon_<?= $showCount ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="green" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg></a>
                                    <a id="showAVUnFlagIcon_<?= $showCount ?>" onclick="unflagVideo(<?= $s['id'] ?>, <?= $showCount ?>)" <?php if($s['flag'] == 0) { echo 'style="display: none;"'; } ?> ><svg id="showVUnFlagIcon_<?= $showCount ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line><line x1="2" y1="2" x2="22" y2="22" opacity="0.9"></line></svg></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Commercials -->
        <div id="Commercials" class="tabcontent">
          <div class="table-container">
              <table>
                <thead>
                    <tr><th>Time</th><th>Category</th><th>Asset Name</th><th>Manage</th></tr>
                </thead>
                <tbody>
                    <?php
						$count = 0;	
						foreach ($View['data']['commercials'] as $s):
							$count++;
					?>
                    <tr class="comm-row" style="background-color: #<?= $s['color'] ?>5A;">
                        <td align="center" style="font-family: monospace; padding:0px 0px 0px 10px;"><?= date("h:i\<\s\m\a\l\l\>:s\<\/\s\m\a\l\l\> A",$s['timestamp']) ?></td>
                        <td align="center"><span style="font-size: 0.75rem; font-weight: bold; padding:0px;"><?= $s['typeLabel'] ?></span></td>
                        <td style="padding-left:20px;">
                            <?= $count ?>. &#x<?= $s['emoji'] ?>; 
                            <a href="/?video=<?= $s['videoUrl'] ?>" style="color:white; font-weight: 600;"><?= $s['filename'] ?></a> <button class="btn btn-play" id="plus<?= $showCount ?>" onclick="playVideo('/?video=<?= $s['videoUrl'] ?>', <?= $count ?>)">▶</button></div>
                            <span style="font-size: 0.75rem; opacity: 0.8;">(<?= $s['length'] ?>)</span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <a href="/videoeditor.php?file=<?= $s['videoUrl'] ?>" title="Edit"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a> 
                                <a href="/?delete=<?= $s['videoUrl'] ?>" onclick="return confirm('Deleting video is permanent.\n\nAre you sure?')" title="Delete"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ff4444" stroke-width="2" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a>
								<a id="commAVFlagIcon_<?= $count ?>" onclick="flagCommercial(<?= $s['id'] ?>, <?= $count ?>)" <?php if($s['flag'] == 1) { echo 'style="display: none;"'; } ?> ><svg id="commVFlagIcon_<?= $count ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="green" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg></a>
                                <a id="commAVUnFlagIcon_<?= $count ?>" onclick="unflagCommercial(<?= $s['id'] ?>, <?= $count ?>)" <?php if($s['flag'] == 0) { echo 'style="display: none;"'; } ?> ><svg id="commVUnFlagIcon_<?= $count ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line><line x1="2" y1="2" x2="22" y2="22" opacity="0.9"></line></svg></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
              </table>
          </div>
        </div>

        <!-- Messages -->
        <section id="Messages" class="tabcontent">
            <div style="margin-top: 1rem;">
                <?php
                    $lastDate = null; 
                    foreach ($View['data']['messages'] as $m):
                        $currentDate = date("m/d/y", $m["timestamp"]);

                        if ($lastDate !== $currentDate):
                ?>
                            <div style="text-align: center; margin: 2rem 0 1rem; position: relative;">
                                <hr style="border: 0; border-top: 1px solid var(--text-muted); opacity: 0.2;">
                                <span style="position: absolute; top: -0.6rem; left: 50%; transform: translateX(-50%); background: var(--bg-main); padding: 0 1rem; font-size: 0.75rem; color: var(--text-muted); font-weight: bold; text-transform: uppercase;">
                                    <?= $currentDate ?>
                                </span>
                            </div>
                <?php 
                            $lastDate = $currentDate;
                        endif; 
                ?>
                
                <div style="background: var(--bg-card); padding: 1rem; border-radius: 0.5rem; border-left: 4px solid var(--accent); margin-bottom: 1rem;">
                    <div style="font-size: 0.75rem; color: var(--accent); font-weight: bold;"><?= date("m/d/y h:i:s A", $m["timestamp"]) ?></div>
                    <div style="font-weight: bold; margin: 0.25rem 0;"><?= $m['header'] ?></div>
                    <ul style="padding-left: 1.25rem; color: var(--text-muted); font-size: 0.875rem;">
                        <?php foreach ($m['details'] as $d): ?><li><?php
						if(substr($d,0, 6) == "ALERT!") {
							$d = "<span style='color:red; font-weight:bold;'>".$d."</span>";
						}
						echo $d;
						?></li><?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Manage -->
        <section id="Manage" class="tabcontent">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <?php if (empty($View['data']['manage']['cards'])): ?>
                    <div class="card-table-wrapper">
                        <h3 style="padding: 1rem; border-bottom: 1px solid var(--border); font-size: 1rem;">Manage Cards</h3>
                        <div style="padding: 1rem; color: var(--text-muted); text-align: center;">
                            <b>No cards to show.</b><br />
                            <span style="color:red;">Add some to the /manage/ directory to see them here.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($View['data']['manage']['cards'] as $cards): ?>
                    <div class="card-table-wrapper">
                        <h3 style="padding: 1rem; border-bottom: 1px solid var(--border); font-size: 1rem;"><?= $cards['name'] ?></h3>
                        <?php if (!empty($cards['links'])): ?>
                            <?php
                            $count = 0;
                            $clean_id = preg_replace('/[^a-zA-Z0-9]/', '', $cards['name']);
                            $opened_extra = false;
                            ?>
                            <ul style="list-style: none;">
                            <?php foreach ($cards['links'] as $link): ?>
                                <?php
                                if ($count == 5) {
                                    echo '</ul>';
                                    echo '<button class="btn" style="margin: 5px 0 5px 20px;" onclick="document.getElementById(\'' . $clean_id . '-extra-links\').style.display = \'block\'; this.style.display = \'none\';">Show More Links</button>';
                                    echo '<div style="display:none;" id="' . $clean_id . '-extra-links">';
                                    echo '<ul style="list-style-type: square; padding-left: 20px; margin-top: 0;">';
                                    $opened_extra = true;
                                }

                                $style = !empty($link['style']) ? ' style="' . $link['style'] . '"' : '';
                                $target = !empty($link['target']) ? ' target="' . $link['target'] . '"' : '';
                                $action = !empty($link['action']) ? ' onclick="' . $link['action'] . '"' : '';

                                if (isset($link['url']) && isset($link['label'])) {
                                    echo '<li style="padding: 0.75rem 1rem;"><a href="' . $link['url'] . '"' . $style . $target . $action . '>' . $link['label'] . '</a></li>';
                                    $count++;
                                }
                                ?>
                            <?php endforeach; ?>
                            </ul>
                            <?php if ($opened_extra): ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($cards['html'])): ?>
                            <?= $cards['html'] ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h3 style="margin-bottom: 1rem;">Flagged Videos</h3>
            <div class="card-table-wrapper" style="margin-bottom: 2rem;">
                <table>
                    <tbody>
                        <?php if (empty($View['data']['flagged_videos'])): ?>
                            <tr><td style="text-align: center; color: var(--text-muted);">Nothing to see here</td></tr>
                        <?php else: ?>
                            <?php 
                                $count = 0;
                                foreach ($View['data']['flagged_videos'] as $v):
                                    $count++;
                            ?>
                            <tr id="manVid_<?= $count ?>">
                                <td><a href="/?video=<?= urlencode($v['name']) ?>" target="_blank"><?= $v['name'] ?></a></td>
                                <td style="text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
                                    <button class="type-badge" style="cursor: pointer; border: none;" onclick="unflagVideo(<?= $v['id'] ?>, 'manVid_<?= $count ?>');">unflag</button>
                                    <button class="type-badge" style="cursor: pointer; border: none; background: var(--accent);" onclick="renameVideo('<?= $v['name'] ?>');">rename</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="margin-bottom: 1rem;">Flagged Commercials</h3>
            <div class="card-table-wrapper">
                <table>
                    <tbody>
                        <?php if (empty($View['data']['flagged_comms'])): ?>
                            <tr><td style="text-align: center; color: var(--text-muted);">Nothing to see here</td></tr>
                        <?php else: ?>
                            <?php 
                                $count = 0;
                                foreach ($View['data']['flagged_comms'] as $v):
                                    $count++;
                            ?>
                            <tr id="manComm_<?= $count ?>">
                                <td><a href="/?video=<?= urlencode($v['name']) ?>" target="_blank"><?= $v['name'] ?></a></td>
                                <td style="text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
                                    <button class="type-badge" style="cursor: pointer; border: none;" onclick="unflagVideo(<?= $v['id'] ?>, 'manComm_<?= $count ?>');">unflag</button>
                                    <button class="type-badge" style="cursor: pointer; border: none; background: var(--accent);" onclick="renameVideo('<?= $v['name'] ?>');">rename</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Stats -->
        <section id="Stats" class="tabcontent">
            <div class="card-table-wrapper" style="margin-top: 1rem;">
                <table>
                    <thead>
                        <tr>
                            <th width="100">Type</th>
                            <th width="*">Name</th>
                            <th style="text-align: center;" width="100">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 0;
                        foreach ($View['data']['stats'] as $s) {
                            $count++;
                        ?>
                        <tr style="background: #<?= $s['color'] ?>5A;">
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 4px; height: 16px; background: #<?= $s['color'] ?>;"></div>
                                    <?= $s['showType'] ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; align-items: center; ">
                                    <button onclick="showStats('<?= $s['shortName'] ?>', <?= $count ?>)" style="background: none; border: 1px solid var(--border); color: var(--text-muted); font-size: 0.6rem; padding: 0.1rem 0.3rem; border-radius: 2px;">+</button>
                                    <span style="font-weight: 500;"><?= $s['shortName'] ?></span>
                                </div>
                                <div id="stats<?= $count ?>" class="hidden scroll-box"></div>
                            </td>
                            <td style="text-align: center; font-weight: bold; color: var(--accent);"><?= $s['occurrence'] ?></td>
                        </tr>
                        <?php 
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <footer style="text-align: center; padding: 3rem; color: var(--text-muted); font-size: 0.7rem;">
        TV Station WebUI - Modern Theme
    </footer>
	<div id="player-container">
		<video id="vidplayer" controls autoplay></video>
		<button onclick="closePlayer();" style="width:100%; border-radius:0;">Close Player</button>
	</div>
</body>
</html>