<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local TV Station Emulator</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100vw;
            height: 100vh;
            background-color: #121212;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Courier New', Courier, monospace;
            overflow: hidden;
        }

        /* TV Assembly Container */
        .tv-assembly {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* --- Rabbit Ears Antenna --- */
        .antenna-assembly {
            position: relative;
            width: 100px;
            height: 80px;
            margin-bottom: -10px;
            z-index: 0;
        }
        .antenna-base {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 15px;
            background: #222;
            border-radius: 10px 10px 0 0;
            box-shadow: inset 0 3px 5px rgba(255,255,255,0.2);
        }
        .antenna-rod {
            position: absolute;
            bottom: 10px;
            width: 6px;
            height: 150px;
            background: linear-gradient(90deg, #999 0%, #eee 50%, #777 100%);
            border-radius: 3px;
        }
        .antenna-rod.left {
            left: 20px;
            transform-origin: bottom center;
            transform: rotate(-35deg);
        }
        .antenna-rod.right {
            right: 20px;
            transform-origin: bottom center;
            transform: rotate(45deg);
        }
        .antenna-tip {
            position: absolute;
            top: -4px;
            left: -2px;
            width: 10px;
            height: 10px;
            background: #111;
            border-radius: 50%;
        }

        /* --- Classic Wood Cabinet --- */
        .tv-cabinet {
            position: relative;
            background: linear-gradient(135deg, #4e3422 0%, #301f12 50%, #1a1005 100%);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 
                inset 0 0 10px rgba(0,0,0,0.8),
                15px 15px 40px rgba(0,0,0,0.9),
                inset 2px 2px 5px rgba(255,255,255,0.1);
            border: 6px solid #1a1005;
            display: flex;
            flex-direction: row;
            gap: 25px;
            z-index: 1;
            align-items: stretch; 
        }

        .tv-cabinet:fullscreen {
            width: 100vw;
            height: 100vh;
            border-radius: 0;
            border: none;
            padding: 2vw;
            background: #000;
        }

        /* --- Screen Bezel & Tube --- */
        .bezel {
            background: #1a1a1a;
            padding: 30px;
            border-radius: 20% / 10%;
            box-shadow: 
                inset 0 0 15px #000, 
                0 5px 15px rgba(0,0,0,0.6),
                inset -2px -2px 5px rgba(255,255,255,0.05);
            border: 2px solid #333;
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            min-width: 0; 
            min-height: 0;
        }

        .screen {
            background-color: #050505;
            width: 640px; 
            height: 480px;
            border-radius: 50% / 5%;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 0 50px rgba(0,0,0,1);
            flex-shrink: 0; 
        }

        .tv-cabinet:fullscreen .screen {
            width: 100%;
            height: auto;
            max-height: 100%;
            aspect-ratio: 4 / 3;
        }

        /* Videos */
        video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
            filter: contrast(1.1) brightness(0.9) saturate(0.8) sepia(0.1);
        }
        
        #main-video { z-index: 1; }
        #override-video { z-index: 2; }
        /* Submedia tags will dynamically spawn at z-index: 3 */

        /* CRT Scanlines & Phosphor Glow */
        .crt-effects {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10;
            pointer-events: none;
            background: 
                linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), 
                linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
            background-size: 100% 4px, 3px 100%;
            box-shadow: inset 0 0 60px rgba(0,0,0,0.9);
        }
        
        .crt-effects::after {
            content: " ";
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 60%);
            z-index: 11;
        }

        @keyframes flicker {
            0% { opacity: 0.98; }
            50% { opacity: 1; }
            100% { opacity: 0.99; }
        }
        .screen.is-on .crt-effects { animation: flicker 0.15s infinite; }

        /* --- Side Control Panel --- */
        .control-panel {
            width: 120px;
            background: #2a2a2a;
            border-radius: 5px;
            border: 2px solid #111;
            box-shadow: inset 2px 2px 5px rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 10px;
            gap: 25px;
            align-self: center; 
        }

        .dial-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        .dial-label {
            color: #777;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .knob {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #555, #111);
            border: 3px solid #000;
            box-shadow: 2px 3px 5px rgba(0,0,0,0.6), inset 0 0 5px rgba(0,0,0,0.8);
            position: relative;
            cursor: pointer;
            transform: rotate(-30deg);
        }
        .knob::after {
            content: '';
            position: absolute;
            top: 5px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 15px;
            background: #ccc;
            border-radius: 2px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.8);
        }

        .speaker-grill {
            flex-grow: 1;
            width: 80%;
            min-height: 100px;
            background: repeating-linear-gradient(
                0deg,
                #111,
                #111 4px,
                #222 4px,
                #222 8px
            );
            border-radius: 4px;
            border: 1px solid #000;
            box-shadow: inset 0 5px 10px rgba(0,0,0,0.8);
        }

        .buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
        }
        .chunky-btn {
            background: #1a1a1a;
            color: #9e9e9e;
            border: 2px solid #000;
            border-top: 2px solid #444;
            border-left: 2px solid #444;
            padding: 10px;
            font-family: inherit;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.6);
            border-radius: 4px;
            transition: all 0.1s;
        }
        .chunky-btn:active {
            border: 2px solid #000;
            border-bottom: 2px solid #444;
            border-right: 2px solid #444;
            box-shadow: inset 2px 2px 5px rgba(0,0,0,0.8);
            transform: translateY(2px);
        }
        .chunky-btn.power-on {
            color: #ff5252;
            text-shadow: 0 0 8px #ff5252;
            border-bottom: 2px solid #444;
            border-right: 2px solid #444;
            border-top: 2px solid #000;
            border-left: 2px solid #000;
            box-shadow: inset 2px 2px 5px rgba(0,0,0,0.8);
            transform: translateY(2px);
        }
    </style>
</head>
<body>

    <div class="tv-assembly" id="tv-assembly">
        <div class="antenna-assembly">
            <div class="antenna-rod left"><div class="antenna-tip"></div></div>
            <div class="antenna-rod right"><div class="antenna-tip"></div></div>
            <div class="antenna-base"></div>
        </div>

        <div class="tv-cabinet" id="tv-cabinet">
            <div class="bezel">
                <div class="screen" id="screen">
                    <div class="crt-effects"></div>
                    <video id="main-video" playsinline></video>
                    <video id="override-video" playsinline></video>
                </div>
            </div>
            
            <div class="control-panel">
                <div class="dial-container">
                    <div class="dial-label">VHF</div>
                    <div class="knob"></div>
                </div>
                <div class="dial-container">
                    <div class="dial-label">UHF</div>
                    <div class="knob" style="transform: rotate(60deg);"></div>
                </div>
                
                <div class="speaker-grill"></div>
                
                <div class="buttons">
                    <button id="fullscreen-btn" class="chunky-btn">FULL</button>
                    <button id="power-btn" class="chunky-btn">POWER</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const tvCabinet = document.getElementById('tv-cabinet');
        const screen = document.getElementById('screen');
        const mainVideo = document.getElementById('main-video');
        const overrideVideo = document.getElementById('override-video');
        const powerBtn = document.getElementById('power-btn');
        const fullscreenBtn = document.getElementById('fullscreen-btn');

        let isActive = false;
        let pollInterval = null;
        const SYNC_THRESHOLD_SECONDS = 2;
        
        // Dictionary to track dynamically created picture-in-picture videos
        const submediaPool = {};

        powerBtn.addEventListener('click', () => {
            isActive = !isActive;
            
            if (isActive) {
                powerBtn.classList.add('power-on');
                screen.classList.add('is-on');
                
                syncPlayer();
                pollInterval = setInterval(syncPlayer, 1000);
            } else {
                powerBtn.classList.remove('power-on');
                screen.classList.remove('is-on');
                
                clearInterval(pollInterval);
                pollInterval = null;
                
                shutdownAllMedia();
            }
        });

        fullscreenBtn.addEventListener('click', () => {
            if (!document.fullscreenElement) {
                tvCabinet.requestFullscreen().catch(err => {
                    console.error("Error attempting to enable fullscreen:", err);
                });
            } else {
                document.exitFullscreen();
            }
        });

        function shutdownAllMedia() {
            mainVideo.pause();
            mainVideo.style.display = 'none';
            delete mainVideo.dataset.currentFile;
            
            overrideVideo.pause();
            overrideVideo.style.display = 'none';
            delete overrideVideo.dataset.currentFile;
            
            // Purge the entire submedia pool
            for (const layerId in submediaPool) {
                submediaPool[layerId].pause();
                submediaPool[layerId].remove();
            }
            Object.keys(submediaPool).forEach(k => delete submediaPool[k]);
        }

        async function syncPlayer() {
            if (!isActive) return;

            try {
                const req = await fetch('functions.php?get_live_status=1').catch(() => null);
                if (!req || !req.ok) return;
                
                const data = await req.json();

                // 1. Process Main Feed
                syncMediaNode(mainVideo, data.main);

                // 2. Process Overrides (Commercials / Emergency Intercepts)
                const overrideKeys = Object.keys(data.overrides || {});
                if (overrideKeys.length > 0) {
                    // Pick the active override block
                    const firstOverride = data.overrides[overrideKeys[0]];
                    syncMediaNode(overrideVideo, firstOverride);
                } else {
                    // Sleep the override player if empty
                    overrideVideo.pause();
                    overrideVideo.style.display = 'none';
                }

                // 3. Process Submedia (Picture-in-Picture & Audio Beds)
                const currentSubKeys = Object.keys(data.submedia || {});
                
                // Garbage Collection: Remove layers that the Pi has cleared
                for (const layerId in submediaPool) {
                    if (!currentSubKeys.includes(layerId)) {
                        submediaPool[layerId].pause();
                        submediaPool[layerId].remove();
                        delete submediaPool[layerId];
                    }
                }
                
                // Mount or Update active layers
                for (const layerId of currentSubKeys) {
                    // Create object if missing
                    if (!submediaPool[layerId]) {
                        const vid = document.createElement('video');
                        vid.playsInline = true;
                        vid.style.position = 'absolute';
                        vid.style.zIndex = '3'; 
                        vid.style.objectFit = 'fill'; 
                        screen.appendChild(vid);
                        submediaPool[layerId] = vid;
                    }
                    
                    const layerData = data.submedia[layerId];
                    const vidNode = submediaPool[layerId];
                    
                    syncMediaNode(vidNode, layerData);
                    
                    // Apply literal hardware coordinates if provided
                    if (layerData.coords) {
                        const [x1, y1, x2, y2] = layerData.coords.split(' ').map(Number);
                        vidNode.style.left = x1 + 'px';
                        vidNode.style.top = y1 + 'px';
                        vidNode.style.width = (x2 - x1) + 'px';
                        vidNode.style.height = (y2 - y1) + 'px';
                    } else {
                        // Default to fullscreen if no coordinates passed
                        vidNode.style.left = '0';
                        vidNode.style.top = '0';
                        vidNode.style.width = '100%';
                        vidNode.style.height = '100%';
                    }
                }

            } catch (err) {
                console.error("Playback sync loop error:", err);
            }
        }

        // Standardized DOM synchronization applied to all media layers
        // Standardized DOM synchronization applied to all media layers
        function syncMediaNode(videoEl, mediaData) {
            // Null or offline check
            if (!mediaData || mediaData.state === "offline") {
                videoEl.pause();
                videoEl.style.display = 'none';
                return;
            }

            const isPaused = mediaData.state === "paused";

            // Source Injection
            if (!videoEl.dataset.currentFile || videoEl.dataset.currentFile !== mediaData.file) {
                videoEl.src = '/?video=' + mediaData.file;
                videoEl.dataset.currentFile = mediaData.file;
                
                // Flag this element to require a strict, immediate sync on the next poll
                videoEl.dataset.forceSync = "true"; 
                
                videoEl.onloadedmetadata = () => {
                    // Start playing immediately, but DO NOT scrub the playhead with stale data here
                    if (!isPaused) {
                        videoEl.play().catch(e => console.error("Play error:", e));
                    }
                };
            } else {
                // Playhead Sync
                if (videoEl.readyState > 0) {
                    // If we just loaded a new file, ignore the 2-second tolerance and force an exact sync.
                    // Otherwise, use the standard 2-second deadband to prevent micro-stuttering.
                    const threshold = (videoEl.dataset.forceSync === "true") ? 0.1 : SYNC_THRESHOLD_SECONDS;

                    if (Math.abs(videoEl.currentTime - mediaData.position) > threshold) {
                        videoEl.currentTime = mediaData.position;
                    }
                    
                    // Clear the flag so we don't stutter on subsequent polls
                    videoEl.dataset.forceSync = "false";
                }
                
                // Play/Pause State
                if (isPaused) {
                    if (!videoEl.paused) videoEl.pause();
                } else {
                    if (videoEl.paused) videoEl.play().catch(e => console.error("Play error:", e));
                }
            }

            // Real-time hardware volume mapping
            if (mediaData.volume !== undefined) {
                videoEl.volume = Math.max(0, Math.min(1, mediaData.volume));
            }

            // Logical visibility decoupling
            videoEl.style.display = (mediaData.visible !== false) ? 'block' : 'none';
        }
    </script>
</body>
</html>