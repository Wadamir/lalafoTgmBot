<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use PhpQuery\PhpQuery;
use GuzzleHttp\Client;

require_once __DIR__ . '/config.php';
$log_dir = __DIR__ . '/logs';

file_put_contents($log_dir . '/parser.log', '[' . date('Y-m-d H:i:s') . '] Start parsing', FILE_APPEND);

$added_items = 0;
$updated_items = 0;
$err_items = 0;

$dbhost = MYSQL_HOST;
$dbuser = MYSQL_USER;
$dbpass = MYSQL_PASSWORD;
$dbname = MYSQL_DB;
$table_users = MYSQL_TABLE_USERS;
$table_city = MYSQL_TABLE_CITY;
$table_district = MYSQL_TABLE_DISTRICT;
$table_data = MYSQL_TABLE_DATA;

// Create connection
$conn = mysqli_connect($dbhost, $dbuser, $dbpass);
if (!$conn) {
    file_put_contents($log_dir . '/parser.log', 'Connection failed' . PHP_EOL, FILE_APPEND);
    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
}

$guzzle = new Client();

$apartments = [];

for ($i = 1; $i < 2; $i++) {
    $response = $guzzle->get('https://lalafo.kg/bishkek/kvartiry/arenda-kvartir/dolgosrochnaya-arenda-kvartir/whole-room' . '?page=' . $i);
    $content = $response->getBody()->getContents();

    $pq = new PhpQuery;
    $pq->load_str($content);

    //return a list of 2 components
    $links = $pq->query('.adTile-title');

    foreach ($links as $link) {
        try {
            echo 'https://lalafo.kg' . $link->getAttribute('href') . '<br>';
            $apartment_response = $guzzle->get('https://lalafo.kg' . $link->getAttribute('href'));
            $apartment_content = $apartment_response->getBody()->getContents();
            file_put_contents('apartment.html', $apartment_content);

            $apartment_pq = new PhpQuery;
            $apartment_pq->load_str($apartment_content);

            $price = ($apartment_pq->query('.price')->length) ? $apartment_pq->query('.price')[0]->textContent : 'n/d';
            $price = str_replace('KGS', '', $price);
            $currency = ($apartment_pq->query('.currency')->length) ? $apartment_pq->query('.currency')[0]->textContent : '';

            $phone = ($apartment_pq->query('.call-button a')->length) ? $apartment_pq->query('.call-button a')[0]->getAttribute('href') : 'n/d';
            $phone = str_replace('tel:', '', $phone);
            $owner_name = ($apartment_pq->query('.userName-text')->length) ? $apartment_pq->query('.userName-text')[0]->textContent : 'n/d';

            $dates = ($apartment_pq->query('.about-ad-info__date')) ? $apartment_pq->query('.about-ad-info__date') : [];
            foreach ($dates as $date) {
                if (mb_strpos($date->textContent, 'Обновлено') !== false) {
                    $date_updated = trim(str_replace('Обновлено:', '', $date->textContent));
                    continue;
                }
                if (mb_strpos($date->textContent, 'Создано') !== false) {
                    $date_created = trim(str_replace('Создано:', '', $date->textContent));
                    continue;
                }
            }

            $details = $apartment_pq->query('.details-page__params li');

            foreach ($details as $detail) {
                if (mb_strpos($detail->textContent, 'Район') !== false) {
                    $district = trim(str_replace('Район:', '', $detail->textContent));
                    continue;
                }
                if (mb_strpos($detail->textContent, 'Депозит, сом') !== false) {
                    $deposit = trim(str_replace('Депозит, сом:', '', $detail->textContent));
                    continue;
                }
                if (mb_strpos($detail->textContent, 'Количество комнат') !== false) {
                    $rooms = trim(str_replace('Количество комнат:', '', $detail->textContent));
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
                    $sharing = trim(str_replace('Подселение:', '', $detail->textContent));
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
                    continue;
                }
            }

            $data = [
                'title' => mysqli_real_escape_string($conn, $link->textContent),
                'link' => 'https://lalafo.kg' . $link->getAttribute('href'),
                'created_at' => isset($date_created) ? mysqli_real_escape_string($conn, $date_created) : 'n/d',
                'updated_at' => isset($date_updated) ? mysqli_real_escape_string($conn, $date_updated) : 'n/d',
                'price' => mysqli_real_escape_string($conn, $price . ' ' . $currency),
                'deposit' => isset($deposit) ? mysqli_real_escape_string($conn, $deposit) : 'n/d',
                'owner' => isset($owner) ? mysqli_real_escape_string($conn, $owner) : 'n/d',
                'owner_name' => mysqli_real_escape_string($conn, $owner_name),
                'phone' => mysqli_real_escape_string($conn, $phone),
                'district' => isset($district) ? mysqli_real_escape_string($conn, $district) : 'n/d',
                'rooms' => isset($rooms) ? mysqli_real_escape_string($conn, $rooms) : 'n/d',
                'floor' => (isset($floor) && isset($total_floor)) ? mysqli_real_escape_string($conn, $floor . ' / ' . $total_floor) : 'n/d',
                'house_type' => isset($house_type) ? mysqli_real_escape_string($conn, $house_type) : 'n/d',
                'sharing' => isset($sharing) ? mysqli_real_escape_string($conn, $sharing) : 'n/d',
                'furniture' => isset($furniture) ? mysqli_real_escape_string($conn, $furniture) : 'n/d',
                'appliances' => isset($appliances) ? mysqli_real_escape_string($conn, $appliances) : 'n/d',
                'condition' => isset($condition) ? mysqli_real_escape_string($conn, $condition) : 'n/d',
                'renovation' => isset($renovation) ? mysqli_real_escape_string($conn, $renovation) : 'n/d',
                'animals' => isset($animals) ? mysqli_real_escape_string($conn, $animals) : 'n/d',
            ];

            $apartments[] = $data;

            // check if apartment exists
            $sql = "SELECT * FROM $table_data WHERE link = '" . $data['link'] . "'";
            if (!mysqli_select_db($conn, $dbname)) {
                file_put_contents($log_dir . '/parser.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
                die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
            }
            $result = mysqli_query($conn, $sql);

            if (count($result->fetch_all()) > 0) {
                continue;
            } else {
                $update_sql = "INSERT INTO $table_data (`title`, `link`, `created_at`, `updated_at`, `price`, `deposit`, `owner`, `owner_name`, `phone`, `district`, `rooms`, `floor`, `house_type`, `sharing`, `furniture`, `condition`, `renovation`, `animals`) VALUES ('" . $data['title'] . "', '" . $data['link'] . "', '" . $data['created_at'] . "', '" . $data['updated_at'] . "', '" . $data['price'] . "', '" . $data['deposit'] . "', '" . $data['owner'] . "', '" . $data['owner_name'] . "', '" . $data['phone'] . "', '" . $data['district'] . "', '" . $data['rooms'] . "', '" . $data['floor'] . "', '" . $data['house_type'] . "', '" . $data['sharing'] . "', '" . $data['furniture'] . "', '" . $data['condition'] . "', '" . $data['renovation'] . "', '" . $data['animals'] . "')";
                if (!mysqli_select_db($conn, $dbname)) {
                    file_put_contents($log_dir . '/parser.log', 'Database NOT SELECTED' . PHP_EOL, FILE_APPEND);
                    die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
                }
                if (mysqli_query($conn, $update_sql)) {
                    file_put_contents($log_dir . '/parser.log', "New record created successfully" . PHP_EOL, FILE_APPEND);
                } else {
                    file_put_contents($log_dir . '/parser.log', "Error: " . $update_sql . "<br>" . mysqli_error($conn) . PHP_EOL, FILE_APPEND);
                    die("Error: " . $update_sql . "<br>" . mysqli_error($conn) . PHP_EOL);
                }
            }
        } catch (\Exception $e) {
            print_r($e->getMessage());
            continue;
        }
    }
    // set timeout
    $rnd_sec = rand(1, 7);
    sleep($rnd_sec);
}

echo "<br><br>";
//return the first element
var_dump($pq->query('.yandex_rt')[0]);
/*
echo "<br><br>";
//return a list of 1 element
var_dump($pq->query('.c1.c3'));

echo "<br><br>";
//return the first element
var_dump($pq->query('.c1.c3')[0]);

echo "<br><br>";
//return a ist of 3 elements element
var_dump($pq->query('ul li'));

echo "<br><br>";
//return a ist of 1 elements element
var_dump($pq->query('ul'));

echo "<br><br>";
//return a ist of 3 elements element
//relative call
//first lookup for ul
//and the from that ul seeks li
$x = $pq->query('ul');
var_dump($x = $pq->query('li', $x[0]));

echo "<br><br>";
//return a ist of 1 elements element
var_dump($pq->query('#myid'));

echo "<br><br>";
//return the first element
var_dump($pq->query('#myid')[0]);
echo "<br>~~textContent ~~> ";
var_dump($pq->query('#myid')[0]->textContent);

echo "<br><br>";
//print the transormation fron jquery syntax
//to xpath syntax
echo $pq->j_to_x('.c1.c3');
echo $pq->j_to_x('#myid');

echo "<br><br>";
//return a list of 1 element
//from xpath syntax
var_dump($pq->xpath('//*[@id="myid"]'));

echo "<br><br>";
//return the first element
//from xpath syntax
var_dump($pq->xpath('//*[@id="myid"]')[0]);

echo '<br><br>';
var_dump($pq->innerHTML($pq->query('.Opin')[0]));

echo '<br><br>';
var_dump($pq->outerHTML($pq->query('.dav-k')[0]));
*/