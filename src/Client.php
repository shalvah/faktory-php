<?php

namespace Knuckles\Faktory;

use Knuckles\Faktory\Utils\Json;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Client
{
    protected array $workerInfo;
    protected LoggerInterface $logger;
    protected TcpClient $tcpClient;

    public function __construct(
        Level $logLevel = Level::Info,
        string $logDestination = 'php://stderr',
        ?LoggerInterface $logger = null,
    )
    {
        $this->logger = $logger ?: self::makeLogger($logLevel, $logDestination);
        $this->workerInfo = [
            "hostname" => gethostname(),
            "wid" => "test-worker-1",
            "pid" => getmypid(),
            "labels" => [],
        ];
        $this->tcpClient = self::makeTcpClient(
            $this->logger, $this->workerInfo, hostname: 'tcp://dreamatorium.local'
        );
    }

    public function info(): array
    {
        $this->tcpClient->send("INFO");
        return $this->tcpClient->readLine(skipLines: 1);
    }

    public function flush()
    {
        $this->tcpClient->sendAndRead("FLUSH");
    }

    public function push(array $job)
    {
        $this->tcpClient->sendAndRead("PUSH", Json::stringify($job));
    }

    public function fetch(string ...$queues): array|null
    {
        $this->tcpClient->send("FETCH", ...$queues);
        // The first line of the response just contains the length of the next line; skip it
        return $this->tcpClient->readLine(skipLines: 1);
    }

    public static function makeTcpClient($workerInfo, $logger, $hostname): TcpClient
    {
        return new TcpClient($workerInfo, $logger, $hostname);
    }

    public static function makeLogger(
        Level $logLevel = Level::Info, string $logDestination = 'php://stderr'): LoggerInterface
    {
        return new Logger(
            name: 'faktory-php',
            handlers: [new StreamHandler($logDestination, $logLevel)]
        );
    }
}
