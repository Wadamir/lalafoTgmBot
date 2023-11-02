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
                $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "⛔ Вы отписаны от обновлений бота. Если решите восстановить уведомления, воспользуйтесь командой /start\n\n📯 Для обратной связи напишите боту сообщение с хештегом #feedback" : "⛔ You are unsubscribed from bot updates. If you decide to restart notifications, use the /start command\n\n📯 For feedback, write a message to the bot with the hashtag #feedback";
                $bot->sendMessage($chat_id, $message_text);
                deactivateUser($user_data['tgm_user_id'], $user_data['chat_id']);
            } catch (Exception $e) {
                $log_error_array[] = $e->getMessage();
            }
            break;
        case '/help':
            try {
                // Send message
                $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "📯 Для обратной связи напишите боту сообщение с хештегом #feedback" : "📯 For feedback, write a message to the bot with the hashtag #feedback";
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
                        $message_text .= ($user_language === 'ru' || $user_language === 'kg') ? "\n\n<b>Настройка</b> \n\n❓В каком городе Вы ищете жилье? \n\n" : "\n\n<b>Settings</b> \n\n❓In which city are you looking for housing? \n\n";

                        try {
                            $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
                        } catch (Exception $e) {
                            $log_error_array[] = $e->getMessage();
                            file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage(), FILE_APPEND);
                        }
                    } else {
                        $log_error_array[] = 'Cities not found';
                        file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Cities not found', FILE_APPEND);
                    }
                } else { // Returned user
                    $get_user_data = getUserData($user_data['tgm_user_id']);
                    if (!empty($get_user_data)) {
                        $user_preference_city = $get_user_data['preference_city_text'];
                        $user_preference_district = $get_user_data['preference_district_text'];
                        $user_preference_property = $get_user_data['preference_property_text'];
                        $user_rooms_min = $get_user_data['rooms_min'];
                        $user_preference_sharing = $get_user_data['preference_sharing_text'];
                        $user_max_price = $get_user_data['price_max'];
                        $user_date_payment = $get_user_data['date_payment'];
                        $user_date_payment_text = $get_user_data['date_payment_text'];

                        // Send message
                        $message_text = ($user_language === 'ru' || $user_language === 'kg') ?  "С возвращением, " . $user_data['first_name'] . "!" : "Welcome back, " . $user_data['first_name'] . "!";
                        $message_text .= ($user_language === 'ru' || $user_language === 'kg') ? "\n\n<b>Ваши настройки</b>\n\n✅ Город: <b>" . $user_preference_city . "</b>\n✅ Тип жилья: <b>" . $user_preference_property . "</b>\n✅ Минимум комнат: <b>" . $user_rooms_min . "</b>\n✅ Тип аренды: <b>" . $user_preference_sharing . "</b>\n✅ Максимальная стоимость аренды в месяц: <b>" . $user_max_price . "</b>\n\n⚙ Если Вы хотите изменить настройки воспользуйтесь командой /settings\n\n📯 Для обратной связи напишите боту сообщение с хештегом #feedback\n\n" : "\n\n<b>Your search settings</b>\n\n✅ City: <b>" . $user_preference_city . "</b>\n✅ Property type: <b>" . $user_preference_property . "</b>\n✅ Minimum rooms: <b>" . $user_rooms_min . "</b>\n✅ Rental type: <b>" . $user_preference_sharing . "</b>\n✅ Maximum rental cost per month: <b>" . $user_max_price . "</b>\n\n⚙ If you want to change the settings, use the /settings command\n\n📯 For feedback, write a message to the bot with the hashtag #feedback\n\n";

                        $now = date('Y-m-d H:i:s');
                        if ($now < $user_date_payment) {
                            $message_text .= ($user_language === 'ru' || $user_language === 'kg') ? "👑 <b>Премиум подписка:</b> " . $user_date_payment_text . "\nКогда ваша премиум-подписка закончится, вы <b><u>продолжите</u></b> получать уведомления, но реже и в сжатом формате. Чтобы проверить состояние подписки или продлить подписку - воспользуйтесь командой /premium \n\n" : "👑 <b>Premium subscription:</b> " . $user_date_payment . "\nWhen your premium subscription ends, you will <b><u>continue</u></b> receive notifications, but less frequently and in a compressed format. To check the status of the subscription or renew the subscription, use the /premium command \n\n";
                        } else {
                            $message_text .= ($user_language === 'ru' || $user_language === 'kg') ? "👑 <b>Ваша премиум подписка истекла</b>\nЧтобы продлить премиум подписку - воспользуйтесь командой /premium \n\n" : "👑 <b>Your premium subscription has expired</b>\nTo renew the premium subscription, use the /premium command \n\n";
                        }

                        $donation_array = getDonation($user_language);
                        $inline_keyboard = $donation_array[1];
                        if (!empty($donation_array[0])) {
                            $message_text .= $donation_array[0];
                        }

                        try {
                            $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
                        } catch (Exception $e) {
                            $log_error_array[] = $e->getMessage();
                            file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage(), FILE_APPEND);
                        }
                    } else {
                        $log_error_array[] = 'Get user data error';
                        file_put_contents($start_error_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Get user data error', FILE_APPEND);
                    }
                }
            } catch (Exception $e) {
                $log_error_array[] = $e->getMessage();
            }
            break;
        case '/settings':
            $get_user_data = getUserData($user_data['tgm_user_id']);
            if (!empty($get_user_data)) {
                $user_preference_city = $get_user_data['preference_city_text'];
                $user_preference_district = $get_user_data['preference_district_text'];
                $user_preference_property = $get_user_data['preference_property_text'];
                $user_rooms_min = $get_user_data['rooms_min'];
                $user_preference_sharing = $get_user_data['preference_sharing_text'];
                $user_max_price = $get_user_data['price_max_text'];
                $user_date_payment = $get_user_data['date_payment'];
                $user_date_payment_text = $get_user_data['date_payment_text'];

                $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "\n\n<b>Ваши настройки</b>\n\n✅ Город: <b>" . $user_preference_city . "</b>\n✅ Тип жилья: <b>" . $user_preference_property . "</b>\n✅ Минимум комнат: <b>" . $user_rooms_min . "</b>\n✅ Тип аренды: <b>" . $user_preference_sharing . "</b>\n✅ Максимальная стоимость аренды в месяц: <b>" . $user_max_price . "</b>\n\n📯 Для обратной связи напишите боту сообщение с хештегом #feedback\n\n" : "\n\n<b>Your search settings</b>\n\n✅ City: <b>" . $user_preference_city . "</b>\n✅ Property type: <b>" . $user_preference_property . "</b>\n✅ Minimum rooms: <b>" . $user_rooms_min . "</b>\n✅ Rental type: <b>" . $user_preference_sharing . "</b>\n✅ Maximum rental cost per month: <b>" . $user_max_price . "</b>\n\n📯 For feedback, write a message to the bot with the hashtag #feedback\n\n";

                $now = date('Y-m-d H:i:s');
                if ($now < $user_date_payment) {
                    $message_text .= ($user_language === 'ru' || $user_language === 'kg') ? "👑 <b>Премиум подписка:</b> " . $user_date_payment_text . "\nКогда ваша премиум-подписка закончится, вы <b><u>продолжите</u></b> получать уведомления, но реже и в сжатом формате. Чтобы проверить состояние подписки или продлить подписку - воспользуйтесь командой /premium \n\n" : "👑 <b>Premium subscription:</b> " . $user_date_payment_text . "\nWhen your premium subscription ends, you will <b><u>continue</u></b> receive notifications, but less frequently and in a compressed format. To check the status of the subscription or renew the subscription, use the /premium command \n\n";
                } else {
                    $message_text .= ($user_language === 'ru' || $user_language === 'kg') ? "👑 <b>Ваша премиум подписка истекла</b>\nЧтобы продлить премиум подписку - воспользуйтесь командой /premium \n\n" : "👑 <b>Your premium subscription has expired</b>\nTo renew the premium subscription, use the /premium command \n\n";
                }

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
                $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "\n\n<b>Настройка</b> \n\n❓В каком городе Вы ищете жилье? \n\n" : "\n\n<b>Settings</b> \n\n❓In which city are you looking for housing? \n\n";

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
                $properties = getProperties();
                if (!empty($properties)) {
                    $properties_array = [];
                    if ($user_language === 'ru' || $user_language === 'kg') {
                        foreach ($properties as $property) {
                            $properties_array[] = ['text' => $property['property_name_ru'], 'callback_data' => 'property_' . $property['property_slug']];
                        }
                        $properties_array[] = ['text' => 'Неважно', 'callback_data' => 'property_none'];
                    } else {
                        foreach ($properties as $property) {
                            $properties_array[] = ['text' => $property['property_name_en'], 'callback_data' => 'property_' . $property['property_slug']];
                        }
                        $properties_array[] = ['text' => 'No matter', 'callback_data' => 'property_none'];
                    }
                    $inline_keyboard_array = [];
                    foreach ($properties_array as $key => $value) {
                        if ($key % 2 === 0) {
                            $inline_keyboard_array[] = [$value];
                        } else {
                            $inline_keyboard_array[count($inline_keyboard_array) - 1][] = $value;
                        }
                    }

                    $inline_keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($inline_keyboard_array);
                    $get_user_data = getUserData($user_data['tgm_user_id']);
                    if (!empty($get_user_data)) {
                        $user_preference_city = $get_user_data['preference_city_text'];
                        $user_preference_district = $get_user_data['preference_district_text'];
                        $user_preference_property = $get_user_data['preference_property_text'];
                        $user_rooms_min = $get_user_data['rooms_min'];
                        $user_preference_sharing = $get_user_data['preference_sharing_text'];
                        $user_max_price = $get_user_data['price_max_text'];

                        $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройка</b>\n\n✅ Город: " . $user_preference_city . "\n\n❓Какой тип жилья Вам нужен?\n\n" : "<b>Settings</b>\n\n✅ City: " . $user_preference_city . "\n\n❓What type of housing do you need? \n\n";
                        try {
                            $bot->sendMessage($chat_id, $message_text, 'HTML', false, null, $inline_keyboard);
                        } catch (Exception $e) {
                            $log_error_array[] = $e->getMessage();
                        }
                    } else {
                        $log_error_array[] = 'Get user data error';
                    }
                } else {
                    $log_error_array[] = 'Properties not found';
                }
            } else {
                $bot->deleteMessage($chat_id, $messageId);
                $log_error_array[] = 'Update user error';
            }
            break;
        case strpos($command_data, "property") === 0:
            $property_slug = str_replace('property_', '', $command_data);
            $log_message_array[] = 'Property type - ' . $property_slug;
            $update_result = false;
            if ($property_slug !== 'none') {
                $property_data = getPropertyBySlug($property_slug);
                $new_data['preference_property'] = $property_data[0]['property_id'];
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
                if (!empty($get_user_data)) {
                    $user_preference_city = $get_user_data['preference_city_text'];
                    $user_preference_district = $get_user_data['preference_district_text'];
                    $user_preference_property = $get_user_data['preference_property_text'];
                    $user_rooms_min = $get_user_data['rooms_min'];
                    $user_preference_sharing = $get_user_data['preference_sharing_text'];
                    $user_max_price = $get_user_data['price_max_text'];

                    $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройка</b>\n\n✅ Город: " . $user_preference_city . "\n✅ Тип жилья: " . $user_preference_property . "\n\n❓Сколько минимум комнат в квартире вам нужно? \n\n" : "<b>Settings</b>\n\n✅ City: " . $user_preference_city . "\n✅ Property type: " . $user_preference_property . "\n\n❓How many minimum rooms do you need in the apartment? \n\n";
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
                if (!empty($get_user_data)) {
                    $user_preference_city = $get_user_data['preference_city_text'];
                    $user_preference_district = $get_user_data['preference_district_text'];
                    $user_preference_property = $get_user_data['preference_property_text'];
                    $user_rooms_min = $get_user_data['rooms_min'];
                    $user_preference_sharing = $get_user_data['preference_sharing_text'];
                    $user_max_price = $get_user_data['price_max_text'];

                    $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройка</b>\n\n✅ Город: " . $user_preference_city . "\n✅ Тип жилья: " . $user_preference_property . "\n✅ Минимум комнат: " . $user_rooms_min . "\n\n❓Предпочтительный тип аренды?\n\n" : "<b>Settings</b>\n\n✅ City: " . $user_preference_city . "\n✅ Property type: " . $user_preference_property . "\n✅ Minimum rooms: " . $user_rooms_min . "\n\n❓Rental type? \n\n";
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
                if (!empty($get_user_data)) {
                    $user_preference_city = $get_user_data['preference_city_text'];
                    $user_preference_district = $get_user_data['preference_district_text'];
                    $user_preference_property = $get_user_data['preference_property_text'];
                    $user_rooms_min = $get_user_data['rooms_min'];
                    $user_preference_sharing = $get_user_data['preference_sharing_text'];
                    $user_max_price = $get_user_data['price_max_text'];

                    $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройка</b>\n\n✅ Город: " . $user_preference_city . "\n✅ Тип жилья: " . $user_preference_property . "\n✅ Минимум комнат: " . $user_rooms_min . "\n✅ Тип аренды: " . $user_preference_sharing . "\n\n❓Максимальная стоимость аренды в месяц?\n\n" : "<b>Settings</b>\n\n✅ City: " . $user_preference_city . "\n✅ Property type: " . $user_preference_property . "\n✅ Minimum rooms: " . $user_rooms_min . "\n✅ Rental type: " . $user_preference_sharing . "\n\n❓Maximum rental cost per month? \n\n";
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
                    $user_preference_city = $get_user_data['preference_city_text'];
                    $user_preference_district = $get_user_data['preference_district_text'];
                    $user_preference_property = $get_user_data['preference_property_text'];
                    $user_rooms_min = $get_user_data['rooms_min'];
                    $user_preference_sharing = $get_user_data['preference_sharing_text'];
                    $user_max_price = $get_user_data['price_max_text'];
                    $user_date_payment = $get_user_data['date_payment'];
                    $user_date_payment_text = $get_user_data['date_payment_text'];

                    $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "<b>Настройки успешно сохранены!</b>\n\n✅ Город: " . $user_preference_city . "\n✅ Минимум комнат: " . $user_rooms_min . "\n✅ Тип аренды: " . $user_preference_sharing . "\n✅ Максимальная стоимость аренды в месяц: " . $user_max_price . "\n\n👉 Вы будете получать мгновенные уведомления обо всех новых объявлениях ⚡⚡⚡\n\n📯 Для обратной связи напишите боту сообщение с хештегом #feedback\n\n" : "<b>Settings successfully saved!</b>\n\n✅ City: " . $user_preference_city . "\n✅ Minimum rooms: " . $user_rooms_min . "\n✅ Rental type: " . $user_preference_sharing . "\n✅ Maximum rental cost per month: " . $user_max_price . "\n\n👉 You will receive instant notifications of all new ads ⚡⚡⚡\n\n📯 For feedback, write a message to the bot with the hashtag #feedback\n\n";

                    $now = date('Y-m-d H:i:s');
                    if ($now < $user_date_payment) {
                        $message_text .= ($user_language === 'ru' || $user_language === 'kg') ? "👑 <b>Премиум подписка:</b> " . $user_date_payment_text . "\nКогда ваша премиум-подписка закончится, вы <b><u>продолжите</u></b> получать уведомления, но реже и в сжатом формате. Чтобы проверить состояние подписки или продлить подписку - воспользуйтесь командой /premium \n\n" : "👑 <b>Premium subscription:</b> " . $user_date_payment_text . "\nWhen your premium subscription ends, you will <b><u>continue</u></b> receive notifications, but less frequently and in a compressed format. To check the status of the subscription or renew the subscription, use the /premium command \n\n";
                    } else {
                        $message_text .= ($user_language === 'ru' || $user_language === 'kg') ? "👑 <b>Ваша премиум подписка истекла</b>\nЧтобы продлить премиум подписку - воспользуйтесь командой /premium \n\n" : "👑 <b>Your premium subscription has expired</b>\nTo renew the premium subscription, use the /premium command \n\n";
                    }

                    $bot->sendMessage($chat_id, $message_text, 'HTML');

                    // set timeout
                    $rnd_sec = rand(2, 5);
                    sleep($rnd_sec);

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
        $message_text = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ Что-то пошло не так. Попробуйте позже, пожалуйста...\n\n📯 Для обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ Something went wrong. Try again later, please...\n\n📯 For feedback, write a message to the bot with the hashtag #feedback";
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
        $now_plus_day = date('Y-m-d H:i:s', strtotime('+1 day'));
        $user_data['date_payment'] = $now_plus_day;
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


function deactivateUser($tgm_user_id, $chat_id)
{
    global $log_error_array;
    global $log_message_array;

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
    if ($result === false) {
        $log_error_array[] = 'deactivateUser() - Error: ' . $sql . ' | ' . mysqli_error($conn);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
        return false;
    }

    // remove  from chat_ids_to_send
    $sql = "SELECT * FROM $table_data WHERE JSON_CONTAINS(chat_ids_to_send, '\"$chat_id\"') AND done IS NULL";
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        $log_error_array = 'deactivateUser() - Error: ' . $sql . ' | ' . mysqli_error($conn);
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
                    $log_message_array[] = 'Chat id: ' . $chat_id . ' removed';
                } else {
                    $log_error_array[] = 'deactivateUser() - Error: ' . $sql . ' | ' . mysqli_error($conn);
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

    $formatter_usd = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
    $formatter_usd->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);

    $formatter_kgs = new NumberFormatter('ru_RU', NumberFormatter::CURRENCY);
    $formatter_kgs->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);

    $log_message_array[] = 'getUserData() - ' . $tgm_user_id;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_user = MYSQL_TABLE_USER;
    $table_city = MYSQL_TABLE_CITY;
    $table_district = MYSQL_TABLE_DISTRICT;
    $table_property = MYSQL_TABLE_PROPERTY;
    $table_owner = MYSQL_TABLE_OWNER;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        $log_error_array[] = 'getUserData() - Connection failed';
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "SELECT * FROM $table_user 
            LEFT JOIN $table_city ON $table_user.preference_city = $table_city.city_id 
            LEFT JOIN $table_property ON $table_user.preference_property = $table_property.property_id 
            LEFT JOIN $table_owner ON $table_user.preference_owner = $table_owner.owner_id 
            LEFT JOIN $table_district ON $table_user.preference_district = $table_district.district_id 
            WHERE tgm_user_id = " . $tgm_user_id;
    $result = mysqli_query($conn, $sql);
    $user_data = [];
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {

            $user_language = ($row['language_code']) ? $row['language_code'] : 'en';

            $price_max = ($user_language === 'ru' || $user_language === 'kg') ? 'без ограничений' : 'no limit';
            if ($row['price_max'] !== NULL && intval($row['price_max']) !== 1000000) {
                $price_max = ($row['price_currency'] === 'USD') ? $formatter_usd->formatCurrency($row['price_max'], 'USD') : $formatter_kgs->formatCurrency($row['price_max'], 'KGS');
            }

            $rooms_min = ($user_language === 'ru' || $user_language === 'kg') ? 'неважно' : 'no matter';
            if ($row['rooms_min'] !== NULL) {
                $rooms_min = $row['rooms_min'];
            }

            $preference_city = ($user_language === 'ru' || $user_language === 'kg') ? 'неважно' : 'no matter';
            if ($row['preference_city'] !== NULL) {
                $preference_city = ($user_language === 'ru' || $user_language === 'kg') ? $row['city_name_ru'] : $row['city_name_en'];
            }

            $preference_district = ($user_language === 'ru' || $user_language === 'kg') ? 'неважно' : 'no matter';
            if ($row['preference_district'] !== NULL) {
                $preference_district = ($user_language === 'ru' || $user_language === 'kg') ? $row['district_name_ru'] : $row['district_name_en'];
            }

            $preference_sharing = ($user_language === 'ru' || $user_language === 'kg') ? 'неважно' : 'no matter';
            if ($row['preference_sharing'] !== NULL) {
                $preference_sharing = ($row['preference_sharing'] === '1') ? (($user_language === 'ru' || $user_language === 'kg') ? 'без подселения' : 'without sharing') : (($user_language === 'ru' || $user_language === 'kg') ? 'с подселением' : 'with sharing');
            }

            $preference_owner = ($user_language === 'ru' || $user_language === 'kg') ? 'неважно' : 'no matter';
            if ($row['preference_owner'] !== NULL) {
                $preference_owner = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? $row['owner_name_ru']  : $row['owner_name_en'];
            }

            $preference_property = ($user_language === 'ru' || $user_language === 'kg') ? 'неважно' : 'no matter';
            if ($row['preference_property'] !== NULL) {
                $preference_property = ($user_language === 'ru' || $user_language === 'kg') ? $row['property_name_ru'] : $row['property_name_en'];
            }

            $now = date('Y-m-d H:m:s');
            $date_payment = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'не оплачена' : 'not paid';
            if ($row['date_payment'] !== NULL) {
                if ($row['date_payment'] > $now) {
                    $date_payment = ($row['language_code'] === 'ru' || $row['language_code'] === 'kg') ? 'оплачена до ' . $row['date_payment'] : 'paid until ' . $row['date_payment'];
                }
            }


            $user_data = [
                'tgm_user_id'               => $row['tgm_user_id'],
                'is_bot'                    => $row['is_bot'],
                'is_deleted'                => $row['is_deleted'],
                'is_premium'                => $row['is_premium'],
                'is_admin'                  => $row['is_admin'],
                'is_returned'               => $row['is_returned'],
                'is_ads'                    => $row['is_ads'],
                'is_statistics'             => $row['is_statistics'],
                'first_name'                => $row['first_name'],
                'last_name'                 => $row['last_name'],
                'username'                  => $row['username'],
                'language_code'             => $row['language_code'],
                'chat_id'                   => $row['chat_id'],
                'refresh_time'              => $row['refresh_time'],
                'price_min'                 => $row['price_min'],
                'price_max'                 => $row['price_max'],
                'price_max_text'            => $price_max,
                'price_currency'            => $row['price_currency'],
                'rooms_min'                 => $row['rooms_min'],
                'rooms_min_text'            => $rooms_min,
                'rooms_max'                 => $row['rooms_max'],
                'preference_city'           => $row['preference_city'],
                'preference_city_text'      => $preference_city,
                'preference_district'       => $row['preference_district'],
                'preference_district_text'  => $preference_district,
                'preference_sharing'        => $row['preference_sharing'],
                'preference_sharing_text'   => $preference_sharing,
                'preference_owner'          => $row['preference_owner'],
                'preference_owner_text'     => $preference_owner,
                'preference_property'       => $row['preference_property'],
                'preference_property_text'  => $preference_property,
                'date_payment'              => $row['date_payment'],
                'date_payment_text'         => $date_payment,
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

    $formatter_usd = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
    $formatter_usd->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);

    $formatter_kgs = new NumberFormatter('ru_RU', NumberFormatter::CURRENCY);
    $formatter_kgs->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);

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
            $username = $user_data['username'];
            $parameters_array = [];
            if ($user_preference_city !== NULL) {
                $parameters_array[] = "city = " . $user_preference_city;
            }
            if ($user_data['preference_property'] !== NULL) {
                $parameters_array[] = "property_type = " . $user_data['preference_property'];
            }
            if ($user_data['price_max'] !== NULL) {
                $parameters_array[] = "price_usd <= " . $user_data['price_max'];
            }
            if ($user_data['rooms_min'] !== NULL) {
                $parameters_array[] = "rooms >= " . $user_data['rooms_min'];
            }
            if ($user_data['preference_sharing'] !== NULL) {
                if ($user_data['preference_sharing'] === '1') {
                    $parameters_array[] = "sharing = '1'";
                } elseif ($user_data['preference_sharing'] === '0') {
                    $parameters_array[] = "sharing = '0'";
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

                    $message = "<b>$title</b>\n\n";

                    if ($district !== NULL) {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Район:</b> $district\n" : "<b>District:</b> $district\n";
                    }
                    if ($house_type !== 'n/d' && $house_type !== NULL) {
                        $house_type_en = slug($house_type, true);
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Серия:</b> $house_type\n" : "<b>House type:</b> $house_type_en\n";
                    }
                    if ($sharing !== 'n/d' && $sharing !== NULL) {
                        if ($sharing === '1') {
                            $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Подселение:</b> без подселения\n" : "<b>Sharing:</b> without sharing\n";
                        } elseif ($sharing === '0') {
                            $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Подселение:</b> с подселением\n" : "<b>Sharing:</b> with sharing\n";
                        }
                    }
                    if ($floor !== 'n/d' && $floor !== NULL && $total_floor !== 'n/d' && $total_floor !== NULL) {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Этаж:</b> $floor/$total_floor\n" : "<b>Floor:</b> $floor/$total_floor\n";
                    } elseif ($floor !== 'n/d' && $floor !== NULL) {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Этаж:</b> $floor\n" : "<b>Floor:</b> $floor\n";
                    } elseif ($total_floor !== 'n/d' && $total_floor !== NULL) {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Всего этажей:</b> $total_floor\n" : "<b>Total floor:</b> $total_floor\n";
                    }
                    if ($property_type === 1) {
                        if ($house_area !== 'n/d' && $house_area !== NULL) {
                            $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Площадь дома:</b> $house_area м²\n" : "<b>House area:</b> $house_area sq.m.\n";
                        }
                        if ($land_area !== 'n/d' && $land_area !== NULL) {
                            $sqm = intval($land_area) * 100;
                            $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Площадь участка:</b> $land_area соток\n" : "<b>Land area:</b> $sqm sq.m.\n";
                        }
                    }
                    if ($animals !== 'n/d' && $animals !== NULL) {
                        if ($animals === '1') {
                            $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Животные:</b> да\n" : "<b>Animals:</b> yes\n";
                        } elseif ($animals === '0') {
                            $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Животные:</b> нет\n" : "<b>Animals:</b> no\n";
                        }
                    }

                    if ($furniture !== 'n/d' && $furniture !== NULL) {
                        $furniture_array = json_decode($furniture);
                        $furniture_array_name = [];
                        foreach ($furniture_array as $furniture_item) {
                            $furniture_data = getAmenityById($furniture_item);
                            $furniture_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $furniture_data['amenity_name_ru'] : $furniture_data['amenity_name_en'];
                        }
                        $furniture = implode(', ', $furniture_array_name);
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Мебель:</b> $furniture\n" : "<b>Furniture:</b> $furniture\n";
                    }
                    if ($condition !== 'n/d' && $condition !== NULL) {
                        $condition_array = json_decode($condition);
                        $condition_array_name = [];
                        foreach ($condition_array as $condition_item) {
                            $condition_data = getAmenityById($condition_item);
                            $condition_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $condition_data['amenity_name_ru'] : $condition_data['amenity_name_en'];
                        }
                        $condition = implode(', ', $condition_array_name);
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Состояние:</b> $condition\n" : "<b>Condition:</b> $condition\n";
                    }
                    if ($appliances !== 'n/d' && $appliances !== NULL) {
                        $appliances_array = json_decode($appliances);
                        $appliances_array_name = [];
                        foreach ($appliances_array as $appliances_item) {
                            $appliances_data = getAmenityById($appliances_item);
                            $appliances_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $appliances_data['amenity_name_ru'] : $appliances_data['amenity_name_en'];
                        }
                        $appliances = implode(', ', $appliances_array_name);
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Бытовая техника:</b> $appliances\n" : "<b>Appliances:</b> $appliances\n";
                    }
                    if ($improvement_out !== 'n/d' && $improvement_out !== NULL) {
                        $improvement_out_array = json_decode($improvement_out);
                        $improvement_out_array_name = [];
                        foreach ($improvement_out_array as $improvement_out_item) {
                            $improvement_out_data = getAmenityById($improvement_out_item);
                            $improvement_out_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $improvement_out_data['amenity_name_ru'] : $improvement_out_data['amenity_name_en'];
                        }
                        $improvement_out = implode(', ', $improvement_out_array_name);
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Благоустройство:</b> $improvement_out\n" : "<b>Improvements:</b> $improvement_out\n";
                    }
                    if ($property_type === 1) {
                        if ($utility !== 'n/d' && $utility !== NULL) {
                            $utility_array = json_decode($utility);
                            $utility_array_name = [];
                            foreach ($utility_array as $utility_item) {
                                $utility_data = getAmenityById($utility_item);
                                $utility_array_name[] = ($user_language === 'ru' || $user_language === 'kg') ? $utility_data['amenity_name_ru'] : $utility_data['amenity_name_en'];
                            }
                            $utility = implode(', ', $utility_array_name);
                            $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Коммуникации:</b> $utility\n" : "<b>Utility:</b> $utility\n";
                        }
                    }

                    if ($min_rent_month !== 'n/d' && $min_rent_month !== NULL) {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "\n<b>Мин. срок аренды:</b> $min_rent_month месяцев\n" : "\n<b>Min. rent period:</b> $min_rent_month months\n";
                    }
                    if ($price_kgs !== 'n/d' && $price_kgs !== NULL) {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "\n<b>Цена:</b> $price_kgs ($price_usd)\n" : "\n<b>Price:</b> $price_kgs ($price_usd)\n";
                    }
                    if ($deposit_kgs !== 'n/d' && $deposit_kgs !== NULL) {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Депозит:</b> $deposit_kgs ($deposit_usd)\n" : "<b>Deposit:</b> $deposit_kgs ($deposit_usd)\n";
                    }
                    if ($owner_name !== 'n/d' && $owner_name !== NULL) {
                        $owner_name_en = slug($owner_name, true);
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "\n<b>Кто сдаёт:</b> $owner_name\n" : "\n<b>Owner:</b> $owner_name_en\n";
                    }
                    if ($phone !== 'n/d' && $phone !== NULL && $phone !== '') {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Телефон:</b> $phone\n" : "<b>Phone:</b> $phone\n";
                        $message .= "<a href='https://wa.me/$phone'>Whatsapp</a>\n";
                    } else {
                        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "<b>Ссылка:</b> $link\n" : "<b>Link:</b> $link\n";
                    }

                    $gallery = ($row['gallery']) ? json_decode($row['gallery']) : NULL;
                    try {
                        if (!empty($gallery)) {
                            $bot = new \TelegramBot\Api\BotApi($token);
                            $gallery = array_map('strval', $gallery);
                            $gallery = array_unique($gallery);
                            $gallery = array_values($gallery);
                            sort($gallery);
                            $media = new \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia();
                            $image_counter = 0;
                            foreach ($gallery as $image) {
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
                            file_put_contents($start_log_file, ' | User: ' . $username . ' | Error: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
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
                                $log_error_array[] = 'sendLastAds() - Error: ' . mysqli_error($conn);
                                $msg_error++;
                            }
                        }
                    } catch (\TelegramBot\Api\Exception $e) {
                        $error = $e->getMessage();
                        $log_error_array[] = 'sendLastAds() - Error: ' . $e->getMessage();
                        if ($error === 'Forbidden: bot was blocked by the user') {
                            try {
                                deactivateUser($tgm_user_id, $chat_id);
                            } catch (Exception $e) {
                                $log_error_array[] = 'sendLastAds() - Error: ' . $e->getMessage();
                            }
                        }
                        break;
                    }
                    // set timeout
                    $rnd_sec = rand(1, 3);
                    sleep($rnd_sec);
                    $counter++;
                }
            } else {
                $log_message_array[] = 'sendLastAds() - No ads found';
                $message = ($user_language === 'ru' || $user_language === 'kg') ? "⭕ По Вашим критериям объявлений не найдено.\n⚙ Попробуйте изменить критерии, для этого воспользуйтесь командой /settings\n\n📯 Для обратной связи напишите боту сообщение с хештегом #feedback" : "⭕ No ads found for your criteria.\n⚙ Try to change the criteria, to do this, use the /settings command\n\n📯 For feedback, write a message to the bot with the hashtag #feedback";
                try {
                    $bot->sendMessage($chat_id, $message, 'HTML');
                } catch (\TelegramBot\Api\Exception $e) {
                    $error = $e->getMessage();
                    $log_error_array[] = 'sendLastAds() - Error: ' . $e->getMessage();
                    if ($error === 'Forbidden: bot was blocked by the user') {
                        $error = 'User blocked bot';
                        deactivateUser($tgm_user_id, $chat_id);
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

function getProperties()
{
    global $log_message_array;
    global $log_error_array;

    $log_message_array[] = 'getProperties()';

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_property = MYSQL_TABLE_PROPERTY;

    $properties = [];

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        $log_error_array[] = 'getProperties() - Connection failed';
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "SELECT * FROM $table_property";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $properties[] = $row;
        }
    } else {
        $log_error_array[] = 'getProperties() - No properties found';
    }

    // Close connection
    mysqli_close($conn);

    return $properties;
}

function getPropertyBySlug($property_slug)
{
    global $log_message_array;
    global $log_error_array;

    $log_message_array[] = 'getPropertyBySlug() - ' . $property_slug;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_property = MYSQL_TABLE_PROPERTY;

    $property = [];

    if ($property_slug === '') {
        $log_error_array[] = 'getPropertyBySlug() - Slug is empty';
    } else {
        // Create connection
        $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
        if (!$conn) {
            $log_error_array[] = 'getPropertyBySlug() - Connection failed';
            throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
        }

        $sql = "SELECT * FROM $table_property WHERE property_slug = '$property_slug'";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $property = $row;
            }
        } else {
            $log_error_array[] = 'getPropertyBySlug() - No properties found';
        }

        // Close connection
        mysqli_close($conn);
    }

    return $property;
}

function getPropertyById($property_id)
{
    global $log_message_array;
    global $log_error_array;

    $log_message_array[] = 'getPropertyById() - ' . $property_id;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_property = MYSQL_TABLE_PROPERTY;

    $property = [];

    if ($property_id !== '') {
        $log_error_array = 'getPropertyById() - Id is empty';
    } else {
        // create connection
        $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
        if (!$conn) {
            $log_error_array[] = 'getPropertyById() - Connection failed';
            throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
        }

        $sql = "SELECT * FROM $table_property WHERE property_id = '$property_id'";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $property = $row;
            }
        } else {
            $log_error_array[] = 'getPropertyById() - No properties found';
        }

        // close connection
        mysqli_close($conn);
    }

    return $property;
}

function getDonation($user_language)
{

    global $start_error_log_file;

    $message = null;

    $donations = [];

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_donation = MYSQL_TABLE_DONATION;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($start_error_log_file, ' | getDonation - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $sql = "SELECT * FROM $table_donation WHERE is_active = 1 ORDER BY id ASC";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        foreach ($rows as $row) {
            $donations[] = [
                'text' => $row['donation_icon'] . ' ' . $row['donation_name_' . $user_language],
                'url' => $row['donation_link']
            ];
        }
    }

    if (!empty($donations)) {
        $inline_keyboard_array = [];
        foreach ($donations as $key => $value) {
            if ($key % 2 === 0) {
                $inline_keyboard_array[] = [$value];
            } else {
                $inline_keyboard_array[count($inline_keyboard_array) - 1][] = $value;
            }
        }

        $inline_keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($inline_keyboard_array);

        $message = "\n";
        $message .= "\n";
        $message .= ($user_language === 'ru' || $user_language === 'kg') ? "💪 Преимущества премиум доступа:\n1. Ускоренное уведомление о новых объявлениях.\n2. Полноый набор фотографий.\n3. Расширенное описание.\n\n👑 Стоимость премиум доступа на 3 дня - 200 сом (220 руб)\n👑 Стоимость премиум доступа на 7 дней - 300 сом (330 руб)\n👑 Стоимость премиум доступа на 14 дней - 500 сом (550 руб)\n\n💰 Для оплаты воспользуйтесь кнопками внизу ⬇" : "💪 Benefits of premium access:\n1. Expedited notification of new announcements.\n2. Full set of photos.\n3. Extended description.\n\n👑 The cost of premium access for 3 days is 200 soms (220 rubles)\n👑 The cost of premium access for 7 days is 300 soms (330 rubles)\n👑 The cost of premium access for 14 days is 500 soms ( 550 rubles)\n\n💰 To pay, use the buttons below ⬇";
    } else {
        $inline_keyboard = null;
    }

    return [$message, $inline_keyboard];
}
