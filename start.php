<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/config.php';
$log_dir = __DIR__ . '/logs';

file_put_contents($log_dir . '/start.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Start ', FILE_APPEND);

$token = TOKEN;
if (!$token) {
    file_put_contents($log_dir . '/start.log', ' | Token not found' . PHP_EOL, FILE_APPEND);
    die('Token not found');
}

// Todo move to api
$get_content = file_get_contents("php://input");
if (!$get_content) {
    exit;
}
$update = json_decode($get_content, TRUE);
file_put_contents($log_dir . '/start.log', '[' . date('Y-m-d H:i:s') . '] Received: ' . $get_content . PHP_EOL, FILE_APPEND);

$command_data = '';
if (isset($update['message'])) {
    file_put_contents($log_dir . '/start.log', ' | Message: ' . $update['message']['text'], FILE_APPEND);
    $user_data = [
        'user_id' => $update['message']['from']['id'],
        'is_bot' => (isset($update['message']['from']['is_bot']) && $update['message']['from']['is_bot'] !== 'false' && $update['message']['from']['is_bot'] !== false) ? 1 : 0,
        'first_name' => (isset($update['message']['from']['first_name']) && $update['message']['from']['first_name'] !== '') ? $update['message']['from']['first_name'] : null,
        'last_name' => (isset($update['message']['from']['last_name']) && $update['message']['from']['last_name'] !== '') ? $update['message']['from']['last_name'] : null,
        'username' => $update['message']['from']['username'],
        'language_code' => $update['message']['from']['language_code'],
        'is_premium' => (isset($update['message']['from']['is_premium']) && $update['message']['from']['is_premium'] !== 'false' && $update['message']['from']['is_premium'] !== false) ? 1 : 0,
        'chat_id' => $update['message']['chat']['id'],
        'text'  => $update['message']['text'],
    ];

    $chat_type = 'message';
    $chatId = $update["message"]["chat"]["id"];
    $message = $update["message"]["text"];
    $message_type = $update["message"]["entities"][0]["type"];
} elseif (isset($update['callback_query'])) {
    file_put_contents($log_dir . '/start.log', ' | Callback: ' . $update['callback_query']['data'], FILE_APPEND);
    $user_data = [
        'user_id' => $update['callback_query']['from']['id'],
        'is_bot' => (isset($update['callback_query']['from']['is_bot']) && $update['messacallback_querye']['from']['is_bot'] !== 'false' && $update['callback_query']['from']['is_bot'] !== false) ? 1 : 0,
        'first_name' => (isset($update['callback_query']['from']['first_name']) && $update['callback_query']['from']['first_name'] !== '') ? $update['callback_query']['from']['first_name'] : null,
        'last_name' => (isset($update['callback_query']['from']['last_name']) && $update['callback_query']['from']['last_name'] !== '') ? $update['callback_query']['from']['last_name'] : null,
        'username' => $update['callback_query']['from']['username'],
        'language_code' => $update['callback_query']['from']['language_code'],
        'is_premium' => (isset($update['callback_query']['from']['is_premium']) && $update['callback_query']['from']['is_premium'] !== 'false' && $update['callback_query']['from']['is_premium'] !== false) ? 1 : 0,
        'chat_id' => $update['callback_query']['message']['chat']['id'],
        'text'  => $update['callback_query']['message']['text'],
    ];

    $chat_type = 'callback_query';
    $chatId = $update['callback_query']["message"]["chat"]["id"];
    $messageId = $update['callback_query']["message"]["message_id"];
    $message = $update['callback_query']["message"]["text"];
    $message_type = $update['callback_query']["message"]["entities"][0]["type"];
    $command_data = $update['callback_query']['data'];
}

if ($chat_type === 'message' && $user_data['is_bot'] === 0 && $message_type === 'bot_command') {
    switch ($message) {
        case '/start':
            file_put_contents($log_dir . '/start.log', ' | Bot command - /start', FILE_APPEND);
            try {
                $bot = new \TelegramBot\Api\BotApi($token);
                $user_result = createUser($user_data);
                if ($user_result === true) {
                    // Send message
                    $messageText = "Hello, " . $user_data['first_name'] . "! You are successfully registered. Use /help command to get help.";
                    $messageResponse = $bot->sendMessage($chatId, $messageText);
                } else {
                    // Send message
                    $messageText = "Hello, " . $user_data['first_name'] . "! You are already registered.";
                    $messageResponse = $bot->sendMessage($chatId, $messageText);
                    file_put_contents($log_dir . '/start.log', ' | Existing user', FILE_APPEND);
                }
            } catch (Exception $e) {
                file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
            break;
        case '/stop':
            file_put_contents($log_dir . '/start.log', ' | Bot command - /stop', FILE_APPEND);
            try {
                // Send message
                $bot = new \TelegramBot\Api\BotApi($token);
                $messageText = "You are unsubscribed from bot updates. If you decide to restore notifications, use the /start command. You can find help setting up the bot here - https://wadamir.ru/lalafo-tgm-bot/";
                $messageResponse = $bot->sendMessage($chatId, $messageText);
                deactivateUser($user_data['user_id']);
            } catch (Exception $e) {
                file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            break;
        case '/help':
            file_put_contents($log_dir . '/start.log', ' | Bot command - /help', FILE_APPEND);
            try {
                // Send message
                $bot = new \TelegramBot\Api\BotApi($token);
                $messageText = "You can get help here - https://wadamir.ru/lalafo-tgm-bot/";
                $messageResponse = $bot->sendMessage($chatId, $messageText);
            } catch (Exception $e) {
                file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
            break;
        case '/settings':
            file_put_contents($log_dir . '/start.log', ' | Bot command - /settings', FILE_APPEND);
            try {
                // Send message
                $bot = new \TelegramBot\Api\BotApi($token);
                $inline_keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [
                            ['text' => '1', 'callback_data' => 'room_1'],
                            ['text' => '2', 'callback_data' => 'room_2'],
                            ['text' => '3+', 'callback_data' => 'room_3'],
                        ],
                    ]
                );
                $messageText = "<b>Настройка / Settings</b> \n\n❓Сколько минимум комнат в квартире вам нужно? \n❓How many minimum rooms in an apartment do you need? \n\n";
                $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML', false, null, $inline_keyboard);
            } catch (Exception $e) {
                file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
            break;
        default:
            file_put_contents($log_dir . '/start.log', ' | Bot command - undefined', FILE_APPEND);
            try {
                // Send message
                $bot = new \TelegramBot\Api\BotApi($token);
                $messageResponse = $bot->sendMessage($chatId, "Something went wrong. Try again later, please...");
            } catch (Exception $e) {
                file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
    }
} elseif ($chat_type === 'message' && strpos($message, "https://www.upwork.com/") === 0 && $message_type === 'url') {
    file_put_contents($log_dir . '/start.log', ' | Add RSS link - ' . $message, FILE_APPEND);
    try {
        $bot = new \TelegramBot\Api\BotApi($token);
        // $add_rss_link_response = addRssLink($user_data['user_id'], $user_data['text']);
        if ($add_rss_link_response) {
            // Send message
            $existing_links = implode("\n", $user_result);
            $messageText = "Ok, " . $user_data['first_name'] . "! I will send you updates from this channel. Use /getrss command to get list of your RSS links.";
            $messageResponse = $bot->sendMessage($chatId, $messageText);
        } else {
            // Send message
            $messageText = "Sorry, " . $user_data['first_name'] . "! This RSS link is already added. Use /getrss command to get list of your RSS links.";
            $messageResponse = $bot->sendMessage($chatId, $messageText);
        }
    } catch (Exception $e) {
        file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
    }
    file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
} elseif ($chat_type === 'callback_query' && strpos($command_data, "room") === 0) {
    file_put_contents($log_dir . '/start.log', ' | command_data - ' . $command_data, FILE_APPEND);
    $new_data = [];
    switch ($command_data) {
        case 'room_1':
            $new_data['rooms_min'] = 1;
            break;
        case 'room_2':
            $new_data['rooms_min'] = 2;
            break;
        case 'room_3':
            $new_data['rooms_min'] = 3;
            break;
        default:
            $new_data['rooms_min'] = 1;
    }
    $update_result = updateUser($new_data, $user_data['user_id']);
    if ($update_result) {
        // Send message
        $bot = new \TelegramBot\Api\BotApi($token);
        $inline_keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
            [
                [
                    ['text' => '200$', 'callback_data' => 'usd_200'],
                    ['text' => '300$', 'callback_data' => 'usd_300'],
                    ['text' => '400$', 'callback_data' => 'usd_400'],
                    ['text' => '500$', 'callback_data' => 'usd_500'],
                    ['text' => '600$', 'callback_data' => 'usd_600'],
                ],
                [
                    ['text' => '700$', 'callback_data' => 'usd_700'],
                    ['text' => '800$', 'callback_data' => 'usd_800'],
                    ['text' => '900$', 'callback_data' => 'usd_900'],
                    ['text' => '1000$', 'callback_data' => 'usd_1000'],
                    ['text' => '> 1000$', 'callback_data' => 'usd_none'],
                ]
            ]
        );
        $bot->deleteMessage($chatId, $messageId);
        $get_user_data = getUserData($user_data['user_id']);
        if (!empty($get_user_data)) {
            $messageText = "<b>Настройка / Settings</b>\n\n✅ Минимум комнат (minimum rooms): " . $get_user_data['rooms_min'] . "\n\n❓Максимальная стоимость аренды в месяц? \n❓Maximum rental cost per month? \n\n";
            $send_result = $bot->sendMessage($chatId, $messageText, 'HTML', false, null, $inline_keyboard);
        } else {
            $messageText = "Something went wrong. Try again later, please...";
            $bot->sendMessage($chatId, $messageText);
        }
    } else {
        $bot->deleteMessage($chatId, $messageId);
        $messageText = "Something went wrong. Try again later, please...";
        $bot->sendMessage($chatId, $messageText);
    }
    file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
} elseif ($chat_type === 'callback_query' && strpos($command_data, "usd") === 0) {
    file_put_contents($log_dir . '/start.log', ' | command_data - ' . $command_data, FILE_APPEND);
    $new_data = [];
    switch ($command_data) {
        case 'usd_100':
            $new_data['price_max'] = 100;
            break;
        case 'usd_200':
            $new_data['price_max'] = 200;
            break;
        case 'usd_300':
            $new_data['price_max'] = 300;
            break;
        case 'usd_400':
            $new_data['price_max'] = 400;
            break;
        case 'usd_500':
            $new_data['price_max'] = 500;
            break;
        case 'usd_600':
            $new_data['price_max'] = 600;
            break;
        case 'usd_700':
            $new_data['price_max'] = 700;
            break;
        case 'usd_800':
            $new_data['price_max'] = 800;
            break;
        case 'usd_900':
            $new_data['price_max'] = 900;
            break;
        case 'usd_1000':
            $new_data['price_max'] = 1000;
            break;
        case 'usd_none':
            $new_data['price_max'] = 1000000;
            break;
        default:
            $new_data['price_max'] = 1000000;
    }
    $new_data['price_currency'] = 'USD';
    $update_result = updateUser($new_data, $user_data['user_id']);
    if ($update_result) {
        $bot = new \TelegramBot\Api\BotApi($token);
        $bot->deleteMessage($chatId, $messageId);
        $get_user_data = getUserData($user_data['user_id']);
        if (!empty($get_user_data)) {
            file_put_contents($log_dir . '/start.log', ' | User data - ' . print_r($get_user_data, true), FILE_APPEND);
            $messageText = "<b>Настройки успешно сохранены!</b>\n<b>Settings succefully saved!</b>\n\n✅ Минимум комнат (minimum rooms): <b>" . $get_user_data['rooms_min'] . "</b>\n\✅ Максимальная стоимость аренды в месяц (maximum rental cost per month): <b>" . $get_user_data['price_max'] . "</b>";
            $bot->sendMessage($chatId, $messageText, 'HTML');
        } else {
            $messageText = "Something went wrong. Try again later, please...";
            $bot->sendMessage($chatId, $messageText);
        }
    } else {
        try {
            // Send message
            $bot = new \TelegramBot\Api\BotApi($token);
            $messageText = "Something went wrong. Try again later, please...";
            $messageResponse = $bot->sendMessage($chatId, $messageText);
        } catch (Exception $e) {
            file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
        }
    }
    file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($log_dir . '/start.log', ' | Bot command - undefined', FILE_APPEND);
    try {
        // Send message
        $bot = new \TelegramBot\Api\BotApi($token);
        $messageResponse = $bot->sendMessage($chatId, "Try again, please");
    } catch (Exception $e) {
        file_put_contents($log_dir . '/start.log', ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
    }
    file_put_contents($log_dir . '/start.log', PHP_EOL, FILE_APPEND);
}

function createUser($user_data)
{
    global $log_dir;
    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_users = MYSQL_TABLE_USERS;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Connection failed', FILE_APPEND);
        throw new ErrorException("Connection failed: " . mysqli_connect_error());
    }
    // Check if user exists
    $sql = "SELECT * FROM $table_users WHERE user_id = " . $user_data['user_id'];
    $result = mysqli_query($conn, $sql);
    // $rss_links = '';
    if (mysqli_num_rows($result) > 0) {
        file_put_contents($log_dir . '/start.log', ' | User already exists', FILE_APPEND);
        activateUser($user_data['user_id']);

        // $rss_links = getRssLinksByUser($user_data['user_id']);
        // Close connection
        mysqli_close($conn);
        return false; // $rss_links;
    } else {
        // Insert user
        unset($user_data['text']);
        $columns = implode(", ", array_keys($user_data));
        $escaped_values = array_map(array($conn, 'real_escape_string'), array_values($user_data));
        $values  = implode("', '", $escaped_values);
        $sql = "INSERT INTO $table_users ($columns) VALUES ('$values')";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            file_put_contents($log_dir . '/start.log', " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
            throw new ErrorException("Error: " . $sql . ' | ' . mysqli_error($conn));
        }
        // Close connection
        mysqli_close($conn);
        return true;
    }
}


function activateUser($user_id)
{
    global $log_dir;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_users = MYSQL_TABLE_USERS;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Update User - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "UPDATE $table_users SET is_deleted = NULL WHERE user_id = " . $user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($log_dir . '/start.log', " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }
    // Close connection
    mysqli_close($conn);

    return true;
}


function deactivateUser($user_id)
{
    global $log_dir;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_users = MYSQL_TABLE_USERS;
    $table_city = MYSQL_TABLE_CITY;
    $table_district = MYSQL_TABLE_DISTRICT;
    $table_data = MYSQL_TABLE_DATA;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Update User - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "UPDATE $table_users SET is_deleted = 1 WHERE user_id = " . $user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($log_dir . '/start.log', " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }

    // remove all from data table
    $sql = "DELETE FROM $table_data WHERE chat_id = " . $user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($log_dir . '/start.log', " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }

    // Close connection
    mysqli_close($conn);

    return true;
}


function updateUser($user_data, $user_id)
{
    global $log_dir;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_users = MYSQL_TABLE_USERS;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Update User - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "UPDATE $table_users SET";
    foreach ($user_data as $key => $value) {
        $sql .= " $key = '" . $value . "',";
    }
    $sql = rtrim($sql, ',');
    $sql .= " WHERE user_id = " . $user_id;
    file_put_contents($log_dir . '/start.log', ' | Update User - ' . $sql, FILE_APPEND);

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($log_dir . '/start.log', " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
        // Close connection
        mysqli_close($conn);
        return false;
    }

    return true; // $updateRssLinksResult;
}

function getUserData($user_id)
{
    global $log_dir;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_users = MYSQL_TABLE_USERS;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Get User Data - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "SELECT * FROM $table_users WHERE user_id = " . $user_id;
    file_put_contents($log_dir . '/start.log', ' | Get User Data - ' . $sql, FILE_APPEND);
    $result = mysqli_query($conn, $sql);
    $user_data = [];
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $user_data = [
                'user_id' => $row['user_id'],
                'is_bot' => $row['is_bot'],
                'is_deleted' => $row['is_deleted'],
                'is_premium' => $row['is_premium'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'username' => $row['username'],
                'language_code' => $row['language_code'],
                'chat_id' => $row['chat_id'],
                'refresh_time' => $row['refresh_time'],
                'price_min' => $row['price_min'],
                'price_max' => $row['price_max'],
                'price_currency' => $row['price_currency'],
                'rooms_min' => $row['rooms_min'],
                'rooms_max' => $row['rooms_max'],
                'preference_city' => $row['preference_city'],
                'preference_district' => $row['preference_district'],
                'date_updated' => $row['date_updated'],
                'date_added' => $row['date_added'],
            ];
        }
    }
    file_put_contents($log_dir . '/start.log', ' | Get User Data - ' . print_r($user_data, true), FILE_APPEND);
    // Close connection
    mysqli_close($conn);
    return $user_data;
}
/*
function getRssLinksByUser($user_id)
{
    global $log_dir;

    file_put_contents($log_dir . '/start.log', ' | Get RSS Links By User', FILE_APPEND);

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($log_dir . '/start.log', ' | Get RSS Links By User - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "SELECT * FROM $table_rss_links WHERE user_id = " . $user_id;
    $result = mysqli_query($conn, $sql);
    $rss_links = [];
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rss_links[] = [
                'id'        => $row['id'],
                'rss_link'  => $row['rss_link'],
                'rss_name'  => $row['rss_name'],
            ];
        }
    }
    // Close connection
    mysqli_close($conn);

    return $rss_links;
}
*/