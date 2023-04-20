<?php

namespace Knuckles\Faktory;

use Knuckles\Faktory\Utils\Json;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Faktory
{
    protected array $workerInfo;
    protected LoggerInterface $logger;
    protected TcpClient $client;

    public function __construct(
        Level $logLevel = Level::Info,
        string $logDestination = 'php://stderr',
        ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: self::makeLogger($logLevel, $logDestination);
        $this->workerInfo = [
            "hostname" => gethostname(),
            "wid" => "test-worker-1",
            "pid" => getmypid(),
            "labels" => [],
            "v" => 2,
        ];
        $this->client = new TcpClient($this->workerInfo, $this->logger, hostname: 'tcp://dreamatorium.local');
    }

    public static function makeLogger(
        Level $logLevel = Level::Info, string $logDestination = 'php://stderr'): LoggerInterface
    {
        return new Logger(
            name: 'faktory-php',
            handlers: [(new StreamHandler($logDestination, $logLevel))]
        );
    }

    public function flush()
    {
        $this->client->operation("FLUSH");
    }

    public function push(array $job)
    {
        $this->client->operation("PUSH", Json::stringify($job));
    }

    public function fetch(string ...$queues)
    {
        $this->client->operation("FETCH", ...$queues);
        // The first line of the response just contains the length of the next line; skip it
        $response = $this->client->readLine(skipLines: 1);
        return Json::parse($response);
    }

}
