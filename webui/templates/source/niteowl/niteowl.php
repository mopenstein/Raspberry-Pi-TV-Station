<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($View['title']) ?> // GUIDE</title>
    <style>
        :root {
            --epg-bg: #040817;
            --epg-blue-deep: #0a1435;
            --epg-blue-bar: #102258;
            --epg-blue-border: #1e3a8a;
            --epg-grid-line: #172554;
            --epg-gold: #ffcc00;
            --epg-gold-glow: rgba(255, 204, 0, 0.3);
            --epg-cyan: #38bdf8;
            --epg-white: #f8fafc;
            --epg-dim: #94a3b8;
            --epg-alert: #ef4444;
            --epg-live: #22c55e;
            --epg-live-glow: rgba(34, 197, 94, 0.35);
            --epg-font: "Courier New", Courier, monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--epg-bg);
            color: var(--epg-white);
            font-family: var(--epg-font);
            font-size: 13px;
            line-height: 1.4;
            letter-spacing: 0.03em;
            padding-bottom: 70px;
        }

        /* Subtle phosphor scanlines via pure local CSS gradients */
        body::before {
            content: " ";
            display: block;
            position: fixed;
            top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%);
            background-size: 100% 4px;
            z-index: 999;
            pointer-events: none;
            opacity: 0.6;
        }

        /* EPG Master Header Banner */
        .epg-header-block {
            background: linear-gradient(180deg, #162a6b 0%, #0c1842 100%);
            border-bottom: 3px double var(--epg-gold);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.8);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 8px 14px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .station-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Retro Dynamic Broadcast Terminal Tag */
        .epg-air-pill {
            font-family: var(--epg-font);
            font-weight: 900;
            font-size: 0.75rem;
            padding: 2px 7px;
            border-radius: 2px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            user-select: none;
            transition: all 0.25s ease;
        }

        .epg-air-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            background-color: currentColor;
        }

        /* ON AIR Active State */
        .epg-air-pill.is-live {
            background: rgba(34, 197, 94, 0.2);
            color: var(--epg-live);
            border: 1px solid var(--epg-live);
            box-shadow: 0 0 10px var(--epg-live-glow);
        }

        .epg-air-pill.is-live .epg-air-dot {
            box-shadow: 0 0 6px var(--epg-live);
            animation: epg-pulse 2s infinite ease-in-out;
        }

        /* OFF AIR Inactive State */
        .epg-air-pill.is-off {
            background: rgba(148, 163, 184, 0.1);
            color: var(--epg-dim);
            border: 1px solid #334155;
            box-shadow: none;
        }

        .epg-air-pill.is-off .epg-air-dot {
            opacity: 0.4;
            box-shadow: none;
            animation: none;
        }

        @keyframes epg-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .station-name {
            color: var(--epg-gold);
            font-weight: 900;
            font-size: 1.1rem;
            text-shadow: 1px 1px 0 #000;
            text-transform: uppercase;
        }

        .channel-dropdown-wrap select {
            background: #000;
            color: var(--epg-gold);
            border: 1px solid var(--epg-gold);
            font-family: var(--epg-font);
            font-size: 0.75rem;
            font-weight: bold;
            padding: 3px 6px;
        }

        .date-shifter {
            color: var(--epg-cyan);
            font-weight: bold;
            font-size: 0.85rem;
        }
        .date-shifter a { color: var(--epg-gold); text-decoration: none; }
        .date-shifter a:hover { text-decoration: underline; }

        /* Telemetry Ticker */
        .telemetry-ticker {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            background: #000;
            border: 1px solid var(--epg-blue-border);
            padding: 4px 10px;
            font-size: 0.75rem;
        }

        .telemetry-ticker span b { color: var(--epg-cyan); }

        .btn-skip-broadcast {
            background: #7f1d1d;
            border: 2px solid #ef4444;
            color: #fff;
            padding: 4px 10px;
            font-family: var(--epg-font);
            font-size: 0.75rem;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-skip-broadcast:hover {
            background: #ef4444;
            color: #000;
        }

        /* EPG Channel Selector Bar */
        .epg-nav-row {
            max-width: 1400px;
            margin: 10px auto 0;
            padding: 0 12px;
            display: flex;
            gap: 4px;
            border-bottom: 2px solid var(--epg-gold);
        }

        .tablinks {
            background: #070d24;
            border: 1px solid var(--epg-blue-border);
            border-bottom: none;
            color: var(--epg-dim);
            padding: 8px 16px;
            font-family: var(--epg-font);
            font-weight: bold;
            font-size: 0.8rem;
            cursor: pointer;
            text-transform: uppercase;
        }
        .tablinks:hover { background: var(--epg-blue-bar); color: #fff; }
        .tablinks.active {
            background: var(--epg-gold);
            color: #000;
            border-color: var(--epg-gold);
            font-weight: 900;
        }

        /* Content Area */
        main {
            max-width: 1400px;
            margin: 12px auto;
            padding: 0 12px;
        }

        .tabcontent { display: none; }
        .tabcontent.active { display: block; }

        /* Guide Grid Box */
        .guide-box {
            background: var(--epg-blue-deep);
            border: 2px solid var(--epg-blue-border);
            box-shadow: 0 4px 16px rgba(0,0,0,0.6);
        }

        table.guide-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        table.guide-table th {
            background: #000;
            color: var(--epg-gold);
            padding: 8px 10px;
            border-bottom: 2px solid var(--epg-gold);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        table.guide-table td {
            padding: 7px 10px;
            border-bottom: 1px solid var(--epg-grid-line);
            vertical-align: middle;
            font-size: 0.85rem;
        }

        table.guide-table tr:nth-child(even) td {
            background-color: rgba(0, 0, 0, 0.2);
        }

        /* Program Row Styling */
        .time-badge {
            color: var(--epg-cyan);
            font-weight: bold;
            white-space: nowrap;
        }

        .show-title-link {
            color: var(--epg-white);
            font-weight: bold;
            text-decoration: none;
        }
        .show-title-link:hover {
            color: var(--epg-gold);
            text-decoration: underline;
        }

        .guide-pill {
            display: inline-block;
            padding: 1px 6px;
            font-size: 0.65rem;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #475569;
            background: rgba(0, 0, 0, 0.4);
            margin-right: 4px;
        }

        /* Action Buttons */
        .epg-btn {
            background: #09122e;
            border: 1px solid var(--epg-blue-border);
            color: var(--epg-white);
            padding: 2px 8px;
            font-family: var(--epg-font);
            font-size: 0.72rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .epg-btn:hover {
            background: var(--epg-gold);
            color: #000;
            border-color: var(--epg-gold);
        }

        .epg-btn.play {
            background: #064e3b;
            border-color: #059669;
            color: #6ee7b7;
            font-weight: bold;
            margin-left: 6px;
        }
        .epg-btn.play:hover { background: #059669; color: #000; }

        .btn-flag-guide {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 2px;
            display: inline-flex;
            align-items: center;
        }

        .guide-expand-box {
            background: #000;
            border: 1px dashed var(--epg-blue-border);
            color: var(--epg-gold);
            padding: 6px 10px;
            font-size: 0.75rem;
            margin-top: 6px;
            width: 100%;
            overflow-x: auto;
            white-space: nowrap;
        }

        /* Log / Messages Feed */
        .log-date-divider {
            color: var(--epg-gold);
            font-size: 0.8rem;
            font-weight: bold;
            margin: 18px 0 6px;
            padding-bottom: 2px;
            border-bottom: 1px solid var(--epg-gold);
            text-transform: uppercase;
        }

        .log-card {
            background: #08102a;
            border-left: 4px solid var(--epg-gold);
            border-right: 1px solid var(--epg-blue-border);
            border-top: 1px solid var(--epg-blue-border);
            border-bottom: 1px solid var(--epg-blue-border);
            padding: 8px 12px;
            margin-bottom: 8px;
        }

        .log-header {
            color: var(--epg-gold);
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .log-card ul {
            list-style: square;
            padding-left: 18px;
            color: var(--epg-dim);
            font-size: 0.78rem;
        }

        /* Manage Tab Grid */
        .manage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .guide-box-header {
            background: #000;
            color: var(--epg-gold);
            border-bottom: 1px solid var(--epg-blue-border);
            padding: 6px 10px;
            font-weight: bold;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        /* CRT Floating Preview */
        #player-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 420px;
            background: #000;
            border: 2px solid var(--epg-gold);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.9);
            z-index: 1000;
            display: none;
        }

        .crt-bar {
            background: var(--epg-gold);
            color: #000;
            padding: 3px 6px;
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 0.72rem;
        }

        #vidplayer { width: 100%; display: block; background: #000; }

        @media (max-width: 768px) {
            .header-content { flex-direction: column; align-items: stretch; }
            .telemetry-ticker { justify-content: space-between; }
            #player-container { width: calc(100% - 24px); right: 12px; bottom: 12px; }

            /* 2-row retro keypad layout */
            .epg-nav-row {
                display: grid;
                grid-template-columns: repeat(6, 1fr);
                gap: 4px;
                border-bottom: 2px solid var(--epg-gold);
                padding: 0 8px;
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
                font-size: 0.68rem;
                text-align: center;
                border-bottom: 1px solid var(--epg-blue-border);
            }

            .tablinks.active {
                border-bottom: 1px solid var(--epg-gold);
            }
            
            html, body {
                overflow-x: hidden;
                max-width: 100vw;
            }

            main {
                padding: 0 4px;
                overflow-x: hidden;
            }

            .guide-box {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                width: 100%;
            }

            table.guide-table {
                table-layout: auto;
                min-width: 100%;
            }

            table.guide-table th, 
            table.guide-table td {
                padding: 6px 4px;
                font-size: 0.75rem;
                word-break: break-word;
                overflow-wrap: anywhere;
            }

            table.guide-table th[width="120"] {
                width: auto !important;
            }

            #Commercials table.guide-table th:first-child,
            #Commercials table.guide-table td:first-child {
                display: none;
            }

            #Commercials td[style*="text-align: right"] div {
                flex-direction: column;
                gap: 2px !important;
                align-items: flex-end;
            }

            #Commercials .epg-btn {
                padding: 2px 4px;
                font-size: 0.65rem;
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
            const toFile = prompt("RENAME FILE ENTRY:", fileName);
            if (!toFile) return;
            if (!confirm(`CONFIRM: Rename "${fileName}" to "${toFile}"?`)) return;
            ajax(`/?rename_video=${encodeURIComponent(fileName)}&to=${encodeURIComponent(toFile)}`, function(responseText) {
                alert(responseText.trim());
            });
        }

        function viewCommercials(video, showId) {
            const commDiv = document.getElementById('show' + showId);
            commDiv.innerHTML = 'SCANNING REEL DATA...';
            commDiv.style.display = 'block';
            ajax(`/?get_commercials=${video}&showId=${showId}`, function(responseText) {
                const [commercials, id] = responseText.split('|');
                const targetDiv = document.getElementById('show' + id);
                const listItems = commercials.trim().split("\n").map(c => `<div>* ${c}</div>`).join('');
                targetDiv.innerHTML = listItems;
            });
        }

        function showStats(shortName, id) {
            const commDiv = document.getElementById('stats' + id);
            commDiv.innerHTML = 'FETCHING PLAYBACK HISTOGRAM...';
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
            if (activeRow) activeRow.style.outline = "2px solid var(--epg-gold)";
        }

        function closePlayer() {
            const container = document.getElementById("player-container");
            const video = document.getElementById("vidplayer");
            video.pause();
            video.src = "";
            container.style.display = "none";
            document.querySelectorAll('tr[id^="row"]').forEach(el => el.style.outline = "none");
        }

        /* Asynchronous Status Check for EPG Theme */
        function updateEpgAirBadge(isRunning) {
            const badge = document.getElementById("epgAirBadge");
            const text = document.getElementById("epgAirText");
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

        async function pollEpgAirStatus() {
            try {
                const res = await fetch("/?station_status=1", { cache: "no-store" });
                if (res.ok) {
                    const data = await res.json();
                    updateEpgAirBadge(data.running);
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
            setInterval(pollEpgAirStatus, 4000);
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

    <header class="epg-header-block">
        <div class="header-content">
            <div class="station-brand">
                <?php $isBroadcasting = !empty($View['sys']['is_broadcasting']); ?>
                <span id="epgAirBadge" class="epg-air-pill <?= $isBroadcasting ? 'is-live' : 'is-off' ?>">
                    <span class="epg-air-dot"></span>
                    <span id="epgAirText"><?= $isBroadcasting ? 'ON AIR' : 'OFF AIR' ?></span>
                </span>
                <span class="station-name"><?= htmlspecialchars($View['title']) ?></span>
                <div class="channel-dropdown-wrap">
                    <?= $View['nav']['channel_select'] ?>
                </div>
            </div>

            <div class="date-shifter">
                <?= $View['nav']['days_links'] ?>
            </div>

            <div class="telemetry-ticker">
                <span>DISK: <b><?= implode(" | ", array_map('htmlspecialchars', $View['sys']['disk'])) ?></b></span>
                <span>CPU: <b style="color: <?= $View['sys']['load'] > 80 ? 'var(--epg-alert)' : 'var(--epg-cyan)' ?>;"><?= (int)$View['sys']['load'] ?>%</b></span>
                <span>TEMP: <b><?= (int)$View['sys']['temp_f'] ?>°F</b></span>
                <span>UP: <b><?= htmlspecialchars($View['sys']['uptime']) ?></b></span>
            </div>

            <a href="/?skip=1" class="btn-skip-broadcast">
                SKIP FEED &#9658;&#9658;
            </a>
        </div>
    </header>

    <nav class="epg-nav-row">
        <button id="btnShows" class="tablinks active" onclick="swapTab('Shows')">Broadcast Guide</button>
        <button id="btnCommercials" class="tablinks" onclick="swapTab('Commercials')">Station Breaks</button>
        <button id="btnMessages" class="tablinks" onclick="swapTab('Messages')">System Bulletin</button>
        <button id="btnManage" class="tablinks" onclick="swapTab('Manage')">Program Ops</button>
        <button id="btnStats" class="tablinks" onclick="swapTab('Stats')">Airplay Log</button>
    </nav>

    <main>
        <!-- Tab 1: Shows / Broadcast Guide -->
        <section id="Shows" class="tabcontent active">
            <div class="guide-box">
                <table class="guide-table">
                    <thead>
                        <tr>
                            <th width="120">TIME (EST)</th>
                            <th>PROGRAMMING DETAILS</th>
                            <th width="100" style="text-align: right;">CONTROL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($View['data']['shows'])): ?>
                            <tr><td colspan="3" style="text-align:center; padding: 2rem; color: var(--epg-gold);">*** NO TRANSMISSIONS SCHEDULED FOR THIS BLOCK ***</td></tr>
                        <?php else: ?>
                            <?php 
                            $showCount = 0;
                            foreach ($View['data']['shows'] as $s): 
                                $showCount++;
                                $hexColor = ltrim($s['color'], '#');
                            ?>
                            <tr id="row<?= $showCount ?>" style="border-left: 5px solid #<?= $hexColor ?>;">
                                <td class="time-badge" valign="top">
                                    <?= date("g:i:s A", $s['timestamp']) ?>
                                </td>
                                <td>
                                    <div style="margin-bottom: 4px;">
                                        <a href="/?video=<?= $s['url'] ?>" class="show-title-link"><?= htmlspecialchars($s['name']) ?></a>
                                        <button class="epg-btn play" onclick="playVideo('/?video=<?= $s['url'] ?>', '<?= $showCount ?>')">PREVIEW</button>
                                    </div>
                                    <div>
                                        <span class="guide-pill" style="color: #fff; border-color: #64748b;"><?= htmlspecialchars($s['len']) ?></span>
                                        <span class="guide-pill" style="color: #<?= $hexColor ?>; border-color: #<?= $hexColor ?>;"><?= htmlspecialchars($s['type']) ?></span>
                                    </div>
                                    <div id="show<?= $showCount ?>" class="guide-expand-box" style="display: none;"></div>
                                </td>
                                <td valign="top" style="text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                        <a href="javascript:void(0)" class="epg-btn" title="Inspect Spots" onclick="viewCommercials('<?= $s['url'] ?>', '<?= $showCount ?>')">ADS</a>
                                        <button id="showAVFlagIcon_<?= $showCount ?>" class="btn-flag-guide" onclick="flagVideo(<?= (int)$s['id'] ?>, '<?= $showCount ?>')" style="<?= $s['flag'] == 1 ? 'display: none;' : '' ?>" title="Flag Program">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                                        </button>
                                        <button id="showAVUnFlagIcon_<?= $showCount ?>" class="btn-flag-guide" onclick="unflagVideo(<?= (int)$s['id'] ?>, '<?= $showCount ?>')" style="<?= $s['flag'] == 0 ? 'display: none;' : '' ?>" title="Unflag Program">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line><line x1="2" y1="2" x2="22" y2="22"></line></svg>
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
            <div class="guide-box">
                <table class="guide-table">
                    <thead>
                        <tr>
                            <th width="120">TIME</th>
                            <th width="120">TIER</th>
                            <th>INTERSTITIAL / AD TITLE</th>
                            <th width="120" style="text-align: right;">OPS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($View['data']['commercials'])): ?>
                            <tr><td colspan="4" style="text-align:center; padding: 2rem; color: var(--epg-gold);">*** ZERO INTERSTITIAL ENTRIES ***</td></tr>
                        <?php else: ?>
                            <?php 
                            $commCount = 0;
                            foreach ($View['data']['commercials'] as $c): 
                                $commCount++;
                                $hexColor = ltrim($c['color'], '#');
                            ?>
                            <tr id="rowComm<?= $commCount ?>" style="border-left: 5px solid #<?= $hexColor ?>;">
                                <td class="time-badge"><?= date("g:i:s A", $c['timestamp']) ?></td>
                                <td><span class="guide-pill" style="color: #<?= $hexColor ?>; border-color: #<?= $hexColor ?>;"><?= htmlspecialchars($c['typeLabel']) ?></span></td>
                                <td>
                                    <span style="color: var(--epg-dim);">&#x<?= $c['emoji'] ?>;</span>
                                    <a href="/?video=<?= $c['videoUrl'] ?>" class="show-title-link"><?= htmlspecialchars($c['filename']) ?></a>
                                    <button class="epg-btn play" onclick="playVideo('/?video=<?= $c['videoUrl'] ?>', 'Comm<?= $commCount ?>')">&#9658;</button>
                                    <span style="color: var(--epg-dim); font-size: 0.75rem;">(<?= htmlspecialchars($c['length']) ?>)</span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                        <a href="/videoeditor.php?file=<?= $c['videoUrl'] ?>" class="epg-btn">EDIT</a>
                                        <a href="/?delete=<?= $c['videoUrl'] ?>" class="epg-btn" onclick="return confirm('DELETE SPOT RECORD?')" style="color: var(--epg-alert);">DEL</a>
                                        <button id="commAVFlagIcon_<?= $commCount ?>" class="btn-flag-guide" onclick="flagCommercial(<?= (int)$c['id'] ?>, '<?= $commCount ?>')" style="<?= $c['flag'] == 1 ? 'display: none;' : '' ?>">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                                        </button>
                                        <button id="commAVUnFlagIcon_<?= $commCount ?>" class="btn-flag-guide" onclick="unflagCommercial(<?= (int)$c['id'] ?>, '<?= $commCount ?>')" style="<?= $c['flag'] == 0 ? 'display: none;' : '' ?>">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line><line x1="2" y1="2" x2="22" y2="22"></line></svg>
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

        <!-- Tab 3: System Log -->
        <section id="Messages" class="tabcontent">
            <?php 
            $lastDate = null;
            if (empty($View['data']['messages'])):
            ?>
                <div class="guide-box" style="padding: 2rem; text-align: center; color: var(--epg-gold);">
                    *** NO CARRIER ANOMALIES RECORDED ***
                </div>
            <?php else: ?>
                <?php foreach ($View['data']['messages'] as $m): 
                    $entryDate = date("m/d/Y", $m["timestamp"]);
                    if ($lastDate !== $entryDate):
                ?>
                    <div class="log-date-divider"><?= $entryDate ?> BULLETINS</div>
                <?php 
                        $lastDate = $entryDate;
                    endif;
                ?>
                <div class="log-card">
                    <div class="log-header">
                        <span><?= htmlspecialchars($m['header']) ?></span>
                        <span><?= date("h:i:s A", $m['timestamp']) ?> [REPEATS: <?= (int)$m['repeats'] ?>]</span>
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

        <!-- Tab 4: Program Ops & Flagged Triage -->
        <section id="Manage" class="tabcontent">
            <div class="manage-grid">
                <?php if (empty($View['data']['manage']['cards'])): ?>
                    <div class="guide-box">
                        <div class="guide-box-header">OPERATIONS CARDS</div>
                        <div style="padding: 1rem; color: var(--epg-dim);">No expansion cards loaded.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($View['data']['manage']['cards'] as $card): ?>
                        <div class="guide-box">
                            <div class="guide-box-header"><?= htmlspecialchars($card['name']) ?></div>
                            <div style="padding: 8px;">
                                <?php if (!empty($card['links'])): ?>
                                    <?php 
                                    $linkCount = 0;
                                    $cleanId = preg_replace('/[^a-zA-Z0-9]/', '', $card['name']);
                                    $openedExtra = false;
                                    ?>
                                    <ul style="list-style: none; margin-bottom: 6px;">
                                        <?php foreach ($card['links'] as $link): ?>
                                            <?php 
                                            if ($linkCount == 5) {
                                                echo '</ul>';
                                                echo '<button class="epg-btn" style="margin-bottom: 6px;" onclick="document.getElementById(\'' . $cleanId . '-more\').style.display = \'block\'; this.style.display = \'none\';">EXPAND ADDITIONAL FUNCTIONS</button>';
                                                echo '<div style="display: none;" id="' . $cleanId . '-more"><ul style="list-style: none; margin-bottom: 6px;">';
                                                $openedExtra = true;
                                            }
                                            $styleAttr  = !empty($link['style']) ? ' style="' . htmlspecialchars($link['style']) . '"' : '';
                                            $targetAttr = !empty($link['target']) ? ' target="' . htmlspecialchars($link['target']) . '"' : '';
                                            $actionAttr = !empty($link['action']) ? ' onclick="' . htmlspecialchars($link['action']) . '"' : '';
                                            ?>
                                            <li style="margin-bottom: 4px;">
                                                <a href="<?= htmlspecialchars($link['url']) ?>" class="epg-btn" style="width: 100%;"<?= $styleAttr . $targetAttr . $actionAttr ?>>
                                                    &#9656; <?= htmlspecialchars($link['label']) ?>
                                                </a>
                                            </li>
                                            <?php $linkCount++; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if ($openedExtra) echo '</div>'; ?>
                                <?php endif; ?>

                                <?php if (!empty($card['html'])): ?>
                                    <div style="margin-top: 6px;"><?= $card['html'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Triage Decks -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 12px;">
                <div class="guide-box">
                    <div class="guide-box-header">FLAGGED TRANSMISSIONS</div>
                    <table class="guide-table">
                        <tbody>
                            <?php if (empty($View['data']['flagged_videos'])): ?>
                                <tr><td style="color: var(--epg-dim); text-align: center; padding: 1.5rem;">NONE RECORDED</td></tr>
                            <?php else: ?>
                                <?php $fvCount = 0; foreach ($View['data']['flagged_videos'] as $v): $fvCount++; ?>
                                <tr id="manVid_<?= $fvCount ?>">
                                    <td><a href="/?video=<?= urlencode($v['name']) ?>" target="_blank" class="show-title-link"><?= htmlspecialchars($v['name']) ?></a></td>
                                    <td style="text-align: right; width: 140px;">
                                        <button class="epg-btn" onclick="unflagVideo(<?= (int)$v['id'] ?>, 'manVid_<?= $fvCount ?>');">RESOLVE</button>
                                        <button class="epg-btn" style="color: var(--epg-gold);" onclick="renameVideo('<?= htmlspecialchars(addslashes($v['name'])) ?>');">RENAME</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="guide-box">
                    <div class="guide-box-header">FLAGGED INTERSTITIALS</div>
                    <table class="guide-table">
                        <tbody>
                            <?php if (empty($View['data']['flagged_comms'])): ?>
                                <tr><td style="color: var(--epg-dim); text-align: center; padding: 1.5rem;">NONE RECORDED</td></tr>
                            <?php else: ?>
                                <?php $fcCount = 0; foreach ($View['data']['flagged_comms'] as $v): $fcCount++; ?>
                                <tr id="manComm_<?= $fcCount ?>">
                                    <td><a href="/?video=<?= urlencode($v['name']) ?>" target="_blank" class="show-title-link"><?= htmlspecialchars($v['name']) ?></a></td>
                                    <td style="text-align: right; width: 140px;">
                                        <button class="epg-btn" onclick="unflagCommercial(<?= (int)$v['id'] ?>, 'manComm_<?= $fcCount ?>');">RESOLVE</button>
                                        <button class="epg-btn" style="color: var(--epg-gold);" onclick="renameVideo('<?= htmlspecialchars(addslashes($v['name'])) ?>');">RENAME</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Tab 5: Airplay Histogram -->
        <section id="Stats" class="tabcontent">
            <div class="guide-box">
                <table class="guide-table">
                    <thead>
                        <tr>
                            <th width="150">GENRE CODE</th>
                            <th>ASSET TITLE</th>
                            <th width="120" style="text-align: right;">SPINS RECORDED</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $statCount = 0;
                        foreach ($View['data']['stats'] as $st): 
                            $statCount++;
                            $hexColor = ltrim($st['color'], '#');
                        ?>
                        <tr style="border-left: 5px solid #<?= $hexColor ?>;">
                            <td><span class="guide-pill" style="color: #<?= $hexColor ?>; border-color: #<?= $hexColor ?>;"><?= htmlspecialchars($st['showType']) ?></span></td>
                            <td>
                                <div>
                                    <button class="epg-btn" onclick="showStats('<?= htmlspecialchars(addslashes($st['shortName'])) ?>', '<?= $statCount ?>')">+</button>
                                    <span style="font-weight: bold; margin-left: 6px;"><?= htmlspecialchars($st['shortName']) ?></span>
                                </div>
                                <div id="stats<?= $statCount ?>" class="guide-expand-box" style="display: none;"></div>
                            </td>
                            <td style="text-align: right; color: var(--epg-gold); font-weight: 900; font-size: 0.95rem;">
                                <?= (int)$st['occurrence'] ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Floating CRT Player -->
    <div id="player-container">
        <div class="crt-bar">
            <span>*** MONITOR DIRECT OUT ***</span>
            <button onclick="closePlayer();" style="background: none; border: none; font-weight: bold; cursor: pointer;">[X]</button>
        </div>
        <video id="vidplayer" controls autoplay></video>
    </div>

</body>
</html>
