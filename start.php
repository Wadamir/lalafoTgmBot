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
    file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Token not found', FILE_APPEND);
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
    file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Get content failed', FILE_APPEND);
    die('Get content failed');
}
$update = json_decode($get_content, TRUE);
file_put_contents($start_log_file, '[' . date('Y-m-d H:i:s') . '] Received:', FILE_APPEND);

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
    $chat_id = $update["message"]["chat"]["id"];
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
    $chat_id = $update['callback_query']["message"]["chat"]["id"];
    $messageId = $update['callback_query']["message"]["message_id"];
    $message = $update['callback_query']["message"]["text"];
    $message_type = $update['callback_query']["message"]["entities"][0]["type"];
    $command_data = $update['callback_query']['data'];
}

$user_language = $user_data['language_code'] === 'ru' ? 'ru' : $user_data['language_code'];

// Accumulate log messages & errors
$log_message_array = [];
$log_error_array = [];
if ($chat_type === 'callback_query') {
    file_put_contents($start_log_file, ' | command_data - ' . $command_data, FILE_APPEND);
}

// Create bot object
$bot = new \TelegramBot\Api\BotApi($token);
if ($chat_type === 'message' && $user_data['is_bot'] === 0 && $message_type === 'bot_command') {
    $log_message_array[] = 'Bot command - ' . $message;
    switch ($message) {
        case '/stop':
            try {
                // Send message
                $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "Вы отписаны от обновлений бота. Если решите восстановить уведомления, воспользуйтесь командой /start. Для обратной связи напишите боту сообщение с хештегом #feedback" : "You are unsubscribed from bot updates. If you decide to restart notifications, use the /start command. For feedback, write a message to the bot with the hashtag #feedback";
                $bot->sendMessage($chat_id, $message_text);
                deactivateUser($user_data['tgm_user_id']);
            } catch (Exception $e) {
                $log_error_array[] = $e->getMessage();
            }
            break;
        case '/help':
            try {
                // Send message
                $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "Для обратной связи напишите боту сообщение с хештегом #feedback" : "For feedback, write a message to the bot with the hashtag #feedback";
                $bot->sendMessage($chat_id, $message_text);
            } catch (Exception $e) {
                $log_error_array[] = $e->getMessage();
            }
            break;
        case '/start':
            try {
                $user_result = createUser($user_data);
                if ($user_result === true) { // New user
                    // Get all cities
                    $cities = getCity();
                    if (!empty($cities)) {
                        $city_array = [];
                        if ($user_language === 'ru' || $user_language === 'kg') {
                            foreach ($cities as $city) {
                                $city_array[] = ['text' => $city['city_name_ru'], 'callback_data' => 'city_' . $city['city_slug']];
                            }
                            $city_array[] = ['text' => 'Неважно', 'callback_data' => 'city_none'];
                        } else {
                            foreach ($cities as $city) {
                                $city_array[] = ['text' => $city['city_name_en'], 'callback_data' => 'city_' . $city['city_slug']];
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
                        $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "Привет, " . $user_data['first_name'] . "! Вы успешно зарегистрированы!" : "Hello, " . $user_data['first_name'] . "! You are successfully registered!";
                        $message_text .= ($user_language === 'ru' || $user_language === 'kg') ? "\n\n <b>Настройка</b> \n\n❓В каком городе Вы ищете жилье? \n\n" : "\n\n <b>Settings</b> \n\n❓In which city are you looking for housing? \n\n";

                        try {
                            $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
                        } catch (Exception $e) {
                            $log_error_array[] = $e->getMessage();
                        }
                    } else {
                        $log_error_array[] = 'Cities not found';
                    }
                } else { // Returned user
                    $get_user_data = getUserData($user_data['tgm_user_id']);
                    if (!empty($get_user_data)) {
                        if ($get_user_data['preference_city'] === NULL) {
                            $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбран' : 'not selected';
                        } else {
                            $city = getCityById($get_user_data['preference_city']);
                            $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? $city['city_name_ru'] : $city['city_name_en'];
                        }
                        if ($get_user_data['rooms_min'] === NULL) {
                            $user_rooms_min = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбрано' : 'not selected';
                        } else {
                            $user_rooms_min = $get_user_data['rooms_min'];
                        }
                        $user_preference_sharing = $get_user_data['preference_sharing_text'];
                        if ($get_user_data['price_max'] === 1000000 || $get_user_data['price_max'] === NULL) {
                            $user_max_price = ($user_language === 'ru' || $user_language === 'kg') ? 'без ограничений' : 'no limit';
                        } else {
                            $user_max_price = $get_user_data['price_max'] . ' ' . $get_user_data['price_currency'];
                        }
                        // Send message
                        $message_text = ($user_language === 'ru' || $user_language === 'kg') ?  "С возвращением, " . $user_data['first_name'] . "!" : "Welcome back, " . $user_data['first_name'] . "!";
                        $message_text .= ($user_language === 'ru' || $user_language === 'kg') ? "\n\n<b>Ваши настройки</b>\n\n✅ Город: <b>" . $user_preference_city . "</b>\n✅ Минимум комнат: <b>" . $user_rooms_min . "</b>\n✅ Тип аренды: <b>" . $user_preference_sharing . "</b>\n✅ Максимальная стоимость аренды в месяц: <b>" . $user_max_price . "</b>\n\nЕсли Вы хотите изменить настройки воспользуйтесь командой /settings\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "\n\n<b>Your search settings</b>\n\n✅ City: <b>" . $user_preference_city . "</b>\n✅ Minimum rooms: <b>" . $user_rooms_min . "</b>\n✅ Rental type: <b>" . $user_preference_sharing . "</b>\n✅ Maximum rental cost per month: <b>" . $user_max_price . "</b>\n\nIf you want to change the settings, use the /settings command\n\nFor feedback, write a message to the bot with the hashtag #feedback";
                        try {
                            $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
                        } catch (Exception $e) {
                            $log_error_array[] = $e->getMessage();
                        }
                    } else {
                        $log_error_array[] = 'Get user data error';
                    }
                }
            } catch (Exception $e) {
                $log_error_array[] = $e->getMessage();
            }
            break;
        case '/settings':
            $get_user_data = getUserData($user_data['tgm_user_id']);
            if (!empty($get_user_data)) {
                if ($get_user_data['preference_city'] === NULL) {
                    $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбран' : 'not selected';
                } else {
                    $city = getCityById($get_user_data['preference_city']);
                    $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? $city['city_name_ru'] : $city['city_name_en'];
                }
                if ($get_user_data['rooms_min'] === NULL) {
                    $user_rooms_min = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбрано' : 'not selected';
                } else {
                    $user_rooms_min = $get_user_data['rooms_min'];
                }
                $user_preference_sharing = $get_user_data['preference_sharing_text'];
                if ($get_user_data['price_max'] === 1000000 || $get_user_data['price_max'] === NULL) {
                    $user_max_price = ($user_language === 'ru' || $user_language === 'kg') ? 'без ограничений' : 'no limit';
                } else {
                    $user_max_price = $get_user_data['price_max'] . ' ' . $get_user_data['price_currency'];
                }
                // Send message
                $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "\n\n<b>Ваши настройки</b>\n\n✅ Город: <b>" . $user_preference_city . "</b>\n✅ Минимум комнат: <b>" . $user_rooms_min . "</b>\n✅ Тип аренды: <b>" . $user_preference_sharing . "</b>\n✅ Максимальная стоимость аренды в месяц: <b>" . $user_max_price . "</b>\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "\n\n<b>Your search settings</b>\n\n✅ City: <b>" . $user_preference_city . "</b>\n✅ Minimum rooms: <b>" . $user_rooms_min . "</b>\n✅ Rental type: <b>" . $user_preference_sharing . "</b>\n✅ Maximum rental cost per month: <b>" . $user_max_price . "</b>\n\nFor feedback, write a message to the bot with the hashtag #feedback";

                $update_settings_text = ($user_language === 'ru' || $user_language === 'kg') ? "Изменить настройки" : "Change settings";
                $inline_keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [
                            ['text' => $update_settings_text, 'callback_data' => 'update_settings'],
                        ],
                    ]
                );
                try {
                    $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
                } catch (Exception $e) {
                    $log_error_array[] = $e->getMessage();
                }
            } else {
                $log_error_array[] = 'Get user data error';
            }
            /*
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
                $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "\n\n <b>Настройка</b> \n\n❓Сколько минимум комнат в квартире вам нужно? \n\n" : "\n\n <b>Settings</b> \n\n❓How many minimum rooms in an apartment do you need? \n\n";
                $messageResponse = $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
            } catch (Exception $e) {
                $log_error_array[] = $e->getMessage();
            }
            */
            break;
        default:
            $log_error_array[] = 'Undefined bot command';
    }
} elseif ($chat_type === 'message' && strpos($message, "#feedback") !== false) {
    $log_message_array[] = 'Feedback - ' . $message;

    // Send message to admin
    $message_text = "Feedback: " . $user_data['first_name'] . "\n\n" . $message;
    try {
        $bot->sendMessage($adminChatId, $message_text);
    } catch (Exception $e) {
        $log_error_array[] = $e->getMessage();
    }

    // Send message to user
    $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "Спасибо! Ваше сообщение отправлено." : "Thank you! Your message has been sent.";
    try {
        $bot->sendMessage($chat_id, $message_text);
    } catch (Exception $e) {
        $log_error_array[] = $e->getMessage();
    }
} elseif ($chat_type === 'callback_query') {
    $new_data = [];
    switch (true) {
        case strpos($command_data, "update_settings") === 0:
            try {
                $bot->deleteMessage($chat_id, $messageId);
            } catch (Exception $e) {
                $log_error_array[] = $e->getMessage();
            }
            // Get all cities
            $cities = getCity();
            if (!empty($cities)) {
                $city_array = [];
                if ($user_language === 'ru' || $user_language === 'kg') {
                    foreach ($cities as $city) {
                        $city_array[] = ['text' => $city['city_name_ru'], 'callback_data' => 'city_' . $city['city_slug']];
                    }
                    $city_array[] = ['text' => 'Неважно', 'callback_data' => 'city_none'];
                } else {
                    foreach ($cities as $city) {
                        $city_array[] = ['text' => $city['city_name_en'], 'callback_data' => 'city_' . $city['city_slug']];
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
                $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "\n\n <b>Настройка</b> \n\n❓В каком городе Вы ищете жилье? \n\n" : "\n\n <b>Settings</b> \n\n❓In which city are you looking for housing? \n\n";

                try {
                    $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
                } catch (Exception $e) {
                    $log_error_array[] = $e->getMessage();
                }
            } else {
                $log_error_array[] = 'Cities not found';
            }
            break;
        case strpos($command_data, "city") === 0:
            $city_slug = str_replace('city_', '', $command_data);
            $log_message_array[] = 'City - ' . $city_slug;
            $update_result = false;
            if ($city_slug !== 'none') {
                $city_data = getCity($city_slug);
                $new_data['preference_city'] = $city_data[0]['city_id'];
                $update_result = updateUser($new_data, $user_data['tgm_user_id']);
            } else {
                $update_result = true;
            }
            if ($update_result) {
                $bot->deleteMessage($chat_id, $messageId);
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
                if ($get_user_data['preference_city'] === NULL) {
                    $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбран' : 'not selected';
                } else {
                    $city = getCityById($get_user_data['preference_city']);
                    $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? $city['city_name_ru'] : $city['city_name_en'];
                }
                if (!empty($get_user_data)) {
                    $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройка</b>\n\n✅ Город: " . $user_preference_city . "\n\n❓Сколько минимум комнат в квартире вам нужно? \n\n" : "<b>Settings</b>\n\n✅ City: " . $user_preference_city . "\n\n❓How many minimum rooms in an apartment do you need? \n\n";
                    try {
                        $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
                    } catch (Exception $e) {
                        $log_error_array[] = $e->getMessage();
                    }
                } else {
                    $log_error_array[] = 'Get user data error';
                }
            } else {
                $bot->deleteMessage($chat_id, $messageId);
                $log_error_array[] = 'Update user error';
            }
            break;
        case strpos($command_data, "room") === 0:
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
                $bot->deleteMessage($chat_id, $messageId);
                if ($user_language === 'ru' || $user_language === 'kg') {
                    $sharing_0_text = 'С подселением';
                    $sharing_1_text = 'Без подселения';
                    $sharing_none_text = 'Неважно';
                } else {
                    $sharing_0_text = 'With sharing';
                    $sharing_1_text = 'Without sharing';
                    $sharing_none_text = 'No matter';
                }
                // Send message
                $inline_keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [
                            ['text' => $sharing_0_text, 'callback_data' => 'sharing_0'],
                            ['text' => $sharing_1_text, 'callback_data' => 'sharing_1'],
                            ['text' => $sharing_none_text, 'callback_data' => 'sharing_none'],
                        ]
                    ]
                );
                $get_user_data = getUserData($user_data['tgm_user_id']);
                if ($get_user_data['preference_city'] === NULL) {
                    $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбран' : 'not selected';
                } else {
                    $city = getCityById($get_user_data['preference_city']);
                    $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? $city['city_name_ru'] : $city['city_name_en'];
                }
                if ($get_user_data['rooms_min'] === NULL) {
                    $user_rooms_min = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбрано' : 'not selected';
                } else {
                    $user_rooms_min = $get_user_data['rooms_min'];
                }
                if (!empty($get_user_data)) {
                    $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройка</b>\n\n✅ Город: " . $user_preference_city . "\n✅ Минимум комнат: " . $user_rooms_min . "\n\n❓Предпочтительный тип аренды?\n\n" : "<b>Settings</b>\n\n✅ City: " . $user_preference_city . "\n✅ Minimum rooms: " . $user_rooms_min . "\n\n❓Rental type? \n\n";
                    $send_result = $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
                } else {
                    $log_error_array[] = 'Get user data error';
                }
            } else {
                $bot->deleteMessage($chat_id, $messageId);
                $log_error_array[] = 'Update user error';
            }
            break;
        case strpos($command_data, "sharing") === 0:
            switch ($command_data) {
                case 'sharing_1':
                    $new_data['preference_sharing'] = 1;
                    break;
                case 'sharing_0':
                    $new_data['preference_sharing'] = 0;
                    break;
                default:
                    $new_data['preference_sharing'] = NULL;
            }
            $update_result = updateUser($new_data, $user_data['tgm_user_id']);
            if ($update_result) {
                $bot->deleteMessage($chat_id, $messageId);
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
                if ($get_user_data['preference_city'] === NULL) {
                    $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбран' : 'not selected';
                } else {
                    $city = getCityById($get_user_data['preference_city']);
                    $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? $city['city_name_ru'] : $city['city_name_en'];
                }
                if ($get_user_data['rooms_min'] === NULL) {
                    $user_rooms_min = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбрано' : 'not selected';
                } else {
                    $user_rooms_min = $get_user_data['rooms_min'];
                }
                $user_preference_sharing = $get_user_data['preference_sharing_text'];
                if (!empty($get_user_data)) {
                    $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройка</b>\n\n✅ Город: " . $user_preference_city . "\n✅ Минимум комнат: " . $user_rooms_min . "\n✅ Тип аренды: " . $user_preference_sharing . "\n\n❓Максимальная стоимость аренды в месяц?\n\n" : "<b>Settings</b>\n\n✅ City: " . $user_preference_city . "\n✅ Minimum rooms: " . $user_rooms_min . "\n✅ Rental type: " . $user_preference_sharing . "\n\n❓Maximum rental cost per month? \n\n";
                    $send_result = $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
                } else {
                    $log_error_array[] = 'Get user data error';
                }
            } else {
                $bot->deleteMessage($chat_id, $messageId);
                $log_error_array[] = 'Update user error';
            }
            break;
        case strpos($command_data, "usd") === 0:
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
                $bot->deleteMessage($chat_id, $messageId);
                $get_user_data = getUserData($user_data['tgm_user_id']);
                if (!empty($get_user_data)) {
                    if ($get_user_data['preference_city'] === NULL) {
                        $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбран' : 'not selected';
                    } else {
                        $city = getCityById($get_user_data['preference_city']);
                        $user_preference_city = ($user_language === 'ru' || $user_language === 'kg') ? $city['city_name_ru'] : $city['city_name_en'];
                    }
                    if ($get_user_data['rooms_min'] === NULL) {
                        $user_rooms_min = ($user_language === 'ru' || $user_language === 'kg') ? 'не выбрано' : 'not selected';
                    } else {
                        $user_rooms_min = $get_user_data['rooms_min'];
                    }
                    $user_preference_sharing = $get_user_data['preference_sharing_text'];
                    if ($get_user_data['price_max'] === 1000000 || $get_user_data['price_max'] === NULL) {
                        $user_max_price = ($user_language === 'ru' || $user_language === 'kg') ? 'без ограничений' : 'no limit';
                    } else {
                        $user_max_price = $get_user_data['price_max'] . ' ' . $get_user_data['price_currency'];
                    }
                    $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройки успешно сохранены!</b>\n\n✅ Город: " . $user_preference_city . "\n✅ Минимум комнат: " . $user_rooms_min . "\n✅ Тип аренды: " . $user_preference_sharing . "\n✅ Максимальная стоимость аренды в месяц: " . $user_max_price . "\n\n👉 Вы будете получать мгновенные уведомления обо всех новых объявлениях ⚡⚡⚡\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "<b>Settings successfully saved!</b>\n\n✅ City: " . $user_preference_city . "\n✅ Minimum rooms: " . $user_rooms_min . "\n✅ Rental type: " . $user_preference_sharing . "\n✅ Maximum rental cost per month: " . $user_max_price . "\n\n👉 You will receive instant notifications of all new ads ⚡⚡⚡\n\nFor feedback, write a message to the bot with the hashtag #feedback";
                    $bot->sendMessage($chat_id, $message_text, 'HTML');
                    sendLastAds($user_data['tgm_user_id'], $chat_id);
                } else {
                    $log_error_array[] = 'Get user data error';
                }
            } else {
                $log_error_array[] = 'Update user error';
            }
    }
} else {
    $log_error_array[] = 'Undefined bot message type or user is bot';
}

if (!empty($log_error_array)) {
    file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] ERROR - ' . implode(' | ', $log_error_array), FILE_APPEND);
    try {
        // Send message
        $bot = new \TelegramBot\Api\BotApi($token);
        $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\nFor feedback, write a message to the bot with the hashtag #feedback";
        $messageResponse = $bot->sendMessage($chat_id, $message_text, 'HTML');
    } catch (Exception $e) {
        file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] ERROR - ' . $e->getMessage(), FILE_APPEND);
    }
}

if (!empty($log_message_array)) {
    $log_message_array[] = 'End [' . date('Y-m-d H:i:s') . ']' . PHP_EOL;
    file_put_contents($start_log_file, implode(' | ', $log_message_array), FILE_APPEND);
}

function createUser($user_data)
{
    global $log_message_array;
    global $log_error_array;

    $log_message_array[] = 'createUser() - ' . $user_data['tgm_user_id'] . ' - ' . $user_data['username'];

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USER;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        $log_error_array[] = 'createUser() - Connection failed';
        throw new ErrorException("Connection failed: " . mysqli_connect_error());
    }
    // Check if user exists
    $sql = "SELECT * FROM $table_user WHERE tgm_user_id = " . $user_data['tgm_user_id'];
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        activateUser($user_data['tgm_user_id']);
        $log_message_array[] = 'User already exists ' . $user_data['tgm_user_id'] . ' - ' . $user_data['username'];

        // Close connection
        mysqli_close($conn);
        return false;
    } else {
        // Insert user
        unset($user_data['text']);
        // Add 1 week
        $now_plus_week = date('Y-m-d H:i:s', strtotime('+1 week'));
        $user_data['date_payment'] = $now_plus_week;
        $columns = implode(", ", array_keys($user_data));
        $escaped_values = array_map(array($conn, 'real_escape_string'), array_values($user_data));
        $values  = implode("', '", $escaped_values);
        $sql = "INSERT INTO $table_user ($columns) VALUES ('$values')";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            $log_error_array[] = 'createUser() - Error: ' . $sql . ' | ' . mysqli_error($conn);
            throw new ErrorException("Error: " . $sql . ' | ' . mysqli_error($conn));
        }
        $log_message_array[] = 'New user created ' . $user_data['tgm_user_id'] . ' - ' . $user_data['username'];

        // Close connection
        mysqli_close($conn);
        return true;
    }
}


function activateUser($tgm_user_id)
{
    global $log_message_array;
    global $log_error_array;

    $log_message_array[] = 'activateUser() - ' . $tgm_user_id;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USER;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        $log_error_array[] = 'activateUser() - Connection failed';
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "UPDATE $table_user SET is_deleted = NULL, is_returned = 1 WHERE tgm_user_id = " . $tgm_user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        $log_error_array[] = 'activateUser() - Error: ' . $sql . ' | ' . mysqli_error($conn);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }
    // Close connection
    mysqli_close($conn);
    return true;
}


function deactivateUser($tgm_user_id)
{
    global $log_message_array;
    global $log_error_array;

    $log_message_array[] = 'deactivateUser() - ' . $tgm_user_id;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USER;
    $table_data = MYSQL_TABLE_DATA;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        $log_error_array[] = 'deactivateUser() - Connection failed';
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "UPDATE $table_user SET is_deleted = 1 WHERE tgm_user_id = " . $tgm_user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        $log_error_array[] = 'deactivateUser() - Error: ' . $sql . ' | ' . mysqli_error($conn);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    } else {
        $log_message_array[] = 'User successfully deactivated ' . $tgm_user_id;
    }

    // remove all from data table
    $sql = "DELETE FROM $table_data WHERE chat_id = " . $tgm_user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        $log_error_array[] = 'deactivateUser() - Error: ' . $sql . ' | ' . mysqli_error($conn);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    } else {
        $log_message_array[] = 'Data successfully deleted ' . $tgm_user_id;
    }

    // Close connection
    mysqli_close($conn);
    return true;
}


function updateUser($user_data, $tgm_user_id)
{
    global $log_message_array;
    global $log_error_array;

    $log_message_array[] = 'updateUser() - ' . $tgm_user_id . ' - ' . $user_data['username'];

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USER;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        $log_error_array[] = 'updateUser() - Connection failed';
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "UPDATE $table_user SET";
    foreach ($user_data as $key => $value) {
        if ($value === NULL) {
            $sql .= " $key = NULL,";
            continue;
        }
        $sql .= " $key = '" . $value . "',";
    }
    $sql = rtrim($sql, ',');
    $sql .= " WHERE tgm_user_id = " . $tgm_user_id;

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        $log_error_array[] = 'updateUser() - Error: ' . $sql . ' | ' . mysqli_error($conn);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));

        // Close connection
        mysqli_close($conn);
        return false;
    }
    $log_message_array[] = 'User successfully updated ' . $tgm_user_id;

    // Close connection
    mysqli_close($conn);
    return true;
}

function getUserData($tgm_user_id)
{
    global $log_message_array;
    global $log_error_array;

    $log_message_array[] = 'getUserData() - ' . $tgm_user_id;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USER;
    $table_city = MYSQL_TABLE_CITY;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        $log_error_array[] = 'getUserData() - Connection failed';
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "SELECT * FROM $table_user LEFT JOIN $table_city ON $table_user.preference_city = $table_city.city_id WHERE tgm_user_id = " . $tgm_user_id;
    $result = mysqli_query($conn, $sql);
    $user_data = [];
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['preference_sharing'] === '1') {
                $preference_sharing = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'без подселения' : 'without sharing';
            } elseif ($row['preference_sharing'] === '0') {
                $preference_sharing = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'с подселением' : 'with sharing';
            } else {
                $preference_sharing = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'неважно' : 'no matter';
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
                'preference_sharing' => $row['preference_sharing'],
                'preference_sharing_text' => $preference_sharing,
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
    $log_message_array[] = 'User data successfully received ' . $tgm_user_id;

    // Close connection
    mysqli_close($conn);
    return $user_data;
}

function sendLastAds($tgm_user_id, $chat_id)
{
    global $token;
    global $log_message_array;
    global $log_error_array;
    global $start_log_file;

    file_put_contents($start_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] sendLastAds() - ' . $tgm_user_id, FILE_APPEND);

    $log_message_array[] = 'sendLastAds() - ' . $tgm_user_id;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_data = MYSQL_TABLE_DATA;
    $table_district = MYSQL_TABLE_DISTRICT;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        $log_error_array[] = 'sendLastAds() - Connection failed';
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $user_data = getUserData($tgm_user_id);

    if (!empty($user_data)) {
        if (isset($user_data['is_returned']) && $user_data['is_returned'] === '1') {
            $log_message_array[] = 'User is returned - nothing to send' . $tgm_user_id;
            return false;
        } else {
            $user_language = $user_data['language_code'];
            $user_preference_city = $user_data['preference_city'];
            $parameters_array = [];
            if ($user_preference_city !== NULL) {
                $parameters_array[] = "city = " . $user_preference_city;
            }
            if ($user_data['price_max'] !== NULL) {
                $parameters_array[] = "price_usd <= " . $user_data['price_max'];
            }
            if ($user_data['rooms_min'] !== NULL) {
                $parameters_array[] = "rooms >= " . $user_data['rooms_min'];
            }
            if ($user_data['preference_sharing'] !== NULL) {
                if ($user_data['preference_sharing'] === 1) {
                    $parameters_array[] = "sharing = 1";
                } elseif ($user_data['preference_sharing'] === 0) {
                    $parameters_array[] = "sharing = 0";
                }
            }
            if (!empty($parameters_array)) {
                $parameters = " AND " . implode(" AND ", $parameters_array);
            } else {
                $parameters = "";
            }
            $sql = "SELECT * FROM $table_data WHERE owner != 'Риэлтор'" . $parameters . " ORDER BY date_added DESC LIMIT 3";
            file_put_contents($start_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] sendLastAds() - ' . $sql, FILE_APPEND);
            $result = mysqli_query($conn, $sql);
            $result = mysqli_query($conn, $sql);
            $counter = 0;
            $msg_sent = 0;
            $msg_error = 0;

            $bot = new \TelegramBot\Api\BotApi($token);
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
                        $bot->sendMessage($chat_id, $message, 'HTML');
                    } catch (\TelegramBot\Api\Exception $e) {
                        $error = $e->getMessage();
                        $log_error_array[] = 'sendLastAds() - Error: ' . $e->getMessage();
                        if ($error === 'Forbidden: bot was blocked by the user') {
                            $error = 'User blocked bot';
                            deactivateUser($tgm_user_id);
                        }
                    }
                    $counter++;
                }
            } else {
                $log_error_array[] = 'sendLastAds() - No ads found';
                $message = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ По Вашим критериям объявлений не найдено\n\nДля обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ No ads found for your criteria\n\nFor feedback, write a message to the bot with the hashtag #feedback";
                try {
                    $bot->sendMessage($chat_id, $message, 'HTML');
                } catch (\TelegramBot\Api\Exception $e) {
                    $error = $e->getMessage();
                    $log_error_array[] = 'sendLastAds() - Error: ' . $e->getMessage();
                    if ($error === 'Forbidden: bot was blocked by the user') {
                        $error = 'User blocked bot';
                        deactivateUser($tgm_user_id);
                    }
                }
            }
            return true;
        }
    } else {
        $log_error_array[] = 'sendLastAds() - User not found';
    }
}

function getCity($slug = '')
{
    global $log_message_array;
    global $log_error_array;

    if ($slug !== '') {
        $log_message_array[] = 'getCity() - ' . $slug;
    } else {
        $log_message_array[] = 'getCity() - all';
    }

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_city = MYSQL_TABLE_CITY;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        $log_error_array[] = 'getCity() - Connection failed';
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
            $log_error_array[] = 'getCity() - No cities found';
        }
    } else {
        $sql = "SELECT * FROM $table_city WHERE city_slug = '$slug'";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $cities[] = $row;
            }
        } else {
            $log_error_array[] = 'getCity() - No cities found';
        }
    }

    // Close connection
    mysqli_close($conn);

    return $cities;
}

function getCityById($city_id)
{
    global $start_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_city = MYSQL_TABLE_CITY;

    $city = [];

    if ($city_id === '') {
        file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] getCityById - id is empty', FILE_APPEND);
    } else {
        // Create connection
        $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
        if (!$conn) {
            file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] getCityById - connection failed', FILE_APPEND);
            throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
        }

        $sql = "SELECT * FROM $table_city WHERE city_id = '$city_id'";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $city = $row;
            }
        } else {
            file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] getCityById with id ' . $city_id . ' - no cities found', FILE_APPEND);
        }

        // Close connection
        mysqli_close($conn);
    }

    return $city;
}
