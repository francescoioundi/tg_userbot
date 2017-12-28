<?php
echo 'Loading settings...'.PHP_EOL;
require('settings.php');
$strings = @json_decode(file_get_contents('strings_'.$settings['language'].'.json'), 1);
if ($settings['multithread'] and function_exists('pcntl_fork') == 0) $settings['multithread'] = 0;
if (!is_array($strings)) {
  if (!file_exists('strings_it.json')) {
    echo 'downloading strings_it.json...'.PHP_EOL;
    file_put_contents('strings_it.json', file_get_contents('https://raw.githubusercontent.com/peppelg/TGUserbot/master/strings_it.json'));
  }
  $strings = json_decode(file_get_contents('strings_it.json'), 1);
}
echo $strings['loading'].PHP_EOL;
require('vendor/autoload.php');
include('functions.php');
if ($settings['multithread']) {
  $m = readline($strings['shitty_multithread_warning']);
  if ($m != 'y') exit;
}
$MadelineProto = new \danog\MadelineProto\API(['app_info' => ['api_id' => 6, 'api_hash' => 'eb06d4abfb49dc3eeb1aeb98ae0f581e', 'lang_code' => $settings['language']], 'logger' => ['logger' => 0], 'updates' => ['handle_old_updates' => 0]]);
echo $strings['loaded'].PHP_EOL;
set_error_handler(
  function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
  }
);
try {
  if (!file_exists($settings['session'])) {
    echo $strings['ask_phone_number'];
    $phoneNumber = fgets(STDIN);
    $sentCode = $MadelineProto->phone_login($phoneNumber);
    echo $strings['ask_login_code'];
    $code = fgets(STDIN, (isset($sentCode['type']['length']) ? $sentCode['type']['length'] : 5) + 1);
    $authorization = $MadelineProto->complete_phone_login($code);
    if ($authorization['_'] === 'account.password') {
      $authorization = $MadelineProto->complete_2fa_login(readline($strings['ask_2fa_password']));
    }
    if ($authorization['_'] === 'account.needSignup') {
      echo $strings['ask_name'];
      $name = fgets(STDIN);
      if ($name == "") {
        $name = 'TGUserbot';
      }
      $authorization = $MadelineProto->complete_signup($name, '');
    }
    $MadelineProto->serialize($settings['session']);
  } else {
    $MadelineProto = \danog\MadelineProto\Serialization::deserialize($settings['session']);
  }
  echo $strings['session_loaded'].PHP_EOL;
  $offset = 0;
  while (true) {
    $updates = $MadelineProto->API->get_updates(['offset' => $offset, 'limit' => 50, 'timeout' => 0]);
    foreach ($updates as $update) {
      $offset = $update['update_id'] + 1;
      if (isset($update['update']['message']['message'])) $msg = $update['update']['message']['message'];
      if (isset($update['update']['message']['to_id']['channel_id'])) {
        $chatID = '-100'.$update['update']['message']['to_id']['channel_id'];
        $type = $strings['type_megagroup'];
      }
      if (isset($update['update']['message']['to_id']['chat_id'])) {
        $chatID = '-'.$update['update']['message']['to_id']['chat_id'];
        $type = $strings['type_group'];
      }
      if (isset($update['update']['message']['from_id'])) $userID = $update['update']['message']['from_id'];
      if (isset($update['update']['message']['to_id']['user_id'])) {
        $chatID = $update['update']['message']['from_id'];
        $type = $strings['type_pvtchat'];
      }
      if (isset($update['update']['message']['id'])) $msgid = $update['update']['message']['id'];
      if (isset($msg) and $msg) {
        if ($settings['readmsg'] and isset($type) and isset($msgid) and isset($chatID) and $type == $strings['type_pvtchat'] and $msgid and $chatID) $MadelineProto->messages->readHistory(['peer' => $chatID, 'max_id' => $msgid]);
        if (isset($msg) and isset($chatID) and isset($type) and $msg and $chatID and $type) echo $chatID.' ('.$type.') >>> '.$msg.PHP_EOL;
      }
      if ($settings['multithread']) {
        if (!isset($tmsgid)) $tmsgid = 1;
        if (isset($msg) and isset($chatID) and isset($userID) and isset($msgid) and isset($tmsgid) and $msg and $chatID and $userID and $msgid != $tmsgid) {
          $pid = pcntl_fork();
          if ($pid == -1) {
            die('could not fork');
          } elseif ($pid) {
          } else {
            $MadelineProto->reset_session(1, 1);
            require('bot.php');
            if (isset($msg)) unset($msg);
            if (isset($chatID)) unset($chatID);
            if (isset($userID)) unset($userID);
            if (isset($type)) unset($type);
            if (isset($msgid)) unset($msgid);
          }
        } elseif(isset($tmsgid) and isset($msgid) and $tmsgid != $msgid) {
          require('bot.php');
        }
      } else {
        require('bot.php');
      }
      if ($settings['multithread'] and isset($msgid) and $msgid) $tmsgid = $msgid;
      if (isset($msg)) unset($msg);
      if (isset($chatID)) unset($chatID);
      if (isset($userID)) unset($userID);
      if (isset($type)) unset($type);
      if (isset($msgid)) unset($msgid);
      $MadelineProto->serialize($settings['session']);
    }
  }
} catch(Exception $e) {
  echo $strings['error'].$e->getMessage().PHP_EOL;
  if (isset($chatID) and $settings['senderrors']) {
    try {
      $MadelineProto->messages->sendMessage(['peer' => $chatID, 'message' => '<b>'.$strings['error'].$e->getMessage().'</b>', 'parse_mode' => 'HTML']);
    } catch(Exception $e) { }
  }
}
