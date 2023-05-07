<?php

namespace Knuckles\Faktory;

use Clue\Redis\Protocol\Factory as ProtocolFactory;
use Clue\Redis\Protocol\Parser\ParserInterface;
use Knuckles\Faktory\Problems\CouldntConnect;
use Knuckles\Faktory\Problems\UnexpectedResponse;
use Knuckles\Faktory\Utils\Json;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class TcpClient implements LoggerAwareInterface
{

    const SUPPORTED_FAKTORY_PROTOCOL_VERSION = 2;

    /** @var resource|null */
    protected $connection = null;
    protected State $state = State::Disconnected;
    protected ParserInterface $responseParser;

    public function __construct(
        protected LoggerInterface $logger,
        protected array $workerInfo = [],
        protected string $hostname = 'tcp://localhost',
        protected int $port = 7419,
    ) {
        $this->responseParser = (new ProtocolFactory())->createResponseParser();
    }

    public function connect(): bool
    {
        $this->state = State::Connecting;

        $this->logger->info("Connecting to Faktory server on $this->hostname:$this->port");
        $this->createTcpConnection();
        $this->handshake();

        $this->state = State::Connected;
        return true;
    }

    public function disconnect()
    {
        $this->send('END');
        fclose($this->connection);
        $this->connection = null;
        $this->state = State::Disconnected;
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

        $version = Json::parse(str_replace("HI ", "", $hi))['v'];
        if (floatval($version) > static::SUPPORTED_FAKTORY_PROTOCOL_VERSION) {
            $this->logger->warning(
                sprintf(
                    "Expected Faktory protocol v%s or lower; received v%s from the server",
                    static::SUPPORTED_FAKTORY_PROTOCOL_VERSION, $version
                )
            );;
        }
    }

    protected function sendHello()
    {
        $workerInfo = Json::stringify(array_merge(
            $this->workerInfo, ["v" => static::SUPPORTED_FAKTORY_PROTOCOL_VERSION]
        ));
        $this->send("HELLO", $workerInfo);
    }

    /**
     * Send a command and raise an error if the response is not OK.
     */
    public function operation($command, string ...$args): void
    {
        $this->send($command, ...$args);
        self::checkOk($this->readLine(), operation: $command);
    }

    public function send($command, string ...$args): void
    {
        if ($this->state == State::Disconnected) {
            $this->connect();
        }

        $message = $command . " " . join(' ', $args) . "\r\n";
        $this->logger->debug("Sending: " . $message);
        fwrite($this->connection, $message);
    }

    public function readLine(?int $skipLines = 0): mixed
    {
        if ($this->state == State::Disconnected) {
            $this->connect();
        }

        do {
            $line = fgets($this->connection);
            $this->logger->debug("Received: " . $line);
        } while ($skipLines--);

        if (str_starts_with($line, "{"))
            return Json::parse($line);

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

    public function isConnected(): bool
    {
        return $this->state == State::Connected;
    }
}

enum State
{
    case Connecting;
    case Connected;
    case Disconnected;
}
