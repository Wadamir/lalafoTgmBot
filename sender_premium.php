<?php
/*
Sending to telegram users
*******************************************
1. Set all variables & constants
2. Send messages to telegram
*/



// 1. Set all variables & constants
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/config.php';
$log_dir = __DIR__ . '/logs';
$sender_log_file = $log_dir . '/sender_premium.log';
$sender_error_log_file = $log_dir . '/sender_premium_error.log';
file_put_contents($sender_log_file, '[' . date('Y-m-d H:i:s') . '] Start Premium', FILE_APPEND);

$users_total = 0;
$users_active = 0;
$msg_sent = 0;
$msg_error = 0;

$token = TOKEN;

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
    file_put_contents($sender_error_log_file, '[' . date('Y-m-d H:i:s') . '] Connection failed' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}
if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($sender_error_log_file, '[' . date('Y-m-d H:i:s') . '] Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

// Get arguments
$arguments = $_SERVER['argv'];

$formatter_usd = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
$formatter_usd->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);

$formatter_kgs = new NumberFormatter('ru_RU', NumberFormatter::CURRENCY);
$formatter_kgs->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);



// 2. Send messages to telegram users with active subscription
$now_minus_9_min = date('Y-m-d H:i:s', strtotime('9 minutes'));
$sql = "SELECT * FROM $table_user WHERE (is_deleted = 0 OR is_deleted IS NULL) AND (date_payment IS NULL OR date_payment > '$now_minus_9_min')";
$users_result = mysqli_query($conn, $sql);
if ($users_result && mysqli_num_rows($users_result)) {
    file_put_contents($sender_log_file, ' | Active premium users: ' . mysqli_num_rows($users_result), FILE_APPEND);
    $users_rows = mysqli_fetch_all($users_result, MYSQLI_ASSOC);
    foreach ($users_rows as $user) {
        $tgm_user_id = $user['tgm_user_id'];
        $chat_id = $user['chat_id'];
        $username = $user['username'];
        $user_language = $user['language_code'];


        // select ids from data table where user_id is in chat_ids_to_send and not in chat_ids_sent
        $sql = "SELECT id FROM $table_data WHERE JSON_CONTAINS(chat_ids_sent, '\"$chat_id\"') AND done IS NULL";
        $result = mysqli_query($conn, $sql);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $sent_ids_array = array_column($rows, 'id');
        $sent_ids = implode(',', $sent_ids_array);


        $sql = "SELECT * FROM $table_data WHERE JSON_CONTAINS(chat_ids_to_send, '\"$chat_id\"') AND done IS NULL";
        if (!empty($sent_ids)) {
            $sql .= " AND id NOT IN ($sent_ids)";
        }
        $result = mysqli_query($conn, $sql);
        $counter = 0;
        if (mysqli_num_rows($result) > 0) {
            $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
            foreach ($rows as $row) {
                $property_type = ($row['property_type']) ? intval($row['property_type']) : NULL;
                $title = ($user_language === 'ru' || $user_language === 'kg') ? $row['title_ru'] : $row['title_en'];
                $district = ($row['district']) ? $row['district'] : NULL;
                if ($district !== NULL) {
                    $district = getDistrictById($district);
                    $district = ($user_language === 'ru' || $user_language === 'kg') ? $district['district_name_ru'] : $district['district_name_en'];
                }
                $link = $row['link'];
                $created_at = ($row['created_at']) ? date('d.m.Y', strtotime($row['created_at'])) : NULL;
                $updated_at = ($row['updated_at']) ? date('d.m.Y', strtotime($row['updated_at'])) : NULL;
                $price_kgs = ($row['price_kgs']) ? $formatter_kgs->formatCurrency($row['price_kgs'], 'KGS') : NULL;
                $price_usd = ($row['price_usd']) ? $formatter_usd->formatCurrency($row['price_usd'], 'USD') : NULL;
                $deposit_kgs = ($row['deposit_kgs']) ? $formatter_kgs->formatCurrency($row['deposit_kgs'], 'KGS') : NULL;
                $deposit_usd = ($row['deposit_usd']) ? $formatter_usd->formatCurrency($row['deposit_usd'], 'USD') : NULL;
                $owner = ($row['owner']) ? $row['owner'] : NULL;
                if ($row['owner_name']) {
                    $owner_name = ($user_language === 'ru' || $user_language === 'kg') ? $row['owner_name'] : slug($row['owner_name'], true);
                } else {
                    $owner_name = NULL;
                }
                $phone = ($row['phone']) ? $row['phone'] : NULL;
                $rooms = ($row['rooms']) ? $row['rooms'] : NULL;
                $floor = ($row['floor']) ? $row['floor'] : NULL;
                $total_floor = ($row['total_floor']) ? $row['total_floor'] : NULL;
                $house_type = ($row['house_type']) ? $row['house_type'] : NULL;
                $sharing = ($row['sharing']) ? $row['sharing'] : NULL;
                $animals = ($row['animals']) ? $row['animals'] : NULL;
                $house_area = ($row['house_area']) ? $row['house_area'] : NULL;
                $land_area = ($row['land_area']) ? $row['land_area'] : NULL;
                $min_rent_month = ($row['min_rent_month']) ? $row['min_rent_month'] : NULL;
                $condition = ($row['condition']) ? $row['condition'] : NULL;
                $additional = ($row['additional']) ? $row['additional'] : NULL;
                $heating = ($row['heating']) ? $row['heating'] : NULL;
                $renovation = ($row['renovation']) ? $row['renovation'] : NULL;
                $improvement_in = ($row['improvement_in']) ? $row['improvement_in'] : NULL;
                $improvement_out = ($row['improvement_out']) ? $row['improvement_out'] : NULL;
                $furniture = ($row['furniture']) ? $row['furniture'] : NULL;
                $appliances = ($row['appliances']) ? $row['appliances'] : NULL;
                $utility = ($row['utility']) ? $row['utility'] : NULL;

                $description = ($row['description']) ? $row['description'] : NULL;
                if ($description !== NULL) {
                    $description_ru = ($row['description_ru'] !== '' && $row['description_ru'] !== NULL) ? $row['description_ru'] : NULL;
                    $description_en = ($row['description_en'] !== '' && $row['description_en'] !== NULL) ? $row['description_en'] : NULL;
                }
                $description = ($user_language === 'ru' || $user_language === 'kg') ? $description_ru : $description_en;


                $message = "<b>$title</b>\n\n";

                if ($phone !== 'n/d' && $phone !== NULL && $phone !== '') {
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Телефон:</b> $phone " : "<b>Phone:</b> $phone ";
                    $message .= "(<a href='https://wa.me/$phone'>Whatsapp</a>)\n";
                }
                if ($owner_name !== 'n/d' && $owner_name !== NULL) {
                    $owner_name_en = slug($owner_name, true);
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Кто сдаёт:</b> $owner_name\n" : "<b>Owner:</b> $owner_name_en\n";
                }
                if ($link !== 'n/d' && $link !== NULL) {
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Ссылка:</b> $link\n" : "<b>Link:</b> $link\n";
                }
                $message .= "\n";
                if ($district !== NULL) {
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Район:</b> $district\n" : "<b>District:</b> $district\n";
                }
                if ($house_type !== 'n/d' && $house_type !== NULL) {
                    $house_type_en = slug($house_type, true);
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Серия:</b> $house_type\n" : "<b>House type:</b> $house_type_en\n";
                }
                if ($sharing !== 'n/d' && $sharing !== NULL) {
                    if ($sharing === '1') {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "👉 <b>Без подселения</b>\n" : "👉 <b>Without sharing</b>\n";
                    } elseif ($sharing === '0') {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "👉 <b>С подселением</b>\n" : "👉 <b>With sharing</b>\n";
                    }
                }
                if ($floor !== 'n/d' && $floor !== NULL && $total_floor !== 'n/d' && $total_floor !== NULL) {
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "👉 <b>Этаж:</b> $floor/$total_floor\n" : "👉 <b>Floor:</b> $floor/$total_floor\n";
                } elseif ($floor !== 'n/d' && $floor !== NULL) {
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "👉 <b>Этаж:</b> $floor\n" : "👉 <b>Floor:</b> $floor\n";
                } elseif ($total_floor !== 'n/d' && $total_floor !== NULL) {
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Всего этажей:</b> $total_floor\n" : "<b>Total floor:</b> $total_floor\n";
                }

                if ($min_rent_month !== 'n/d' && $min_rent_month !== NULL) {
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "\n❗ <b>Мин. срок аренды:</b> $min_rent_month месяцев\n" : "\n❗ <b>Min. rent period:</b> $min_rent_month months\n";
                }
                if ($price_kgs !== 'n/d' && $price_kgs !== NULL) {
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "\n💵 <b>Цена:</b> $price_kgs ($price_usd)\n" : "\n💵 <b>Price:</b> $price_kgs ($price_usd)\n";
                }
                if ($deposit_kgs !== 'n/d' && $deposit_kgs !== NULL) {
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "💵 <b>Депозит:</b> $deposit_kgs ($deposit_usd)\n" : "💵 <b>Deposit:</b> $deposit_kgs ($deposit_usd)\n";
                }
                $message .= "\n";
                if ($house_area !== 'n/d' && $house_area !== NULL) {
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "👉 <b>Площадь дома:</b> $house_area м²\n" : "👉 <b>House area:</b> $house_area sq.m.\n";
                }
                if ($land_area !== 'n/d' && $land_area !== NULL) {
                    $sqm = intval($land_area) * 100;
                    $message .= ($user_language === 'ru' || $user_language === 'kg') ? "👉 <b>Площадь участка:</b> $land_area соток\n" : "👉 <b>Land area:</b> $sqm sq.m.\n";
                }

                if ($animals !== 'n/d' && $animals !== NULL) {
                    if ($animals === '1') {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "👉 <b>Животные:</b> да\n" : "👉 <b>Animals:</b> yes\n";
                    } elseif ($animals === '0') {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "👉 <b>Животные:</b> нет\n" : "👉 <b>Animals:</b> no\n";
                    }
                }

                if ($furniture !== 'n/d' && $furniture !== NULL) {
                    $furniture_array = json_decode($furniture);
                    $furniture_array_name = [];
                    $counter = 0;
                    foreach ($furniture_array as $furniture_item) {
                        if ($counter === 3) break;
                        $furniture_data = getAmenityById($furniture_item);
                        $furniture_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $furniture_data['amenity_name_ru'] : $furniture_data['amenity_name_en'];
                    }
                    $furniture = implode(', ', $furniture_array_name);
                    $message .= "👉 $furniture\n";
                }
                if ($condition !== 'n/d' && $condition !== NULL) {
                    $condition_array = json_decode($condition);
                    $condition_array_name = [];
                    $counter = 0;
                    foreach ($condition_array as $condition_item) {
                        if ($counter === 3) break;
                        $condition_data = getAmenityById($condition_item);
                        $condition_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $condition_data['amenity_name_ru'] : $condition_data['amenity_name_en'];
                    }
                    $condition = implode(', ', $condition_array_name);
                    $message .= "👉 $condition\n";
                }
                if ($appliances !== 'n/d' && $appliances !== NULL) {
                    $appliances_array = json_decode($appliances);
                    $appliances_array_name = [];
                    $counter = 0;
                    foreach ($appliances_array as $appliances_item) {
                        if ($counter === 3) break;
                        $appliances_data = getAmenityById($appliances_item);
                        $appliances_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $appliances_data['amenity_name_ru'] : $appliances_data['amenity_name_en'];
                    }
                    $appliances = implode(', ', $appliances_array_name);
                    $message .= "👉 $appliances\n";
                }
                if ($improvement_in !== 'n/d' && $improvement_in !== NULL) {
                    $improvement_in_array = json_decode($improvement_in);
                    $improvement_in_array_name = [];
                    $counter = 0;
                    foreach ($improvement_in_array as $improvement_in_item) {
                        if ($counter === 3) break;
                        $improvement_in_data = getAmenityById($improvement_in_item);
                        $improvement_in_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $improvement_in_data['amenity_name_ru'] : $improvement_in_data['amenity_name_en'];
                    }
                    $improvement_in = implode(', ', $improvement_in_array_name);
                    $message .= "👉 $improvement_in\n";
                }
                if ($improvement_out !== 'n/d' && $improvement_out !== NULL) {
                    $improvement_out_array = json_decode($improvement_out);
                    $improvement_out_array_name = [];
                    $counter = 0;
                    foreach ($improvement_out_array as $improvement_out_item) {
                        if ($counter === 3) break;
                        $improvement_out_data = getAmenityById($improvement_out_item);
                        $improvement_out_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $improvement_out_data['amenity_name_ru'] : $improvement_out_data['amenity_name_en'];
                    }
                    $improvement_out = implode(', ', $improvement_out_array_name);
                    $message .= "👉 $improvement_out\n";
                }
                if ($property_type === 1) {
                    if ($utility !== 'n/d' && $utility !== NULL) {
                        $utility_array = json_decode($utility);
                        $utility_array_name = [];
                        $counter = 0;
                        foreach ($utility_array as $utility_item) {
                            if ($counter === 3) break;
                            $utility_data = getAmenityById($utility_item);
                            $utility_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $utility_data['amenity_name_ru'] : $utility_data['amenity_name_en'];
                        }
                        $utility = implode(', ', $utility_array_name);
                        $message .= "👉 $utility\n";
                    }
                }
                if (strlen($message) < 1000) {
                    $message .= "\n";
                    if ($description !== NULL) {
                        $message .= "\n";
                        $message .= "<i>$description</i>\n";
                    }
                }
                $message = cutString($message);
                /*
                if ($created_at !== $updated_at) {
                    if ($created_at !== 'n/d' && $created_at !== NULL) {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Создано:</b> $created_at\n" : "<b>Created:</b> $created_at\n";
                    }
                    if ($updated_at !== 'n/d' && $updated_at !== NULL) {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Обновлено:</b> $updated_at\n" : "<b>Updated:</b> $updated_at\n";
                    }
                } else {
                    if ($created_at !== 'n/d' && $created_at !== NULL) {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Создано:</b> $created_at\n" : "<b>Created:</b> $created_at\n";
                    }
                }
                */
                // $message .= "$link\n";

                $gallery = ($row['gallery']) ? json_decode($row['gallery']) : NULL;
                $new_gallery = [];
                if (!empty($gallery)) {
                    $gallery = array_map('strval', $gallery);
                    $gallery = array_unique($gallery);
                    $gallery = array_values($gallery);
                    sort($gallery);
                    foreach ($gallery as $image) {
                        if (remoteFileExists($image)) {
                            $new_gallery[] = $image;
                        }
                    }
                }
                if (empty($new_gallery)) {
                    $new_gallery[] = "https://wadamir.ru/no_photo.png";
                }
                try {
                    if (!empty($new_gallery)) {
                        $bot = new \TelegramBot\Api\BotApi($token);
                        $media = new \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia();
                        $image_counter = 0;
                        foreach ($new_gallery as $image) {
                            if ($image_counter === 9) break;
                            if ($image_counter === 0) {
                                $photo = new TelegramBot\Api\Types\InputMedia\InputMediaPhoto($image, $message, 'HTML');
                            } else {
                                $photo = new TelegramBot\Api\Types\InputMedia\InputMediaPhoto($image);
                            }
                            $media->addItem($photo);
                            $image_counter++;
                        }
                        $bot->sendMediaGroup($chat_id, $media);
                    } else {
                        $bot = new \TelegramBot\Api\BotApi($token);
                        $media = new \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia();
                        $image = "https://wadamir.ru/no_photo.png";
                        $photo = new TelegramBot\Api\Types\InputMedia\InputMediaPhoto($image, $message, 'HTML');
                        $media->addItem($photo);
                        $bot->sendMediaGroup($chat_id, $media);
                    }
                    // Update sent_to_user
                    $chat_ids_sent = [];
                    if ($row['chat_ids_sent'] !== '[]' && $row['chat_ids_sent'] !== '' && $row['chat_ids_sent'] !== NULL) {
                        $chat_ids_sent = json_decode($row['chat_ids_sent']);
                    }
                    $chat_ids_sent = array_map('strval', $chat_ids_sent);
                    $chat_ids_sent[] = strval($chat_id);
                    $chat_ids_sent = array_unique($chat_ids_sent);
                    $chat_ids_sent = array_values($chat_ids_sent);
                    sort($chat_ids_sent);
                    $chat_ids_sent = json_encode($chat_ids_sent);
                    $sql = "UPDATE $table_data SET chat_ids_sent = '$chat_ids_sent' WHERE id = " . $row['id'];
                    if (mysqli_query($conn, $sql)) {
                        $msg_sent++;
                    } else {
                        file_put_contents($sender_log_file, ' | User: ' . $username . ' | Error: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
                        $msg_error++;
                    }
                    $chat_ids_to_send = $row['chat_ids_to_send'];
                    $chat_ids_to_send = json_decode($chat_ids_to_send);
                    $chat_ids_to_send = array_map('strval', $chat_ids_to_send);
                    $chat_ids_to_send = array_unique($chat_ids_to_send);
                    $chat_ids_to_send = array_values($chat_ids_to_send);
                    sort($chat_ids_to_send);
                    $chat_ids_to_send = json_encode($chat_ids_to_send);
                    if ($chat_ids_sent === $chat_ids_to_send) {
                        $sql = "UPDATE $table_data SET done = '1' WHERE id = " . $row['id'];
                        if (mysqli_query($conn, $sql)) {
                            $msg_sent++;
                        } else {
                            file_put_contents($sender_log_file, ' | User: ' . $username . ' | Error: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
                            $msg_error++;
                        }
                    }
                } catch (\TelegramBot\Api\Exception $e) {
                    $error = $e->getMessage();
                    file_put_contents($sender_error_log_file, ' | User: ' . $username . ' Error: ' . $e->getMessage(), FILE_APPEND);
                    if ($error === 'Forbidden: bot was blocked by the user') {
                        try {
                            deactivateUser($tgm_user_id, $chat_id);
                        } catch (Exception $e) {
                            file_put_contents($sender_error_log_file, ' | Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                        }
                    }
                    if (strpos($error, 'Wrong file identifier') !== false) {
                        removeGalleryFirstImage($row['id']);
                    }
                }
                // set timeout
                $rnd_sec = rand(1, 3);
                sleep($rnd_sec);
                $counter++;
            }
        }
        file_put_contents($sender_log_file, ' | Msgs for ' . $username . ' sent: ' . $counter, FILE_APPEND);
    }
} else {
    file_put_contents($sender_log_file, ' | No active premium users found', FILE_APPEND);
}

file_put_contents($sender_log_file, ' | End: ' . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);
mysqli_close($conn);



function deactivateUser($tgm_user_id, $chat_id)
{
    global $sender_log_file;
    global $sender_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USER;
    $table_data = MYSQL_TABLE_DATA;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($sender_error_log_file, ' | deactivateUser - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "UPDATE $table_user SET is_deleted = 1 WHERE tgm_user_id = " . $tgm_user_id;
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        file_put_contents($sender_error_log_file, " | deactivateUser - error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
        return false;
    }

    // remove  from chat_ids_to_send
    $sql = "SELECT * FROM $table_data WHERE JSON_CONTAINS(chat_ids_to_send, '\"$chat_id\"') AND done IS NULL";
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        file_put_contents($sender_error_log_file, " | deactivateUser - error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
        return false;
    } else {
        if (mysqli_num_rows($result) > 0) {
            $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
            foreach ($rows as $row) {
                $chat_ids_to_send = $row['chat_ids_to_send'];
                $chat_ids_to_send = json_decode($chat_ids_to_send);
                $chat_ids_to_send = array_map('strval', $chat_ids_to_send);
                $chat_ids_to_send = array_unique($chat_ids_to_send);
                $chat_ids_to_send = array_values($chat_ids_to_send);
                sort($chat_ids_to_send);
                $chat_id_key = array_search($chat_id, $chat_ids_to_send);
                if ($chat_id_key !== false) {
                    unset($chat_ids_to_send[$chat_id_key]);
                }
                $chat_ids_to_send = json_encode($chat_ids_to_send);
                $sql = "UPDATE $table_data SET chat_ids_to_send = '$chat_ids_to_send' WHERE id = " . $row['id'];
                if (mysqli_query($conn, $sql)) {
                    file_put_contents($sender_log_file, ' | Chat id: ' . $chat_id . ' removed', FILE_APPEND);
                } else {
                    file_put_contents($sender_error_log_file, ' | Error: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
                    throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
                    return false;
                }
            }
        }
    }

    // Close connection
    mysqli_close($conn);

    return true;
}

function getAmenityById($amenity_id)
{
    global $sender_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_amenity = MYSQL_TABLE_AMENITY;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($sender_error_log_file, ' | getAmenityById - connection failed' . PHP_EOL, FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "SELECT * FROM $table_amenity WHERE amenity_id = '$amenity_id'";
    $result = mysqli_query($conn, $sql);
    if ($result !== false && mysqli_num_rows($result) > 0) {
        $amenity = mysqli_fetch_assoc($result);
    } else {
        $amenity = false;
    }

    // Close connection
    mysqli_close($conn);

    return $amenity;
}

function getDistrictById($district_id)
{
    global $sender_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_district = MYSQL_TABLE_DISTRICT;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($sender_error_log_file, ' | getDistrictById - connection failed' . PHP_EOL, FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "SELECT * FROM $table_district WHERE district_id = '$district_id'";
    $result = mysqli_query($conn, $sql);
    if ($result !== false && mysqli_num_rows($result) > 0) {
        $district = mysqli_fetch_assoc($result);
    } else {
        $district = false;
    }

    // Close connection
    mysqli_close($conn);

    return $district;
}

function slug($string, $transliterate = false)
{
    $rus = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я', ' ');

    $lat = array('A', 'B', 'V', 'G', 'D', 'E', 'E', 'Zh', 'Z', 'I', 'Y', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', 'Kh', 'Ts', 'Ch', 'Sh', 'Sch', 'Y', 'I', 'Y', 'E', 'Yu', 'Ya', 'a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'kh', 'ts', 'ch', 'sh', 'sch', 'y', 'i', 'y', 'e', 'yu', 'ya', ' ');

    $string = trim($string);
    $string = str_replace($rus, $lat, $string);
    if (!$transliterate) {
        $string = strtolower($string);
        $string = str_replace('-', '_', $string);
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $string);
    } else {
        $slug = $string;
    }
    return $slug;
}

function cutString($string, $max_length = 1000)
{
    if (strlen($string) <= $max_length) return $string;
    $string = substr($string, 0, $max_length);
    $string = rtrim($string, "!,.-");
    $string = substr($string, 0, strrpos($string, ' '));
    $string .= "...";

    return $string;
}

function remoteFileExists($url)
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_NOBODY, true);
    $result = curl_exec($curl);
    $ret = false;
    if ($result !== false) {
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($statusCode === 200) {
            $ret = true;
        }
    }
    curl_close($curl);
    return $ret;
}

function removeGalleryFirstImage($id)
{
    global $sender_log_file;
    global $sender_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_data = MYSQL_TABLE_DATA;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($sender_error_log_file, ' | removeGalleryFirstImage - connection failed' . PHP_EOL, FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "SELECT * FROM $table_data WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    if ($result !== false && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $gallery = ($row['gallery']) ? json_decode($row['gallery']) : NULL;
        $new_gallery = [];
        if (!empty($gallery)) {
            $gallery = array_map('strval', $gallery);
            $gallery = array_unique($gallery);
            $gallery = array_values($gallery);
            sort($gallery);
            $counter = 0;
            foreach ($gallery as $image) {
                if ($counter === 0) {
                    continue;
                }
                if (remoteFileExists($image)) {
                    $new_gallery[] = $image;
                }
                $counter++;
            }
        }
        if (empty($new_gallery)) {
            $new_gallery[] = "https://wadamir.ru/no_photo.png";
        }
        $new_gallery = json_encode($new_gallery);
        $sql = "UPDATE $table_data SET gallery = '$new_gallery' WHERE id = " . $row['id'];
        if (mysqli_query($conn, $sql)) {
            file_put_contents($sender_log_file, ' | Gallery first image removed', FILE_APPEND);
        }
    } else {
        return false;
    }

    // Close connection
    mysqli_close($conn);

    return true;
}
