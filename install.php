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
8. Close connection
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
        `preference_owner` tinyint(1) DEFAULT '1',
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

if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($log_dir . '/install.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

if (mysqli_query($conn, $sql)) {
    file_put_contents($log_dir . '/install.log', "Data inserted into table $table_amenity successfully" . PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/install.log', "Error inserting data into table $table_amenity: " . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
}

// 8. Close connection
file_put_contents($log_dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] 7. Close connection' . PHP_EOL . PHP_EOL, FILE_APPEND);
mysqli_close($conn);
