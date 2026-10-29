<?php
$schema_file  = __DIR__ . '/db_schema.json';
$version_file = __DIR__ . '/.db_version';

if (file_exists($schema_file)) {
    $h = (php_sapi_name() === 'cli') ? (isset($argv[1]) && in_array($argv[1], ['-h', 'h', '--human'])) : isset($_GET['h']);
    require_once(__DIR__ . "/db_helper.inc");
    $schema = json_decode(file_get_contents($schema_file), true);
    $local_version = file_exists($version_file) ? (int)file_get_contents($version_file) : 0;

    if ($schema && isset($schema['version']) && $schema['version'] > $local_version) {
        if($h) echo "Updating database schema from version $local_version to {$schema['version']}...\n";
        // Helper to construct column definition strings
        $build_col_sql = function($name, $def) {
            $type = strtoupper($def['type']);
            $null = (!empty($def['null'])) ? 'NULL' : 'NOT NULL';
            $auto = (!empty($def['auto_increment'])) ? ' AUTO_INCREMENT' : '';
            $extra = (!empty($def['extra'])) ? ' ' . $def['extra'] : '';
            
            $default = '';
            if (array_key_exists('default', $def)) {
                $default = ($def['default'] === null) ? " DEFAULT NULL" : " DEFAULT '{$def['default']}'";
            }
            
            return "`{$name}` {$type} {$null}{$default}{$auto}{$extra}";
        };

        foreach ($schema['tables'] as $table => $table_def) {
            // Support both direct column lists and tables containing explicit 'columns' / 'indexes' blocks
            $columns = isset($table_def['columns']) ? $table_def['columns'] : $table_def;
            $indexes = isset($table_def['indexes']) ? $table_def['indexes'] : [];

            $existing_cols = [];
            $res = $mysqli->query("SHOW COLUMNS FROM `{$table}`");

            if ($res) {
                if($h) echo "Table `{$table}` exists. Checking columns and indexes...\n";
                while ($row = $res->fetch_assoc()) {
                    // Map existing columns with their raw type and nullability for comparison
                    $existing_cols[$row['Field']] = [
                        'type' => strtolower($row['Type']),
                        'null' => ($row['Null'] === 'YES')
                    ];
                }

                // 1. Process Columns: Add missing, or modify if requested
                foreach ($columns as $col_name => $col_def) {
                    $col_sql = $build_col_sql($col_name, $col_def);
                    $after = (!empty($col_def['after'])) ? " AFTER `{$col_def['after']}`" : "";

                    if (!array_key_exists($col_name, $existing_cols)) {
                        // Column is missing -> Add it
                        if($h) echo "Adding column `{$col_name}` to table `{$table}`...\n";
                        $mysqli->query("ALTER TABLE `{$table}` ADD {$col_sql}{$after}");
                    } elseif (!empty($col_def['modify'])) {
                        // Explicit flag to modify existing column attributes
                        if($h) echo "Modifying column `{$col_name}` in table `{$table}`...\n";
                        $mysqli->query("ALTER TABLE `{$table}` MODIFY {$col_sql}{$after}");
                    }
                }

                // 2. Process Indexes on Existing Table
                $existing_indexes = [];
                $idx_res = $mysqli->query("SHOW INDEX FROM `{$table}`");
                if ($idx_res) {
                    if($h) echo "Checking existing indexes for table `{$table}`...\n";
                    while ($idx_row = $idx_res->fetch_assoc()) {
                        $existing_indexes[] = $idx_row['Key_name'];
                    }
                }

                foreach ($indexes as $idx_name => $idx_def) {
                    if (!in_array($idx_name, $existing_indexes, true)) {
                        $idx_cols = implode('`, `', (array)$idx_def['columns']);
                        $is_unique = !empty($idx_def['unique']) ? 'UNIQUE ' : '';
                        if($h) echo "Adding index `{$idx_name}` to table `{$table}`...\n";
                        $mysqli->query("ALTER TABLE `{$table}` ADD {$is_unique}INDEX `{$idx_name}` (`{$idx_cols}`)");
                    }
                }

            } else {
                if($h) echo "Table `{$table}` does not exist. Creating table...\n";
                // Table does not exist -> Create from scratch
                $col_sqls = [];
                $primary_keys = [];

                foreach ($columns as $col_name => $col_def) {
                    $col_sqls[] = $build_col_sql($col_name, $col_def);
                    if (!empty($col_def['primary_key'])) {
                        $primary_keys[] = "`{$col_name}`";
                    }
                }

                $table_elements = $col_sqls;
                if (!empty($primary_keys)) {
                    $table_elements[] = "PRIMARY KEY (" . implode(', ', $primary_keys) . ")";
                }

                // Add declared indexes directly into table creation
                foreach ($indexes as $idx_name => $idx_def) {
                    $idx_cols = implode('`, `', (array)$idx_def['columns']);
                    $is_unique = !empty($idx_def['unique']) ? 'UNIQUE ' : '';
                    $table_elements[] = "{$is_unique}INDEX `{$idx_name}` (`{$idx_cols}`)";
                }

                if($h) echo "Creating table `{$table}` with columns: " . implode(', ', array_keys($columns)) . "\n";
                $create_sql = "CREATE TABLE `{$table}` (" . implode(', ', $table_elements) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $mysqli->query($create_sql);
            }
        }

        try {
            // Commit version update to disable checks until the next version bump
            if($h) echo "Writing new schema version to version file: $version_file\n";
            file_put_contents($version_file, (int)$schema['version']);
        } catch (Exception $e) {
            if($h) echo "Failed to write version file: " . $e->getMessage();
            die();
        }

        if($h) echo "Database schema updated to version: " . (int)$schema['version'];
    } else {
        if($h) echo "Database schema is up to date. No changes were made. Current version: $local_version\n";
    }
} else {
    echo "No schema file found at: $schema_file\n";
}
?>