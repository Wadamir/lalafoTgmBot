<?php
/*
Parsing apartments from lalafo.kg
*******************************************
1. Set all variables & constants
2. Get rates from fx.kg
3. Get all chat_id from table user db
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
use Google\Cloud\Translate\V2\TranslateClient;

require_once __DIR__ . '/config.php';
$log_dir = __DIR__ . '/logs';
$parser_log_file = $log_dir . '/parser.log';
$parser_error_log_file = $log_dir . '/parser_error.log';
file_put_contents($parser_log_file, '[' . date('Y-m-d H:i:s') . '] Start', FILE_APPEND);

$items_total = 0;
$items_added = 0;
$items_error = 0;

$fx_token = FX_TOKEN;

$google_api_key = GOOGLE_API_KEY;
$translate = new TranslateClient([
    'key' => $google_api_key
]);

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
$table_owner = MYSQL_TABLE_OWNER;
$table_property = MYSQL_TABLE_PROPERTY;
$table_donation = MYSQL_TABLE_DONATION;



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

$formatter_usd = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
$formatter_usd->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);

$formatter_kgs = new NumberFormatter('ru_RU', NumberFormatter::CURRENCY);
$formatter_kgs->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);


// Get all owners
$owner_types = [];
$sql = "SELECT * FROM $table_owner";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    $owner_rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    foreach ($owner_rows as $owner_row) {
        $owner_types[] = [
            'id' => $owner_row['owner_id'],
            'name_ru' => $owner_row['owner_name_ru'],
            'name_en' => $owner_row['owner_name_en'],
        ];
    }
} else {
    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] No owners found' . PHP_EOL, FILE_APPEND);
    die('No owners found');
}


// Get all property types
$property_types = [];
$sql = "SELECT * FROM $table_property";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    $property_rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    foreach ($property_rows as $property_row) {
        $property_types[] = [
            'id' => $property_row['property_id'],
            'name_ru' => $property_row['property_name_ru'],
            'name_en' => $property_row['property_name_en'],
            'name_kg' => $property_row['property_name_kg'],
            'slug' => $property_row['property_slug'],
            'icon' => $property_row['property_icon'],
            'link' => $property_row['property_link'],
        ];
    }
} else {
    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] No property types found' . PHP_EOL, FILE_APPEND);
    die('No property types found');
}



// 2. Get rates from fx.kg
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
    $sql = "SELECT date_updated FROM $table_rate ORDER BY date_updated DESC LIMIT 1";
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
        $insert_sql = "INSERT INTO $table_rate (`usd`, `eur`, `gbp`, `cny`, `rub`, `kzt`, `date_updated`) VALUES ('" . $rates['usd'] . "', '" . $rates['eur'] . "', '" . $rates['gbp'] . "', '" . $rates['cny'] . "', '" . $rates['rub'] . "', '" . $rates['kzt'] . "', '" . $response['updated_at'] . "')";
        if (mysqli_query($conn, $insert_sql)) {
            file_put_contents($parser_log_file, ' | Rates updated', FILE_APPEND);
        } else {
            file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] Error updating rates: ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
        }
    }
} else {
    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] No rates found' . PHP_EOL, FILE_APPEND);
}



// 3. Get all chat_id from table user db
$now = date('Y-m-d H:i:s');
$sql = "SELECT `chat_id` FROM $table_user WHERE `is_deleted` IS NULL OR `is_deleted` = 0";
$result = mysqli_query($conn, $sql);
$chat_ids = [];
if ($result !== false) {
    $chat_ids = $result->fetch_all(MYSQLI_ASSOC);
    $chat_ids = array_column($chat_ids, 'chat_id');
}



// 4. Get all cities from table city
$cities = [];
$sql = "SELECT * FROM $table_city";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    $city_rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    foreach ($city_rows as $city_row) {
        $cities[] = [
            'city_id' => $city_row['city_id'],
            'city_name_en' => $city_row['city_name_en'],
            'city_name_ru' => $city_row['city_name_ru'],
            'city_name_kg' => $city_row['city_name_kg'],
            'city_slug' => $city_row['city_slug'],
        ];
    }
} else {
    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] No cities found' . PHP_EOL, FILE_APPEND);
    die('No cities found');
}



// 5. Parse apartments from lalafo.kg
$usd_rate_sql = "SELECT usd FROM $table_rate ORDER BY date_updated DESC LIMIT 1";
$usd_rate_result = mysqli_query($conn, $usd_rate_sql);
$usd_rate = $usd_rate_result->fetch_all(MYSQLI_ASSOC);
$usd_rate = floatval($usd_rate[0]['usd']);
if (count($cities) > 0) {
    file_put_contents($parser_log_file, ' | Cities found: ' . count($cities), FILE_APPEND);
    $guzzle = new Client();
    $apartments = [];
    foreach ($cities as $city) {
        $city_id = $city['city_id'];
        $city_slug = $city['city_slug'];
        if ($city_slug === NULL || $city_slug === '') {
            continue;
        }
        foreach ($property_types as $property_type) {
            $parse_link = 'https://lalafo.kg/' . $city_slug . $property_type['link'];
            $property_type_id = $property_type['id'];
            $property_type_slug = $property_type['slug'];
            for ($i = 1; $i < 2; $i++) {
                try {
                    $response = $guzzle->get($parse_link . '?page=' . $i);
                } catch (\Exception $e) {
                    file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                    continue;
                }
                $content = $response->getBody()->getContents();

                // file_put_contents('apartment.html', $content);

                $pq = new PhpQuery;
                $pq->load_str($content);

                $links = $pq->query('.adTile-title');

                foreach ($links as $link) {
                    // file_put_contents($parser_log_file, PHP_EOL . '[' . date('Y-m-d H:i:s') . '] Parsing: ' . $link->getAttribute('href'), FILE_APPEND);
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
                        // file_put_contents('apartment.html', $apartment_content);

                        $apartment_pq = new PhpQuery;
                        $apartment_pq->load_str($apartment_content);

                        $phone = ($apartment_pq->query('.call-button a')->length) ? $apartment_pq->query('.call-button a')[0]->getAttribute('href') : NULL;
                        $phone = ($phone) ? str_replace('tel:', '', $phone) : NULL;

                        $owner_name = ($apartment_pq->query('.userName-text')->length) ? removeSpecialChars($apartment_pq->query('.userName-text')[0]->textContent) : NULL;

                        $dates = ($apartment_pq->query('.about-ad-info__date')) ? $apartment_pq->query('.about-ad-info__date') : [];
                        $date_created = NULL;
                        $date_updated = NULL;
                        foreach ($dates as $date) {
                            if (mb_strpos($date->textContent, 'Создано') !== false) {
                                $date_created = trim(str_replace('Создано:', '', $date->textContent));
                                $date_created = cyrDateToLatin($date_created);
                                $date_created = date('Y-m-d H:i:s', strtotime($date_created));
                                if ($date_created === '1970-01-01 00:00:00') {
                                    $date_created = NULL;
                                }
                                continue;
                            }
                            if (mb_strpos($date->textContent, 'Обновлено') !== false) {
                                $date_updated = trim(str_replace('Обновлено:', '', $date->textContent));
                                $date_updated = cyrDateToLatin($date_updated);
                                $date_updated = date('Y-m-d H:i:s', strtotime($date_updated));
                                if ($date_updated === '1970-01-01 00:00:00') {
                                    $date_updated = NULL;
                                }
                                continue;
                            }
                        }


                        $gallery = NULL;
                        $gallery_array = [];
                        $gallery_script_list = $apartment_pq->xpath('//*[@type="application/ld+json"]');
                        foreach ($gallery_script_list as $gallery_script) {
                            $gallery_script_text = $gallery_script->textContent;
                            if (mb_strpos($gallery_script_text, 'image') !== false) {
                                $gallery_script_text = json_decode($gallery_script_text, true);
                                $gallery_script_text = $gallery_script_text['image'];
                                if (is_array($gallery_script_text) && count($gallery_script_text) > 0) {
                                    foreach ($gallery_script_text as $gallery_item) {
                                        $gallery_array[] = $gallery_item;
                                    }
                                }
                            }
                        }
                        if ($phone == NULL) {
                            $phone_script_list = $apartment_pq->xpath('//*[@id="__NEXT_DATA__"]');
                            $phone_script_text = $phone_script_list[0]->textContent;
                            $phone_script_text = json_decode($phone_script_text, true);
                            $phone_script_currentAdId = $phone_script_text['props']['initialState']['feed']['adDetails']['currentAdId'];
                            $phone_script_text = $phone_script_text['props']['initialState']['feed']['adDetails'][$phone_script_currentAdId]['item']['mobile'];
                            // remove all symbols exclude numbers and plus
                            $phone = ($phone_script_text) ? preg_replace('/[^0-9+]/', '', $phone_script_text) : NULL;
                        }

                        $gallery = json_encode($gallery_array);


                        $details = $apartment_pq->query('.details-page__params li');

                        $district_id = NULL;
                        $deposit_kgs = NULL;
                        $deposit_usd = NULL;
                        $rooms = NULL;
                        $floor = NULL;
                        $total_floor = NULL;
                        $house_type = NULL;
                        $sharing = NULL;
                        $animals = NULL;
                        $owner = NULL;
                        $owner_value = 1;
                        $price_kgs = NULL;
                        $price_usd = NULL;
                        $house_area = NULL;
                        $land_area = NULL;
                        $min_rent_month = NULL;
                        $additional = NULL;
                        $additional_array = [];
                        $heating = NULL;
                        $heating_array = [];
                        $improvement_in = NULL;
                        $improvement_in_array = [];
                        $improvement_out = NULL;
                        $improvement_out_array = [];
                        $nearby = NULL;
                        $nearby_array = [];
                        $furniture = NULL;
                        $furniture_array = [];
                        $appliances = NULL;
                        $appliances_array = [];
                        $utility = NULL;
                        $utility_array = [];
                        $condition = NULL;
                        $condition_array = [];
                        $renovation = NULL;
                        $renovation_array = [];

                        foreach ($details as $detail) {
                            $childs = NULL;
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
                            if (mb_strpos($detail->textContent, 'Животные') !== false) {
                                $animals_text = trim(str_replace('Животные:', '', $detail->textContent));
                                if ($animals_text === 'Можно с животными') {
                                    $animals = 1;
                                } elseif ($animals_text === 'Без животных') {
                                    $animals = 0;
                                } else {
                                    $animals = NULL;
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Кто сдает') !== false) {
                                $owner = trim(str_replace('Кто сдает:', '', $detail->textContent));
                                foreach ($owner_types as $owner_type) {
                                    if (mb_strpos($owner, $owner_type['name_ru']) !== false) {
                                        $owner_value = intval($owner_type['id']);
                                        break;
                                    }
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Площадь (м2)') !== false) {
                                $house_area = trim(str_replace('Площадь (м2):', '', $detail->textContent));
                                $house_area = preg_replace('/[^0-9]/', '', $house_area);
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Площадь участка (соток)') !== false) {
                                $land_area = trim(str_replace('Площадь участка (соток):', '', $detail->textContent));
                                $land_area = preg_replace('/[^0-9]/', '', $land_area);
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'На срок') !== false) {
                                $min_rent_month = trim(str_replace('На срок:', '', $detail->textContent));
                                switch (true) {
                                    case (mb_strpos($min_rent_month, 'от 1 мес') !== false):
                                        $min_rent_month = 1;
                                        break;
                                    case (mb_strpos($min_rent_month, 'от 3 мес') !== false):
                                        $min_rent_month = 3;
                                        break;
                                    case (mb_strpos($min_rent_month, 'от 6 мес') !== false):
                                        $min_rent_month = 6;
                                        break;
                                    case (mb_strpos($min_rent_month, 'от 1 год') !== false):
                                        $min_rent_month = 12;
                                        break;
                                    default:
                                        $min_rent_month = NULL;
                                        break;
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Дополнительно') !== false) {
                                $childs = $detail->childNodes;
                                foreach ($childs as $child) {
                                    if ($child->nodeName === 'a') {
                                        $additional_array[] = $child->textContent;
                                    }
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Отопление') !== false) {
                                $childs = $detail->childNodes;
                                foreach ($childs as $child) {
                                    if ($child->nodeName === 'a') {
                                        $heating_array[] = $child->textContent;
                                    }
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Удобства в квартире') !== false) {
                                $childs = $detail->childNodes;
                                foreach ($childs as $child) {
                                    if ($child->nodeName === 'a') {
                                        $improvement_in_array[] = $child->textContent;
                                    }
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Благоустройство дома') !== false) {
                                $childs = $detail->childNodes;
                                foreach ($childs as $child) {
                                    if ($child->nodeName === 'a') {
                                        $improvement_out_array[] = $child->textContent;
                                    }
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'В шаговой доступности') !== false) {
                                $childs = $detail->childNodes;
                                foreach ($childs as $child) {
                                    if ($child->nodeName === 'a') {
                                        $nearby_array[] = $child->textContent;
                                    }
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Мебель') !== false) {
                                $childs = $detail->childNodes;
                                foreach ($childs as $child) {
                                    if ($child->nodeName === 'a') {
                                        $furniture_array[] = $child->textContent;
                                    }
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Бытовая техника') !== false) {
                                $childs = $detail->childNodes;
                                foreach ($childs as $child) {
                                    if ($child->nodeName === 'a') {
                                        $appliances_array[] = $child->textContent;
                                    }
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Коммуникации') !== false) {
                                $childs = $detail->childNodes;
                                foreach ($childs as $child) {
                                    if ($child->nodeName === 'a') {
                                        $utility_array[] = $child->textContent;
                                    }
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Состояние') !== false) {
                                $childs = $detail->childNodes;
                                foreach ($childs as $child) {
                                    if ($child->nodeName === 'a') {
                                        $condition_array[] = $child->textContent;
                                    }
                                }
                                continue;
                            }
                            if (mb_strpos($detail->textContent, 'Ремонт') !== false) {
                                $childs = $detail->childNodes;
                                foreach ($childs as $child) {
                                    if ($child->nodeName === 'a') {
                                        $renovation_array[] = $child->textContent;
                                    }
                                }
                                continue;
                            }
                        }

                        if (!empty($additional_array)) {
                            $additional = getAmenitiesJson($additional_array);
                        }

                        if (!empty($heating_array)) {
                            $heating = getAmenitiesJson($heating_array);
                        }

                        if (!empty($improvement_in_array)) {
                            $improvement_in = getAmenitiesJson($improvement_in_array);
                        }

                        if (!empty($improvement_out_array)) {
                            $improvement_out = getAmenitiesJson($improvement_out_array);
                        }

                        if (!empty($nearby_array)) {
                            $nearby = getAmenitiesJson($nearby_array);
                        }

                        if (!empty($furniture_array)) {
                            $furniture = getAmenitiesJson($furniture_array);
                        }

                        if (!empty($appliances_array)) {
                            $appliances = getAmenitiesJson($appliances_array);
                        }

                        if (!empty($utility_array)) {
                            $utility = getAmenitiesJson($utility_array);
                        }

                        if (!empty($condition_array)) {
                            $condition = getAmenitiesJson($condition_array);
                        }

                        if (!empty($renovation_array)) {
                            $renovation = getAmenitiesJson($renovation_array);
                        }

                        if ($apartment_pq->query('.price')->length) {
                            $price_kgs_text = $apartment_pq->query('.price')[0]->textContent;
                            if (preg_replace('/[^0-9]/', '', trim(str_replace('KGS', '', $price_kgs_text))) !== '') {
                                $price_kgs = intval(preg_replace('/[^0-9]/', '', trim(str_replace('KGS', '', $price_kgs_text))));
                                $price_usd = round($price_kgs / $usd_rate);
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
                            $user_sql = "SELECT * FROM $table_user WHERE chat_id = '$chat_id'";
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
                            $user_preference_property = isset($user['preference_property']) ? $user['preference_property'] : NULL;
                            $user_preference_sharing = isset($user['preference_sharing']) ? $user['preference_sharing'] : NULL;
                            $user_preference_owner = isset($user['preference_owner']) ? $user['preference_owner'] : NULL;

                            if ($user_price_min === NULL && $user_price_max === NULL && $user_rooms_min === NULL && $user_rooms_max === NULL && $user_preference_city === NULL && $user_preference_district === NULL) {
                                continue;
                            }

                            if ($user_price_currency === 'USD') {
                                if (($user_price_max !== NULL && $price_usd > $user_price_max) || ($user_price_max !== NULL && $price_usd === NULL)) {
                                    unset($chat_ids_to_send[$key]);
                                }
                                if (($user_price_min !== NULL && $price_usd < $user_price_min) || ($user_price_min !== NULL && $price_usd === NULL)) {
                                    unset($chat_ids_to_send[$key]);
                                }
                            }
                            if ($user_price_currency === 'KGS') {
                                if (($user_price_max !== NULL && $price_kgs > $user_price_max) || ($user_price_max !== NULL && $price_kgs === NULL)) {
                                    unset($chat_ids_to_send[$key]);
                                }
                                if (($user_price_min !== NULL && $price_kgs < $user_price_min) || ($user_price_min !== NULL && $price_kgs === NULL)) {
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

                            if ($user_preference_property !== NULL && $user_preference_property !== $property_type_id) {
                                unset($chat_ids_to_send[$key]);
                            }

                            if ($user_preference_sharing !== NULL) {
                                if (intval($user_preference_sharing) === 1 && $sharing === 0) {
                                    unset($chat_ids_to_send[$key]);
                                }
                            }

                            if ($user_preference_owner !== NULL && $owner !== NULL) {
                                if (intval($owner_value) < intval($user_preference_owner)) {
                                    unset($chat_ids_to_send[$key]);
                                }
                            }
                        }

                        $chat_ids_to_send = array_values($chat_ids_to_send);
                        $done = count($chat_ids_to_send) === 0 ? 1 : NULL;

                        $data = [
                            'property_type' => $property_type_id,
                            'city' => $city_id,
                            'title' => mysqli_real_escape_string($conn, $link->textContent),
                            'link' => $apartment_link,
                            'chat_ids_to_send' => json_encode($chat_ids_to_send),
                        ];

                        if ($gallery !== NULL) {
                            $data['gallery'] = $gallery;
                        }
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
                            $data['owner'] = $owner_value;
                        }
                        if ($owner_name !== NULL) {
                            $data['owner_name'] = mysqli_real_escape_string($conn, $owner_name);
                        }
                        if ($phone !== NULL) {
                            $data['phone'] = mysqli_real_escape_string($conn, $phone);
                        }
                        if ($rooms !== NULL) {
                            $data['rooms'] = $rooms;
                        } elseif ($property_type_slug === 'room') {
                            $data['rooms'] = 1;
                        }
                        if ($floor !== NULL) {
                            $data['floor'] = mysqli_real_escape_string($conn, $floor);
                        }
                        if ($total_floor !== NULL) {
                            $data['total_floor'] = mysqli_real_escape_string($conn, $total_floor);
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
                        if ($additional !== NULL) {
                            $data['additional'] = $additional;
                        }
                        if ($heating !== NULL) {
                            $data['heating'] = $heating;
                        }
                        if ($improvement_in !== NULL) {
                            $data['improvement_in'] = $improvement_in;
                        }
                        if ($improvement_out !== NULL) {
                            $data['improvement_out'] = $improvement_out;
                        }
                        if ($nearby !== NULL) {
                            $data['nearby'] = $nearby;
                        }
                        if ($furniture !== NULL) {
                            $data['furniture'] = $furniture;
                        }
                        if ($appliances !== NULL) {
                            $data['appliances'] = $appliances;
                        }
                        if ($utility !== NULL) {
                            $data['utility'] = $utility;
                        }
                        if ($house_area !== NULL) {
                            $data['house_area'] = $house_area;
                        }
                        if ($land_area !== NULL) {
                            $data['land_area'] = $land_area;
                        }
                        if ($min_rent_month !== NULL) {
                            $data['min_rent_month'] = $min_rent_month;
                        }

                        $title_ru_array = [];
                        $title_en_array = [];


                        $title_ru_array[] = $property_type['icon'];
                        $title_en_array[] = $property_type['icon'];
                        $title_ru_array[] = $city['city_name_ru'];
                        $title_en_array[] = $city['city_name_en'];
                        if ($rooms !== NULL) {
                            $title_ru_array[] = $rooms . '-ком.' . ' ' . mb_strtolower($property_type['name_ru']);
                            $title_en_array[] = $rooms . ' room ' . mb_strtolower($property_type['name_en']);
                        } else {
                            $title_ru_array[] = $property_type['name_ru'];
                            $title_en_array[] = $property_type['name_en'];
                        }
                        if ($house_area !== NULL) {
                            $title_ru_array[] = ' (' . $house_area . ' м²)';
                            $title_en_array[] = ' (' . $house_area . ' m²)';
                        }
                        if ($price_kgs !== NULL && $price_usd !== NULL) {
                            $price_kgs_formatted = $formatter_kgs->formatCurrency($price_kgs, 'KGS');
                            $price_usd_formatted = $formatter_usd->formatCurrency($price_usd, 'USD');
                            $title_ru_array[] = $price_kgs_formatted . ' (' . $price_usd_formatted . ')';
                            $title_en_array[] = $price_kgs_formatted . ' (' . $price_usd_formatted . ')';
                        }
                        $data['title_ru'] = implode(' ', $title_ru_array);
                        $data['title_en'] = implode(' ', $title_en_array);
                        if ($renovation !== NULL) {
                            $renovation_data = json_decode($renovation, true);
                            $renovation_name_array_ru = [];
                            $renovation_name_array_en = [];
                            foreach ($renovation_data as $renovation_item) {
                                $renovation_name = getAmenityById($renovation_item);
                                $renovation_name_array_ru[] = $renovation_name['amenity_name_ru'];
                                $renovation_name_array_en[] = $renovation_name['amenity_name_en'];
                            }

                            if (count($renovation_name_array_ru) > 0) {
                                $data['title_ru'] .= ' | ' . implode(', ', $renovation_name_array_ru);
                            }
                            if (count($renovation_name_array_en) > 0) {
                                $data['title_en'] .= ' | ' . implode(', ', $renovation_name_array_en);
                            }
                        }

                        $description = $apartment_pq->query('.description__wrap')->length ? trim($apartment_pq->query('.description__wrap')[0]->textContent) : NULL;
                        if ($description !== NULL && $description !== '') {
                            $data['description'] = mysqli_real_escape_string($conn, $description);
                            $data['description_ru'] = $data['description'];
                            if ($done !== 1) {
                                $data['description_en'] = translate($data['description'], 'en');
                            }
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
                $rnd_sec = rand(1, 3);
                sleep($rnd_sec);
            }
        }
    }
    file_put_contents($parser_log_file, ' | Total units: ' . $items_total . ' | Added: ' . $items_added . ' | Error: ' . $items_error . ' | ', FILE_APPEND);
} else {
    file_put_contents($parser_log_file, ' | No users found | ', FILE_APPEND);
}
file_put_contents($parser_log_file, ' End [' . date('Y-m-d H:i:s') . ']' . PHP_EOL, FILE_APPEND);



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
    $districtNameEn = slug($districtName, true);
    $districtNameEn = mysqli_real_escape_string($conn, $districtNameEn);
    $districtName = mysqli_real_escape_string($conn, $districtName);
    $sql = "SELECT * FROM $table_district WHERE city_id = '$cityId' AND district_slug = '$districtSlug'";
    $result = mysqli_query($conn, $sql);
    if ($result !== false && mysqli_num_rows($result) > 0) {
        $district = mysqli_fetch_assoc($result);
        $district_id = $district['district_id'];
    } else {
        $sql = "INSERT INTO $table_district (`city_id`, `district_name_en`, `district_name_ru`, `district_name_kg`, `district_slug`) VALUES ('$cityId', '$districtNameEn', '$districtName', '$districtName', '$districtSlug')";
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

function translate($text, $target = 'en')
{
    global $parser_error_log_file;
    global $translate;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_data = MYSQL_TABLE_DATA;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($parser_error_log_file, ' | Translate - connection failed' . PHP_EOL, FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    // Search existing translation
    $sql = "SELECT title_en FROM $table_data WHERE title_ru = '$text' LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result !== false && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['title_en'];
    }


    // Translate text from english to french.
    $result = $translate->translate($text, [
        'target' => $target,
    ]);

    return $result['text'];
}

function getAmenitiesJson($amenities_array)
{
    global $parser_error_log_file;

    if (empty($amenities_array)) {
        return json_encode([]);
    }

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_amenity = MYSQL_TABLE_AMENITY;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($parser_error_log_file, ' | getAmenitiesJson - connection failed' . PHP_EOL, FILE_APPEND);
        throw new Exception("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
    }

    $amenities = [];
    foreach ($amenities_array as $amenity) {
        $amenity_slug = slug($amenity);

        $sql = "SELECT amenity_id FROM $table_amenity WHERE amenity_slug = '$amenity_slug'";
        $result = mysqli_query($conn, $sql);
        if ($result !== false && mysqli_num_rows($result) > 0) {
            $amenity_id = intval(mysqli_fetch_assoc($result)['amenity_id']);
            $amenities[] = $amenity_id;
        } else {
            $amenity_name_ru = $amenity_name_kg = mysqli_real_escape_string($conn, $amenity);
            $amenity_name_en = translate($amenity_name_ru, 'en');
            $sql = "INSERT INTO $table_amenity (`amenity_name_en`, `amenity_name_ru`, `amenity_name_kg`, `amenity_slug`) VALUES ('$amenity_name_en', '$amenity_name_ru', '$amenity_name_kg', '$amenity_slug')";
            if (mysqli_query($conn, $sql)) {
                $amenity_id = mysqli_insert_id($conn);
                $amenities[] = $amenity_id;
            } else {
                file_put_contents($parser_error_log_file, '[' . date('Y-m-d H:i:s') . '] Error insert amenity: ' . $sql . ' | ' . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
            }
        }
    }

    // All values to int
    $amenities = array_map('intval', $amenities);
    $amenities = array_unique($amenities);
    sort($amenities);

    // Close connection
    mysqli_close($conn);

    return json_encode($amenities);
}

function getAmenityById($amenity_id)
{
    global $parser_error_log_file;

    $dbhost = MYSQL_HOST;
    $dbuser = MYSQL_USER;
    $dbpass = MYSQL_PASSWORD;
    $dbname = MYSQL_DB;
    $table_amenity = MYSQL_TABLE_AMENITY;

    // Create connection
    $conn = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
    if (!$conn) {
        file_put_contents($parser_error_log_file, ' | getAmenityById - connection failed' . PHP_EOL, FILE_APPEND);
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

function cyrDateToLatin($date)
{
    $date_array = explode(' ', $date);
    $date_array[1] = mb_strtolower($date_array[1]);
    switch (true) {
        case (mb_strpos($date_array[1], 'янв') === 0):
            $date_array[1] = 'January';
            break;
        case (mb_strpos($date_array[1], 'фев') === 0):
            $date_array[1] = 'February';
            break;
        case (mb_strpos($date_array[1], 'мар') === 0):
            $date_array[1] = 'March';
            break;
        case (mb_strpos($date_array[1], 'апр') === 0):
            $date_array[1] = 'April';
            break;
        case (mb_strpos($date_array[1], 'май') === 0):
            $date_array[1] = 'May';
            break;
        case (mb_strpos($date_array[1], 'июн') === 0):
            $date_array[1] = 'June';
            break;
        case (mb_strpos($date_array[1], 'июл') === 0):
            $date_array[1] = 'July';
            break;
        case (mb_strpos($date_array[1], 'авг') === 0):
            $date_array[1] = 'August';
            break;
        case (mb_strpos($date_array[1], 'сен') === 0):
            $date_array[1] = 'September';
            break;
        case (mb_strpos($date_array[1], 'окт') === 0):
            $date_array[1] = 'October';
            break;
        case (mb_strpos($date_array[1], 'ноя') === 0):
            $date_array[1] = 'November';
            break;
        case (mb_strpos($date_array[1], 'дек') === 0):
            $date_array[1] = 'December';
            break;
    }
    return implode(' ', $date_array);
}

function removeSpecialChars($string)
{
    $result = preg_replace('/[^ a-zа-я\d.]/ui', '', $string);

    return $result;
}
