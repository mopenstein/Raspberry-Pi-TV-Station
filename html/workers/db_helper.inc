<?php

    /*
        Name: db_helper.inc
        Description:
        
            This file is a helper that can be included in any worker script to handle JSON loading, database connection, and $mysqli setup. It abstracts away the common setup code so that each worker script can focus on its specific functionality without worrying about the underlying database connection details.
        
        Usage:
        
            1. Include this file at the beginning of your worker script using:
                require_once("db_helper.inc");
            
            2. After including this file, you will have access to the $mysqli variable, which is a connected MySQLi instance ready for executing queries.
            
            3. The JSON settings will be loaded from the specified path in the Settings class, and the database connection will be established based on those settings.


    */

	// This handles JSON, DB Connection, and $mysqli setup
	require_once("db_helper.inc");

	// 1. Load the Settings Class
	require_once(__DIR__ . "/../settings.class.inc");

	$Settings = new Settings();
	// Use the absolute path to your SSD/Desktop config
	$json_settings = $Settings->load("/home/pi/Desktop/settings.json");

	if($json_settings[0] == null) {
		die("0|JSON VALIDATION ERROR: Check settings.json");
	}

	$json_settings = $json_settings[0];
	$database_info = $json_settings["web-ui"]["database_info"];

	// 2. Initialize Database Connection
	require_once(__DIR__ . "/../db_manage_extx.inc");
	$mysqli = new mysqli(
		$database_info["host"],
		$database_info["username"],
		$database_info["password"],
		$database_info["database_name"]
	);

	if ($mysqli->connect_error) {
		die("0|Connection failed: " . $mysqli->connect_error);
	}
?>