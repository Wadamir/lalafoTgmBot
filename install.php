<?php
/*
Create database for Lalafo tgm bot
*******************************************
1. Create database
2. Create table users
3. Create table city
4. Create table district
5. Create table data
6. Create table rates
7. Close connection
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
$table_user = MYSQL_TABLE_USER;
$table_city = MYSQL_TABLE_CITY;
$table_district = MYSQL_TABLE_DISTRICT;
$table_data = MYSQL_TABLE_DATA;
$table_rate = MYSQL_TABLE_RATE;

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
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 2. Create table ' . $table_user . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_user (
        `user_id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `tgm_user_id` varchar(255) DEFAULT NULL,
        `is_bot` tinyint(1) DEFAULT NULL,
        `is_deleted` tinyint(1) DEFAULT NULL,
        `is_premium` tinyint(1) DEFAULT NULL,
        `is_returned` tinyint(1) DEFAULT NULL,
        `is_statistics` tinyint(1) DEFAULT '1',
        `first_name` varchar(255) DEFAULT NULL,
        `last_name` varchar(255) DEFAULT NULL,
        `username` varchar(255) DEFAULT NULL,
        `language_code` varchar(16) DEFAULT NULL,
        `chat_id` varchar(255) DEFAULT NULL,
        `refresh_time` bigint DEFAULT NULL,
        `price_min` int DEFAULT NULL,
        `price_max` int DEFAULT NULL,
        `price_currency` varchar(255) DEFAULT 'USD',
        `rooms_min` int DEFAULT NULL,
        `rooms_max` int DEFAULT NULL,
        `preference_city` text DEFAULT NULL,
        `preference_district` text DEFAULT NULL,
        `preference_sharing` tinyint(1) DEFAULT NULL,
        `preference_owner` tinyint(1) DEFAULT '1',
        `date_payment` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `date_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Table $table_user created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating table $table_user: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 3. Create table city
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 3. Create table ' . $table_city . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_city (
        `city_id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `city_name_en` varchar(255)  NOT NULL,
        `city_name_ru` varchar(255)  NOT NULL,
        `city_name_kg` varchar(255)  NOT NULL,
        `city_slug` varchar(255)  NOT NULL
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Table $table_city created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating table $table_city: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 4. Create table district
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 4. Create table ' . $table_district . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_district (
        `district_id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `city_id` bigint NOT NULL,
        `district_name_en` varchar(255) NOT NULL,
        `district_name_ru` varchar(255) NOT NULL,
        `district_name_kg` varchar(255) NOT NULL,
        `district_slug` varchar(255) NOT NULL
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

// 5. Create table data
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 5. Create table ' . $table_data . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_data (
        `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `chat_ids_to_send` TEXT DEFAULT NULL,
        `chat_ids_sent` TEXT DEFAULT NULL,
        `done` tinyint(1) DEFAULT NULL,
        `city` bigint NOT NULL,
        `district` bigint DEFAULT NULL,
        `title` varchar(255) DEFAULT NULL,
        `link` text DEFAULT NULL,
        `created_at` varchar(255) DEFAULT NULL,
        `updated_at` varchar(255) DEFAULT NULL,
        `price_kgs` int DEFAULT NULL,
        `price_usd` int DEFAULT NULL,
        `deposit_kgs` int DEFAULT NULL,
        `deposit_usd` int DEFAULT NULL,
        `owner` varchar(255) DEFAULT NULL,
        `owner_name` varchar(255) DEFAULT NULL,
        `phone` varchar(255) DEFAULT NULL,
        `rooms` int DEFAULT NULL,
        `floor` varchar(255) DEFAULT NULL,
        `house_type` varchar(255) DEFAULT NULL,
        `sharing` tinyint(1) DEFAULT NULL,
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

// 6. Create table rate
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 6. Create table ' . $table_rate . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_rate (
        `rate_id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `usd` DECIMAL (10,4) NOT NULL,
        `eur` DECIMAL (10,4) NOT NULL,
        `gbp` DECIMAL (10,4) NOT NULL,
        `cny` DECIMAL (10,4) NOT NULL,
        `rub` DECIMAL (10,4) NOT NULL,
        `kzt` DECIMAL (10,4) NOT NULL,
        `date_updated` varchar(255) NOT NULL,
        `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Table $table_rate created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating table $table_rate: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 7. Close connection
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 7. Close connection' . PHP_EOL . PHP_EOL, FILE_APPEND);
mysqli_close($conn);
