<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/config.php';
$log_dir = __DIR__ . '/logs';
$start_log_file = $log_dir . '/start.log';
$start_error_log_file = $log_dir . '/start_error.log';

$token = TOKEN;
if (!$token) {
    file_put_contents($start_error_log_file, ' | Token not found' . PHP_EOL, FILE_APPEND);
    die('Token not found');
}

$adminChatId = ADMIN_CHAT_ID;

$dbhost = MYSQL_HOST;
$dbuser = MYSQL_USER;
$dbpass = MYSQL_PASSWORD;
$dbname = MYSQL_DB;
$table_user = MYSQL_TABLE_USER;
$table_city = MYSQL_TABLE_CITY;
$table_district = MYSQL_TABLE_DISTRICT;
$table_data = MYSQL_TABLE_DATA;

// Todo move to api
$get_content = file_get_contents("php://input");
if (!$get_content) {
    file_put_contents($start_error_log_file, ' | Get content failed' . PHP_EOL, FILE_APPEND);
    die('Get content failed');
}
$update = json_decode($get_content, TRUE);
file_put_contents($start_log_file, '[' . date('Y-m-d H:i:s') . '] Received: ', FILE_APPEND);

$command_data = '';
if (isset($update['message'])) {
    file_put_contents($start_log_file, ' | Message: ' . $update['message']['text'], FILE_APPEND);
    $user_data = [
        'tgm_user_id' => $update['message']['from']['id'],
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
    file_put_contents($start_log_file, ' | Callback: ' . $update['callback_query']['data'], FILE_APPEND);
    $user_data = [
        'tgm_user_id' => $update['callback_query']['from']['id'],
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

$user_language = $user_data['language_code'] === 'ru' ? 'ru' : $user_data['language_code'];

// Accumulate errors
$error_array = [];

if ($chat_type === 'message' && $user_data['is_bot'] === 0 && $message_type === 'bot_command') {
    file_put_contents($start_log_file, ' | Bot command - ' . $message, FILE_APPEND);
    $bot = new \TelegramBot\Api\BotApi($token);

    switch ($message) {
        case '/stop':
            try {
                // Send message
                $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "Вы отписаны от обновлений бота. Если решите восстановить уведомления, воспользуйтесь командой /start. Для обратной связи напишите боту сообщение с хештегом #feedback" : "You are unsubscribed from bot updates. If you decide to restart notifications, use the /start command. For feedback, write a message to the bot with the hashtag #feedback";
                $messageResponse = $bot->sendMessage($chatId, $messageText);
                deactivateUser($user_data['tgm_user_id']);
            } catch (Exception $e) {
                $error_array[] = $e->getMessage();
            }
            break;
        case '/help':
            try {
                // Send message
                $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "Для обратной связи напишите боту сообщение с хештегом #feedback" : "For feedback, write a message to the bot with the hashtag #feedback";
                $messageResponse = $bot->sendMessage($chatId, $messageText);
            } catch (Exception $e) {
                $error_array[] = $e->getMessage();
            }
            break;
        case '/start':
            try {
                // Send message
                $user_result = createUser($user_data);
                if ($user_result === true) { // New user
                    // Get all cities
                    $cities = getCity();
                    if (!empty($cities)) {
                        $city_array = [];
                        if ($user_language === 'ru' || $user_language === 'kg') {
                            foreach ($cities as $city) {
                                $city_array[] = ['text' => $city['name_ru'], 'callback_data' => 'city_' . $city['slug']];
                            }
                            $city_array[] = ['text' => 'Неважно', 'callback_data' => 'city_none'];
                        } else {
                            foreach ($cities as $city) {
                                $city_array[] = ['text' => $city['name_en'], 'callback_data' => 'city_' . $city['slug']];
                            }
                            $city_array[] = ['text' => 'No matter', 'callback_data' => 'city_none'];
                        }
                        $inline_keyboard_array = [];
                        foreach ($city_array as $key => $value) {
                            if ($key % 2 === 0) {
                                $inline_keyboard_array[] = [$value];
                            } else {
                                $inline_keyboard_array[count($inline_keyboard_array) - 1][] = $value;
                            }
                        }

                        $inline_keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($inline_keyboard_array);
                        $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "Привет, " . $user_data['first_name'] . "! Вы успешно зарегистрированы!" : "Hello, " . $user_data['first_name'] . "! You are successfully registered!";
                        $messageText .= ($user_language === 'ru' || $user_language === 'kg') ? "\n\n <b>Настройка</b> \n\n❓В каком городе вы ищете жилье? \n\n" : "\n\n <b>Settings</b> \n\n❓In which city are you looking for housing? \n\n";
                        file_put_contents($start_log_file, ' | Message - ' . $messageText, FILE_APPEND);
                        try {
                            $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML', false, null, $inline_keyboard);
                        } catch (Exception $e) {
                            $error_array[] = $e->getMessage();
                        }
                    } else {
                        $error_array[] = 'Cities not found';
                    }
                } else { // Returned user
                    $get_user_data = getUserData($user_data['tgm_user_id']);
                    if (!empty($get_user_data)) {
                        if ($get_user_data['price_max'] === 1000000) {
                            $user_max_price = ($user_language === 'ru' || $user_language === 'kg') ? 'без ограничений' : 'no limit';
                        } else {
                            $user_max_price = $get_user_data['price_max'] . ' ' . $get_user_data['price_currency'];
                        }
                        if ($get_user_data['preference_city'] === NULL) {
                            $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбран' : 'not selected';
                        } else {
                            $city = getCity($get_user_data['preference_city']);
                            $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? $city['name_ru'] : $city['name_en'];
                        }
                        // Send message
                        $messageText = ($user_language === 'ru' || $user_language === 'kg') ?  "С возвращением, " . $user_data['first_name'] . "!" : "Welcome back, " . $user_data['first_name'] . "!";
                        $messageText .= ($user_language === 'ru' || $user_language === 'kg') ? "\n\n<b>Ваши настройки</b>\n\n✅ Город: " . $get_user_data['city_name_ru'] . "\n✅ Минимум комнат: " . $get_user_data['rooms_min'] . "\n✅ Максимальная стоимость аренды в месяц: " . $user_max_price . "\n\nЕсли Вы хотите изменить настройки воспользуйтесь командой /settings\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "\n\n<b>Your search settings</b>\n\n✅ City: " . $get_user_data['city_name_en'] . "\n✅ Minimum rooms: " . $get_user_data['rooms_min'] . "\n✅ Maximum rental cost per month: " . $user_max_price . "\n\nIf you want to change the settings, use the /settings command\n\nFor feedback, write a message to the bot with the hashtag #feedback";
                        $send_result = $bot->sendMessage($chatId, $messageText, 'HTML', false, null, $inline_keyboard);
                    } else {
                        // Send message
                        $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\nFor feedback, write a message to the bot with the hashtag #feedback";
                        $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML');
                    }
                }
                // Close connection
                mysqli_close($conn);
            } catch (Exception $e) {
                file_put_contents($start_error_log_file, ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($start_log_file, PHP_EOL, FILE_APPEND);
            break;
        case '/settings':
            file_put_contents($start_log_file, ' | Bot command - /settings', FILE_APPEND);
            try {
                // Send message
                $bot = new \TelegramBot\Api\BotApi($token);

                // Send message
                $inline_keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [
                            ['text' => '1', 'callback_data' => 'room_1'],
                            ['text' => '2', 'callback_data' => 'room_2'],
                            ['text' => '3+', 'callback_data' => 'room_3'],
                        ],
                    ]
                );
                $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "\n\n <b>Настройка</b> \n\n❓Сколько минимум комнат в квартире вам нужно? \n\n" : "\n\n <b>Settings</b> \n\n❓How many minimum rooms in an apartment do you need? \n\n";
                $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML', false, null, $inline_keyboard);
            } catch (Exception $e) {
                file_put_contents($start_error_log_file, ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($start_log_file, PHP_EOL, FILE_APPEND);
            break;
        default:
            file_put_contents($start_log_file, ' | Bot command - undefined', FILE_APPEND);
            try {
                // Send message
                $bot = new \TelegramBot\Api\BotApi($token);
                $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\nFor feedback, write a message to the bot with the hashtag #feedback";
                $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML');
            } catch (Exception $e) {
                file_put_contents($start_error_log_file, ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
            }
            file_put_contents($start_log_file, PHP_EOL, FILE_APPEND);
    }
} elseif ($chat_type === 'message' && strpos($message, "#feedback") !== false) {
    file_put_contents($start_log_file, ' | Feedback - ' . $message, FILE_APPEND);
    $bot = new \TelegramBot\Api\BotApi($token);
    try {
        // Send message to admin
        $messageText = "Feedback: " . $user_data['first_name'] . "\n\n" . $message;
        $messageResponse = $bot->sendMessage($adminChatId, $messageText);

        // Send message to user
        $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "Спасибо! Ваше сообщение отправлено." : "Thank you! Your message has been sent.";
        $bot->sendMessage($chatId, $messageText);
    } catch (Exception $e) {
        file_put_contents($start_error_log_file, ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
    }
    file_put_contents($start_log_file, PHP_EOL, FILE_APPEND);
} elseif ($chat_type === 'callback_query' && strpos($command_data, "city") === 0) {
    file_put_contents($start_log_file, ' | command_data - ' . $command_data, FILE_APPEND);
    $city_slug = str_replace('city_', '', $command_data);
    file_put_contents($start_log_file, ' | city_slug - ' . $city_slug, FILE_APPEND);
    $bot = new \TelegramBot\Api\BotApi($token);
    if ($city_slug !== 'none') {
        $sql = "SELECT * FROM $table_city WHERE slug = '$city_slug'";
        $result = mysqli_query($conn, $sql);
        if ($result !== false && mysqli_num_rows($result) > 0) {
            $city_data = mysqli_fetch_assoc($result);
            $new_data = [];
            $new_data['preference_city'] = $city_data['id'];
            $update_result = updateUser($new_data, $user_data['tgm_user_id']);
        }
    } else {
        $update_result = true;
    }
    if ($update_result) {
        $bot->deleteMessage($chatId, $messageId);
        // Send message
        $inline_keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
            [
                [
                    ['text' => '1', 'callback_data' => 'room_1'],
                    ['text' => '2', 'callback_data' => 'room_2'],
                    ['text' => '3+', 'callback_data' => 'room_3'],
                ],
            ]
        );
        $get_user_data = getUserData($user_data['tgm_user_id']);
        if (!empty($get_user_data)) {
            $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройка</b>\n\n✅ Город: " . $get_user_data['city_name_ru'] . "\n\n❓Сколько минимум комнат в квартире вам нужно? \n\n" : "<b>Settings</b>\n\n✅ City: " . $get_user_data['city_name_en'] . "\n\n❓How many minimum rooms in an apartment do you need? \n\n";
            $send_result = $bot->sendMessage($chatId, $messageText, 'HTML', false, null, $inline_keyboard);
        } else {
            // Send message
            $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\nFor feedback, write a message to the bot with the hashtag #feedback";
            $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML');
        }
    } else {
        $bot->deleteMessage($chatId, $messageId);
        // Send message
        $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\nFor feedback, write a message to the bot with the hashtag #feedback";
        $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML');
    }

    // Close connection
    mysqli_close($conn);
    file_put_contents($start_log_file, PHP_EOL, FILE_APPEND);
} elseif ($chat_type === 'callback_query' && strpos($command_data, "room") === 0) {
    file_put_contents($start_log_file, ' | command_data - ' . $command_data, FILE_APPEND);
    $bot = new \TelegramBot\Api\BotApi($token);
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
    $update_result = updateUser($new_data, $user_data['tgm_user_id']);
    if ($update_result) {
        $bot->deleteMessage($chatId, $messageId);
        // Send message
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
        $get_user_data = getUserData($user_data['tgm_user_id']);
        if (!empty($get_user_data)) {
            $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройка</b>\n\n✅ Минимум комнат: " . $get_user_data['rooms_min'] . "\n\n❓Максимальная стоимость аренды в месяц?\n\n" : "<b>Settings</b>\n\n✅ Minimum rooms: " . $get_user_data['rooms_min'] . "\n\n❓Maximum rental cost per month? \n\n";
            $send_result = $bot->sendMessage($chatId, $messageText, 'HTML', false, null, $inline_keyboard);
        } else {
            // Send message
            $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\nFor feedback, write a message to the bot with the hashtag #feedback";
            $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML');
        }
    } else {
        $bot->deleteMessage($chatId, $messageId);
        // Send message
        $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\nFor feedback, write a message to the bot with the hashtag #feedback";
        $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML');
    }
    file_put_contents($start_log_file, PHP_EOL, FILE_APPEND);
} elseif ($chat_type === 'callback_query' && strpos($command_data, "usd") === 0) {
    file_put_contents($start_log_file, ' | command_data - ' . $command_data, FILE_APPEND);
    $bot = new \TelegramBot\Api\BotApi($token);
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
    $update_result = updateUser($new_data, $user_data['tgm_user_id']);
    if ($update_result) {
        $bot->deleteMessage($chatId, $messageId);
        $get_user_data = getUserData($user_data['tgm_user_id']);
        if (!empty($get_user_data)) {
            if ($get_user_data['price_max'] === 1000000) {
                $user_max_price = ($user_language === 'ru' || $user_language === 'kg') ? 'без ограничений' : 'no limit';
            } else {
                $user_max_price = $get_user_data['price_max'] . ' ' . $get_user_data['price_currency'];
            }
            $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройки успешно сохранены!</b>\n\n✅ Минимум комнат: " . $get_user_data['rooms_min'] . "\n\n✅ Максимальная стоимость аренды в месяц: " . $user_max_price . "\n\n👉 Вы будете получать мгновенные уведомления обо всех новых объявлениях ⚡⚡⚡\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "<b>Settings successfully saved!</b>\n\n✅ Minimum rooms: " . $get_user_data['rooms_min'] . "\n\n✅ Maximum rental cost per month: " . $user_max_price . "\n\n👉 You will receive instant notifications of all new ads ⚡⚡⚡\n\nFor feedback, write a message to the bot with the hashtag #feedback";
            $bot->sendMessage($chatId, $messageText, 'HTML');
            sendLastAds($user_data['tgm_user_id'], $chatId);
        } else {
            // Send message
            $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\nFor feedback, write a message to the bot with the hashtag #feedback";
            $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML');
        }
    } else {
        try {
            // Send message
            $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\nFor feedback, write a message to the bot with the hashtag #feedback";
            $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML');
        } catch (Exception $e) {
            file_put_contents($start_error_log_file, ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
        }
    }
    file_put_contents($start_log_file, PHP_EOL, FILE_APPEND);
} else {
    file_put_contents($start_log_file, ' | Bot command - undefined', FILE_APPEND);
    try {
        // Send message
        $bot = new \TelegramBot\Api\BotApi($token);
        $messageText = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\nFor feedback, write a message to the bot with the hashtag #feedback";
        $messageResponse = $bot->sendMessage($chatId, $messageText, 'HTML');
    } catch (Exception $e) {
        file_put_contents($start_error_log_file, ' | ERROR - ' . $e->getMessage(), FILE_APPEND);
    }
    file_put_contents($start_log_file, PHP_EOL, FILE_APPEND);
}

function createUser($user_data)
{
    global $log_dir;
    global $start_log_file;
    global $start_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USER;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($start_error_log_file, ' | Connection failed', FILE_APPEND);
        throw new ErrorException("Connection failed: " . mysqli_connect_error());
    }
    // Check if user exists
    $sql = "SELECT * FROM $table_user WHERE tgm_user_id = " . $user_data['tgm_user_id'];
    $result = mysqli_query($conn, $sql);
    // $rss_links = '';
    if (mysqli_num_rows($result) > 0) {
        activateUser($user_data['tgm_user_id']);
        file_put_contents($start_log_file, ' | User already exists ' . $user_data['tgm_user_id'] . ' - ' . $user_data['username'], FILE_APPEND);
        // Close connection
        mysqli_close($conn);
        return false; // $rss_links;
    } else {
        // Insert user
        unset($user_data['text']);
        $now = date('Y-m-d H:i:s');
        // Add 1 week
        $now_plus_week = date('Y-m-d H:i:s', strtotime('+1 week'));
        $user_data['date_payment'] = $now_plus_week;
        $columns = implode(", ", array_keys($user_data));
        $escaped_values = array_map(array($conn, 'real_escape_string'), array_values($user_data));
        $values  = implode("', '", $escaped_values);
        $sql = "INSERT INTO $table_user ($columns) VALUES ('$values')";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            file_put_contents($start_error_log_file, " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
            throw new ErrorException("Error: " . $sql . ' | ' . mysqli_error($conn));
        }
        file_put_contents($start_log_file, ' | New user created ' . $user_data['tgm_user_id'] . ' - ' . $user_data['username'], FILE_APPEND);
        // Close connection
        mysqli_close($conn);
        return true;
    }
}


function activateUser($tgm_user_id)
{
    global $log_dir;
    global $start_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USERS;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($start_error_log_file, ' | Update User - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "UPDATE $table_user SET is_deleted = NULL, is_returned = 1 WHERE tgm_user_id = " . $tgm_user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($start_error_log_file, " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }
    // Close connection
    mysqli_close($conn);

    return true;
}


function deactivateUser($tgm_user_id)
{
    global $log_dir;
    global $start_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USERS;
    $table_city = MYSQL_TABLE_CITY;
    $table_district = MYSQL_TABLE_DISTRICT;
    $table_data = MYSQL_TABLE_DATA;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($start_error_log_file, ' | Update User - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "UPDATE $table_user SET is_deleted = 1 WHERE tgm_user_id = " . $tgm_user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($start_error_log_file, " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }

    // remove all from data table
    $sql = "DELETE FROM $table_data WHERE chat_id = " . $tgm_user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($start_error_log_file, " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }

    // Close connection
    mysqli_close($conn);

    return true;
}


function updateUser($user_data, $tgm_user_id)
{
    global $log_dir;
    global $start_log_file;
    global $start_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USERS;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($start_error_log_file, ' | Update User - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "UPDATE $table_user SET";
    foreach ($user_data as $key => $value) {
        $sql .= " $key = '" . $value . "',";
    }
    $sql = rtrim($sql, ',');
    $sql .= " WHERE tgm_user_id = " . $tgm_user_id;

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($start_error_log_file, " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
        // Close connection
        mysqli_close($conn);
        return false;
    }
    file_put_contents($start_log_file, ' | User successfully updated ' . $tgm_user_id . ' - ' . $user_data['username'], FILE_APPEND);

    return true;
}

function getUserData($tgm_user_id)
{
    global $log_dir;
    global $start_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USERS;
    $table_city = MYSQL_TABLE_CITY;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($start_error_log_file, ' | Get User Data - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "SELECT * FROM $table_user LEFT JOIN $table_city ON $table_user.preference_city = $table_city.id WHERE tgm_user_id = " . $tgm_user_id;
    $result = mysqli_query($conn, $sql);
    $user_data = [];
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['preference_sharing'] === '1') {
                $preference_sharing = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'без подселения' : 'without sharing';
            } else {
                $preference_sharing = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'неважно' : 'any';
            }
            if ($row['preference_owner'] === '1') {
                $preference_owner = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'без посредников' : 'without agents';
            } else {
                $preference_owner = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'неважно' : 'any';
            }
            $now = date('Y-m-d H:m:s');
            $user_payment = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'не оплачена' : 'not paid';
            if ($row['date_payment']) {
                if ($row['date_payment'] > $now) {
                    $user_payment = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'оплачена до ' . $row['date_payment'] : 'paid until ' . $row['date_payment'];
                }
            }
            $user_data = [
                'tgm_user_id' => $row['tgm_user_id'],
                'is_bot' => $row['is_bot'],
                'is_deleted' => $row['is_deleted'],
                'is_premium' => $row['is_premium'],
                'is_returned' => $row['is_returned'],
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
                'preference_sharing' => $preference_sharing,
                'preference_owner' => $preference_owner,
                'date_payment' => $user_payment,
                'date_updated' => $row['date_updated'],
                'date_added' => $row['date_added'],
                'city_name_ru' => $row['name_ru'],
                'city_name_en' => $row['name_en'],
                'city_name_kg' => $row['name_kg'],
                'city_slug' => $row['slug'],
            ];
        }
    }
    // Close connection
    mysqli_close($conn);
    return $user_data;
}

function sendLastAds($tgm_user_id, $chat_id)
{
    global $log_dir;
    global $start_log_file;
    global $start_error_log_file;
    global $token;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USERS;
    $table_data = MYSQL_TABLE_DATA;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($start_error_log_file, ' | Send last ads - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    file_put_contents($start_log_file, ' | sendLastAds!', FILE_APPEND);
    $user_data = getUserData($tgm_user_id);

    if (!empty($user_data)) {
        if (isset($user_data['is_returned']) && $user_data['is_returned'] === '1') {
            file_put_contents($start_log_file, ' | sendLastAds - User: ' . $user_data['username'] . ' is returned - send nothing', FILE_APPEND);
            return false;
        } else {
            $username = $user_data['username'];

            $sql = "SELECT * FROM $table_data WHERE owner != 'Риэлтор' AND price_usd <= " . $user_data['price_max'] . " AND rooms >= " . $user_data['rooms_min'] . " ORDER BY date_added DESC LIMIT 3";
            $result = mysqli_query($conn, $sql);
            $result = mysqli_query($conn, $sql);
            $counter = 0;
            $msg_sent = 0;
            $msg_error = 0;
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
                    $deposit = $row['deposit'];
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
                    if ($renovation !== 'n/d') {
                        $message .= ", $renovation\n";
                    } else {
                        $message .= "\n";
                    }
                    $message .= "<b>Район:</b> $district\n";
                    if ($price_kgs !== 'n/d')   $message .= "<b>Цена:</b> $price_kgs KGS ($price_usd USD)\n";
                    if ($deposit !== 'n/d')     $message .= "<b>Депозит:</b> $deposit\n";
                    if ($house_type !== 'n/d')  $message .= "<b>Серия:</b> $house_type\n";
                    if ($sharing !== 'n/d')     $message .= "<b>Подселение:</b> $sharing\n";
                    // if ($rooms !== 'n/d')    $message .= "<b>Комнат:</b> $rooms\n";
                    if ($floor !== 'n/d')       $message .= "<b>Этаж:</b> $floor\n";
                    // if ($furniture !== 'n/d') $message .= "<b>Мебель:</b> $furniture\n";
                    if ($condition !== 'n/d')   $message .= "<b>Состояние:</b> $condition\n";
                    // if ($renovation !== 'n/d') $message .= "<b>Ремонт:</b> $renovation\n";
                    if ($animals !== 'n/d')     $message .= "<b>Животные:</b> $animals\n";
                    if ($owner !== 'n/d' && $owner_name !== 'n/d') {
                        $message .= "<b>Кто сдает:</b> $owner, $owner_name\n";
                    } else {
                        if ($owner !== 'n/d')   $message .= "<b>Кто сдает:</b> $owner\n";
                        if ($owner_name !== 'n/d') $message .= "<b>Имя:</b> $owner_name\n";
                    }
                    if ($phone !== 'n/d')       $message .= "<b>Телефон:</b> $phone\n";
                    if ($created_at !== $updated_at) {
                        if ($created_at !== 'n/d') $message .= "<b>Создано:</b> $created_at\n";
                        if ($updated_at !== 'n/d') $message .= "<b>Обновлено:</b> $updated_at\n";
                    } else {
                        if ($created_at !== 'n/d') $message .= "<b>Создано:</b> $created_at\n";
                    }
                    $message .= "$link\n";

                    try {
                        if (trim($owner) !== 'Агентство' && trim($owner) !== 'Агентство недвижимости' && trim($owner) !== 'Риэлтор') {
                            $bot = new \TelegramBot\Api\BotApi($token);
                            $bot->sendMessage($chat_id, $message, 'HTML');
                            // Update sent_to_user
                            $chat_ids_sent = [];
                            if ($row['chat_ids_sent'] !== '[]' && $row['chat_ids_sent'] !== '' && $row['chat_ids_sent'] !== null) {
                                $chat_ids_sent = json_decode($row['chat_ids_sent']);
                            }
                            $chat_ids_sent[] = $tgm_user_id;
                            $chat_ids_sent = array_unique($chat_ids_sent);
                            $chat_ids_sent = json_encode($chat_ids_sent);
                            $sql = "UPDATE $table_data SET chat_ids_sent = '$chat_ids_sent' WHERE id = " . $row['id'];
                            if (mysqli_query($conn, $sql)) {
                                // file_put_contents($start_log_file, ' | User: ' . $username . ' | Msg sent: ' . $message . PHP_EOL, FILE_APPEND);
                                $msg_sent++;
                            } else {
                                file_put_contents($start_log_file, ' | User: ' . $username . ' | Error: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
                                $msg_error++;
                            }
                            $chat_ids_to_send = $row['chat_ids_to_send'];
                            if ($chat_ids_sent === $chat_ids_to_send) {
                                $sql = "UPDATE $table_data SET done = '1' WHERE id = " . $row['id'];
                                if (mysqli_query($conn, $sql)) {
                                    // file_put_contents( $start_log_file, ' | User: ' . $username . ' | Msg sent: ' . $message . PHP_EOL, FILE_APPEND);
                                    $msg_sent++;
                                } else {
                                    file_put_contents($start_log_file, ' | User: ' . $username . ' | Error: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
                                    $msg_error++;
                                }
                            }
                        } else {
                            $sql = "UPDATE $table_data SET done = '1' WHERE id = " . $row['id'];
                            if (mysqli_query($conn, $sql)) {
                                // file_put_contents( $start_log_file, ' | User: ' . $username . ' | Msg sent: ' . $message . PHP_EOL, FILE_APPEND);
                                $msg_sent++;
                            } else {
                                file_put_contents($start_log_file, ' | User: ' . $username . ' | Error: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
                                $msg_error++;
                            }
                        }
                    } catch (\TelegramBot\Api\Exception $e) {
                        $error = $e->getMessage();
                        file_put_contents($start_log_file, ' | User: ' . $username . ' Error: ' . $e->getMessage(), FILE_APPEND);
                        if ($error === 'Forbidden: bot was blocked by the user') {
                            try {
                                // file_put_contents( $start_log_file, ' | User: ' . $username . ' try to deactivate', FILE_APPEND);
                                deactivateUser($tgm_user_id);
                            } catch (Exception $e) {
                                file_put_contents($start_log_file, ' | Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                            }
                        }
                        break;
                    }
                    $counter++;
                }
            } else {
                file_put_contents($start_log_file, ' | User: ' . $username . ' | No last ads!' . PHP_EOL, FILE_APPEND);
                $bot = new \TelegramBot\Api\BotApi($token);
                $messageText = $user_data['language_code'] === 'ru' ? "❌ По Вашим критериям не удалось найти объявлений, попробуйте изменить настройки, для этого используйте команду /settings" : "❌ No ads found for your criteria, try changing the settings, to do this, use the command /settings";
            }
            return true;
        }
    } else {
        file_put_contents($start_error_log_file, ' | User: ' . $tgm_user_id . ' | User data is empty!' . PHP_EOL, FILE_APPEND);
    }
}

function getCity($slug = '')
{
    global $start_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_city = MYSQL_TABLE_CITY;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($start_error_log_file, ' | getCity - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $cities = [];

    if ($slug === '') {
        $sql = "SELECT * FROM city";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $cities[] = $row;
            }
        } else {
            file_put_contents($start_error_log_file, ' | getCity - no cities found', FILE_APPEND);
        }
    } else {
        $sql = "SELECT * FROM $table_city WHERE slug = '$slug'";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $cities[] = $row;
            }
        } else {
            file_put_contents($start_error_log_file, ' | getCity with slug ' . $slug . ' - no cities found', FILE_APPEND);
        }
    }

    // Close connection
    mysqli_close($conn);

    return $cities;
}

function getCityById($id)
{
    global $start_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_city = MYSQL_TABLE_CITY;

    $city = [];

    if ($id === '') {
        file_put_contents($start_error_log_file, ' | getCityById - id is empty', FILE_APPEND);
    } else {
        // Create connection
        $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
        if (!$conn) {
            file_put_contents($start_error_log_file, ' | getCityById - connection failed', FILE_APPEND);
            throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
        }

        $sql = "SELECT * FROM $table_city WHERE id = '$id'";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $city = $row;
            }
        } else {
            file_put_contents($start_error_log_file, ' | getCityById with id ' . $id . ' - no cities found', FILE_APPEND);
        }

        // Close connection
        mysqli_close($conn);
    }

    return $city;
}
