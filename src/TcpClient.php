<?php

namespace Knuckles\Faktory;

use Clue\Redis\Protocol\Factory as ProtocolFactory;
use Clue\Redis\Protocol\Parser\ParserInterface;

class TcpClient
{
    protected ParserInterface $responseParser;

    /** @var resource|null */
    protected $connection;
    protected string $hostname = 'tcp://dreamatorium.local';
    protected int $port = 7419;
    protected bool $connected = false;
    protected array $workerInfo;

    public function __construct()
    {
        $factory = new ProtocolFactory();
        $this->responseParser = $factory->createResponseParser();
        $this->connection = null;

        $this->workerInfo = [
            "hostname" => gethostname(),
            "wid" => "test-worker-1",
            "pid" => getmypid(),
            "labels" => [],
            "v" => 2
        ];
    }

    public function connect(): bool
    {
        $this->createTcpConnection();
        self::checkOk($this->handshake(), operation: "Handshake");

        return $this->connected = true;
    }

    protected function createTcpConnection()
    {
        $filePointer = fsockopen($this->hostname, $this->port, $errorCode, $errorMessage, timeout: 3);
        if ($filePointer === false) {
            throw new \Exception("Failed to connect to Faktory on {$this->hostname}:{$this->port}: $errorMessage (error code $errorCode)");
        }

        $this->connection = $filePointer;
        stream_set_timeout($this->connection, seconds: 5);
    }

    protected function handshake()
    {
        $this->readHi();
        $this->sendHello();
        return $this->readLine();
    }

    protected function readHi()
    {
        $hi = $this->readLine();
        if (empty($hi)) throw new \Exception("Handshake failed");

        $version = json_decode(str_replace("HI ", "", $hi))->v;
        if (intval($version) > 2) echo "Expected Faktory protocol v2 or lower; found $version";
    }

    protected function sendHello()
    {
        $workerInfo = json_encode($this->workerInfo, JSON_THROW_ON_ERROR);
        $this->send("HELLO", $workerInfo);
    }

    protected function send($command, ...$args): void
    {
        fwrite(
            $this->connection, $command . " " . join(' ', $args) . "\r\n"
        );
    }

    protected function readLine(): mixed
    {
        $received = $this->responseParser->pushIncoming(fgets($this->connection));
        if (empty($received)) return null;

        return $received[0]?->getValueNative();
    }

    private static function checkOk(mixed $result, $operation = "Operation")
    {
        if ($result !== "OK") {
            // todo custom exceptions
            throw new \Exception("$operation failed with response: $result");
        }

        return true;
    }
}
