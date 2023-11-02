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
7. Create table amenity
8. Create table property
9. Create table owner
10. Create table donation
11. Close connection
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
$table_amenity = MYSQL_TABLE_AMENITY;
$table_property = MYSQL_TABLE_PROPERTY;
$table_owner = MYSQL_TABLE_OWNER;
$table_donation = MYSQL_TABLE_DONATION;

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
        `is_admin` tinyint(1) DEFAULT NULL,
        `is_returned` tinyint(1) DEFAULT NULL,
        `is_ads` tinyint(1) DEFAULT '1',
        `is_statistics` tinyint(1) DEFAULT '1',
        `first_name` varchar(255) DEFAULT NULL,
        `last_name` varchar(255) DEFAULT NULL,
        `username` varchar(255) DEFAULT NULL,
        `language_code` varchar(16) DEFAULT NULL,
        `chat_id` varchar(255) DEFAULT NULL,
        `refresh_time` bigint DEFAULT NULL,
        `price_min` int DEFAULT NULL,
        `price_max` int DEFAULT NULL,
        `price_currency` varchar(16) DEFAULT 'USD',
        `rooms_min` int DEFAULT NULL,
        `rooms_max` int DEFAULT NULL,
        `preference_city` int DEFAULT NULL,
        `preference_district` int DEFAULT NULL,
        `preference_sharing` tinyint(1) DEFAULT NULL,
        `preference_owner` tinyint(1) DEFAULT '2',
        `preference_property` tinyint(1) DEFAULT NULL,
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

$sql = "ALTER TABLE $table_user ADD UNIQUE KEY `user_id` (`user_id`);";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Unique key user_id added successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error adding unique key user_id: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
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

$sql = "ALTER TABLE $table_city ADD UNIQUE KEY `city_id` (`city_id`);";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Unique key city_id added successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error adding unique key city_id: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

$sql = "INSERT INTO `city` (`city_id`, `city_name_en`, `city_name_ru`, `city_name_kg`, `city_slug`) VALUES
(1, 'Bishkek', 'Бишкек', 'Бишкек', 'bishkek'),
(2, 'Osh', 'Ош', 'Ош', 'osh'),
(3, 'Dzhalal-Abad', 'Джалал-Абад', 'Жалал-Абад', 'dzhalal-abad'),
(4, 'Karakol', 'Каракол', 'Каракол', 'karakol');";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Data inserted into table $table_city successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error inserting data into table $table_city: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
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

$sql = "ALTER TABLE $table_district ADD UNIQUE KEY `district_id` (`district_id`);";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Unique key district_id added successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error adding unique key district_id: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

$sql = "INSERT INTO `district` (`district_id`, `city_id`, `district_name_en`, `district_name_ru`, `district_name_kg`, `district_slug`) VALUES
(1, 1, 'Filarmoniya', 'Филармония', 'Филармония', 'filarmoniya'),
(2, 1, 'Mossovet', 'Моссовет', 'Моссовет', 'mossovet'),
(3, 1, '8 mkr', '8 мкр', '8 мкр', '8-mkr'),
(4, 1, 'Kok-Zhar mkr', 'Кок-Жар мкр', 'Кок-Жар мкр', 'kok-zhar-mkr'),
(5, 1, 'Uchkun mkr', 'Учкун мкр', 'Учкун мкр', 'uchkun-mkr'),
(6, 1, 'Mega Komfort Mall', 'Мега Комфорт ТЦ', 'Мега Комфорт ТЦ', 'mega-komfort-tts'),
(7, 1, 'BGU', 'БГУ', 'БГУ', 'bgu'),
(8, 1, 'Medical Academy', 'Мед Академия', 'Мед Академия', 'med-akademiya'),
(9, 1, '7 mkr', '7 мкр', '7 мкр', '7-mkr'),
(10, 1, '9 mkr', '9 мкр', '9 мкр', '9-mkr'),
(11, 1, 'Ala-Archa zh/m', 'Ала-Арча ж/м', 'Ала-Арча ж/м', 'ala-archa-zh-m'),
(12, 1, 'Dzhal mkr (v t.ch. Verkhniy, Nizhniy, Sredniy)', 'Джал мкр (в т.ч. Верхний, Нижний, Средний)', 'Джал мкр (в т.ч. Верхний, Нижний, Средний)', 'dzhal-mkr-v-t-ch-verkhniy-nizhniy-sredniy-'),
(13, 1, 'Ata-Tyurk park', 'Ата-Тюрк парк', 'Ата-Тюрк парк', 'ata-tyurk-park'),
(14, 1, 'Karavan Mall', 'Караван ТРЦ', 'Караван ТРЦ', 'karavan-trts'),
(15, 1, 'Bishkek Park Mall', 'Бишкек Парк ТРЦ', 'Бишкек Парк ТРЦ', 'bishkek-park-trts'),
(16, 1, 'Asanbay mkr', 'Асанбай мкр', 'Асанбай мкр', 'asanbay-mkr'),
(17, 1, 'Yug-2 mkr', 'Юг-2 мкр', 'Юг-2 мкр', 'yug-2-mkr'),
(18, 1, 'Politekh', 'Политех', 'Политех', 'politekh'),
(19, 1, 'Alamedinskiy rinok / bazar', 'Аламединский рынок / базар', 'Аламединский рынок / базар', 'alamedinskiy-rinok-bazar'),
(20, 1, 'Tsum', 'Цум', 'Цум', 'tsum'),
(21, 1, 'Ulan mkr', 'Улан мкр', 'Улан мкр', 'ulan-mkr'),
(22, 1, 'Sovmina mkr', 'Совмина мкр', 'Совмина мкр', 'sovmina-mkr'),
(23, 1, '5 mkr', '5 мкр', '5 мкр', '5-mkr'),
(24, 1, 'Railway station', 'ЖД вокзал', 'ЖД вокзал', 'zhd-vokzal'),
(25, 1, 'Alamedin-1 mkr', 'Аламедин-1 мкр', 'Аламедин-1 мкр', 'alamedin-1-mkr'),
(26, 1, 'Vostok-5 mkr', 'Восток-5 мкр', 'Восток-5 мкр', 'vostok-5-mkr'),
(27, 1, '6 mkr', '6 мкр', '6 мкр', '6-mkr'),
(28, 1, 'CHP', 'ТЭЦ', 'ТЭЦ', 'tets'),
(29, 1, 'Kok-Zhar zh/m', 'Кок-Жар ж/м', 'Кок-Жар ж/м', 'kok-zhar-zh-m'),
(30, 1, 'Aziya Mall', 'Азия Молл', 'Азия Молл', 'aziya-moll'),
(31, 1, '1000 melochey (Karpinka)', '1000 мелочей (Карпинка)', '1000 мелочей (Карпинка)', '1000-melochey-karpinka-'),
(32, 1, 'KNU', 'КНУ', 'КНУ', 'knu'),
(33, 1, 'Dordoy-2 zh/m', 'Дордой-2 ж/м', 'Дордой-2 ж/м', 'dordoy-2-zh-m'),
(34, 1, 'Karagachevaya roscha', 'Карагачевая роща', 'Карагачевая роща', 'karagachevaya-roscha'),
(35, 1, 'Church', 'Церковь', 'Церковь', 'tserkovy'),
(36, 1, 'Oshskiy rinok / bazar', 'Ошский рынок / базар', 'Ошский рынок / базар', 'oshskiy-rinok-bazar'),
(37, 1, 'Ak-Bosogo zh/m', 'Ак-Босого ж/м', 'Ак-Босого ж/м', 'ak-bosogo-zh-m'),
(38, 1, 'Goin', 'Гоин', 'Гоин', 'goin'),
(39, 1, 'Kirkomstrom', 'Киркомстром', 'Киркомстром', 'kirkomstrom'),
(40, 1, 'Taatan Mall', 'Таатан ТЦ', 'Таатан ТЦ', 'taatan-tts'),
(41, 1, 'Kudaybergen avtorinok', 'Кудайберген авторынок', 'Кудайберген авторынок', 'kudaybergen-avtorinok'),
(42, 1, 'Kizil Asker', 'Кызыл Аскер', 'Кызыл Аскер', 'kizil-asker'),
(43, 1, 'Kirgiziya 1', 'Киргизия 1', 'Киргизия 1', 'kirgiziya-1'),
(44, 1, 'Dordoy rinok / bazar', 'Дордой рынок / базар', 'Дордой рынок / базар', 'dordoy-rinok-bazar'),
(45, 1, 'Kara-Zhigach zh/m', 'Кара-Жыгач ж/м', 'Кара-Жыгач ж/м', 'kara-zhigach-zh-m'),
(46, 1, 'Rabochiy Gorodok', 'Рабочий Городок', 'Рабочий Городок', 'rabochiy-gorodok'),
(47, 1, 'Ak-Orgo zh/m', 'Ак-Орго ж/м', 'Ак-Орго ж/м', 'ak-orgo-zh-m'),
(48, 1, 'Sari-Ozon Diykan rinok', 'Сары-Озон Дыйкан рынок', 'Сары-Озон Дыйкан рынок', 'sari-ozon-diykan-rinok'),
(49, 1, 'Tash-Rabat Mall', 'Таш-Рабат ТРЦ', 'Таш-Рабат ТРЦ', 'tash-rabat-trts'),
(50, 1, 'Tunguch mkr', 'Тунгуч мкр', 'Тунгуч мкр', 'tunguch-mkr'),
(51, 1, '12 mkr', '12 мкр', '12 мкр', '12-mkr'),
(52, 1, 'City hospital 4 (street Ayni)', 'Городская больница №4 (ул. Айни)', 'Городская больница №4 (ул. Айни)', 'gorodskaya-bolynitsa-4-ul-ayni-'),
(53, 1, 'Dordoy-1 zh/m', 'Дордой-1 ж/м', 'Дордой-1 ж/м', 'dordoy-1-zh-m'),
(54, 1, 'Bayat rinok / bazar', 'Баят рынок / базар', 'Баят рынок / базар', 'bayat-rinok-bazar'),
(55, 1, 'Fizpribori', 'Физприборы', 'Физприборы', 'fizpribori'),
(56, 1, 'Ala-Archa Mall', 'Ала-Арча ТРЦ', 'Ала-Арча ТРЦ', 'ala-archa-trts'),
(57, 1, 'Ortosayskiy rinok / bazar', 'Ортосайский рынок / базар', 'Ортосайский рынок / базар', 'ortosayskiy-rinok-bazar'),
(58, 1, 'Dvorets sporta', 'Дворец спорта', 'Дворец спорта', 'dvorets-sporta'),
(59, 1, 'Pishpek', 'Пишпек', 'Пишпек', 'pishpek'),
(60, 1, 'Ak-Keme (old airport)', 'Ак-Кеме (старый аэропорт)', 'Ак-Кеме (старый аэропорт)', 'ak-keme-stariy-aeroport-'),
(61, 1, 'Chekish-Ata rinok', 'Чекиш-Ата рынок', 'Чекиш-Ата рынок', 'chekish-ata-rinok'),
(62, 1, 'Panorama', 'Панорама', 'Панорама', 'panorama'),
(63, 1, 'Stariy tolchok rinok / bazar', 'Старый толчок рынок / базар', 'Старый толчок рынок / базар', 'stariy-tolchok-rinok-bazar'),
(64, 1, 'Archa-Beshik zh/m', 'Арча-Бешик ж/м', 'Арча-Бешик ж/м', 'archa-beshik-zh-m'),
(65, 1, '4 mkr', '4 мкр', '4 мкр', '4-mkr'),
(66, 1, '3 mkr', '3 мкр', '3 мкр', '3-mkr'),
(69, 1, 'Shlagbaum', 'Шлагбаум', 'Шлагбаум', 'shlagbaum'),
(70, 1, 'Ala-Too zh/m', 'Ала-Тоо ж/м', 'Ала-Тоо ж/м', 'ala-too-zh-m'),
(71, 2, 'Dzhal mkr (v t.ch. Verkhniy, Nizhniy, Sredniy)', 'Джал мкр (в т.ч. Верхний, Нижний, Средний)', 'Джал мкр (в т.ч. Верхний, Нижний, Средний)', 'dzhal-mkr-v-t-ch-verkhniy-nizhniy-sredniy-'),
(72, 1, 'Rinok Bayat', 'Рынок Баят', 'Рынок Баят', 'rinok-bayat'),
(73, 1, 'Tokolydosh', 'Токольдош', 'Токольдош', 'tokolydosh'),
(74, 1, 'Central Mosque', 'Центральная мечеть', 'Центральная мечеть', 'tsentralynaya-mechety'),
(75, 1, 'Madina rinok', 'Мадина рынок', 'Мадина рынок', 'madina-rinok'),
(76, 1, 'Vostochniy (old) avtovokzal', 'Восточный (старый) автовокзал', 'Восточный (старый) автовокзал', 'vostochniy-stariy-avtovokzal');";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Data inserted into table $table_district successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error inserting data into table $table_district: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 5. Create table data
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 5. Create table ' . $table_data . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_data (
        `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `chat_ids_to_send` TEXT DEFAULT NULL,
        `chat_ids_sent` TEXT DEFAULT NULL,
        `done` tinyint(1) DEFAULT NULL,
        `property_type` tinyint(1) DEFAULT NULL,
        `city` bigint NOT NULL,
        `district` bigint DEFAULT NULL,
        `title` varchar(255) DEFAULT NULL,
        `title_ru` varchar(255) DEFAULT NULL,
        `title_en` varchar(255) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `description_ru` text DEFAULT NULL,
        `description_en` text DEFAULT NULL,
        `gallery` text DEFAULT NULL,
        `link` text DEFAULT NULL,
        `created_at` datetime DEFAULT NULL,
        `updated_at` datetime DEFAULT NULL,
        `price_kgs` int DEFAULT NULL,
        `price_usd` int DEFAULT NULL,
        `deposit_kgs` int DEFAULT NULL,
        `deposit_usd` int DEFAULT NULL,
        `owner` tinyint(1) DEFAULT NULL,
        `owner_name` varchar(255) DEFAULT NULL,
        `phone` varchar(255) DEFAULT NULL,
        `rooms` tinyint(1) DEFAULT NULL,
        `floor` tinyint(1) DEFAULT NULL,
        `total_floor` tinyint(1) DEFAULT NULL,
        `house_type` varchar(255) DEFAULT NULL,
        `sharing` tinyint(1) DEFAULT NULL,
        `animals` tinyint(1) DEFAULT NULL,
        `house_area` int DEFAULT NULL,
        `land_area` int DEFAULT NULL,
        `min_rent_month` int DEFAULT NULL,
        `condition` varchar(255) DEFAULT NULL,
        `additional` varchar(255) DEFAULT NULL,
        `heating` varchar(255) DEFAULT NULL,
        `renovation` varchar(255) DEFAULT NULL,
        `improvement_in` varchar(255) DEFAULT NULL,
        `improvement_out` varchar(255) DEFAULT NULL,
        `nearby` varchar(255) DEFAULT NULL,
        `furniture` varchar(255) DEFAULT NULL,
        `appliances` varchar(255) DEFAULT NULL,
        `utility` varchar(255) DEFAULT NULL,
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

$sql = "ALTER TABLE $table_data ADD UNIQUE KEY `id` (`id`);";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Unique key id added successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error adding unique key id: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
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

$sql = "ALTER TABLE $table_rate ADD UNIQUE KEY `rate_id` (`rate_id`);";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Unique key rate_id added successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error adding unique key rate_id: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

$sql = "INSERT INTO `rate` (`rate_id`, `usd`, `eur`, `gbp`, `cny`, `rub`, `kzt`, `date_updated`, `date_added`) VALUES
(1, 89.3200, 94.3398, 108.3362, 12.2059, 0.9506, 0.1899, '2023-10-27T10:00:08.000000Z', '2023-10-30 00:35:00'),
(2, 89.3200, 94.3666, 108.3362, 12.2059, 0.9548, 0.1893, '2023-10-30T10:10:05.000000Z', '2023-10-30 00:35:00'),
(3, 89.3192, 95.0803, 108.3362, 12.2059, 0.9642, 0.1902, '2023-10-31T10:00:07.000000Z', '2023-10-31 00:35:00');";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Data inserted into table $table_rate successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error inserting data into table $table_rate: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 7. Create table amenity
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 7. Create table ' . $table_amenity . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_amenity (
        `amenity_id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `amenity_name_en` varchar(255) NOT NULL,
        `amenity_name_ru` varchar(255) NOT NULL,
        `amenity_name_kg` varchar(255) NOT NULL,
        `amenity_slug` varchar(255) NOT NULL
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Table $table_amenity created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating table $table_amenity: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

$sql = "ALTER TABLE $table_amenity ADD UNIQUE KEY `amenity_id` (`amenity_id`);";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Unique key amenity_id added successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error adding unique key amenity_id: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

$sql = "INSERT INTO `amenity` (`amenity_id`, `amenity_name_en`, `amenity_name_ru`, `amenity_name_kg`, `amenity_slug`) VALUES
(1, 'Balcony', 'Балкон', 'Балкон', 'balkon'),
(2, 'Armored doors', 'Бронированные двери', 'Бронированные двери', 'bronirovannie-dveri'),
(3, 'Dressing room', 'Гардеробная комната', 'Гардеробная комната', 'garderobnaya-komnata'),
(4, 'Ironing board', 'Гладильная доска', 'Гладильная доска', 'gladilynaya-doska'),
(5, 'Intercom', 'Домофон', 'Домофон', 'domofon'),
(6, 'Internet, Wi-Fi', 'Интернет, Wi-Fi', 'Интернет, Wi-Fi', 'internet-wi-fi'),
(7, 'Cable TV', 'Кабельное телевидение', 'Кабельное телевидение', 'kabelynoe-televidenie'),
(8, 'Big balcony', 'Лоджия', 'Лоджия', 'lodzhiya'),
(9, 'Panoramic view', 'Панорамный вид', 'Панорамный вид', 'panoramniy-vid'),
(10, 'Plastic windows', 'Пластиковые окна', 'Пластиковые окна', 'plastikovie-okna'),
(11, 'Bed sheets', 'Постельное белье', 'Постельное белье', 'postelynoe-belye'),
(12, 'Vessel', 'Посуда', 'Посуда', 'posuda'),
(13, 'Alarm systems', 'Сигнализация', 'Сигнализация', 'signalizatsiya'),
(14, 'Warm floor', 'Теплый пол', 'Теплый пол', 'tepliy-pol'),
(15, 'Other amenities in the apartment', 'Другие удобства в квартире', 'Другие удобства в квартире', 'drugie-udobstva-v-kvartire'),
(16, 'Video surveillance', 'Видеонаблюдение', 'Видеонаблюдение', 'videonablyudenie'),
(17, 'Childrens playground', 'Детская площадка', 'Детская площадка', 'detskaya-plaschadka'),
(18, 'Private area', 'Закрытая территория', 'Закрытая территория', 'zakritaya-territoriya'),
(19, '24/7 security', 'Круглосуточная охрана', 'Круглосуточная охрана', 'kruglosutochnaya-okhrana'),
(20, 'Elevator', 'Лифт', 'Лифт', 'lift'),
(21, 'Parking', 'Парковка', 'Парковка', 'parkovka'),
(22, 'Pharmacy', 'Аптека', 'Аптека', 'apteka'),
(23, 'Bank', 'Банк', 'Банк', 'bank'),
(24, 'Pool', 'Бассейн', 'Бассейн', 'basseyn'),
(25, 'Playground', 'Детская площадка', 'Детская площадка', 'detskaya-ploschadka'),
(26, 'Kindergarten', 'Детский сад', 'Детский сад', 'detskiy-sad'),
(27, 'Cafe Restaurant', 'Кафе, ресторан', 'Кафе, ресторан', 'kafe-restoran'),
(28, 'Public transport station', 'Остановка общ. транспорта', 'Остановка общ. транспорта', 'ostanovka-obsch-transporta'),
(29, 'Park', 'Парк', 'Парк', 'park'),
(30, 'Clinic, hospital', 'Поликлиника, больница', 'Поликлиника, больница', 'poliklinika-bolynitsa'),
(31, 'Grocery store', 'Продуктовый магазин', 'Продуктовый магазин', 'produktoviy-magazin'),
(32, 'Market', 'Рынок', 'Рынок', 'rinok'),
(33, 'Beauty saloon', 'Салон красоты', 'Салон красоты', 'salon-krasoti'),
(34, 'Supermarket', 'Супермаркет', 'Супермаркет', 'supermarket'),
(35, 'Mall', 'ТРЦ', 'ТРЦ', 'trts'),
(36, 'Gym', 'Фитнес-зал', 'Фитнес-зал', 'fitnes-zal'),
(37, 'School', 'Школа', 'Школа', 'shkola'),
(38, 'Other', 'Другое', 'Другое', 'drugoe'),
(39, 'Fully furnished', 'С мебелью полностью', 'С мебелью полностью', 's-mebelyyu-polnostyyu'),
(40, 'Boiler', 'Водонагреватель', 'Водонагреватель', 'ariston'),
(41, 'Oven', 'Духовка', 'Духовка', 'dukhovka'),
(42, 'Air conditioner', 'Кондиционер', 'Кондиционер', 'konditsioner'),
(43, 'Multicooker', 'Мультиварка', 'Мультиварка', 'mulytivarka'),
(44, 'Heater', 'Обогреватель', 'Обогреватель', 'obogrevately'),
(45, 'Gas stove', 'Плита газовая', 'Плита газовая', 'plita-gazovaya'),
(46, 'Electric stove', 'Плита электрическая', 'Плита электрическая', 'plita-elektricheskaya'),
(47, 'Dishwasher', 'Посудомоечная машина', 'Посудомоечная машина', 'posudomoechnaya-mashina'),
(48, 'Vacuum cleaner', 'Пылесос', 'Пылесос', 'pilesos'),
(49, 'Washing machine', 'Стиральная машина', 'Стиральная машина', 'stiralynaya-mashina'),
(50, 'TV', 'Телевизор', 'Телевизор', 'televizor'),
(51, 'Iron', 'Утюг', 'Утюг', 'utyug'),
(52, 'Refrigerator', 'Холодильник', 'Холодильник', 'kholodilynik'),
(53, 'Other household appliances', 'Другая бытовая техника', 'Другая бытовая техника', 'drugaya-bitovaya-tekhnika'),
(54, 'Water pipes', 'Водопровод', 'Водопровод', 'vodoprovod'),
(55, 'Gas', 'Газ', 'Газ', 'gaz'),
(56, 'Sewerage', 'Канализация', 'Канализация', 'kanalizatsiya'),
(57, 'Electricity', 'Электричество', 'Электричество', 'elektrichestvo'),
(58, 'High quality renovation', 'С евроремонтом', 'С евроремонтом', 's-evroremontom'),
(59, 'Concierge', 'Консьерж', 'Консьерж', 'konsyerzh'),
(60, 'Sports ground', 'Спортивная площадка', 'Спортивная площадка', 'sportivnaya-ploschadka'),
(61, 'Redecorating', 'Косметический ремонт', 'Косметический ремонт', 'kosmeticheskiy-remont'),
(62, 'Other Home Improvements', 'Другие благоустройства дома', 'Другие благоустройства дома', 'drugie-blagoustroystva-doma'),
(63, 'Gym', 'Тренажерный зал', 'Тренажерный зал', 'trenazherniy-zal'),
(64, 'Partially furnished', 'С мебелью частично', 'С мебелью частично', 's-mebelyyu-chastichno'),
(65, 'No furniture', 'Без мебели', 'Без мебели', 'bez-mebeli'),
(66, 'Without renovation', 'Без ремонта', 'Без ремонта', 'bez-remonta'),
(67, 'Fence, fenced', 'Забор, огорожен', 'Забор, огорожен', 'zabor-ogorozhen'),
(68, 'Basement', 'Подвал', 'Подвал', 'podval-pogreb'),
(69, 'Palace', 'Сарай', 'Сарай', 'saray'),
(70, 'Insulated', 'Утепленный', 'Утепленный', 'uteplenniy'),
(71, 'Gas heating', 'Газовое отопление', 'Газовое отопление', 'gazovoe-otoplenie'),
(72, 'Furnished', 'С мебелью', 'С мебелью', 's-mebelyyu'),
(73, 'Other utility lines', 'Другие коммунальные линии', 'Другие коммунальные линии', 'drugie-kommunalynie-linii'),
(74, 'Freshly renovated', 'Свежий ремонт', 'Свежий ремонт', 'svezhiy-remont'),
(75, 'High-quality renovation', 'Евроремонт', 'Евроремонт', 'evroremont'),
(76, 'Combined heating', 'Комбинированное отопление', 'Комбинированное отопление', 'kombinirovannoe-otoplenie'),
(77, 'Electric heating', 'Электрическое отопление', 'Электрическое отопление', 'elektricheskoe-otoplenie'),
(78, 'Central heating', 'Центральное отопление', 'Центральное отопление', 'tsentralynoe-otoplenie'),
(79, 'Kitchen furniture', 'Кухонная мебель', 'Кухонная мебель', 'kukhonnaya-mebely'),
(80, 'Glazed balcony', 'Балкон застеклен', 'Балкон застеклен', 'balkon-zasteklen'),
(81, 'Old renovation', 'Старый ремонт', 'Старый ремонт', 'stariy-remont'),
(82, 'Heating system', 'Автономное отопление', 'Автономное отопление', 'avtonomnoe-otoplenie'),
(83, 'Needs renovation', 'Требуется ремонт', 'Требуется ремонт', 'trebuetsya-remont'),
(84, 'Yes', 'Да', 'Да', 'da');";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Data inserted into table $table_amenity successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error inserting data into table $table_amenity: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 8. Create table property
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 8. Create table ' . $table_property . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_property (
        `property_id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `property_name_en` varchar(255) NOT NULL,
        `property_name_ru` varchar(255) NOT NULL,
        `property_name_kg` varchar(255) NOT NULL,
        `property_slug` varchar(255) NOT NULL,
        `property_icon` varchar(255) NOT NULL,
        `property_link` varchar(255) NOT NULL
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Table $table_property created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating table $table_property: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

$sql = "ALTER TABLE $table_property ADD UNIQUE KEY `property_id` (`property_id`);";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Unique key property_id added successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error adding unique key property_id: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

$sql = "INSERT INTO `property` (`property_id`, `property_name_en`, `property_name_ru`, `property_name_kg`, `property_slug`, `property_icon`, `property_link`) VALUES
(1, 'Apartment', 'Квартира', 'Квартира', 'apartment', '🏢', '/kvartiry/arenda-kvartir/dolgosrochnaya-arenda-kvartir'),
(2, 'House', 'Дом', 'Дом', 'house', '🏠', '/doma-i-dachi/arenda-domov/dolgosrochno-dom'),
(3, 'Room', 'Комната', 'Комната', 'room', '🛏', '/komnaty/arenda-komnat/dolgosrochnaya');";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Data inserted into table $table_property successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error inserting data into table $table_property: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 9. Create table owner
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 9. Create table ' . $table_owner . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_owner (
        `owner_id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `owner_name_en` varchar(255) NOT NULL,
        `owner_name_ru` varchar(255) NOT NULL,
        `owner_name_kg` varchar(255) NOT NULL,
        `owner_slug` varchar(255) NOT NULL
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error());
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Table $table_owner created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating table $table_owner: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

$sql = "ALTER TABLE $table_owner ADD UNIQUE KEY `owner_id` (`owner_id`);";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Unique key owner_id added successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error adding unique key owner_id: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

$sql = "INSERT INTO `owner` (`owner_id`, `owner_name_en`, `owner_name_ru`, `owner_name_kg`, `owner_slug`) VALUES
(1, 'Agent', 'Риэлтор', 'Риэлтор', 'agent'),
(2, 'Owner', 'Собственник', 'Собственник', 'owner'),
(3, 'Agent not allowed', 'Риэлторам не беспокоить', 'Риэлторам не беспокоить', 'agent_not_allowed'),
(4, 'Agent allowed', 'Готов к работе с риэлторами', 'Готов к работе с риэлторами', 'agent_allowed');";

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Data inserted into table $table_owner successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error inserting data into table $table_owner: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 10. Create table donation
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 10. Create table ' . $table_donation . PHP_EOL, FILE_APPEND);
$sql = "CREATE TABLE IF NOT EXISTS $table_donation (
        `donation_id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `is_active` tinyint(1) DEFAULT '1',
        `donation_name_en` varchar(255) NOT NULL,
        `donation_name_ru` varchar(255) NOT NULL,
        `donation_name_kg` varchar(255) NOT NULL,
        `donation_icon` varchar(255) NOT NULL,
        `donation_slug` varchar(255) NOT NULL,
        `donation_link` text NOT NULL
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error());
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Table $table_donation created successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error creating table $table_donation: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 10. Close connection
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 10. Close connection' . PHP_EOL . PHP_EOL, FILE_APPEND);
mysqli_close($conn);
