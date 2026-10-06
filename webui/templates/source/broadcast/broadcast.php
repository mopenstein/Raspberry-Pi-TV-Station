<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($View['title']) ?></title>
    <style>
        :root {
            --bg-chassis: #0b0d10;
            --bg-panel: #14181f;
            --bg-card: #1b2029;
            --bg-input: #0e1117;
            --border-rack: #2a313d;
            --border-highlight: #3b4556;
            --text-lead: #e6edf3;
            --text-dim: #7d8590;
            --amber: #e5a93b;
            --amber-glow: rgba(229, 169, 59, 0.2);
            --crt-green: #3fb950;
            --crt-green-glow: rgba(63, 185, 80, 0.2);
            --tally-red: #f85149;
            --tally-red-glow: rgba(248, 81, 73, 0.25);
			--tally-green: #22c55e;
            --tally-green-glow: rgba(34, 197, 94, 0.25);
            --cyan-accent: #38bdf8;
            --font-mono: ui-monospace, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
            --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg-chassis);
            color: var(--text-lead);
            font-family: var(--font-sans);
            font-size: 13px;
            line-height: 1.5;
            padding-bottom: 80px;
        }

        /* Top Rack Control Bar */
        .rack-header {
            background: var(--bg-panel);
            border-bottom: 2px solid var(--border-rack);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-inner {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0.5rem 1rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
        }

        .station-mast {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Dynamic On/Off Air Studio Badge */
        .on-air-badge {
            font-family: var(--font-mono);
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            padding: 2px 7px;
            border-radius: 2px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.25s ease;
            user-select: none;
        }

        .on-air-badge::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
            transition: all 0.25s ease;
        }

        /* ON AIR Active State */
        .on-air-badge.is-live {
            background: rgba(123, 248, 73, 0.15);
            border: 1px solid var(--tally-green);
            color: var(--tally-green);
            box-shadow: 0 0 8px var(--tally-green-glow);
        }

        .on-air-badge.is-live::before {
            background: var(--tally-green);
            box-shadow: 0 0 4px var(--tally-green);
            animation: pulse-dot 2s infinite ease-in-out;
        }

        /* OFF AIR Inactive State */
        .on-air-badge.is-off {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-rack);
            color: var(--text-dim);
            box-shadow: none;
        }

        .on-air-badge.is-off::before {
            background: var(--border-highlight);
            box-shadow: none;
            animation: none;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.45; }
        }

        .station-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-lead);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .date-stepper {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--bg-input);
            padding: 4px 8px;
            border: 1px solid var(--border-rack);
            border-radius: 4px;
        }

        .date-stepper a {
            color: var(--amber);
            text-decoration: none;
            font-weight: bold;
        }
        .date-stepper a:hover { color: #fff; }

        /* Hardware Telemetry Strip */
        .rack-telemetry {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            font-family: var(--font-mono);
            font-size: 0.72rem;
            background: var(--bg-input);
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid var(--border-rack);
        }

        .meter-unit {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0 4px;
        }

        .meter-unit:not(:last-child) {
            border-right: 1px solid var(--border-rack);
        }

        .meter-lbl { color: var(--text-dim); text-transform: uppercase; font-size: 0.65rem; }
        .meter-val { color: var(--crt-green); font-weight: bold; }
        .meter-val.warn { color: var(--tally-red); }

        /* Skip Command Button */
        .btn-skip {
            background: linear-gradient(180deg, #2b1d1f 0%, #1c1416 100%);
            border: 1px solid var(--tally-red);
            color: #ff9994;
            padding: 4px 12px;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            border-radius: 3px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            box-shadow: 0 0 6px var(--tally-red-glow);
        }
        .btn-skip:hover {
            background: var(--tally-red);
            color: #000;
        }

        /* Bus Navigation Switcher */
        .bus-nav {
            max-width: 1400px;
            margin: 1rem auto 0;
            padding: 0 1rem;
            display: flex;
            gap: 0.35rem;
            border-bottom: 2px solid var(--border-rack);
        }

        .tablinks {
            background: var(--bg-panel);
            border: 1px solid var(--border-rack);
            border-bottom: none;
            color: var(--text-dim);
            padding: 6px 14px;
            cursor: pointer;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            border-radius: 4px 4px 0 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tablinks::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #2a313d;
        }

        .tablinks:hover { background: var(--bg-card); color: var(--text-lead); }

        .tablinks.active {
            background: var(--bg-card);
            border-color: var(--border-highlight);
            color: #fff;
            border-bottom: 2px solid var(--amber);
            margin-bottom: -2px;
        }

        .tablinks.active::before {
            background: var(--amber);
            box-shadow: 0 0 5px var(--amber);
        }

        /* Main Workspace */
        main {
            max-width: 1400px;
            margin: 1rem auto;
            padding: 0 1rem;
        }

        .tabcontent { display: none; }
        .tabcontent.active { display: block; }

        /* Instrument Rack Wrappers & Tables */
        .instrument-frame {
            background: var(--bg-panel);
            border: 1px solid var(--border-rack);
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        table.rack-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        table.rack-table th {
            background: #11141a;
            color: var(--text-dim);
            font-family: var(--font-mono);
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-rack);
        }

        table.rack-table td {
            padding: 8px 12px;
            border-bottom: 1px solid rgba(42, 49, 61, 0.4);
            font-size: 0.82rem;
            vertical-align: middle;
        }

        table.rack-table tr:hover {
            background-color: rgba(255, 255, 255, 0.015);
        }

        /* Custom Badges & Tags */
        .time-cell {
            font-family: var(--font-mono);
            font-size: 0.78rem;
            color: var(--cyan-accent);
            white-space: nowrap;
        }

        .tag-pill {
            display: inline-block;
            font-family: var(--font-mono);
            font-size: 0.65rem;
            padding: 1px 5px;
            border-radius: 2px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .action-tray {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        /* Console Push Buttons */
        .btn-ctl {
            background: #1e2530;
            border: 1px solid var(--border-highlight);
            color: var(--text-lead);
            padding: 2px 7px;
            border-radius: 3px;
            font-family: var(--font-mono);
            font-size: 0.7rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-ctl:hover {
            background: var(--border-highlight);
            color: #fff;
        }

        .btn-play-trigger {
            background: #143521;
            border: 1px solid var(--crt-green);
            color: #85e89d;
            font-weight: bold;
            padding: 1px 6px;
            font-size: 0.65rem;
            margin-left: 6px;
        }
        .btn-play-trigger:hover {
            background: var(--crt-green);
            color: #000;
        }

        .btn-flag {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 2px;
            display: inline-flex;
            align-items: center;
        }

        /* Scroll Box for Inline Payload inspection */
        .scroll-box {
            background: #090b0e;
            border: 1px solid var(--border-rack);
            padding: 6px 10px;
            font-family: var(--font-mono);
            font-size: 0.72rem;
            color: var(--amber);
            border-radius: 3px;
            margin-top: 6px;
            width: 100%;
            overflow-x: auto;
            white-space: nowrap;
        }

        /* System Messages Feed */
        .msg-feed-date {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 1.25rem 0 0.5rem;
            padding-bottom: 4px;
            border-bottom: 1px solid var(--border-rack);
        }

        .msg-log-entry {
            background: var(--bg-panel);
            border: 1px solid var(--border-rack);
            border-left: 3px solid var(--cyan-accent);
            padding: 8px 12px;
            border-radius: 3px;
            margin-bottom: 0.5rem;
            font-family: var(--font-mono);
        }

        .msg-header {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-lead);
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .msg-header .time { color: var(--text-dim); font-weight: normal; }

        .msg-log-entry ul {
            list-style: none;
            font-size: 0.72rem;
            color: var(--text-dim);
        }

        .msg-log-entry li::before {
            content: "› ";
            color: var(--border-highlight);
        }

        /* Manage Layout */
        .manage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .panel-heading {
            background: #11141a;
            padding: 8px 12px;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-lead);
            border-bottom: 1px solid var(--border-rack);
        }

        /* Channel Select in Nav */
        .channel-dropdown-wrap select {
            background: var(--bg-input);
            color: var(--text-lead);
            border: 1px solid var(--border-rack);
            padding: 3px 6px;
            font-family: var(--font-mono);
            font-size: 0.75rem;
            border-radius: 3px;
        }

        /* Floating Studio Monitor Container */
        #player-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 420px;
            background: #000;
            border: 2px solid var(--border-highlight);
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.8), 0 0 12px rgba(56, 189, 248, 0.15);
            z-index: 1000;
            display: none;
        }

        .monitor-titlebar {
            background: #11141a;
            padding: 5px 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-rack);
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--text-dim);
        }

        #vidplayer { width: 100%; display: block; background: #000; }

        .monitor-btn-close {
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            font-weight: bold;
        }
        .monitor-btn-close:hover { color: var(--tally-red); }

        a { color: var(--text-lead); text-decoration: none; }
        a:hover { color: var(--amber); }

        @media (max-width: 768px) {
            /* Mobile 2-Row Rack Switcher Grid */
            .bus-nav {
                display: grid;
                grid-template-columns: repeat(6, 1fr);
                gap: 4px;
                border-bottom: 2px solid var(--border-rack);
                padding: 0 0.5rem;
                margin-top: 0.5rem;
            }

            .tablinks:nth-child(1),
            .tablinks:nth-child(2),
            .tablinks:nth-child(3) {
                grid-column: span 2;
            }

            .tablinks:nth-child(4),
            .tablinks:nth-child(5) {
                grid-column: span 3;
            }

            .tablinks {
                padding: 6px 4px;
                font-size: 0.65rem;
                text-align: center;
                justify-content: center;
                white-space: nowrap;
                border-radius: 3px;
                border: 1px solid var(--border-rack);
                margin-bottom: 0;
            }

            .tablinks::before {
                display: none;
            }

            .tablinks.active {
                border-color: var(--amber);
                border-bottom: 1px solid var(--amber);
            }
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
                        el.style.opacity = '1';
                    }
                }, 1000);
            }, seconds * 1000);
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
                    const hideEl = document.getElementById(toHide + id);
                    const showEl = document.getElementById(toShow + id);
                    if (hideEl) hideEl.style.display = 'none';
                    if (showEl) showEl.style.display = 'inline-flex';
                } else if (element) {
                    element.parentElement.removeChild(element);
                }
                return;
            }

            if (element) {
                const listItems = splits.trim().split("\n").map(c => `<div>${c}</div>`).join('');
                element.innerHTML = listItems;
                fadeAfterDelay(element, 2);
            }
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

        function flagVideo(vid, id) { ajax("/?flag_video=" + vid + "&id=" + id, flagCallback); }
        function unflagVideo(vid, id) { ajax("/?unflag_video=" + vid + "&id=" + id, flagCallback); }
        function flagCommercial(vid, id) { ajax("/?flag_comm=" + vid + "&id=" + id, flagCallback); }
        function unflagCommercial(vid, id) { ajax("/?unflag_comm=" + vid + "&id=" + id, flagCallback); }

        function renameVideo(fileName) {
            const toFile = prompt("Rename video file to:", fileName);
            if (!toFile) return;
            if (!confirm(`Are you sure you want to rename "${fileName}" to "${toFile}"?`)) return;
            ajax(`/?rename_video=${encodeURIComponent(fileName)}&to=${encodeURIComponent(toFile)}`, function(responseText) {
                alert(responseText.trim());
            });
        }

        function viewCommercials(video, showId) {
            const commDiv = document.getElementById('show' + showId);
            commDiv.innerHTML = 'Querying reel...';
            commDiv.style.display = 'block';
            ajax(`/?get_commercials=${video}&showId=${showId}`, function(responseText) {
                const [commercials, id] = responseText.split('|');
                const targetDiv = document.getElementById('show' + id);
                const listItems = commercials.trim().split("\n").map(c => `<div>› ${c}</div>`).join('');
                targetDiv.innerHTML = listItems;
            });
        }

        function showStats(shortName, id) {
            const commDiv = document.getElementById('stats' + id);
            commDiv.innerHTML = 'Inspecting transmission counts...';
            commDiv.style.display = 'block';
            ajax(`/?showstats=${shortName}&id=${id}`, function(responseText) {
                const [commercials, id] = responseText.split('|');
                const targetDiv = document.getElementById('stats' + id);
                targetDiv.innerHTML = commercials.trim().split("\n").map(c => `<div>${c}</div>`).join('');
            });
        }

        function swapTab(tabId) {
            document.querySelectorAll('.tabcontent').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tablinks').forEach(b => b.classList.remove('active'));
            
            const targetContent = document.getElementById(tabId);
            const targetBtn = document.getElementById('btn' + tabId);

            if (targetContent) targetContent.classList.add('active');
            if (targetBtn) targetBtn.classList.add('active');

            history.pushState(null, null, '#' + tabId);
        }

        function playVideo(url, id) {
            const container = document.getElementById("player-container");
            const video = document.getElementById("vidplayer");
            container.style.display = "block";
            video.src = url;
            video.play();
            
            document.querySelectorAll('tr[id^="row"]').forEach(el => el.style.outline = "none");
            const activeRow = document.getElementById('row' + id);
            if (activeRow) activeRow.style.outline = "1px solid var(--cyan-accent)";
        }

        function closePlayer() {
            const container = document.getElementById("player-container");
            const video = document.getElementById("vidplayer");
            video.pause();
            video.src = "";
            container.style.display = "none";
            document.querySelectorAll('tr[id^="row"]').forEach(el => el.style.outline = "none");
        }

        /* Asynchronous Global On-Air Status Poller */
        function updateGlobalAirBadge(isRunning) {
            const badge = document.getElementById("globalAirBadge");
            const text = document.getElementById("globalAirText");
            if (!badge || !text) return;

            if (isRunning) {
                badge.classList.remove("is-off");
                badge.classList.add("is-live");
                text.textContent = "ON AIR";
            } else {
                badge.classList.remove("is-live");
                badge.classList.add("is-off");
                text.textContent = "OFF AIR";
            }
        }

        async function pollAirStatus() {
            try {
                const res = await fetch("/?station_status=1", { cache: "no-store" });
                if (res.ok) {
                    const data = await res.json();
                    updateGlobalAirBadge(data.running);
                }
            } catch (e) {
                // Silently bypass transient connection drops
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.substring(1);
            if (hash && document.getElementById(hash)) {
                swapTab(hash);
            }
            // Poll station status every 4 seconds
            setInterval(pollAirStatus, 4000);
        });

        window.addEventListener('popstate', () => {
            const hash = window.location.hash.substring(1);
            if (hash && document.getElementById(hash)) {
                swapTab(hash);
            }
        });
    </script>
</head>
<body>

    <header class="rack-header">
        <div class="header-inner">
            <div class="station-mast">
                <?php 
                $isLive = !empty($View['sys']['is_broadcasting']); 
                ?>
                <span id="globalAirBadge" class="on-air-badge <?= $isLive ? 'is-live' : 'is-off' ?>">
                    <span id="globalAirText"><?= $isLive ? 'ON AIR' : 'OFF AIR' ?></span>
                </span>
                <span class="station-title"><?= htmlspecialchars($View['title']) ?></span>
                <div class="channel-dropdown-wrap">
                    <?= $View['nav']['channel_select'] ?>
                </div>
            </div>

            <div class="date-stepper">
                <?= $View['nav']['days_links'] ?>
            </div>

            <div class="rack-telemetry">
                <div class="meter-unit">
                    <span class="meter-lbl">SD/Mnt:</span>
                    <span class="meter-val"><?= implode(" / ", array_map('htmlspecialchars', $View['sys']['disk'])) ?></span>
                </div>
                <div class="meter-unit">
                    <span class="meter-lbl">CPU:</span>
                    <span class="meter-val <?= $View['sys']['load'] > 80 ? 'warn' : '' ?>"><?= (int)$View['sys']['load'] ?>%</span>
                </div>
                <div class="meter-unit">
                    <span class="meter-lbl">Core:</span>
                    <span class="meter-val"><?= (int)$View['sys']['temp_f'] ?>°F</span>
                </div>
                <div class="meter-unit">
                    <span class="meter-lbl">Up:</span>
                    <span class="meter-val"><?= htmlspecialchars($View['sys']['uptime']) ?></span>
                </div>
            </div>

            <a href="/?skip=1" class="btn-skip">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"></polygon><line x1="19" y1="5" x2="19" y2="19"></line></svg>
                SKIP BUS
            </a>
        </div>
    </header>

    <nav class="bus-nav">
        <button id="btnShows" class="tablinks active" onclick="swapTab('Shows')">Program Lineup</button>
        <button id="btnCommercials" class="tablinks" onclick="swapTab('Commercials')">Spots</button>
        <button id="btnMessages" class="tablinks" onclick="swapTab('Messages')">System Log</button>
        <button id="btnManage" class="tablinks" onclick="swapTab('Manage')">Rack Control</button>
        <button id="btnStats" class="tablinks" onclick="swapTab('Stats')">Rotation Metrics</button>
    </nav>

    <main>
        <!-- Tab 1: Shows Lineup -->
        <section id="Shows" class="tabcontent active">
            <div class="instrument-frame">
                <table class="rack-table">
                    <thead>
                        <tr>
                            <th width="120">Timestamp</th>
                            <th>Segment / Metadata</th>
                            <th width="90" style="text-align: right;">Ops</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($View['data']['shows'])): ?>
                            <tr><td colspan="3" style="text-align:center; color: var(--text-dim); padding: 2rem;">No transmission logs recorded for this schedule window.</td></tr>
                        <?php else: ?>
                            <?php 
                            $showCount = 0;
                            foreach ($View['data']['shows'] as $s): 
                                $showCount++;
                                $hexColor = ltrim($s['color'], '#');
                            ?>
                            <tr id="row<?= $showCount ?>" style="border-left: 4px solid #<?= $hexColor ?>;">
                                <td class="time-cell" valign="top">
                                    <?= date("H:i:s A", $s['timestamp']) ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: baseline; gap: 0.5rem; margin-bottom: 4px;">
                                        <a href="/?video=<?= $s['url'] ?>" style="font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars($s['name']) ?></a>
                                        <button class="btn-ctl btn-play-trigger" onclick="playVideo('/?video=<?= $s['url'] ?>', '<?= $showCount ?>')">▶ MON</button>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <span class="tag-pill" style="background: rgba(255,255,255,0.06); border: 1px solid var(--border-rack); color: var(--text-dim);"><?= htmlspecialchars($s['len']) ?></span>
                                        <span class="tag-pill" style="background: #<?= $hexColor ?>20; border: 1px solid #<?= $hexColor ?>; color: #<?= $hexColor ?>;"><?= htmlspecialchars($s['type']) ?></span>
                                    </div>
                                    <div class="scroll-box" style="display: none;" id="show<?= $showCount ?>"></div>
                                </td>
                                <td valign="top" style="text-align: right;">
                                    <div class="action-tray">
                                        <a href="javascript:void(0)" class="btn-ctl" title="Inspect Break Commercials" onclick="viewCommercials('<?= $s['url'] ?>', '<?= $showCount ?>')">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 6.5C18 5 16.5 4 14.5 4 10 4 6.5 7.5 6.5 12s3.5 8 8 8c2 0 3.5-1 4.5-2.5" stroke-width="3"></path><path d="M4.5 3c-1.5 2.5-2.5 5-2.5 9s1 6.5 2.5 9"></path></svg>
                                        </a>
                                        <button id="showAVFlagIcon_<?= $showCount ?>" class="btn-flag" onclick="flagVideo(<?= (int)$s['id'] ?>, '<?= $showCount ?>')" style="<?= $s['flag'] == 1 ? 'display: none;' : '' ?>" title="Flag Asset">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--crt-green)" stroke-width="2.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                                        </button>
                                        <button id="showAVUnFlagIcon_<?= $showCount ?>" class="btn-flag" onclick="unflagVideo(<?= (int)$s['id'] ?>, '<?= $showCount ?>')" style="<?= $s['flag'] == 0 ? 'display: none;' : '' ?>" title="Remove Flag">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--tally-red)" stroke-width="2.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line><line x1="2" y1="2" x2="22" y2="22"></line></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Tab 2: Commercials -->
        <section id="Commercials" class="tabcontent">
            <div class="instrument-frame">
                <table class="rack-table">
                    <thead>
                        <tr>
                            <th width="120">Timestamp</th>
                            <th width="140">Traffic Tier</th>
                            <th>Spot Filename</th>
                            <th width="120" style="text-align: right;">Ops</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($View['data']['commercials'])): ?>
                            <tr><td colspan="4" style="text-align:center; color: var(--text-dim); padding: 2rem;">No commercial spots queued for this block.</td></tr>
                        <?php else: ?>
                            <?php 
                            $commCount = 0;
                            foreach ($View['data']['commercials'] as $c): 
                                $commCount++;
                                $hexColor = ltrim($c['color'], '#');
                            ?>
                            <tr id="rowComm<?= $commCount ?>" style="border-left: 4px solid #<?= $hexColor ?>;">
                                <td class="time-cell">
                                    <?= date("H:i:s A", $c['timestamp']) ?>
                                </td>
                                <td>
                                    <span class="tag-pill" style="background: #<?= $hexColor ?>20; border: 1px solid #<?= $hexColor ?>; color: #<?= $hexColor ?>;">
                                        <?= htmlspecialchars($c['typeLabel']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: baseline; gap: 0.5rem;">
                                        <span style="font-family: var(--font-mono); color: var(--text-dim);">&#x<?= $c['emoji'] ?>;</span>
                                        <a href="/?video=<?= $c['videoUrl'] ?>" style="font-weight: 500;"><?= htmlspecialchars($c['filename']) ?></a>
                                        <button class="btn-ctl btn-play-trigger" onclick="playVideo('/?video=<?= $c['videoUrl'] ?>', 'Comm<?= $commCount ?>')">▶</button>
                                        <span style="font-family: var(--font-mono); font-size: 0.72rem; color: var(--text-dim);">(<?= htmlspecialchars($c['length']) ?>)</span>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <div class="action-tray">
                                        <a href="/videoeditor.php?file=<?= $c['videoUrl'] ?>" class="btn-ctl" title="Editor">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>
                                        <a href="/?delete=<?= $c['videoUrl'] ?>" class="btn-ctl" onclick="return confirm('Deleting spot is irreversible.\nConfirm?')" title="Purge" style="color: var(--tally-red);">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </a>
                                        <button id="commAVFlagIcon_<?= $commCount ?>" class="btn-flag" onclick="flagCommercial(<?= (int)$c['id'] ?>, '<?= $commCount ?>')" style="<?= $c['flag'] == 1 ? 'display: none;' : '' ?>" title="Flag Spot">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--crt-green)" stroke-width="2.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                                        </button>
                                        <button id="commAVUnFlagIcon_<?= $commCount ?>" class="btn-flag" onclick="unflagCommercial(<?= (int)$c['id'] ?>, '<?= $commCount ?>')" style="<?= $c['flag'] == 0 ? 'display: none;' : '' ?>" title="Unflag Spot">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--tally-red)" stroke-width="2.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line><line x1="2" y1="2" x2="22" y2="22"></line></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Tab 3: Messages / Alert Log -->
        <section id="Messages" class="tabcontent">
            <?php 
            $lastDate = null;
            if (empty($View['data']['messages'])):
            ?>
                <div class="instrument-frame" style="padding: 2rem; text-align: center; color: var(--text-dim); font-family: var(--font-mono);">
                    All operational loops nominal. Zero faults or system errors reported.
                </div>
            <?php else: ?>
                <?php foreach ($View['data']['messages'] as $m): 
                    $entryDate = date("Y-m-d", $m["timestamp"]);
                    if ($lastDate !== $entryDate):
                ?>
                    <div class="msg-feed-date"><?= $entryDate ?></div>
                <?php 
                        $lastDate = $entryDate;
                    endif;
                ?>
                <div class="msg-log-entry">
                    <div class="msg-header">
                        <span><?= htmlspecialchars($m['header']) ?></span>
                        <span class="time"><?= date("H:i:s", $m['timestamp']) ?> (x<?= (int)$m['repeats'] ?>)</span>
                    </div>
                    <ul>
                        <?php foreach ($m['details'] as $detail): ?>
                            <li><?= htmlspecialchars($detail) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Tab 4: Manage & Flagged Triage -->
        <section id="Manage" class="tabcontent">
            <div class="manage-grid">
                <?php if (empty($View['data']['manage']['cards'])): ?>
                    <div class="instrument-frame">
                        <div class="panel-heading">Hardware Cards</div>
                        <div style="padding: 1rem; color: var(--text-dim); font-size: 0.8rem;">No custom control cards detected in <code>/manage/</code>.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($View['data']['manage']['cards'] as $card): ?>
                        <div class="instrument-frame">
                            <div class="panel-heading"><?= htmlspecialchars($card['name']) ?></div>
                            <div style="padding: 0.75rem;">
                                <?php if (!empty($card['links'])): ?>
                                    <?php 
                                    $linkCount = 0;
                                    $cleanId = preg_replace('/[^a-zA-Z0-9]/', '', $card['name']);
                                    $openedExtra = false;
                                    ?>
                                    <ul style="list-style: none; margin-bottom: 0.5rem;">
                                        <?php foreach ($card['links'] as $link): ?>
                                            <?php 
                                            if ($linkCount == 5) {
                                                echo '</ul>';
                                                echo '<button class="btn-ctl" style="margin-bottom: 0.5rem;" onclick="document.getElementById(\'' . $cleanId . '-more\').style.display = \'block\'; this.style.display = \'none\';">Expand All Routines</button>';
                                                echo '<div style="display: none;" id="' . $cleanId . '-more"><ul style="list-style: none; margin-bottom: 0.5rem;">';
                                                $openedExtra = true;
                                            }
                                            $styleAttr  = !empty($link['style']) ? ' style="' . htmlspecialchars($link['style']) . '"' : '';
                                            $targetAttr = !empty($link['target']) ? ' target="' . htmlspecialchars($link['target']) . '"' : '';
                                            $actionAttr = !empty($link['action']) ? ' onclick="' . htmlspecialchars($link['action']) . '"' : '';
                                            ?>
                                            <li style="margin-bottom: 4px;">
                                                <a href="<?= htmlspecialchars($link['url']) ?>" class="btn-ctl" style="width: 100%; justify-content: flex-start;"<?= $styleAttr . $targetAttr . $actionAttr ?>>
                                                    › <?= htmlspecialchars($link['label']) ?>
                                                </a>
                                            </li>
                                            <?php $linkCount++; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if ($openedExtra) echo '</div>'; ?>
                                <?php endif; ?>

                                <?php if (!empty($card['html'])): ?>
                                    <div style="margin-top: 0.5rem;"><?= $card['html'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Triage Tables -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1rem;">
                <div class="instrument-frame">
                    <div class="panel-heading">Flagged Broadcast Sequences</div>
                    <table class="rack-table">
                        <tbody>
                            <?php if (empty($View['data']['flagged_videos'])): ?>
                                <tr><td style="color: var(--text-dim); text-align: center; padding: 1.5rem;">Clean slate. No video sequences flagged.</td></tr>
                            <?php else: ?>
                                <?php $fvCount = 0; foreach ($View['data']['flagged_videos'] as $v): $fvCount++; ?>
                                <tr id="manVid_<?= $fvCount ?>">
                                    <td><a href="/?video=<?= urlencode($v['name']) ?>" target="_blank" style="font-family: var(--font-mono); font-size: 0.78rem;"><?= htmlspecialchars($v['name']) ?></a></td>
                                    <td style="text-align: right; width: 140px;">
                                        <div class="action-tray">
                                            <button class="btn-ctl" onclick="unflagVideo(<?= (int)$v['id'] ?>, 'manVid_<?= $fvCount ?>');">Unflag</button>
                                            <button class="btn-ctl" style="border-color: var(--amber); color: var(--amber);" onclick="renameVideo('<?= htmlspecialchars(addslashes($v['name'])) ?>');">Rename</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="instrument-frame">
                    <div class="panel-heading">Flagged Interstitials & Ads</div>
                    <table class="rack-table">
                        <tbody>
                            <?php if (empty($View['data']['flagged_comms'])): ?>
                                <tr><td style="color: var(--text-dim); text-align: center; padding: 1.5rem;">Clean slate. No spots flagged.</td></tr>
                            <?php else: ?>
                                <?php $fcCount = 0; foreach ($View['data']['flagged_comms'] as $v): $fcCount++; ?>
                                <tr id="manComm_<?= $fcCount ?>">
                                    <td><a href="/?video=<?= urlencode($v['name']) ?>" target="_blank" style="font-family: var(--font-mono); font-size: 0.78rem;"><?= htmlspecialchars($v['name']) ?></a></td>
                                    <td style="text-align: right; width: 140px;">
                                        <div class="action-tray">
                                            <button class="btn-ctl" onclick="unflagCommercial(<?= (int)$v['id'] ?>, 'manComm_<?= $fcCount ?>');">Unflag</button>
                                            <button class="btn-ctl" style="border-color: var(--amber); color: var(--amber);" onclick="renameVideo('<?= htmlspecialchars(addslashes($v['name'])) ?>');">Rename</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Tab 5: Rotation Stats -->
        <section id="Stats" class="tabcontent">
            <div class="instrument-frame">
                <table class="rack-table">
                    <thead>
                        <tr>
                            <th width="140">Classification</th>
                            <th>Series Title</th>
                            <th width="100" style="text-align: right;">Air Playback Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $statCount = 0;
                        foreach ($View['data']['stats'] as $st): 
                            $statCount++;
                            $hexColor = ltrim($st['color'], '#');
                        ?>
                        <tr style="border-left: 4px solid #<?= $hexColor ?>;">
                            <td>
                                <span class="tag-pill" style="background: #<?= $hexColor ?>20; border: 1px solid #<?= $hexColor ?>; color: #<?= $hexColor ?>;">
                                    <?= htmlspecialchars($st['showType']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <button class="btn-ctl" onclick="showStats('<?= htmlspecialchars(addslashes($st['shortName'])) ?>', '<?= $statCount ?>')" style="padding: 1px 5px; font-size: 0.65rem;">EXPAND</button>
                                    <span style="font-weight: 600;"><?= htmlspecialchars($st['shortName']) ?></span>
                                </div>
                                <div id="stats<?= $statCount ?>" class="scroll-box" style="display: none;"></div>
                            </td>
                            <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; color: var(--crt-green);">
                                <?= (int)$st['occurrence'] ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Floating Master Monitor (Preview Video Out) -->
    <div id="player-container">
        <div class="monitor-titlebar">
            <span>MONITOR BUS [CH A]</span>
            <button class="monitor-btn-close" onclick="closePlayer();">✕ CLOSE</button>
        </div>
        <video id="vidplayer" controls autoplay></video>
    </div>

</body>
</html>
