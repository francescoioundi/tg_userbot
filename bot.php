<?php
if (isset($update['update']['message']['out']) and $update['update']['message']['out'] == true) return 0;
if (!isset($msg)) return 0;
if ($msg == '/start') {
  sm($chatID, 'Ciao!');
}
