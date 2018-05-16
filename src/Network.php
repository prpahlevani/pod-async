<?php

namespace Chat;

use SebastianBergmann\Exporter\Exception;
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

class Network
{
//    protected $connection;
    public $address = "ws://172.16.110.235:8003/ws";
    public $ws_connection;
    private $lastSentMessageTime = 0;
    public $lastReceivedMessageTime = 0;
    public $output_thread;
    public $eventCallback;

    public $waitForSocketToConnectTimeoutId;
    public $wsConnectionWaitTime = 500;
    public $connectionCheckTimeout = 70000;
    public $connectionCheckTimeoutThreshold = 400;
    public $pingTimeCheck;

    public function __construct($params)
    {
        foreach ($params as $key => $value) {
            if (property_exists($this, $key))
                $this->$key = $value;
        }

        $this->pingTimeCheck = $this->connectionCheckTimeout - $this->connectionCheckTimeoutThreshold;

//        $this->init();
    }

    public function init()
    {
        $this->ws_connection = new AsyncTcpConnection($this->address);
        $this->worker();
    }

    public function worker($callback = null)
    {
        if (!$callback) {
            $callback = function () {
                $this->connect();
            };
        }

        $worker = new Worker();
        $worker->onWorkerStart = $callback;

        $worker->run();
//        Worker::runAll();

    }

    public function connect()
    {
        // Websocket protocol for client.
        try {
            $ws_connection = $this->ws_connection;

            $ws_connection->onConnect = function ($connection) use ($ws_connection) {
                $this->eventCallback["open"]();
            };

            $ws_connection->onMessage = function ($connection, $data) {
                $decoded_msg = json_decode($data);
                $this->eventCallback["message"]($decoded_msg);
                $this->lastSentMessageTime = time();
            };

            $ws_connection->onError = function ($connection, $code, $msg) {
                $this->eventCallback["error"]($msg);
            };

            $ws_connection->onClose = function ($connection) {
                //todo
                $this->eventCallback["close"]($connection);
                echo "connection closed\n";
            };

            $ws_connection->connect();

        } catch (Exception $exception) {
            echo 'an error occurred: ' . $exception . "\n";
        }
    }

    public function sendData($content)
    {
        $this->lastSentMessageTime = time();
        $this->ws_connection->send($content);
    }

    public function emit($message)
    {
        $this->sendData($message);
    }

    public function ping()
    {
        $input_array = json_encode(['type' => 0]);
        $this->sendData($input_array);
    }

    public function on($messageName, $callback)
    {
        $this->eventCallback[$messageName] = $callback;
    }

    public function getLastSentMessageTime()
    {
        return $this->lastSentMessageTime;
    }

    public function setLastSentMessageTime($time)
    {
        $this->lastSentMessageTime = $time;
    }

    public function getLastReceivedMessageTime()
    {
        return $this->lastReceivedMessageTime;
    }

    public function setLastReceivedMessageTime($time)
    {
        $this->lastReceivedMessageTime = $time;
    }

    public function close()
    {
        $this->ws_connection->close();
    }

    public function getSocketStatus()
    {
        return $this->ws_connection->getStatus();
    }

}
