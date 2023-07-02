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
    protected string $workerId;

    public function __construct(
        Level $logLevel = Level::Info,
        string $logDestination = 'php://stderr',
        ?LoggerInterface $logger = null,
        string $hostname = 'tcp://localhost',
        int|string $port = 7419,
        string $password = '',
    )
    {
        $this->logger = $logger ?: self::makeLogger($logLevel, $logDestination);
        $this->workerId = bin2hex(random_bytes(12));
        $this->workerInfo = [
            "hostname" => gethostname(),
            "wid" => $this->workerId,
            "pid" => getmypid(),
            "labels" => [],
        ];
        $this->tcpClient = self::makeTcpClient(
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
    }

    public function push(array $job)
    {
        $this->tcpClient->sendAndRead("PUSH", Json::stringify($job));
    }

    public function pushBulk(array ...$jobs)
    {
        $this->tcpClient->sendAndRead("PUSHB", Json::stringify($jobs));
        return $this->tcpClient->readLine();
    }

    public function fetch(string ...$queues): array|null
    {
        $this->tcpClient->send("FETCH", ...$queues);
        // The first line of the response just contains the length of the next line; skip it
        return $this->tcpClient->readLine(skipLines: 1);
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
}
