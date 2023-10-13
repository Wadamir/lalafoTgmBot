<?php
/*
Create database for tgm bot
*******************************************
1. Create database
2. Create table users
3. Create table rss links
4. Create table data
5. Close connection
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
$log_dir = __DIR__ . '/logs';

// 1. Create database
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 1. Create database' . PHP_EOL, FILE_APPEND);

$dbhost = MYSQL_HOST;
$dbuser = MYSQL_USER;
$dbpass = MYSQL_PASSWORD;
$dbname = MYSQL_DB;
$table_users = MYSQL_TABLE_USERS;
$table_city = MYSQL_TABLE_CITY;
$table_district = MYSQL_TABLE_DISTRICT;
$table_data = MYSQL_TABLE_DATA;

// Create connection
$conn = mysqli_connect($dbhost, $dbuser, $dbpass);
if (!$conn) {
    file_put_contents($log_dir . '/install.log', 'Connection failed' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Database $dbname created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating database $dbname" . PHP_EOL, FILE_APPEND);
}

// 2. Create table users
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 2. Create table ' . $table_users . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_users (
        `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` varchar(256) DEFAULT NULL,
        `is_bot` tinyint(1) DEFAULT NULL,
        `is_deleted` tinyint(1) DEFAULT NULL,
        `is_premium` tinyint(1) DEFAULT NULL,
        `first_name` varchar(256) DEFAULT NULL,
        `last_name` varchar(256) DEFAULT NULL,
        `username` varchar(256) DEFAULT NULL,
        `language_code` varchar(16) DEFAULT NULL,
        `chat_id` varchar(256) DEFAULT NULL,
        `refresh_time` bigint DEFAULT NULL,
        `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Table $table_users created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating table $table_users: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 3. Create table districts
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 3. Create table ' . $table_district . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_district (
        `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `city_id` bigint DEFAULT NULL,
        `name` varchar(255) DEFAULT NULL,
        `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Table $table_district created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating table $table_district: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 4. Create table data
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 4. Create table ' . $table_data . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_data (
        `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `chat_ids_to_send` TEXT DEFAULT NULL,
        `chat_ids_sent` TEXT DEFAULT NULL,
        `done` tinyint(1) DEFAULT NULL,
        `title` varchar(255) DEFAULT NULL,
        `link` text DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        `price` varchar(255) DEFAULT NULL,
        `deposit` varchar(255) DEFAULT NULL,
        `owner` varchar(255) DEFAULT NULL,
        `owner_name` varchar(255) DEFAULT NULL,
        `phone` varchar(255) DEFAULT NULL,
        `district` varchar(255) DEFAULT NULL,
        `rooms` varchar(255) DEFAULT NULL,
        `floor` varchar(255) DEFAULT NULL,
        `house_type` varchar(255) DEFAULT NULL,
        `sharing` varchar(255) DEFAULT NULL,
        `furniture` varchar(255) DEFAULT NULL,
        `condition` varchar(255) DEFAULT NULL,
        `renovation` varchar(255) DEFAULT NULL,
        `animals` varchar(255) DEFAULT NULL,
        `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Table $table_data created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating table $table_data: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 5. Close connection
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 5. Close connection' . PHP_EOL . PHP_EOL, FILE_APPEND);
mysqli_close($conn);
