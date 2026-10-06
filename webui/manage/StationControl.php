<?php

class StationControl implements ManageCard {
    private $name = 'TV Station Control';
    private $links = [];
    private $html = '';

    public function __construct() {
        // 1. Handle AJAX status polling
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['station_action'])) {
            header('Content-Type: application/json');
            $action = $_POST['station_action'];
            $success = false;

            if ($action === 'start') {
                exec('sudo /usr/local/bin/station-start >/dev/null 2>&1 &');
                $success = true;
            } elseif ($action === 'stop') {
                exec('sudo /usr/local/bin/station-stop >/dev/null 2>&1 &');
                $success = true;
            } elseif ($action === 'restart') {
                exec('sudo /usr/local/bin/station-restart >/dev/null 2>&1 &');
                $success = true;
            }

            echo json_encode(['success' => $success, 'action' => $action]);
            exit;
        }

        // 3. Render Card HTML
        $this->renderCard();
    }

    private function renderCard() {
        $initialState = isStationRunning();
        $statusText   = $initialState ? 'BROADCASTING' : 'OFF AIR';
        $statusColor  = $initialState ? '#22c55e' : '#ef4444';

        $this->html = '
        <style>
            .st-card {
                padding: 10px 12px;
                font-family: inherit;
            }
            .st-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
            }
            .st-badge {
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.5px;
                padding: 3px 8px;
                border-radius: 4px;
                text-transform: uppercase;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin-bottom: 8px;
            }
            .st-pulse {
                width: 7px;
                height: 7px;
                border-radius: 50%;
                background-color: currentColor;
            }
            .st-controls {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .st-btn {
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.15);
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
                color: #dcdce0;
                padding: 6px 14px;
                font-size: 0.78rem;
                font-weight: 600;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.15s ease;
                font-family: inherit;
            }
            .st-btn:hover:not(:disabled) {
                background: rgba(255, 255, 255, 0.18);
                color: #ffffff;
                border-color: rgba(255, 255, 255, 0.25);
            }
            .st-btn:disabled {
                opacity: 0.35;
                cursor: not-allowed;
            }
            .st-btn-start:hover:not(:disabled) {
                border-color: #22c55e;
                color: #22c55e;
            }
            .st-btn-stop:hover:not(:disabled) {
                border-color: #ef4444;
                color: #ef4444;
            }
            .st-btn-restart:hover:not(:disabled) {
                border-color: #3b82f6;
                color: #60a5fa;
            }
        </style>

        <div class="st-card">
            <div id="stStatusBadge" class="st-badge" style="background: ' . $statusColor . '22; color: ' . $statusColor . '; border: 1px solid ' . $statusColor . '55;">
                    <span class="st-pulse"></span>
                    <span id="stStatusText">' . $statusText . '</span>
            </div>
            <div class="st-header">
                <br />
            </div>

            <div class="st-controls">
                <button id="stBtnStart" class="st-btn st-btn-start" onclick="sendStationAction(\'start\')" ' . ($initialState ? 'disabled' : '') . '>
                    Start
                </button>
                <button id="stBtnStop" class="st-btn st-btn-stop" onclick="sendStationAction(\'stop\')" ' . (!$initialState ? 'disabled' : '') . '>
                    Stop
                </button>
                <button id="stBtnRestart" class="st-btn st-btn-restart" onclick="sendStationAction(\'restart\')">
                    Restart
                </button>
            </div>
        </div>

        <script>
        (function() {
            function updateControlUI(running) {
                const badge  = document.getElementById("stStatusBadge");
                const text   = document.getElementById("stStatusText");
                const bStart = document.getElementById("stBtnStart");
                const bStop  = document.getElementById("stBtnStop");

                if (!badge || !text || !bStart || !bStop) return;

                if (running) {
                    text.textContent = "BROADCASTING";
                    badge.style.color = "#22c55e";
                    badge.style.background = "rgba(34, 197, 94, 0.13)";
                    badge.style.borderColor = "rgba(34, 197, 94, 0.35)";
                    bStart.disabled = true;
                    bStop.disabled = false;
                } else {
                    text.textContent = "OFF AIR";
                    badge.style.color = "#ef4444";
                    badge.style.background = "rgba(239, 68, 68, 0.13)";
                    badge.style.borderColor = "rgba(239, 68, 68, 0.35)";
                    bStart.disabled = false;
                    bStop.disabled = true;
                }
            }

            async function pollStatus() {
                try {
                    const res = await fetch(window.location.pathname + "?station_status=1", { cache: "no-store" });
                    if (res.ok) {
                        const data = await res.json();
                        updateControlUI(data.running);
                    }
                } catch (e) {
                    // Suppress transient network poll hiccups
                }
            }

            window.sendStationAction = async function(action) {
                const bStart   = document.getElementById("stBtnStart");
                const bStop    = document.getElementById("stBtnStop");
                const bRestart = document.getElementById("stBtnRestart");

                if (bStart) bStart.disabled = true;
                if (bStop) bStop.disabled = true;
                if (bRestart) bRestart.disabled = true;

                try {
                    const body = new FormData();
                    body.append("station_action", action);

                    await fetch(window.location.pathname, {
                        method: "POST",
                        body: body
                    });

                    // Quick checks to track the transition without page reload
                    setTimeout(pollStatus, 800);
                    setTimeout(pollStatus, 2500);
                } catch (err) {
                    console.error("Station action failed:", err);
                } finally {
                    if (bRestart) bRestart.disabled = false;
                }
            };

            // Background polling cycle (every 4 seconds)
            setInterval(pollStatus, 4000);
        })();
        </script>
        ';
    }

    public function priority() {
        return 300; // Positions this card at the top of the stack
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
}
?>