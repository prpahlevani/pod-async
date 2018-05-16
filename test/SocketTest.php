<?php
declare(strict_types=1);

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';
use Chat\Async;
use Chat\Network;
use SebastianBergmann\Exporter\Exception;
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;


class SocketTest extends PHPUnit_Framework_TestCase
{
    private $async;
    private $socket;
    private $params;

    private $ws_connection;

    function setUp()
    {
        $this->params = [
            'socketAddress' => "ws://172.16.110.235:8003/ws",
            'serverName' => "oauth-wire",
            'reconnectOnClose' => false
        ];

        $this->ws_connection = new AsyncTcpConnection($this->params['socketAddress']);
    }


    public function tearDown()
    {
        $this->ws_connection->close();
    }


    public function testCanConnect()
    {

        $worker = new Worker();

        $worker->onWorkerStart = function () {

            $ws_connection = $this->ws_connection;
//            try {
//                $ws_connection->onConnect = function ($connection) use ($ws_connection) {
//
//                };
//            } catch (Exception $exception) {
//                echo 'an error occurred: ' . $exception . "\n";
//            }

            $ws_connection->connect();

            $this->assertEquals(1, $ws_connection->getStatus(),'done baby');


        };

        $worker->run();
    }

}