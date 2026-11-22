<?php

class FilesSystem implements ManageCard {
	private $name = 'Files and System';
	private $links = [];
	private $html = null;

	private function loadLinks() {
		// Load links from a file or database
		$this->links = [
			[
				"label" => "Browse Files",
				"url" 	=> "/dir.php",
				"style" => null,
				"action" => null
			],
			[
				"label" => "Video Editor",
				"url" 	=> "/videoeditor.php",
				"style" => null,
				"action" => null
			],
			[
				"label" => "Run Setup Wizard",
				"url" 	=> "/setup/?action=reset",
				"style" => null,
				"action" => null
			]
		];
	}

	private function loadHtml() {
		// Load HTML content from a file or database
		$this->html = '<ul style="list-style: none;">
		<form method="POST" action="" style="display:inline;">
			<input type="hidden" name="action" value="reboot">
			<center>
				<button style="margin-top:12px; padding: 10px; background: #ff000038; color: #f00; border-radius: 8px;" onclick="if(confirm(\'Reboot the Pi now?\')) this.closest(\'form\').submit(); return false;">{ ! } Reboot System { ! }</button>
			</center>
		</form>
		<br />
		</ul>';
	}

	public function __construct() {
		// You can initialize any properties or perform setup tasks here
		// I will load other links here
		$this->loadLinks();
		$this->loadHtml();
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