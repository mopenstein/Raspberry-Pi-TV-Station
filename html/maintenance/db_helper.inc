<?php
    /*
        Name: db_helper.inc
        Description:
            Handles JSON settings loading, database connection, and $mysqli setup.
    */

    // 1. Load the Settings Class
    require_once(__DIR__ . "/../settings.class.inc");

    $Settings = new Settings();
    $json_settings = $Settings->load("/home/pi/Desktop/settings.json");

    if ($json_settings[0] == null) {
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