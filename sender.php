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
$sender_log_file = $log_dir . '/sender.log';
$sender_error_log_file = $log_dir . '/sender_error.log';
file_put_contents($sender_log_file, '[' . date('Y-m-d H:i:s') . '] Start', FILE_APPEND);

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



// 2. Send messages to telegram
$sql = "SELECT * FROM $table_user WHERE is_deleted = 0 OR is_deleted IS NULL";
$users_result = mysqli_query($conn, $sql);
if (mysqli_num_rows($users_result)) {
    file_put_contents($sender_log_file, ' | Active users: ' . mysqli_num_rows($users_result), FILE_APPEND);
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
                $title = $row['title'];
                $district = $row['district'];
                $link = $row['link'];
                $created_at = $row['created_at'];
                $updated_at = $row['updated_at'];
                $price_kgs = $row['price_kgs'];
                $price_usd = $row['price_usd'];
                $deposit_kgs = $row['deposit_kgs'];
                $deposit_usd = $row['deposit_usd'];
                $owner = $row['owner'];
                $owner_name = $row['owner_name'];
                $phone = $row['phone'];
                $rooms = $row['rooms'];
                $floor = $row['floor'];
                $house_type = $row['house_type'];
                $sharing = $row['sharing'];
                $furniture = $row['furniture'];
                $condition = $row['condition'];
                $renovation = $row['renovation'];
                $animals = $row['animals'];

                $message = "<b>$title</b>";
                if ($renovation !== 'n/d' && $renovation !== NULL) {
                    $message .= ", $renovation\n";
                } else {
                    $message .= "\n";
                }
                if ($district) {
                    if ($user_language === 'ru' || $user_language === 'kg') {
                        $sql_district = "SELECT district_name_ru FROM $table_district WHERE district_id = $district";
                    } else {
                        $sql_district = "SELECT district_name_en FROM $table_district WHERE district_id = $district";
                    }
                    $result_district = mysqli_query($conn, $sql_district);
                    $row_district = mysqli_fetch_assoc($result_district);
                    if ($user_language === 'ru' || $user_language === 'kg') {
                        $district_name = $row_district['district_name_ru'];
                        $message .= "<b>Район:</b> $district_name\n";
                    } else {
                        $district_name = $row_district['district_name_en'];
                        $message .= "<b>District:</b> $district_name\n";
                    }
                }
                if ($price_kgs !== 'n/d' && $price_kgs !== NULL)        $message .= "<b>Цена:</b> $price_kgs KGS ($price_usd USD)\n";
                if ($deposit_kgs !== 'n/d' && $deposit_kgs !== NULL)    $message .= "<b>Депозит:</b> $deposit_kgs KGS ($deposit_usd USD)\n";
                if ($house_type !== 'n/d' && $house_type !== NULL)      $message .= "<b>Серия:</b> $house_type\n";
                if ($sharing !== 'n/d' && $sharing !== NULL) {
                    if ($sharing === '1') {
                        $message .= "<b>Подселение:</b> без подселения\n";
                    } elseif ($sharing === '0') {
                        $message .= "<b>Подселение:</b> с подселением\n";
                    }
                }
                // if ($rooms !== 'n/d' && $rooms !== NULL)    $message .= "<b>Комнат:</b> $rooms\n";
                if ($floor !== 'n/d' && $floor !== NULL)       $message .= "<b>Этаж:</b> $floor\n";
                // if ($furniture !== 'n/d' && $furniture !== NULL) $message .= "<b>Мебель:</b> $furniture\n";
                if ($condition !== 'n/d' && $condition !== NULL)   $message .= "<b>Состояние:</b> $condition\n";
                if ($animals !== 'n/d' && $animals !== NULL)     $message .= "<b>Животные:</b> $animals\n";
                if ($owner !== 'n/d' && $owner !== NULL && $owner_name !== 'n/d' && $owner_name !== NULL) {
                    $message .= "<b>Кто сдает:</b> $owner, $owner_name\n";
                } else {
                    if ($owner !== 'n/d' && $owner !== NULL)   $message .= "<b>Кто сдает:</b> $owner\n";
                    if ($owner_name !== 'n/d' && $owner_name !== NULL) $message .= "<b>Имя:</b> $owner_name\n";
                }
                if ($phone !== 'n/d' && $phone !== NULL)       $message .= "<b>Телефон:</b> $phone\n";
                if ($created_at !== $updated_at) {
                    if ($created_at !== 'n/d' && $created_at !== NULL) $message .= "<b>Создано:</b> $created_at\n";
                    if ($updated_at !== 'n/d' && $updated_at !== NULL) $message .= "<b>Обновлено:</b> $updated_at\n";
                } else {
                    if ($created_at !== 'n/d' && $created_at !== NULL) $message .= "<b>Создано:</b> $created_at\n";
                }
                $message .= "$link\n";

                try {
                    $bot = new \TelegramBot\Api\BotApi($token);
                    $bot->sendMessage($chat_id, $message, 'HTML');
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
                    break;
                }
                $counter++;
            }
        }
        file_put_contents($sender_log_file, ' | Msgs for ' . $username . ' sent: ' . $counter, FILE_APPEND);
    }
} else {
    file_put_contents($sender_log_file, ' | No active users found', FILE_APPEND);
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
