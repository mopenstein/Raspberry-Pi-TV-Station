<?php

class Programming implements ManageCard {
    private $name = 'Station Programming';
	private $links = [];
	private $html = null;
	private $settings = null;

	private function loadLinks() {
		if ($this->settings == null) {
			return $this->links;
		}
		// Load links from a file or database
		$this->links = [
			[
				"label" => "Live TV Player",
				"url" 	=> "/tvplayer.php",
				"style" => null,
				"action" => null
			],
			[
				"label" => "Scheduler",
				"url" 	=> "/scheduler.php",
				"style" => null,
				"action" => null
			],
			[
				"label" => "Scheduler Color Picker",
				"url" 	=> "/picker.php",
				"style" => null,
				"action" => null
			],
			[
				"label" => "Settings Editor",
				"url" 	=> "/settings.php",
				"style" => null,
				"action" => null
			],
			[
				"label" => "Test Settings",
				"url" 	=> "/test_settings.php",
				"style" => null,
				"action" => null
			]
		];
	}

	public function __construct() {
		// You can initialize any properties or perform setup tasks here
		// I will load other links here
		$this->loadLinks();
	}

	public function setSettings($json_settings) {
		$this->settings = $json_settings;
		// Reload links to update any that depend on settings
		$this->loadLinks();
	}		

	public function priority() {
        return 100;
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