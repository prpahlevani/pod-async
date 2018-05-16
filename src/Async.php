<?php

namespace Chat;


use SebastianBergmann\Exporter\Exception;

class Async
{
//      eventCallbacks = {
//    connect: {},
//    disconnect: {},
//    reconnect: {},
//    message: {},
//    asyncReady: {}
//},
//      ackCallback = {
//    /**
//     * MessageId
//     */
//},
    private $asyncStateType = [
        'INITIAL' => 0, // The connection is initialized, but isn't open yet.
        'CONNECTING' => 1, // The connection is in the process of opening
        'ESTABLISHED' => 2, // The connection is open and ready to communicate.
        'CLOSING' => 4, // The connection is in the process of closing.
        'CLOSED' => 8 //The connection is closed or couldn't be opened.
    ];

    private $allParameters;

    private $appId = "POD-Chat";
    private $deviceId;
    private $socket;
    private $isSocketOpen = false;
    private $isDeviceRegister = false;
    private $isServerRegister = false;
    private $connectionState = false;
    private $asyncState = 0;
    private $pushSendDataQueue = [];
    private $oldPeerId;
    private $peerId;
    private $messageTtl = 5000;
    private $serverName = "oauth-wire";
    private $connectionRetryInterval = 5000;
    private $reconnectOnClose = true;
    private $retryStep = 1;

    private $consoleLogging = false;
//params.consoleLogging.onFunction

    private $onReceiveLogging = false;
//params.consoleLogging.onMessageReceive

    private $onSendLogging = false;

// params.consoleLogging.onMessageSend

    public function __construct($params)
    {
        $this->allParameters = $params;
        foreach ($params as $key => $value) {
            if (property_exists($this, $key))
                $this->$key = $value;
        }
//        $this->initSocket($params);
    }

    public function initSocket($params)
    {
        $this->socket = $socket = new Network($params);//Socket

        $socket->on("open", function () {
            $this->isSocketOpen = true;
        });

        $socket->on("message", function ($message) {
            echo json_encode($message) . "\n";
            $this->handleSocketMessage($message);
        });

        $socket->on("close", function () {
            $this->close();
            if ($this->reconnectOnClose) {
                //todo : reconnect timer & log
//                while (!$this->isSocketOpen && $this->retryStep < 65) {
//                    $this->reconnectSocket();
//                }
//                if (!$this->isSocketOpen) {
//                    throw new Exception("Can Not Open Socket!");
//                }
                $this->socket->connect();
            } else {
                throw new Exception("Socket Closed!");
            }

//            $this->socket->connect();
        });

        $socket->on("error", function ($error) {
            echo $error;
        });

        $socket->init();
    }

    public function handleSocketMessage($message)
    {
        $this->socket->setLastReceivedMessageTime(time());
        $this->setCloseAlarm(); // set time out for socket connection. if any message received for a while, connection will be closed

        $type = $message->type;
        switch ($type) {

            case 0 :
                if (!$this->isDeviceRegister && $message->content) { //todo: check the if condition
                    $this->deviceId = $message->content;
                    $this->registerDevice($this->deviceId);
                } else {
//                    echo "ping: {$message}\n";
                }
                break;

            case 1 :
                $this->handleServerRegisterMessage($message);

                $this->pingProcess();
                echo "end\n";

                break;
            case 2 :
                $this->handleDeviceRegisterMessage($message->content);

                break;

            case 3 :
//                var_dump('this is response from the other client: ' . $data);
//                die();
//
//                $type = 3;
//                $content = json_encode(['receivers' => [2335703], 'content' => 'salam']);
//                $input_array = json_encode(['type' => $type, 'content' => $content]);
                break;
            case 4 :
            case 5 :
//                var_dump('response from dirana with ack:' . $message);
//                die();
                break;
            case 6 :
        }

    }

    public function registerDevice($deviceId = '', $isRetry = false)
    {
//        echo("\n:::::::::::::: Registering Device ...\n");
        $type = 2;
        $content = ['deviceId' => $deviceId, 'appId' => $this->appId];

        if (!empty($this->peerId)) {
            $content['refresh'] = true;
        } else {
            if (!$isRetry) {
                $content['renew'] = true;
            }
        }
        $input_array = json_encode(['type' => $type, 'content' => json_encode($content)]);
        $this->socket->emit($input_array);; // sendMsg = emit

    }

    public function handleServerRegisterMessage($message)
    {
        if ($message->senderName && $message->senderName == $this->serverName) {
            $this->isServerRegister = true;
            $this->asyncState = $this->asyncStateType['ESTABLISHED'];
            $this->pushSendDataQueue = [];
            // pushSendDataQueueHandler();

            echo("\n... Ready for chat ...\n");
        } else {
            $this->registerServer();
        }

    }

    public function handleDeviceRegisterMessage($recievedPeerId)
    {
        if ($this->isDeviceRegister) {
            return;
        }

        $this->isDeviceRegister = true;
        $this->peerId = $recievedPeerId;

        if ($this->isServerRegister && $this->peerId === $this->oldPeerId) {
            $this->asyncState = $this->asyncStateType['ESTABLISHED'];
            $this->pushSendDataQueueHandler();
        } else {
            $this->registerServer();
        }

    }

    public function registerServer()
    {
        $type = 1;
        $content = json_encode(['name' => $this->serverName]);
        $input_array = json_encode(['type' => $type, 'content' => $content]);
        $this->socket->emit($input_array);
    }

    public function getPeerId()
    {
        return $this->peerId;
    }

    public function getAsyncState()
    {
        return $this->asyncState;
    }

    public function pushSendDataQueueHandler()
    {
        while (count($this->pushSendDataQueue) > 0 && $this->asyncState ==$this->asyncStateType['ESTABLISHED']) {
            $message = array_pop($this->pushSendDataQueue);
            $this->pushSendData($message);
        }
    }

    public function pushSendData($message)
    {
//        $this->logger();
        if ($this->asyncState == $this->asyncStateType['ESTABLISHED']) {
            $this->socket->emit($message);
        } else {
            array_push($this->pushSendDataQueue, $message);
        }
    }

    public function close()
    {
        $this->asyncState = $this->asyncStateType['CLOSED'];
        $this->isDeviceRegister = false;
        $this->isSocketOpen = false;
        $this->socket->close();
    }

    public function logout()
    {
        $this->oldPeerId = $this->peerId;
        $this->peerId = '';
        $this->isServerRegister = false;
        $this->isDeviceRegister = false;
        $this->isSocketOpen = false;
        $this->pushSendDataQueue = [];
        $this->socket->close();
    }

    public function reconnectSocket() //todo
    {
        $this->oldPeerId = $this->peerId;
        $this->close();
        $this->retryStep *= 2;

        $this->socket->connect();
        sleep($this->retryStep);

//        pcntl_signal(SIGALRM, array(&$this, 'reconnect'));
//        pcntl_alarm($this->retryStep);
    }

    public function reconnect()
    {
//        $this->socket->initSocket($this->allParameters);
        $this->socket->connect();
    }

    protected function setCloseAlarm()
    {
        pcntl_signal(SIGALRM, array(&$this, 'close'));
        pcntl_alarm(110);
    }

    protected function pingProcess()
    {
        $pid = pcntl_fork();
        if (!$pid) {
            while (true) {
                if (time() - $this->socket->getLastSentMessageTime() >= $this->socket->pingTimeCheck / 1000) {
                    echo 'ping...' . "\n";
                    $this->socket->ping();
                }

            }
        }
    }


}