<?php
require 'vendor/autoload.php';

$json_string = file_get_contents('php://input');
$json_object = json_decode($json_string);

if (empty($json_object)) {
  exit;
}

//db
$url = parse_url(getenv('DATABASE_URL'));
$dsn = sprintf('pgsql:host=%s;dbname=%s', $url['host'], substr($url['path'], 1));

try {
    $db = new PDO($dsn, $url['user'], $url['pass']);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
    exit;
}

foreach ($json_object->events as $event) {
    $userId = $event->source->userId;
    error_log($userId . ' requested');

    $bot = get_bot();
    $res = $bot->getProfile($userId);

    if ($res->isSucceeded()) {
      error_log('profile fetch success.');
    } else {
      exit('profile fetch error.');
    }
    $profile = $res->getJSONDecodedBody();
    error_log(var_export($profile,true));


    if('message' == $event->type){
        if ($event->message->text === 'up') {
          updateProfile($db, $profile);
        }
        api_post_request($event->replyToken, $event->message->text);
    }else if('beacon' == $event->type){
        $result = updateProfile($db, $profile);
        if ($result) {
          api_post_request($event->replyToken, 'BEACONにチェックイン!');
        }
    }
}

function get_bot() {
  $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient(getenv('CHANNEL_TOKEN'));
  $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
  return $bot;
}

function api_post_request($token, $message) {
    $bot = get_bot();
    $bot->replyText($token, $message);
}

function updateProfile($db, $profile) {

  //count rows
  $stmt = $db->prepare("SELECT * FROM LINE_USERS WHERE USERID = :id");
  $stmt->bindParam(':id', $profile['userId'], PDO::PARAM_STR);
  $stmt->execute();
  $cnt = $stmt->rowCount();

  if ($cnt === 0) {
    $stmt = $db->prepare("INSERT INTO LINE_USERS (USERID, NAME, PICTURE) VALUES (:id, :name, :pict)");
    $stmt->bindParam(':id', $profile['userId'], PDO::PARAM_STR);
    $stmt->bindParam(':name', $profile['displayName'], PDO::PARAM_STR);
    $stmt->bindParam(':pict', $profile['pictureUrl'], PDO::PARAM_STR);
    $stmt->execute();
    return true;
  }

  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $ts = $result['ts'];
  error_log("LAST TS:" . $ts);

  if ( (strtotime($ts) + 60 * 60 * 12) > time() ) {
    error_log("no update.");
    return false;
  }

  $stmt = $db->prepare("UPDATE LINE_USERS SET NAME = :name, PICTURE = :pict, TS = now() WHERE USERID = :id");
  $stmt->bindParam(':id', $profile['userId'], PDO::PARAM_STR);
  $stmt->bindParam(':name', $profile['displayName'], PDO::PARAM_STR);
  $stmt->bindParam(':pict', $profile['pictureUrl'], PDO::PARAM_STR);
  $stmt->execute();

  return true;
}
