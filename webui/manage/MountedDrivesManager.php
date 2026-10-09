<?php

class MountedDrivesManager implements ManageCard {
    private $name = 'Mounted Drives';
    private $links = [];
    private $html = '';

    public function __construct() {
        $this->loadDriveData();
    }

    private function formatBytes($bytes, $precision = 1) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function loadDriveData() {
        // Query all block devices in JSON format
        $raw_json = shell_exec('lsblk -J -b -o NAME,SIZE,TYPE,MOUNTPOINT,FSTYPE,LABEL,UUID 2>/dev/null');
        $data = json_decode($raw_json, true);
        $drives = [];

        // Ignore empty mounts and boot-specific volumes only
        $isBootMount = function($mount) {
            return empty($mount) || $mount === '/boot' || strpos($mount, '/boot/') === 0;
        };

        if (!empty($data['blockdevices'])) {
            foreach ($data['blockdevices'] as $dev) {
                // Ignore loopback devices and virtual memory (zram)
                if (strpos($dev['name'], 'loop') === 0 || strpos($dev['name'], 'zram') === 0) {
                    continue;
                }

                // Check partitions with active mountpoints
                if (!empty($dev['children'])) {
                    foreach ($dev['children'] as $child) {
                        if (!empty($child['mountpoint']) && !$isBootMount($child['mountpoint'])) {
                            $drives[] = $child;
                        }
                    }
                } elseif (!empty($dev['mountpoint']) && !$isBootMount($dev['mountpoint'])) {
                    $drives[] = $dev;
                }
            }
        }

        if (empty($drives)) {
            $this->html = '<div style="margin:5px; padding:10px; color:#888;">No storage devices detected.</div>';
            return;
        }

        $this->html = '<div style="margin:2px; padding:6px; font-size:12px; box-sizing:border-box;">';
        $this->html .= '<strong style="font-size:13px;">Active Mounted Drives</strong><br><br>';
        $this->html .= '<table style="width:100%; border-collapse:collapse; text-align:left; table-layout:auto;">';
        $this->html .= '<tr style="border-bottom:1px solid rgba(255,255,255,0.15); color:#888; font-size:10px; text-transform:uppercase; letter-spacing:0.5px;">';
        $this->html .= '<th style="padding:6px 4px;">Device</th>';
        $this->html .= '<th style="padding:6px 4px;">Mount</th>';
        $this->html .= '</tr>';

        foreach ($drives as $drive) {
            $mount = $drive['mountpoint'];
            $devName = '/dev/' . $drive['name'];
            $fsType = strtoupper($drive['fstype'] ?: 'auto');
            $label = !empty($drive['label']) ? htmlspecialchars($drive['label']) : '';

            // Query filesystem capacity and free space on this mount
            $totalSpace = @disk_total_space($mount);
            $freeSpace = @disk_free_space($mount);

            if ($totalSpace !== false && $freeSpace !== false && $totalSpace > 0) {
                $usedSpace = $totalSpace - $freeSpace;
                $usedPercent = round(($usedSpace / $totalSpace) * 100);
                $freeFormatted = $this->formatBytes($freeSpace);
                $totalFormatted = $this->formatBytes($totalSpace);
            } else {
                $usedPercent = 0;
                $freeFormatted = '--';
                $totalFormatted = $this->formatBytes($drive['size'] ?? 0);
            }

            // Usage bar styling
            $barColor = '#22c55e';
            if ($usedPercent >= 90) {
                $barColor = '#ef4444';
            } elseif ($usedPercent >= 75) {
                $barColor = '#f59e0b';
            }

            $this->html .= '<tr style="border-bottom:1px solid rgba(255,255,255,0.07); vertical-align:middle;">';
            
            // Device column
            $this->html .= '<td style="padding:6px 4px; white-space:nowrap;">';
            $this->html .= '<strong style="font-size:12px;">' . htmlspecialchars($devName) . '</strong>';
            if ($label !== '') {
                $this->html .= '<br><span style="font-size:10px; color:#888;">' . $label . '</span>';
            }
            $this->html .= '</td>';

            // Mount point column
            $this->html .= '<td style="padding:6px 4px; font-family:monospace; font-size:11px; word-break:break-all;">';
            $this->html .= htmlspecialchars($mount);
			$this->html .= '<br />';
            $this->html .= '<div style="background:rgba(255,255,255,0.1); border-radius:3px; height:6px; width:100%; overflow:hidden; margin-bottom:3px;">';
            $this->html .= "<div style=\"background:{$barColor}; width:{$usedPercent}%; height:100%;\"></div>";
            $this->html .= '</div>';
            $this->html .= '<div style="display:flex; justify-content:space-between; font-size:9px; color:#999; font-family:monospace; white-space:nowrap; gap:4px;">';
            $this->html .= "<span>{$usedPercent}%</span>";
            $this->html .= "<span>{$freeFormatted} of {$totalFormatted}</span>";
			$this->html .= "<span>" . htmlspecialchars($fsType) . "</span>";
            $this->html .= '</div>';
            $this->html .= '</td>';

            $this->html .= '</tr>';
        }

        $this->html .= '</table>';
        $this->html .= '<div style="margin-top:8px; text-align:right;">';
        $this->html .= '<a href="#" onclick="location.reload();" style="font-size:11px; color:#888; text-decoration:none;">↻ Refresh Status</a>';
        $this->html .= '</div>';
        $this->html .= '</div>';
    }

    public function name() { return $this->name; }
    public function links() { return $this->links; }
    public function html() { return $this->html; }
}

?>