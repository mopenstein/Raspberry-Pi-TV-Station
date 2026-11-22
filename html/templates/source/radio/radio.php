<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= $View['title'] ?></title>
	<style>
		:root {
			--bg-main: #0a0b0e;
			--bg-rack: #13151a;
			--bg-card: #181b22;
			--bg-lcd: #060809;
			--text-main: #f3f4f6;
			--text-muted: #8b95a5;
			--accent: #f59e0b;		  /* Warm Broadcast Amber */
			--accent-glow: rgba(245, 158, 11, 0.25);
			--vfd-cyan: #00f0ff;		/* Vacuum Fluorescent Display Cyan */
			--border: #262a34;
			--border-highlight: #343a46;
			--danger: #ef4444;
			--success: #10b981;
		}

		* { box-sizing: border-box; margin: 0; padding: 0; }
		
		body { 
			background-color: var(--bg-main); 
			background-image: 
				radial-gradient(circle at 50% 0%, rgba(245, 158, 11, 0.05) 0%, transparent 60%),
				repeating-linear-gradient(0deg, rgba(255,255,255,0.01) 0px, rgba(255,255,255,0.01) 1px, transparent 1px, transparent 2px);
			color: var(--text-main); 
			font-family: system-ui, -apple-system, sans-serif;
			line-height: 1.5;
			padding-bottom: 4rem;
		}

		/* Studio Rack Header */
		header {
			position: sticky;
			top: 0;
			z-index: 50;
			background: linear-gradient(180deg, #1e222b 0%, #12151b 100%);
			border-bottom: 2px solid #050608;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.08);
			padding: 0.85rem 1rem;
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

		.station-control-group {
			display: flex;
			align-items: center;
			gap: 0.85rem;
			flex-wrap: wrap;
		}

		/* Illuminated Studio Booth ON AIR Sign */
		.air-status-badge {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			font-family: 'Courier New', Courier, monospace;
			font-size: 0.78rem;
			font-weight: 900;
			letter-spacing: 0.14em;
			padding: 5px 12px;
			border-radius: 4px;
			text-transform: uppercase;
			user-select: none;
			transition: all 0.3s ease;
			position: relative;
		}

		.air-status-dot {
			width: 8px;
			height: 8px;
			border-radius: 50%;
			background-color: currentColor;
			display: inline-block;
		}

		/* ON AIR State (Glowing Red/Amber Broadcast Sign) */
		.air-status-badge.is-live {
			background: linear-gradient(180deg, #dc2626 0%, #991b1b 100%);
			color: #ffffff;
			border: 1px solid #f87171;
			box-shadow: 
				0 0 15px rgba(239, 68, 68, 0.6),
				inset 0 1px 2px rgba(255, 255, 255, 0.4);
			text-shadow: 0 0 6px rgba(255, 255, 255, 0.8);
		}

		.air-status-badge.is-live .air-status-dot {
			background-color: #fff;
			box-shadow: 0 0 8px #fff;
			animation: air-dot-pulse 1.4s infinite ease-in-out;
		}

		/* OFF AIR State (Unlit Studio Lamp) */
		.air-status-badge.is-off {
			background: #0d0f13;
			color: #4b5563;
			border: 1px solid #262a34;
			box-shadow: inset 0 2px 4px rgba(0,0,0,0.8);
		}

		.air-status-badge.is-off .air-status-dot {
			opacity: 0.3;
			animation: none;
		}

		@keyframes air-dot-pulse {
			0%, 100% { opacity: 1; transform: scale(1); }
			50% { opacity: 0.35; transform: scale(0.85); }
		}

		/* Animated Mini LED VU Meter */
		.vu-meter {
			display: inline-flex;
			align-items: flex-end;
			gap: 2px;
			height: 16px;
			padding: 2px 5px;
			background: var(--bg-lcd);
			border: 1px solid var(--border);
			border-radius: 3px;
		}

		.vu-bar {
			width: 3px;
			background: var(--success);
			border-radius: 1px;
			height: 20%;
			transition: height 0.2s ease;
		}

		.vu-bar:nth-child(4) { background: var(--accent); }
		.vu-bar:nth-child(5) { background: var(--danger); }

		.is-live ~ .vu-meter .vu-bar:nth-child(1) { animation: vu-bounce 0.6s infinite alternate ease-in-out; }
		.is-live ~ .vu-meter .vu-bar:nth-child(2) { animation: vu-bounce 0.45s infinite alternate-reverse ease-in-out; }
		.is-live ~ .vu-meter .vu-bar:nth-child(3) { animation: vu-bounce 0.55s infinite alternate ease-in-out 0.1s; }
		.is-live ~ .vu-meter .vu-bar:nth-child(4) { animation: vu-bounce 0.7s infinite alternate-reverse ease-in-out 0.15s; }
		.is-live ~ .vu-meter .vu-bar:nth-child(5) { animation: vu-bounce 0.85s infinite alternate ease-in-out 0.05s; }

		@keyframes vu-bounce {
			0% { height: 20%; }
			50% { height: 65%; }
			100% { height: 100%; }
		}

		/* VFD Digital Clock / Date Readout */
		.vfd-date-nav {
			background: var(--bg-lcd);
			border: 1px solid #222834;
			box-shadow: inset 0 2px 6px rgba(0,0,0,0.9);
			padding: 0.35rem 0.75rem;
			border-radius: 4px;
			color: var(--vfd-cyan);
			font-family: 'Courier New', Courier, monospace;
			font-size: 0.85rem;
			font-weight: bold;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			text-shadow: 0 0 6px rgba(0, 240, 255, 0.35);
		}

		.vfd-date-nav a {
			color: var(--accent);
			text-shadow: 0 0 6px rgba(245, 158, 11, 0.4);
			padding: 0 2px;
		}

		/* LCD Telemetry Readout Bar */
		.sys-stats {
			display: flex;
			flex-wrap: wrap;
			gap: 0.65rem 1rem;
			background: var(--bg-lcd);
			padding: 0.45rem 0.9rem;
			border-radius: 4px;
			font-size: 0.72rem;
			font-family: 'Courier New', Courier, monospace;
			border: 1px solid #222834;
			box-shadow: inset 0 2px 6px rgba(0,0,0,0.9);
			justify-content: center;
			color: #d1d5db;
		}

		.stat-item { 
			display: flex; 
			gap: 0.4rem; 
			white-space: nowrap;
			align-items: center;
		}

		.stat-label { 
			color: var(--text-muted); 
			text-transform: uppercase;
			font-size: 0.65rem;
			letter-spacing: 0.05em;
		}

		/* Hardware Console "NEXT CART / SKIP" Button */
		.btn-skip {
			background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
			color: #090a0f;
			padding: 0.5rem 1rem;
			border-radius: 4px;
			text-decoration: none;
			font-size: 0.8rem;
			font-weight: 900;
			letter-spacing: 0.06em;
			display: inline-flex;
			align-items: center;
			gap: 0.45rem;
			border: 1px solid #fbbf24;
			box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3), inset 0 1px 0 rgba(255,255,255,0.4);
			cursor: pointer;
			transition: all 0.15s ease;
		}

		.btn-skip:hover {
			background: linear-gradient(180deg, #fbbf24 0%, #f59e0b 100%);
			text-decoration: none;
			transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(245, 158, 11, 0.45);
		}

		.btn-skip:active {
			transform: translateY(1px);
		}

		/* Soundboard Push-Button Navigation */
		nav {
			max-width: 1200px;
			margin: 1.25rem auto;
			display: flex;
			gap: 0.4rem;
			padding: 0.4rem 1rem;
		}

		.tablinks {
			flex: 1 0 0px;
			background: linear-gradient(180deg, #232733 0%, #171a22 100%);
			border: 1px solid #2e3442;
			border-top: 3px solid #3a4152;
			border-radius: 5px;
			color: var(--text-muted);
			padding: 0.75rem 0.4rem;
			cursor: pointer;
			font-size: 0.78rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			transition: all 0.15s ease;
			text-align: center;
			min-width: 0;
			box-shadow: 0 3px 6px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.05);
		}

		.tablinks:hover { 
			background: linear-gradient(180deg, #2b303f 0%, #1d212b 100%);
			color: var(--text-main); 
		}

		.tablinks.active {
			background: linear-gradient(180deg, #1e222d 0%, #12151c 100%);
			color: var(--accent);
			border-color: #2e3442;
			border-top: 3px solid var(--accent);
			box-shadow: 
				inset 0 3px 8px rgba(0,0,0,0.6), 
				0 -2px 10px rgba(245, 158, 11, 0.2);
			text-shadow: 0 0 8px rgba(245, 158, 11, 0.3);
		}

		/* Content Areas */
		main { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
		.tabcontent { display: none; }
		.tabcontent.active { display: block; }

		/* Rackmount Card & Log Wrappers */
		.card-table-wrapper, .table-container {
			background: var(--bg-card);
			border: 1px solid var(--border);
			border-radius: 6px;
			overflow: auto;
			box-shadow: 0 8px 24px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.04);
		}

		.card-table-wrapper.card-layout-wide {
			grid-column: 1 / -1;
			width: 100%;
		}

		/* Manage Console Masonry Layout */
		.manage-cards-grid {
			columns: 300px;
			column-gap: 1.25rem;
			margin-bottom: 2rem;
		}

		.manage-cards-grid .card-table-wrapper {
			break-inside: avoid;
			margin-bottom: 1.25rem;
		}

		.manage-cards-grid .card-table-wrapper.card-layout-wide {
			column-span: all;
			margin-top: 1.25rem;
		}

		table { width: 100%; border-collapse: collapse; text-align: left; }
		
		th { 
			background: #101217;
			padding: 0.75rem 1rem;
			font-size: 0.68rem;
			font-family: 'Courier New', Courier, monospace;
			text-transform: uppercase;
			color: var(--accent);
			letter-spacing: 0.1em;
			border-bottom: 2px solid var(--border);
		}

		td { 
			padding: 0.85rem 1rem; 
			border-top: 1px solid rgba(255, 255, 255, 0.06); 
			font-size: 0.875rem; 
		}

		tr { transition: filter 0.15s ease; }
		tr:hover { filter: brightness(1.12); }

		/* Play Cue Button & Cart Badges */
		.btn {
			background: #262b38;
			color: var(--text-main);
			border: 1px solid #3d4558;
			padding: 2px 7px;
			border-radius: 4px;
			cursor: pointer;
			font-size: 0.75em;
			text-decoration: none;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			transition: all 0.15s ease;
		}

		.btn-play { 
			background: rgba(16, 185, 129, 0.2);
			color: #34d399;
			border: 1px solid rgba(16, 185, 129, 0.5);
			margin-left: 6px;
			font-weight: bold;
		}

		.btn-play:hover {
			background: var(--success);
			color: #050608;
			box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
		}

		.type-badge {
			font-family: 'Courier New', Courier, monospace;
			font-size: 0.65rem;
			font-weight: 800;
			padding: 0.15rem 0.5rem;
			border-radius: 3px;
			background: #262a34;
			color: #e2e8f0;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			border: 1px solid rgba(0,0,0,0.3);
		}

		.hidden { display: none; }
		a { color: var(--text-main); text-decoration: none; font-weight: 600; }
		a:hover { color: var(--accent); text-decoration: none; }

		.scroll-box {
			background: rgba(6, 8, 9, 0.85);
			border: 1px solid var(--border);
			color: var(--vfd-cyan);
			padding: 0.6rem;
			font-family: 'Courier New', Courier, monospace;
			font-size: 0.72rem;
			border-radius: 4px;
			margin-top: 0.5rem;
			width: 100%;
			box-sizing: border-box;
			overflow-x: auto;
			white-space: nowrap;
			box-shadow: inset 0 2px 5px rgba(0,0,0,0.8);
		}

		/* Studio Monitor Deck Player (Handles Audio or Video Streams) */
		#player-container {
			position: fixed;
			bottom: 20px;
			right: 20px;
			width: 380px;
			background: linear-gradient(180deg, #1e222b 0%, #0f1117 100%);
			border: 1px solid var(--accent);
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 12px 35px rgba(0,0,0,0.85), 0 0 15px rgba(245, 158, 11, 0.2);
			z-index: 1000;
			display: none;
		}

		.player-deck-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 6px 12px;
			background: #080a0d;
			border-bottom: 1px solid var(--border);
			font-family: 'Courier New', Courier, monospace;
			font-size: 0.7rem;
			color: var(--accent);
			letter-spacing: 0.08em;
			text-transform: uppercase;
		}

		#vidplayer { 
			width: 100%; 
			max-height: 220px;
			display: block; 
			background: #000;
		}

		#counter {
			background: #060809;
			color: var(--vfd-cyan);
			padding: 4px 10px;
			font-family: 'Courier New', Courier, monospace;
			font-size: 11px;
			text-align: center;
			border-top: 1px solid #1f2430;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.btn-close-deck {
			width: 100%;
			background: #1f242d;
			color: var(--text-muted);
			border: none;
			border-top: 1px solid var(--border);
			padding: 8px;
			font-size: 0.72rem;
			font-weight: bold;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			cursor: pointer;
			transition: all 0.15s;
		}

		.btn-close-deck:hover {
			background: var(--danger);
			color: #fff;
		}

		/* Mobile Responsive Adjustments */
		@media (max-width: 680px) {
			.header-container {
				justify-content: center;
			}
			.station-control-group {
				width: 100%;
				justify-content: space-between;
			}
			.sys-stats {
				width: 100%;
				justify-content: space-between;
				font-size: 0.68rem;
			}
			.uptime-border {
				border-left: none !important;
				padding-left: 0 !important;
			}
			.btn-skip {
				width: 100%;
				justify-content: center;
				padding: 0.6rem;
			}
			nav {
				gap: 0.25rem;
				padding: 0 0.5rem;
			}
			.tablinks {
				font-size: 0.68rem;
				padding: 0.65rem 0.2rem;
				letter-spacing: 0.02em;
			}
			td, th {
				padding: 0.65rem 0.5rem;
			}
			#player-container {
				width: calc(100% - 20px);
				bottom: 10px;
				right: 10px;
			}
		}
	</style>
	<script>
		let currID = null;

		function startCounter() {
			const video = document.getElementById("vidplayer");
			const counter = document.getElementById("counter");
			if (!video || !counter) return;
			video.ontimeupdate = () => {
				const cur = new Date(video.currentTime * 1000).toISOString().substr(11, 8);
				const dur = isNaN(video.duration) ? "00:00:00" : new Date(video.duration * 1000).toISOString().substr(11, 8);
				counter.textContent = `CUE MONITOR // ${cur} / ${dur}`;
			};
		}

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
				'flag':	['showAVFlagIcon_',   'showAVUnFlagIcon_'],
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
			const toFile = prompt("Rename media file to:", fileName);
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
			console.log("Playing stream:", url);
			video.play();
			startCounter();
			
			document.querySelectorAll('.item').forEach(el => el.style.outline = "");
			const targetRow = document.getElementById('row' + id);
			if (targetRow) {
				targetRow.style.outline = "2px solid var(--accent)";
			}
		}

		function closePlayer() {
			const container = document.getElementById("player-container");
			const video = document.getElementById("vidplayer");
			video.pause();
			video.src = "";
			container.style.display = "none";
			document.querySelectorAll('.item').forEach(el => el.style.outline = "");
		}

		/* Asynchronous Status Check */
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
			<div class="station-control-group">
				<div style="display: flex; align-items: center; gap: 0.5rem;">
					<?php $isBroadcasting = !empty($View['sys']['is_broadcasting']); ?>
					<span id="modernAirBadge" class="air-status-badge <?= $isBroadcasting ? 'is-live' : 'is-off' ?>">
						<span class="air-status-dot"></span>
						<span id="modernAirText"><?= $isBroadcasting ? 'ON AIR' : 'OFF AIR' ?></span>
					</span>
					<div class="vu-meter" title="Broadcast Output Modulation">
						<span class="vu-bar"></span>
						<span class="vu-bar"></span>
						<span class="vu-bar"></span>
						<span class="vu-bar"></span>
						<span class="vu-bar"></span>
					</div>
				</div>
				<div class="vfd-date-nav">
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
				<div class="stat-item uptime-border" style="border-left: 1px solid #262a34; padding-left: 0.6rem;">
					<span class="stat-label">Up:</span> <?= $View['sys']['uptime'] ?>
				</div>
			</div>

			<a href="/?skip=1" class="btn-skip" title="Skip Current Track / Cart">
				SKIP <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"></polygon><line x1="19" y1="5" x2="19" y2="19"></line></svg>
			</a>
		</div>
	</header>

	<nav>
		<button id="btnShows" class="tablinks active" onclick="swapTab('Shows')">Shows</button>
		<button id="btnCommercials" class="tablinks" onclick="swapTab('Commercials')">Ads</button>
		<button id="btnMessages" class="tablinks" onclick="swapTab('Messages')">Messages</button>
		<button id="btnManage" class="tablinks" onclick="swapTab('Manage')">Manage</button>
		<button id="btnStats" class="tablinks" onclick="swapTab('Stats')">Stats</button>
	</nav>

	<main>
		<!-- Shows / Broadcast Log -->
		<section id="Shows" class="tabcontent active">
			<div class="card-table-wrapper">
				<table>
					<thead>
						<tr>
							<th width="110">Air Time</th>
							<th width="*">Program / Track</th>
							<th style="text-align: right;" width="100">Cue / Flag</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$showCount = 0;
						foreach ($View['data']['shows'] as $s): 
							$showCount++;
						?>
						<tr id="row<?= $showCount ?>" class="item" style="background-color: #<?= $s['color'] ?>5A;">
							<td style="font-family: 'Courier New', Courier, monospace; color: var(--text-main); font-weight: bold;" valign="top">
								<?= date("h:i\<\s\m\a\l\l\>:s\<\/\s\m\a\l\l\> A",$s['timestamp']) ?>
							</td>
							<td>
								<div style="margin-bottom: 0.45rem;">
									<a href="/?video=<?= $s['url'] ?>"><?= $s['name'] ?></a> 
									<button class="btn btn-play" id="plus<?= $showCount ?>" onclick="playVideo('/?video=<?= $s['url'] ?>', <?= $showCount ?>)" title="Cue Track">▶</button>
								</div>
								<div style="font-size: 0.75rem; color: var(--text-muted); display: flex; flex-wrap: wrap; gap: 0.35rem;">
									<span class="type-badge" style="background-color: #FFFFFF88; color:#000; text-shadow: -1px -1px 0 #bbb, 1px -1px 0 #bbb, -1px 1px 0 #bbb, 1px 1px 0 #bbb;"><?= $s['len'] ?></span> 
									<span class="type-badge" style="background-color: #<?= $s['color'] ?>; text-shadow: -1px -1px 0 #888, 1px -1px 0 #888, -1px 1px 0 #888, 1px 1px 0 #888;"><?= $s['type'] ?></span>
								</div>
								<div class="scroll-box" style="display: none; width: 100%;" id="show<?= $showCount ?>"></div>
							</td>
							<td style="text-align: right;" valign="top">
								<div style="display: inline-flex; gap: 8px; align-items: center;">
									<a id="showAVFlagIcon_<?= $showCount ?>" flag="<?= $s['flag'] ?>" onclick="flagVideo(<?= $s['id'] ?>, <?= $showCount ?>)" style="cursor: pointer;<?php if($s['flag'] == 1) { echo ' display: none;'; } ?>" ><svg id="showVFlagIcon_<?= $showCount ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="green" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg></a>
									<a id="showAVUnFlagIcon_<?= $showCount ?>" flag="<?= $s['flag'] ?>" onclick="unflagVideo(<?= $s['id'] ?>, <?= $showCount ?>)" style="cursor: pointer;<?php if($s['flag'] == 0) { echo ' display: none;'; } ?>" ><svg id="showVUnFlagIcon_<?= $showCount ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line><line x1="2" y1="2" x2="22" y2="22" opacity="0.9"></line></svg></a>
								</div>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>

		<!-- Commercials / Spot Carts -->
		<div id="Commercials" class="tabcontent">
		  <div class="table-container">
			  <table>
				<thead>
					<tr><th>Time</th><th>Cart Type</th><th>Spot / Jingle Asset</th><th>Manage</th></tr>
				</thead>
				<tbody>
					<?php
						$count = 0; 
						foreach ($View['data']['commercials'] as $s):
							$count++;
					?>
					<tr id="rowComm<?= $count ?>" class="comm-row item" style="background-color: #<?= $s['color'] ?>5A;">
						<td align="center" style="font-family: 'Courier New', Courier, monospace; padding:0px 0px 0px 10px; font-weight: bold;">
							<?= date("h:i\<\s\m\a\l\l\>:s\<\/\s\m\a\l\l\> A",$s['timestamp']) ?>
						</td>
						<td align="center"><span class="type-badge"><?= $s['typeLabel'] ?></span></td>
						<td style="padding-left:20px;">
							<?= $count ?>. &#x<?= $s['emoji'] ?>; 
							<a href="/?video=<?= $s['videoUrl'] ?>" style="color:white; font-weight: 600;"><?= $s['filename'] ?></a> 
							<button class="btn btn-play" id="plusComm<?= $count ?>" onclick="playVideo('/?video=<?= $s['videoUrl'] ?>', 'Comm<?= $count ?>')" title="Cue Cart">▶</button>
							<span style="font-size: 0.75rem; opacity: 0.8; font-family: monospace;">(<?= $s['length'] ?>)</span>
						</td>
						<td>
							<div style="display: flex; gap: 8px; align-items: center;">
								<a href="/videoeditor.php?file=<?= $s['videoUrl'] ?>" title="Edit"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a> 
								<a href="/?delete=<?= $s['videoUrl'] ?>" onclick="return confirm('Deleting media asset is permanent.\n\nAre you sure?')" title="Delete"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ff4444" stroke-width="2" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a>
								<a id="commAVFlagIcon_<?= $count ?>" onclick="flagCommercial(<?= $s['id'] ?>, <?= $count ?>)" style="cursor: pointer;<?php if($s['flag'] == 1) { echo ' display: none;'; } ?>" ><svg id="commVFlagIcon_<?= $count ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="green" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg></a>
								<a id="commAVUnFlagIcon_<?= $count ?>" onclick="unflagCommercial(<?= $s['id'] ?>, <?= $count ?>)" style="cursor: pointer;<?php if($s['flag'] == 0) { echo ' display: none;'; } ?>" ><svg id="commVUnFlagIcon_<?= $count ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 0 1px black) drop-shadow(0 0 1px black);"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line><line x1="2" y1="2" x2="22" y2="22" opacity="0.9"></line></svg></a>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			  </table>
		  </div>
		</div>

		<!-- Messages / Station Log -->
		<section id="Messages" class="tabcontent">
			<div style="margin-top: 1rem;">
				<?php
					$lastDate = null; 
					foreach ($View['data']['messages'] as $m):
						$currentDate = date("m/d/y", $m["timestamp"]);

						if ($lastDate !== $currentDate):
				?>
							<div style="text-align: center; margin: 2rem 0 1rem; position: relative;">
								<hr style="border: 0; border-top: 1px solid var(--border);">
								<span style="position: absolute; top: -0.6rem; left: 50%; transform: translateX(-50%); background: var(--bg-main); padding: 0 1rem; font-family: monospace; font-size: 0.75rem; color: var(--accent); font-weight: bold; text-transform: uppercase;">
									LOG // <?= $currentDate ?>
								</span>
							</div>
				<?php 
							$lastDate = $currentDate;
						endif; 
				?>
				
				<div style="background: var(--bg-card); padding: 1rem; border-radius: 6px; border: 1px solid var(--border); border-left: 4px solid var(--accent); margin-bottom: 1rem;">
					<div style="font-size: 0.72rem; font-family: monospace; color: var(--vfd-cyan); font-weight: bold;"><?= date("m/d/y h:i:s A", $m["timestamp"]) ?></div>
					<div style="font-weight: bold; margin: 0.25rem 0;"><?= $m['header'] ?></div>
					<ul style="padding-left: 1.25rem; color: var(--text-muted); font-size: 0.875rem;">
						<?php foreach ($m['details'] as $d): ?><li><?php
						if(substr($d,0, 6) == "ALERT!") {
							$d = "<span style='color:var(--danger); font-weight:bold;'>".$d."</span>";
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
			<?php if (empty($View['data']['manage']['cards'])): ?>
				<div class="card-table-wrapper">
					<h3 style="padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--accent);">Manage Console Modules</h3>
					<div style="padding: 1.5rem; color: var(--text-muted); text-align: center;">
						<b>No rack modules loaded.</b><br />
						<span style="color:var(--danger);">Add some to the /manage/ directory to see them here.</span>
					</div>
				</div>
			<?php else: ?>
				<?php
					// 1. Group cards by display mode
					$grouped = [];
					foreach ($View['data']['manage']['cards'] as $key => $card) {
						$mode = !empty($card['display']) ? $card['display'] : 'default';
						if ($mode === 'hidden') continue;
						$grouped[$mode][$key] = $card;
					}

					// 2. Sort each group internally: expanded first, then priority (descending)
					foreach ($grouped as $mode => &$groupCards) {
						uasort($groupCards, function($a, $b) {
							$colA = !empty($a['collapsed']) ? 1 : 0;
							$colB = !empty($b['collapsed']) ? 1 : 0;
							if ($colA !== $colB) {
								return $colA <=> $colB;
							}
							return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
						});
					}
					unset($groupCards);

					// 3. Put all other display types first, then 'wide' last
					$other_modes = array_diff(array_keys($grouped), ['wide']);
					$render_order = array_values($other_modes);
					if (isset($grouped['wide'])) {
						$render_order[] = 'wide';
					}
				?>

				<?= $View['data']['manage']['toggle_js'] ?? '' ?>

				<div class="manage-cards-grid">
					<?php foreach ($render_order as $mode): ?>
						<?php if (empty($grouped[$mode])) continue; ?>
						<?php foreach ($grouped[$mode] as $cards): ?>
							<?php $is_collapsed = !empty($cards['collapsed']); ?>
							<div class="card-table-wrapper card-layout-<?= htmlspecialchars($mode) ?>"
								 data-priority="<?= (int)($cards['priority'] ?? 0) ?>"
								 data-mode="<?= htmlspecialchars($mode) ?>">
								<div style="padding: 0.9rem 1rem; background: #101217; border-bottom: <?= $is_collapsed ? 'none' : '1px solid var(--border)' ?>; font-size: 0.85rem; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; color: var(--accent); cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
									 onclick="toggleManageCard('<?= htmlspecialchars($cards['key'], ENT_QUOTES) ?>', this)">
									<span><?= htmlspecialchars($cards['name']) ?></span>
									<button type="button" class="toggle-icon" style="background: #181b22; border: 1px solid var(--border-highlight); border-radius: 4px; width: 24px; height: 24px; cursor: pointer; line-height: 1; font-family: monospace; font-weight: bold; color: var(--vfd-cyan);">
										<?= $is_collapsed ? '+' : '-' ?>
									</button>
								</div>

								<div style="display: <?= $is_collapsed ? 'none' : 'block' ?>;">
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
												echo '<button class="btn" style="margin: 8px 0 8px 16px;" onclick="document.getElementById(\'' . $clean_id . '-extra-links\').style.display = \'block\'; this.style.display = \'none\';">Show More Links</button>';
												echo '<div style="display:none;" id="' . $clean_id . '-extra-links">';
												echo '<ul style="list-style-type: square; padding-left: 20px; margin-top: 0;">';
												$opened_extra = true;
											}

											$style = !empty($link['style']) ? ' style="' . $link['style'] . '"' : '';
											$target = !empty($link['target']) ? ' target="' . $link['target'] . '"' : '';
											$action = !empty($link['action']) ? ' onclick="' . $link['action'] . '"' : '';

											if (isset($link['url']) && isset($link['label'])) {
												echo '<li style="padding: 0.7rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.03);"><a href="' . $link['url'] . '"' . $style . $target . $action . '>' . $link['label'] . '</a></li>';
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
							</div>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<!-- Stats / Rotation Telemetry -->
		<section id="Stats" class="tabcontent">
			<div class="card-table-wrapper" style="margin-top: 1rem;">
				<table>
					<thead>
						<tr>
							<th width="120">Format / Type</th>
							<th width="*">Rotation Asset</th>
							<th style="text-align: center;" width="100">Spins</th>
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
								<div style="display: flex; align-items: center; gap: 0.5rem; font-family: monospace; font-size: 0.8rem;">
									<div style="width: 4px; height: 16px; background: #<?= $s['color'] ?>;"></div>
									<?= $s['showType'] ?>
								</div>
							</td>
							<td>
								<div style="display: flex; gap: 0.5rem; align-items: center;">
									<button onclick="showStats('<?= $s['shortName'] ?>', <?= $count ?>)" class="btn" style="padding: 0.1rem 0.4rem; font-family: monospace;">+</button>
									<span style="font-weight: 600;"><?= $s['shortName'] ?></span>
								</div>
								<div id="stats<?= $count ?>" class="hidden scroll-box"></div>
							</td>
							<td style="text-align: center; font-weight: bold; font-family: monospace; color: var(--vfd-cyan);"><?= $s['occurrence'] ?></td>
						</tr>
						<?php 
						}
						?>
					</tbody>
				</table>
			</div>
		</section>
	</main>

	<footer style="text-align: center; padding: 3rem 1rem; color: var(--text-muted); font-family: monospace; font-size: 0.7rem; letter-spacing: 0.08em; text-transform: uppercase;">
		Radio Broadcast Automation Console // Studio Deck UI
	</footer>

	<div id="player-container">
		<div class="player-deck-header">
			<span>STUDIO CUE MONITOR</span>
			<span>CH 1-2 STEREO</span>
		</div>
		<video id="vidplayer" controls autoplay></video>
		<div id="counter">CUE MONITOR // READY</div>
		<button class="btn-close-deck" onclick="closePlayer();">Close Monitor</button>
	</div>

</body>
</html>