<?php
require __DIR__ . '/../../../vendor/autoload.php';

use phpseclib3\Net\SSH2;

$ssh = new SSH2('72.61.117.178');
if (!$ssh->login('root', 'Gnshub112233@')) {
    die('Login Failed');
}

echo $ssh->exec('uptime');

