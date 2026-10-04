<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $View['title'] ?></title>
    <style>
        :root {
            --win-bg: #c0c0c0;
            --win-border-light: #ffffff;
            --win-border-midlight: #dfdfdf;
            --win-border-middark: #808080;
            --win-border-dark: #000000;
            --win-title: #000080;
            --win-title-text: #ffffff;
            --win-text: #000000;
            --win-highlight: #000080;
            --win-highlight-text: #ffffff;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            background-color: #008080; /* Classic Win95/98 Teal Background */
            color: var(--win-text); 
            font-family: "MS Sans Serif", Tahoma, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            padding: 20px;
        }

        /* Generic Window Container */
        .window {
            background-color: var(--win-bg);
            border-top: 1px solid var(--win-border-light);
            border-left: 1px solid var(--win-border-light);
            border-right: 1px solid var(--win-border-dark);
            border-bottom: 1px solid var(--win-border-dark);
            box-shadow: inset 1px 1px var(--win-border-midlight), inset -1px -1px var(--win-border-middark);
            padding: 2px;
            margin: 0 auto;
            max-width: 1200px;
        }

        .title-bar {
            background-color: var(--win-title);
            color: var(--win-title-text);
            padding: 3px 4px 3px 4px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }
        
        .title-bar-controls button {
            background: var(--win-bg);
            border-top: 1px solid var(--win-border-light);
            border-left: 1px solid var(--win-border-light);
            border-right: 1px solid var(--win-border-dark);
            border-bottom: 1px solid var(--win-border-dark);
            box-shadow: inset 1px 1px var(--win-border-midlight), inset -1px -1px var(--win-border-middark);
            font-family: monospace;
            font-size: 10px;
            font-weight: bold;
            padding: 1px 4px;
            cursor: pointer;
        }
        .title-bar-controls button:active {
            border-top: 1px solid var(--win-border-dark);
            border-left: 1px solid var(--win-border-dark);
            border-right: 1px solid var(--win-border-light);
            border-bottom: 1px solid var(--win-border-light);
            box-shadow: inset 1px 1px var(--win-border-middark), inset -1px -1px var(--win-border-midlight);
        }

        /* Toolbar */
        .toolbar {
            padding: 6px;
            border-bottom: 1px solid var(--win-border-middark);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Generic Buttons */
        .btn, .btn-skip {
            background: var(--win-bg);
            color: var(--win-text);
            border-top: 1px solid var(--win-border-light);
            border-left: 1px solid var(--win-border-light);
            border-right: 1px solid var(--win-border-dark);
            border-bottom: 1px solid var(--win-border-dark);
            box-shadow: inset 1px 1px var(--win-border-midlight), inset -1px -1px var(--win-border-middark);
            padding: 4px 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-family: inherit;
            font-size: inherit;
            gap: 4px;
        }
        .btn:active, .btn-skip:active {
            border-top: 1px solid var(--win-border-dark);
            border-left: 1px solid var(--win-border-dark);
            border-right: 1px solid var(--win-border-light);
            border-bottom: 1px solid var(--win-border-light);
            box-shadow: inset 1px 1px var(--win-border-middark), inset -1px -1px var(--win-border-midlight);
            padding: 5px 9px 3px 11px; /* visual push effect */
        }
        
        .btn-play { padding: 2px 6px; font-size: 10px; margin-left: 4px;}

        /* Status Bar */
        .status-bar {
            display: flex;
            flex-wrap: wrap;
            margin-top: 4px;
            gap: 2px;
        }
        .status-item {
            border-top: 1px solid var(--win-border-middark);
            border-left: 1px solid var(--win-border-middark);
            border-right: 1px solid var(--win-border-light);
            border-bottom: 1px solid var(--win-border-light);
            padding: 3px 6px;
            flex: 1;
            white-space: nowrap;
        }

        /* Navigation / Tabs */
        nav {
            margin: 10px 6px 0 6px;
            display: flex;
            position: relative;
            z-index: 9`;
        }

        .tablinks {
            background: var(--win-bg);
            border-top: 1px solid var(--win-border-light);
            border-left: 1px solid var(--win-border-light);
            border-right: 1px solid var(--win-border-dark);
            border-bottom: 1px solid var(--win-border-light);
            color: var(--win-text);
            padding: 4px 10px;
            cursor: pointer;
            font-family: inherit;
            font-size: inherit;
            margin-right: 2px;
            position: relative;
            top: 3px;
            z-index: 1;
        }
        .tablinks.active {
            top: 1px;
            padding-top: 6px;
            padding-bottom: 8px;
            margin-bottom: -1px;
            border-bottom: 1px solid var(--win-bg);
            background: var(--win-bg);
            z-index: 30;
            font-weight: bold;
            box-shadow: inset 1px 1px var(--win-border-midlight);
        }

        /* Content Areas */
        main { 
            padding: 0 6px;
            z-index: 100;
        }

        .tabcontent {
            display: none;
            background: var(--win-bg);
            border-top: 1px solid var(--win-border-light);
            border-left: 1px solid var(--win-border-light);
            border-right: 1px solid var(--win-border-dark);
            border-bottom: 1px solid var(--win-border-dark);
            box-shadow: inset 1px 1px var(--win-border-midlight), inset -1px -1px var(--win-border-middark);
            padding: 12px;
            position: relative;
            z-index: 2;
        }
        .tabcontent.active { display: block; }

        /* Tables & Lists */
        .card-table-wrapper {
            background: #ffffff;
            border-top: 1px solid var(--win-border-middark);
            border-left: 1px solid var(--win-border-middark);
            border-right: 1px solid var(--win-border-light);
            border-bottom: 1px solid var(--win-border-light);
            overflow: auto;
            margin-bottom: 10px;
        }

        table { width: 100%; border-collapse: collapse; text-align: left; background: #ffffff; }
        th { 
            background: var(--win-bg);
            border-top: 1px solid var(--win-border-light);
            border-left: 1px solid var(--win-border-light);
            border-right: 1px solid var(--win-border-dark);
            border-bottom: 1px solid var(--win-border-dark);
            box-shadow: inset 1px 1px var(--win-border-midlight), inset -1px -1px var(--win-border-middark);
            padding: 4px;
            font-weight: normal;
        }
        td { padding: 4px; border-bottom: 1px solid #e0e0e0; }

        /* Badges */
        .type-badge {
            font-size: 10px;
            padding: 1px 4px;
            background: #ffffff;
            color: #000000;
            border: 1px solid var(--win-border-middark);
            text-transform: uppercase;
        }

        /* Fieldsets (replaces modern cards/groupings) */
        fieldset {
            border-top: 1px solid var(--win-border-middark);
            border-left: 1px solid var(--win-border-middark);
            border-right: 1px solid var(--win-border-light);
            border-bottom: 1px solid var(--win-border-light);
            padding: 10px;
            margin-bottom: 15px;
        }
        legend {
            padding: 0 4px;
            color: var(--win-text);
        }

        .hidden { display: none; }
        a { color: #0000ff; text-decoration: underline; }
        a:hover { color: #ff0000; }

        .scroll-box {
            background: #ffffff;
            border-top: 1px solid var(--win-border-middark);
            border-left: 1px solid var(--win-border-middark);
            border-right: 1px solid var(--win-border-light);
            border-bottom: 1px solid var(--win-border-light);
            padding: 4px;
            font-family: monospace;
            font-size: 11px;
            margin-top: 4px;
            width: 100%;
            box-sizing: border-box;
            overflow-x: auto;
            white-space: nowrap;
        }

        #player-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 400px;
            z-index: 1000;
            display: none;
        }
        #vidplayer { width: 100%; display: block; background: #000; border-top: 1px solid var(--win-border-middark); border-left: 1px solid var(--win-border-middark); }

        @media (max-width: 600px) {
            #player-container { width: calc(100% - 40px); bottom: 10px; right: 10px; }
            .status-bar { flex-direction: column; }
        }
    </style>
    <script>

		function fadeAfterDelay(el, seconds) {		
			if (!el) return;
			setTimeout(() => {
				el.style.display = 'none';
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

				element.innerHTML = `<ul style="padding-left:20px; margin:0;">${listItems}</ul>`;
				fadeAfterDelay(element, 2);
			}
		}

		function flagVideo(vid, id) {
			ajax("/?flag_video="+vid+"&id="+id, flagCallback);
		}

		function unflagVideo(vid, id) {
			ajax("/?unflag_video="+vid+"&id="+id, flagCallback);
		}

		function flagCommercial(vid, id) {
			ajax("/?flag_comm="+vid+"&id="+id, flagCallback);
		}

		function unflagCommercial(vid, id) {
			ajax("/?unflag_comm="+vid+"&id="+id, flagCallback);
		}

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

				targetDiv.innerHTML = `<ul style="padding-left:20px; margin:0;">${listItems}</ul>`;
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
			
			// Try calling startCounter if it exists globally
            if (typeof startCounter === "function") {
                startCounter();
            }
			
			// Highlight current with classic Windows selection color
			document.querySelectorAll('tr').forEach(el => {
                if(el.dataset.bg) el.style.background = el.dataset.bg;
                el.style.color = "";
                Array.from(el.getElementsByTagName('a')).forEach(a => a.style.color = "");
            });
			
            const activeRow = document.getElementById('row' + id);
            if (activeRow) {
                if(!activeRow.dataset.bg) activeRow.dataset.bg = activeRow.style.background;
                activeRow.style.background = "#000080";
                activeRow.style.color = "#ffffff";
                Array.from(activeRow.getElementsByTagName('a')).forEach(a => a.style.color = "#ffffff");
            }
		}

		function closePlayer() {
			const container = document.getElementById("player-container");
			const video = document.getElementById("vidplayer");
			video.pause();
			video.src = "";
			container.style.display = "none";

			// Remove highlight
			document.querySelectorAll('tr').forEach(el => {
                if(el.dataset.bg) el.style.background = el.dataset.bg;
                el.style.color = "";
                Array.from(el.getElementsByTagName('a')).forEach(a => a.style.color = "");
            });
		}

		window.addEventListener('DOMContentLoaded', () => {
    		const hash = window.location.hash.substring(1);
    		if (hash) {
				const targetTab = document.getElementById(hash);
				if (targetTab) {
					swapTab(hash);
				}
    		}
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

    <div class="window">
        <div class="title-bar">
            <span><?= $View['title'] ?></span>
            <div class="title-bar-controls">
                <button>?</button>
                <button>X</button>
            </div>
        </div>

        <header>
            <div class="toolbar">
                <div>
                    <?= $View['nav']['days_links'] ?>
                </div>

                <a href="/?skip=1" class="btn-skip">
                    SKIP <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"></polygon><line x1="19" y1="5" x2="19" y2="19"></line></svg>
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
                            <tr id="row<?= $showCount ?>" style="background-color: #<?= $s['color'] ?>5A;">
                                <td style="font-family: monospace;" valign="top"><?= date("h:i\<\s\m\a\l\l\>:s\<\/\s\m\a\l\l\> A",$s['timestamp']) ?></td>
                                <td>
                                    <div style="margin-bottom: 2px;"><a href="/?video=<?= $s['url'] ?>"><?= $s['name'] ?></a> <button class="btn btn-play" id="plus<?= $showCount ?>" onclick="playVideo('/?video=<?= $s['url'] ?>', <?= $showCount ?>)">▶</button></div>
                                    <div><span class="type-badge"><?= $s['len'] ?></span> <span class="type-badge"><?= $s['type'] ?></span></div>
                                    <div class="scroll-box" style="display: none; width: 100%;" id="show<?= $showCount ?>"></div>
                                </td>
                                <td style="text-align: right;" valign="top">
                                    <a href="javascript:void(0)" onclick="viewCommercials('<?= $s['url'] ?>', <?= $showCount ?>)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 6.5C18 5 16.5 4 14.5 4 10 4 6.5 7.5 6.5 12s3.5 8 8 8c2 0 3.5-1 4.5-2.5" stroke-width="3"></path><path d="M4.5 3c-1.5 2.5-2.5 5-2.5 9s1 6.5 2.5 9"></path></svg></a>
                                        <a href="javascript:void(0)" id="showAVFlagIcon_<?= $showCount ?>" onclick="flagVideo(<?= $s['id'] ?>, <?= $showCount ?>)" <?php if($s['flag'] == 1) { echo 'style="display: none;"'; } ?> ><svg id="showVFlagIcon_<?= $showCount ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="green" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg></a>
                                        <a href="javascript:void(0)" id="showAVUnFlagIcon_<?= $showCount ?>" onclick="unflagVideo(<?= $s['id'] ?>, <?= $showCount ?>)" <?php if($s['flag'] == 0) { echo 'style="display: none;"'; } ?> ><svg id="showVUnFlagIcon_<?= $showCount ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line><line x1="2" y1="2" x2="22" y2="22" opacity="0.9"></line></svg></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Commercials -->
            <div id="Commercials" class="tabcontent">
              <div class="card-table-wrapper">
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
                        <tr class="comm-row" id="rowComm<?= $count ?>" style="background-color: #<?= $s['color'] ?>5A;">
                            <td align="center" style="font-family: monospace; padding-left:10px;"><?= date("h:i\<\s\m\a\l\l\>:s\<\/\s\m\a\l\l\> A",$s['timestamp']) ?></td>
                            <td align="center"><strong><?= $s['typeLabel'] ?></strong></td>
                            <td style="padding-left:20px;">
                                <?= $s['monthPrefix'] ?>. &#x<?= $s['emoji'] ?>; 
                                <a href="/?video=<?= $s['videoUrl'] ?>"><?= $s['filename'] ?></a> <button class="btn btn-play" id="plus<?= $showCount ?>" onclick="playVideo('/?video=<?= $s['videoUrl'] ?>', 'Comm<?= $count ?>')">▶</button>
                                <span style="font-size: 10px;">(<?= $s['length'] ?>)</span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 4px;">
                                    <a href="/videoeditor.php?file=<?= $s['videoUrl'] ?>" title="Edit"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a> 
                                    <a href="/?delete=<?= $s['videoUrl'] ?>" onclick="return confirm('Deleting video is permanent.\n\nAre you sure?')" title="Delete"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a>
                                    <a href="javascript:void(0)" id="commAVFlagIcon_<?= $count ?>" onclick="flagCommercial(<?= $s['id'] ?>, <?= $count ?>)" <?php if($s['flag'] == 1) { echo 'style="display: none;"'; } ?> ><svg id="commVFlagIcon_<?= $count ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="green" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg></a>
                                    <a href="javascript:void(0)" id="commAVUnFlagIcon_<?= $count ?>" onclick="unflagCommercial(<?= $s['id'] ?>, <?= $count ?>)" <?php if($s['flag'] == 0) { echo 'style="display: none;"'; } ?> ><svg id="commVUnFlagIcon_<?= $count ?>" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line><line x1="2" y1="2" x2="22" y2="22" opacity="0.9"></line></svg></a>
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
                <div>
                    <?php
                        $lastDate = null; 
                        foreach ($View['data']['messages'] as $m):
                            $currentDate = date("m/d/y", $m["timestamp"]);

                            if ($lastDate !== $currentDate):
                    ?>
                                <fieldset style="border-bottom:none; border-left:none; border-right:none; margin: 15px 0 5px 0;">
                                    <legend><b><?= $currentDate ?></b></legend>
                                </fieldset>
                    <?php 
                                $lastDate = $currentDate;
                            endif; 
                    ?>
                    
                    <fieldset>
                        <legend><?= date("h:i:s A", $m["timestamp"]) ?></legend>
                        <div style="font-weight: bold; margin-bottom: 4px;"><?= $m['header'] ?></div>
                        <ul style="padding-left: 20px; list-style-type: square;">
                            <?php foreach ($m['details'] as $d): ?><li><?= $d ?></li><?php endforeach; ?>
                        </ul>
                    </fieldset>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Manage -->
            <section id="Manage" class="tabcontent">
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">

                    <!-- Dynamic Cards -->
                    <?php if (empty($View['data']['manage']['cards'])): ?>
                        <fieldset style="flex: 1; min-width: 300px;">
                            <legend>Manage Cards</legend>
                            <div style="padding: 10px; text-align: center;">
                                <b>No cards to show.</b><br />
                                <span style="color:red;">Add some to the /manage/ directory to see them here.</span>
                            </div>
                        </fieldset>
                    <?php else: ?>
                    
                        <?php foreach ($View['data']['manage']['cards'] as $cards): ?>
                        <fieldset style="flex: 1; min-width: 300px;">
                            <legend><?= $cards['name'] ?></legend>
                            <?php if (!empty($cards['links'])): ?>
								<?php
								$count = 0;
								$clean_id = preg_replace('/[^a-zA-Z0-9]/', '', $cards['name']);
								$opened_extra = false;
								?>
								<ul style="list-style-type: square; padding-left: 20px; margin-top: 5px;">
								<?php foreach ($cards['links'] as $link): ?>
									<?php
									if ($count == 5) {
										// Close the first <ul> cleanly
										echo '</ul>';
										// Output the button and the hidden wrapper container
										echo '<button class="btn" style="margin: 5px 0 5px 20px;" onclick="document.getElementById(\'' . $clean_id . '-extra-links\').style.display = \'block\'; this.style.display = \'none\';">Show More Links</button>';
										echo '<div style="display:none;" id="' . $clean_id . '-extra-links">';
										// Start a new valid <ul> inside the hidden div
										echo '<ul style="list-style-type: square; padding-left: 20px; margin-top: 0;">';
										$opened_extra = true;
									}

									$style = !empty($link['style']) ? ' style="' . $link['style'] . '"' : '';
									$target = !empty($link['target']) ? ' target="' . $link['target'] . '"' : '';
									$action = !empty($link['action']) ? ' onclick="' . $link['action'] . '"' : '';

									if (isset($link['url']) && isset($link['label'])) {
										echo '<li style="margin-bottom: 4px;"><a href="' . $link['url'] . '"' . $style . $target . $action . '>' . $link['label'] . '</a></li>';
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
                        </fieldset>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <fieldset>
                    <legend>Flagged Videos</legend>
                    <div class="card-table-wrapper" style="margin: 5px;">
                        <table>
                            <tbody>
                                <?php if (empty($View['data']['flagged_videos'])): ?>
                                    <tr><td style="text-align: center;">Nothing to see here</td></tr>
                                <?php else: ?>
                                    <?php 
                                        $count = 0;
                                        foreach ($View['data']['flagged_videos'] as $v):
                                            $count++;
                                    ?>
                                    <tr id="manVid_<?= $count ?>">
                                        <td><a href="/?video=<?= urlencode($v['name']) ?>" target="_blank"><?= $v['name'] ?></a></td>
                                        <td style="text-align: right; display: flex; gap: 4px; justify-content: flex-end;">
                                            <button class="btn" onclick="unflagVideo(<?= $v['id'] ?>, 'manVid_<?= $count ?>');">unflag</button>
                                            <button class="btn" onclick="renameVideo('<?= $v['name'] ?>');">rename</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Flagged Commercials</legend>
                    <div class="card-table-wrapper" style="margin: 5px;">
                        <table>
                            <tbody>
                                <?php if (empty($View['data']['flagged_comms'])): ?>
                                    <tr><td style="text-align: center;">Nothing to see here</td></tr>
                                <?php else: ?>
                                    <?php 
                                        $count = 0;
                                        foreach ($View['data']['flagged_comms'] as $v):
                                            $count++;
                                     ?>
                                    <tr id="manComm_<?= $count ?>">
                                        <td><a href="/?video=<?= urlencode($v['name']) ?>" target="_blank"><?= $v['name'] ?></a></td>
                                        <td style="text-align: right; display: flex; gap: 4px; justify-content: flex-end;">
                                            <button class="btn" onclick="unflagVideo(<?= $v['id'] ?>, 'manComm_<?= $count ?>');">unflag</button>
                                            <button class="btn" onclick="renameVideo('<?= $v['name'] ?>');">rename</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </fieldset>
            </section>

            <!-- Stats -->
            <section id="Stats" class="tabcontent">
                <div class="card-table-wrapper">
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
                            <tr style="background-color: #<?= $s['color'] ?>5A;">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <div style="width: 8px; height: 8px; background: #<?= $s['color'] ?>; border: 1px solid #000;"></div>
                                        <?= $s['showType'] ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; align-items: center; ">
                                        <button class="btn" onclick="showStats('<?= $s['shortName'] ?>', <?= $count ?>)" style="padding: 1px 4px;">+</button>
                                        <span style="font-weight: bold;"><?= $s['shortName'] ?></span>
                                    </div>
                                    <div id="stats<?= $count ?>" class="hidden scroll-box"></div>
                                </td>
                                <td style="text-align: center; font-weight: bold;"><?= $s['occurrence'] ?></td>
                            </tr>
                            <?php 
                                }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <div class="status-bar">
            <div class="status-item">Disk: <?= implode(" | ", $View['sys']['disk']) ?></div>
            <div class="status-item" style="color: <?= $View['sys']['load'] > 80 ? 'red' : 'inherit' ?>">Load: <?= $View['sys']['load'] ?>%</div>
            <div class="status-item">Temp: <?= $View['sys']['temp_f'] ?>°F</div>
            <div class="status-item">Up: <?= $View['sys']['uptime'] ?></div>
        </div>

    </div>

    <!-- Floating Video Player Windows 98 Style -->
    <div id="player-container" class="window">
        <div class="title-bar">
            <span>Video Player</span>
            <div class="title-bar-controls">
                <button onclick="closePlayer();">X</button>
            </div>
        </div>
        <video id="vidplayer" controls autoplay></video>
    </div>
</body>
</html>
