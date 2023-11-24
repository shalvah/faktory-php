<?php

namespace Knuckles\Faktory\Connection;

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
    protected string $workerId;
    protected array $config;

    public function __construct(
        Level $logLevel = Level::Info,
        string $logDestination = 'php://stderr',
        LoggerInterface $logger = null,
        string $hostname = 'tcp://localhost',
        int|string $port = 7419,
        string $password = '',
        TcpClient $customTcpClient = null,
    )
    {
        $this->config = get_defined_vars();
        $this->logger = $logger ?: self::makeLogger($logLevel, $logDestination);
        $this->workerId = 'worker_'.bin2hex(random_bytes(12));
        $this->workerInfo = [
            "hostname" => gethostname(),
            "wid" => $this->workerId,
            "pid" => getmypid(),
            "labels" => [],
        ];
        $this->tcpClient = $customTcpClient ?: self::makeTcpClient(
            logger: $this->logger,
            workerInfo: $this->workerInfo,
            hostname: $hostname,
            port: $port,
            password: $password,
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
        return true;
    }

    public function push(array $job)
    {
        $this->tcpClient->sendAndRead("PUSH", Json::stringify($job));
        return true;
    }

    public function pushBulk(array $jobs)
    {
        $this->tcpClient->sendAndRead("PUSHB", Json::stringify($jobs));
        return $this->tcpClient->readLine();
    }

    public function fetch(string ...$queues): ?array
    {
        $this->tcpClient->send("FETCH", ...$queues);
        // The first line of the response just contains the length of the next line; skip it
        $job = $this->tcpClient->readLine(skipLines: 1);
        return $job;
    }

    public function ack($payload)
    {
        $this->tcpClient->sendAndRead("ACK", Json::stringify($payload));
        return true;
    }

    public function fail($payload)
    {
        $this->tcpClient->sendAndRead("FAIL", Json::stringify($payload));
        return true;
    }

    public static function makeTcpClient(...$args): TcpClient
    {
        return new TcpClient(...$args);
    }

    public static function makeLogger(
        Level $logLevel = Level::Info, string $logDestination = 'php://stderr')
    : LoggerInterface
    {
        return new Logger(
            name: 'faktory-php',
            handlers: [new StreamHandler($logDestination, $logLevel)]
        );
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
