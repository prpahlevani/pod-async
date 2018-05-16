<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Chat\Network;
use Chat\Async;


$params = [
    'socketAddress' => "ws://172.16.110.235:8003/ws",
    'serverName' => "oauth-wire",
    'wsConnectionWaitTime' => 500,
    'connectionRetryInterval' => 5000,
    'connectionCheckTimeout' => 30000,
    'connectionCheckTimeoutThreshold' => 10000,
    'messageTtl' => 5000,
    'reconnectOnClose' => true,
];
//  consoleLogging: {
//    onFunction: true;
//    onMessageReceive: true;
//    onMessageSend: true;
//  }
//$chat = new Network($params);

$asyncClient = new Async($params);

$asyncClient->initSocket($params);

