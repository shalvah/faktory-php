<?php

namespace Knuckles\Faktory;

use Clue\Redis\Protocol\Factory as ProtocolFactory;
use Clue\Redis\Protocol\Parser\ParserInterface;
use Knuckles\Faktory\Problems\CouldntConnect;
use Knuckles\Faktory\Problems\UnexpectedResponse;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class TcpClient implements LoggerAwareInterface
{
    protected ParserInterface $responseParser;

    /** @var resource|null */
    protected $connection;
    protected string $hostname = 'tcp://dreamatorium.local';
    protected int $port = 7419;
    protected bool $connected = false;
    protected array $workerInfo;

    protected LoggerInterface $logger;

    public function __construct(
        $logLevel = Level::Info,
        $logDestination = 'php://stderr',
        ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: self::makeLogger($logLevel, $logDestination);

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
        $this->logLevel = $logLevel;
        $this->logDestination = $logDestination;
    }

    protected static function makeLogger(Level $logLevel, string $logDestination): LoggerInterface
    {
        return new Logger(
            name: 'faktory-php',
            handlers: [(new StreamHandler($logDestination, $logLevel))]
        );
    }

    public function connect(): bool
    {
        $this->logger->info("Connecting to Faktory server on $this->hostname");
        $this->createTcpConnection();
        $this->handshake();

        return $this->connected = true;
    }

    protected function createTcpConnection()
    {
        // By default, fsockopen() will emit a warning, return false, and pass error message and code by ref.
        // But if a user has a global error handler registered, it may raise an error instead,
        // so we have to handle both.

        $filePointer = false;
        try {
            $filePointer = fsockopen($this->hostname, $this->port, $errorCode, $errorMessage, timeout: 3);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
        }

        if ($filePointer === false) {
            throw CouldntConnect::to("{$this->hostname}:{$this->port}", $errorMessage, $errorCode);
        }

        $this->connection = $filePointer;
        stream_set_timeout($this->connection, seconds: 2); // Faktory may block for up to 2s on FETCH
    }

    protected function handshake()
    {
        $this->readHi();
        $this->sendHello();
        self::checkOk($this->readLine(), operation: "Handshake");
    }

    protected function readHi()
    {
        $hi = $this->readLine();
        if (empty($hi)) throw UnexpectedResponse::from("Handshake (HI)", $hi);

        $version = json_decode(str_replace("HI ", "", $hi))->v;
        if (intval($version) > 2) {
            $this->logger->warning("Expected Faktory protocol v2 or lower; received $version from the server");;
        }
    }

    protected function sendHello()
    {
        $workerInfo = json_encode($this->workerInfo, JSON_THROW_ON_ERROR);
        $this->send("HELLO", $workerInfo);
    }

    public function push(array $job)
    {
        $this->send("PUSH", json_encode($job, JSON_THROW_ON_ERROR));
        return self::checkOk($this->readLine(), operation: "PUSH");
    }

    public function fetch(string ...$queues)
    {
        $this->send("FETCH", ...$queues);
        // The first line of the response just contains the length of the next line; skip it
        $this->readLine();
        return json_decode($this->readLine(), true, JSON_THROW_ON_ERROR);
    }

    protected function send($command, ...$args): void
    {
        $message = $command . " " . join(' ', $args) . "\r\n";
        $this->logger->debug("Sending: " . $message);
        fwrite($this->connection, $message);
    }

    protected function readLine(): mixed
    {
        $line = fgets($this->connection);
        $this->logger->debug("Received: " . $line);
        $messages = $this->responseParser->pushIncoming($line);
        if (empty($messages)) return null;

        return $messages[0]?->getValueNative();
    }

    private static function checkOk(mixed $result, $operation = "Operation")
    {
        if ($result !== "OK") {
            throw UnexpectedResponse::from($operation, $result);
        }

        return true;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
