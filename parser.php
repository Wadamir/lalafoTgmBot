<?php
/*
Parsing apartments from lalafo.kg
*******************************************
1. Set all variables & constants
2. Get rates from fx.kg
3. Get all chat_id from table users db
4. Get all city names from table city
5. Parse apartments from lalafo.kg
*/



// 1. Set all variables & constants
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use PhpQuery\PhpQuery;
use GuzzleHttp\Client;

require_once __DIR__ . '/config.php';
$log_dir = __DIR__ . '/logs';
$parser_log_file = $log_dir . '/parser.log';
$parser_error_log_file = $log_dir . '/parser_error.log';
file_put_contents($parser_log_file, '[' . date('Y-m-d H:i:s') . '] Start', FILE_APPEND);

$items_total = 0;
$items_added = 0;
$items_error = 0;

$token = TOKEN;
$fx_token = FX_TOKEN;

$dbhost = MYSQL_HOST;
$dbuser = MYSQL_USER;
$dbpass = MYSQL_PASSWORD;
$dbname = MYSQL_DB;
$table_users = MYSQL_TABLE_USERS;
$table_city = MYSQL_TABLE_CITY;
$table_district = MYSQL_TABLE_DISTRICT;
$table_data = MYSQL_TABLE_DATA;
$table_rates = MYSQL_TABLE_RATES;

// Create connection
$conn = mysqli_connect($dbhost, $dbuser, $dbpass);
if (!$conn) {
    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] Connection failed' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}
if (!mysqli_select_db($conn, $dbname)) {
    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}


// 2. Get rates from fx.kg
// file_put_contents($parser_log_file, ' | Get rates from fx.kg', FILE_APPEND);
$rates = [];
$guzzle_client = new Client();
$bearer_token = 'Bearer ' . $fx_token;
$headers = [
    'Authorization' => $bearer_token,
];
try {
    $request = $guzzle_client->request('GET', 'https://data.fx.kg/api/v1/central', [
        'headers' => $headers,
    ]);
    $response = $request->getBody()->getContents();
    $response = json_decode($response, true);
    // put rates to table rates
    foreach ($response as $currency => $rate) {
        if ($currency === 'updated_at') continue;
        if ($currency === 'created_at') continue;
        $rates[$currency] = floatval($rate);
    }
    file_put_contents($parser_log_file, ' | Rates updated: ' . count($rates), FILE_APPEND);
} catch (\Exception $e) {
    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] Error getting rates: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
}
if (count($rates) > 0) {
    // get last date_updated from table rates
    $sql = "SELECT date_updated FROM $table_rates ORDER BY date_updated DESC LIMIT 1";
    $result = mysqli_query($conn, $sql);
    $last_date_updated = $result->fetch_all(MYSQLI_ASSOC);
    if (count($last_date_updated) === 0) {
        $last_date_updated = '2000-01-01 00:00:00';
    } else {
        $last_date_updated = $last_date_updated[0]['date_updated'];
    }
    $last_date_updated = strtotime($last_date_updated);

    // get current date_updated from fx.kg
    $current_date_updated = $response['updated_at'];
    $current_date_updated = strtotime($current_date_updated);

    if ($current_date_updated > $last_date_updated) {
        // insert new rates
        $insert_sql = "INSERT INTO $table_rates (`usd`, `eur`, `gbp`, `cny`, `rub`, `kzt`, `date_updated`) VALUES ('" . $rates['usd'] . "', '" . $rates['eur'] . "', '" . $rates['gbp'] . "', '" . $rates['cny'] . "', '" . $rates['rub'] . "', '" . $rates['kzt'] . "', '" . $response['updated_at'] . "')";
        if (mysqli_query($conn, $insert_sql)) {
            file_put_contents($parser_log_file, ' | Rates updated', FILE_APPEND);
        } else {
            file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] Error updating rates: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
        }
    }
} else {
    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] No rates found' . PHP_EOL, FILE_APPEND);
}



// 3. Get all chat_id from table users db
$now = date('Y-m-d H:i:s');
$sql = "SELECT `chat_id` FROM $table_users WHERE (`is_deleted` IS NULL OR `is_deleted` = 0) AND ('$now' <= `date_payment` OR `date_payment` IS NULL)";
$result = mysqli_query($conn, $sql);
$chat_ids = [];
if ($result !== false) {
    $chat_ids = $result->fetch_all(MYSQLI_ASSOC);
    $chat_ids = array_column($chat_ids, 'chat_id');
}



// 4. Get all cities from table city
// file_put_contents($parser_log_file, ' | Get all cities from table city', FILE_APPEND);
$cities = [];
$sql = "SELECT * FROM $table_city";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    $city_rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    foreach ($city_rows as $city_row) {
        $cities[] = [
            'id' => $city_row['id'],
            'name_en' => $city_row['name_en'],
            'name_ru' => $city_row['name_ru'],
            'name_kg' => $city_row['name_kg'],
            'slug' => $city_row['slug'],
        ];
    }
} else {
    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] No cities found' . PHP_EOL, FILE_APPEND);
    die('No cities found');
}



// 5. Parse apartments from lalafo.kg
$usd_rate_sql = "SELECT usd FROM $table_rates ORDER BY date_updated DESC LIMIT 1";
$usd_rate_result = mysqli_query($conn, $usd_rate_sql);
$usd_rate = $usd_rate_result->fetch_all(MYSQLI_ASSOC);
$usd_rate = floatval($usd_rate[0]['usd']);
if (count($cities) > 0) {
    file_put_contents($parser_log_file, ' | Cities found: ' . count($cities), FILE_APPEND);
    $guzzle = new Client();
    $apartments = [];
    foreach ($cities as $city) {
        $city_id = $city['id'];
        $city_slug = $city['slug'];
        if ($city_slug === NULL || $city_slug === '') {
            continue;
        }
        $parse_link = 'https://lalafo.kg/' . $city_slug . '/kvartiry/arenda-kvartir/dolgosrochnaya-arenda-kvartir';
        for ($i = 1; $i < 2; $i++) {
            $response = $guzzle->get($parse_link . '?page=' . $i);
            $content = $response->getBody()->getContents();

            $pq = new PhpQuery;
            $pq->load_str($content);

            $links = $pq->query('.adTile-title');

            foreach ($links as $link) {
                // set timeout
                $rnd_sec = rand(1, 3);
                sleep($rnd_sec);
                $items_total++;
                try {
                    // check if apartment exists
                    $apartment_link = 'https://lalafo.kg' . $link->getAttribute('href');
                    $sql = "SELECT * FROM $table_data WHERE link = '" . $apartment_link . "'";
                    $result = mysqli_query($conn, $sql);

                    if (count($result->fetch_all()) > 0) {
                        continue;
                    }

                    $apartment_response = $guzzle->get($apartment_link);
                    $apartment_content = $apartment_response->getBody()->getContents();
                    file_put_contents('apartment.html', $apartment_content);

                    $apartment_pq = new PhpQuery;
                    $apartment_pq->load_str($apartment_content);

                    $price_kgs = ($apartment_pq->query('.price')->length) ? $apartment_pq->query('.price')[0]->textContent : NULL;
                    if ($price_kgs === NULL) {
                        continue;
                    }
                    $price_kgs = intval(preg_replace('/[^0-9]/', '', trim(str_replace('KGS', '', $price_kgs))));
                    if ($price_kgs === 0) {
                        continue;
                    }
                    $price_usd = round($price_kgs / $usd_rate);


                    $phone = ($apartment_pq->query('.call-button a')->length) ? $apartment_pq->query('.call-button a')[0]->getAttribute('href') : NULL;
                    $phone = ($phone) ? str_replace('tel:', '', $phone) : NULL;

                    $owner_name = ($apartment_pq->query('.userName-text')->length) ? $apartment_pq->query('.userName-text')[0]->textContent : NULL;

                    $dates = ($apartment_pq->query('.about-ad-info__date')) ? $apartment_pq->query('.about-ad-info__date') : [];
                    $date_created = NULL;
                    $date_updated = NULL;
                    foreach ($dates as $date) {
                        if (mb_strpos($date->textContent, 'Создано') !== false) {
                            $date_created = trim(str_replace('Создано:', '', $date->textContent));
                            continue;
                        }
                        if (mb_strpos($date->textContent, 'Обновлено') !== false) {
                            $date_updated = trim(str_replace('Обновлено:', '', $date->textContent));
                            continue;
                        }
                    }

                    $details = $apartment_pq->query('.details-page__params li');

                    $district_id = NULL;
                    $deposit_kgs = NULL;
                    $deposit_usd = NULL;
                    $rooms = NULL;
                    $floor = NULL;
                    $total_floor = NULL;
                    $house_type = NULL;
                    $sharing = NULL;
                    $furniture = NULL;
                    $appliances = NULL;
                    $condition = NULL;
                    $renovation = NULL;
                    $animals = NULL;
                    $owner = NULL;
                    $owner_value = 1;

                    foreach ($details as $detail) {
                        if (mb_strpos($detail->textContent, 'Район') !== false) {
                            $district = trim(str_replace('Район:', '', $detail->textContent));
                            $district_result = getDistrictId($city_id, $district);
                            if ($district_result !== false) {
                                $district_id = $district_result;
                            } else {
                                $district_id = NULL;
                            }
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Депозит, сом') !== false) {
                            $deposit_kgs = trim(str_replace('Депозит, сом:', '', $detail->textContent));
                            $deposit_kgs = preg_replace('/[^0-9]/', '', $deposit_kgs);
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Количество комнат') !== false) {
                            $rooms = intval(preg_replace('/[^0-9]/', '', trim(str_replace('Количество комнат:', '', $detail->textContent))));
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Этаж') !== false) {
                            $floor = trim(str_replace('Этаж:', '', $detail->textContent));
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Количество этажей') !== false) {
                            $total_floor = trim(str_replace('Количество этажей:', '', $detail->textContent));
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Серия') !== false) {
                            $house_type = trim(str_replace('Серия:', '', $detail->textContent));
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Подселение') !== false) {
                            $sharing_text = trim(str_replace('Подселение:', '', $detail->textContent));
                            if ($sharing_text === 'Без подселения') {
                                $sharing = 1;
                            } elseif ($sharing_text === 'С подселением') {
                                $sharing = 0;
                            } else {
                                $sharing = NULL;
                            }
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Мебель') !== false) {
                            $furniture = trim(str_replace('Мебель:', '', $detail->textContent));
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Бытовая техника') !== false) {
                            $appliances = trim(str_replace('Бытовая техника:', '', $detail->textContent));
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Состояние') !== false) {
                            $condition = trim(str_replace('Состояние:', '', $detail->textContent));
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Ремонт') !== false) {
                            $renovation = trim(str_replace('Ремонт:', '', $detail->textContent));
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Животные') !== false) {
                            $animals = trim(str_replace('Животные:', '', $detail->textContent));
                            continue;
                        }
                        if (mb_strpos($detail->textContent, 'Кто сдает') !== false) {
                            $owner = trim(str_replace('Кто сдает:', '', $detail->textContent));
                            if ($owner === 'Риэлтор') {
                                $owner_value = 0;
                            }
                            continue;
                        }
                    }

                    $district_id = ($district_id === NULL) ? NULL : intval($district_id);

                    $deposit_kgs = (intval($deposit_kgs) === 0) ? NULL : $deposit_kgs;
                    if ($deposit_kgs !== NULL) {
                        $deposit_usd = round($deposit_kgs / $usd_rate);
                    } else {
                        $deposit_usd = NULL;
                    }



                    $chat_ids_to_send = array_unique($chat_ids);
                    foreach ($chat_ids_to_send as $key => $chat_id) {
                        $user_sql = "SELECT * FROM $table_users WHERE chat_id = '$chat_id'";
                        $user_result = mysqli_query($conn, $user_sql);
                        $user = $user_result->fetch_all(MYSQLI_ASSOC);
                        $user = $user[0];
                        $user_price_min = isset($user['price_min']) ? $user['price_min'] : NULL;
                        $user_price_max = isset($user['price_max']) ? $user['price_max'] : NULL;
                        $user_price_currency = isset($user['price_currency']) ? strtoupper(trim($user['price_currency'])) : 'USD';
                        $user_rooms_min = isset($user['rooms_min']) ? $user['rooms_min'] : NULL;
                        $user_rooms_max = isset($user['rooms_max']) ? $user['rooms_max'] : NULL;
                        $user_preference_city = isset($user['preference_city']) ? $user['preference_city'] : NULL;
                        $user_preference_district = isset($user['preference_district']) ? $user['preference_district'] : NULL;
                        $user_preference_sharing = isset($user['preference_sharing']) ? $user['preference_sharing'] : NULL;
                        $user_preference_owner = isset($user['preference_owner']) ? $user['preference_owner'] : NULL;

                        if ($user_price_min === NULL && $user_price_max === NULL && $user_rooms_min === NULL && $user_rooms_max === NULL && $user_preference_city === NULL && $user_preference_district === NULL) {
                            continue;
                        }

                        if ($user_price_currency === 'USD') {
                            if ($user_price_max !== NULL && $price_usd > $user_price_max) {
                                unset($chat_ids_to_send[$key]);
                            }
                            if ($user_price_min !== NULL && $price_usd < $user_price_min) {
                                unset($chat_ids_to_send[$key]);
                            }
                        }
                        if ($user_price_currency === 'KGS') {
                            if ($user_price_max !== NULL && $price_kgs > $user_price_max) {
                                unset($chat_ids_to_send[$key]);
                            }
                            if ($user_price_min !== NULL && $price_kgs < $user_price_min) {
                                unset($chat_ids_to_send[$key]);
                            }
                        }

                        if ($user_rooms_min !== NULL && $rooms < $user_rooms_min) {
                            unset($chat_ids_to_send[$key]);
                        }
                        if ($user_rooms_max !== NULL && $rooms > $user_rooms_max) {
                            unset($chat_ids_to_send[$key]);
                        }

                        if ($user_preference_city !== NULL && $user_preference_city !== $city_id) {
                            unset($chat_ids_to_send[$key]);
                        }

                        if ($user_preference_district !== NULL && $user_preference_district !== $district_id) {
                            unset($chat_ids_to_send[$key]);
                        }

                        if ($user_preference_sharing !== NULL) {
                            if (intval($user_preference_sharing) === 1 && $sharing !== 1) {
                                unset($chat_ids_to_send[$key]);
                            }
                        }

                        if ($user_preference_owner !== NULL && intval($user_preference_owner) !== $owner_value) {
                            unset($chat_ids_to_send[$key]);
                        }
                    }

                    $chat_ids_to_send = array_values($chat_ids_to_send);
                    $done = count($chat_ids_to_send) === 0 ? 1 : NULL;

                    $data = [
                        'title' => mysqli_real_escape_string($conn, $link->textContent),
                        'link' => $apartment_link,
                        'city' => $city_id,
                        'floor' => (isset($floor) && isset($total_floor)) ? mysqli_real_escape_string($conn, $floor . ' / ' . $total_floor) : NULL,
                        'chat_ids_to_send' => json_encode($chat_ids_to_send),
                    ];

                    if ($done !== NULL) {
                        $data['done'] = $done;
                    }
                    if ($district_id !== NULL) {
                        $data['district'] = $district_id;
                    }
                    if ($date_created !== NULL) {
                        $data['created_at'] = $date_created;
                    }
                    if ($date_updated !== NULL) {
                        $data['updated_at'] = $date_updated;
                    }
                    if ($price_kgs !== NULL) {
                        $data['price_kgs'] = $price_kgs;
                    }
                    if ($price_usd !== NULL) {
                        $data['price_usd'] = $price_usd;
                    }
                    if ($deposit_kgs !== NULL) {
                        $data['deposit_kgs'] = $deposit_kgs;
                    }
                    if ($deposit_usd !== NULL) {
                        $data['deposit_usd'] = $deposit_usd;
                    }
                    if ($owner !== NULL) {
                        $data['owner'] = mysqli_real_escape_string($conn, $owner);
                    }
                    if ($owner_name !== NULL) {
                        $data['owner_name'] = mysqli_real_escape_string($conn, $owner_name);
                    }
                    if ($phone !== NULL) {
                        $data['phone'] = mysqli_real_escape_string($conn, $phone);
                    }
                    if ($rooms !== NULL) {
                        $data['rooms'] = $rooms;
                    }
                    if ($house_type !== NULL) {
                        $data['house_type'] = mysqli_real_escape_string($conn, $house_type);
                    }
                    if ($furniture !== NULL) {
                        $data['furniture'] = mysqli_real_escape_string($conn, $furniture);
                    }
                    if ($condition !== NULL) {
                        $data['condition'] = mysqli_real_escape_string($conn, $condition);
                    }
                    if ($renovation !== NULL) {
                        $data['renovation'] = mysqli_real_escape_string($conn, $renovation);
                    }
                    if ($animals !== NULL) {
                        $data['animals'] = mysqli_real_escape_string($conn, $animals);
                    }
                    if ($sharing !== NULL) {
                        $data['sharing'] = $sharing;
                    }

                    $apartments[] = $data;

                    $insert_sql = "INSERT INTO $table_data (";
                    $insert_sql .= "`" . implode('`, `', array_keys($data)) . "`";
                    $insert_sql .= ") VALUES (";
                    $insert_sql .= "'" . implode("', '", array_values($data)) . "'";
                    $insert_sql .= ")";

                    if (mysqli_query($conn, $insert_sql)) {
                        $items_added++;
                    } else {
                        file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] Error: ' . $insert_sql . ' | ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
                        $items_error++;
                    }
                } catch (\Exception $e) {
                    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                    $items_error++;
                    continue;
                }
            }
            // set timeout
            $rnd_sec = rand(1, 7);
            sleep($rnd_sec);
        }
    }
    file_put_contents($parser_log_file, ' | Total units: ' . $items_total . ' | Added: ' . $items_added . ' | Error: ' . $items_error . ' | ', FILE_APPEND);
} else {
    file_put_contents($parser_log_file, ' | No users found | ', FILE_APPEND);
}
file_put_contents($parser_log_file, ' End [' . date('Y-m-d H:i:s') . ']' . PHP_EOL, FILE_APPEND);



function deactivateUser($user_id)
{
    global $parser_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_users = MYSQL_TABLE_USERS;
    $table_data = MYSQL_TABLE_DATA;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($parser_log_file, ' | Update User - connection failed', FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }
    $sql = "UPDATE $table_users SET is_deleted = 1 WHERE user_id = " . $user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($parser_log_file, " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }

    // remove all from data table
    $sql = "DELETE FROM $table_data WHERE chat_id = " . $user_id;
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        file_put_contents($parser_log_file, " | Error: " . $sql . ' | ' . mysqli_error($conn), FILE_APPEND);
        throw new Exception("Error: " . $sql . ' | ' . mysqli_error($conn));
    }

    // Close connection
    mysqli_close($conn);

    return true;
}

function slug($string)
{

    $rus = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я', ' ');

    $lat = array('a', 'b', 'v', 'g', 'd', 'e', 'e', 'gh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya', 'a', 'b', 'v', 'g', 'd', 'e', 'e', 'gh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya', ' ');

    $string = trim($string);
    $string = str_replace($rus, $lat, $string);
    $string = str_replace('-', '_', $string);
    $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $string);
    return $slug;
}

function getDistrictId($cityId, $districtName)
{
    global $parser_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_district = MYSQL_TABLE_DISTRICT;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($parser_error_log_file, ' | Get district - connection failed' . PHP_EOL, FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $districtSlug = slug($districtName);
    $sql = "SELECT * FROM $table_district WHERE city_id = '$cityId' AND slug = '$districtSlug'";
    $result = mysqli_query($conn, $sql);
    if ($result !== false && mysqli_num_rows($result) > 0) {
        $district = mysqli_fetch_assoc($result);
        $district_id = $district['id'];
    } else {
        $sql = "INSERT INTO $table_district (`city_id`, `name`, `slug`) VALUES ('$cityId', '$districtName', '$districtSlug')";
        if (mysqli_query($conn, $sql)) {
            $district_id = mysqli_insert_id($conn);
        } else {
            file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] Error insert district: ' . $sql . ' | ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
            $district_id = false;
        }
    }

    // Close connection
    mysqli_close($conn);

    return $district_id;
}
