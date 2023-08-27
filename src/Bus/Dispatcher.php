<?php

namespace Knuckles\Faktory\Bus;

use Knuckles\Faktory\Connection\Client;
use Monolog\Level;
use Psr\Log\LoggerInterface;

class Dispatcher
{
    protected static self $defaultInstance;
    protected Client $client;

    public function __construct(
        protected array $clientConfig = []
    )
    {
        $this->client = new Client(...$this->clientConfig);
    }

    public function dispatch(string $jobClass, array $args = [], int $delaySeconds = null)
    {
        $jobPayload = static::toJobPayload($jobClass, $args, $delaySeconds);
        $this->client->push($jobPayload);
    }

    public function dispatchMany(string $jobClass, array $argumentsListing, int $delaySeconds = null)
    {
        $basicPayload = static::toJobPayload($jobClass, [], $delaySeconds);

        $jobPayloads = [];
        foreach ($argumentsListing as $index => $arguments) {
            $jobPayloads[] = array_merge($basicPayload, [
                "jid" => "{$basicPayload['jid']}_{$index}",
                "args" => $arguments,
            ]);
        }

        return $this->client->pushBulk($jobPayloads);
    }

    public static function make(
        Level $logLevel = Level::Info,
        string $logDestination = 'php://stderr',
        LoggerInterface $logger = null,
        string $hostname = 'tcp://localhost',
        int|string $port = 7419,
        string $password = '',
    )
    {
        return new static(clientConfig: get_defined_vars());
    }

    /**
     * Configure the global Dispatcher.
     *
     * Usage:
     *
     *     Dispatcher::configure(
     *       logLevel: \Monolog\Level::Info,
     *       logDestination: 'php://stderr',
     *       hostname: 'tcp://localhost',
     *       port: 7419,
     *       password: ENV['thing'],
     *     );
     */
    public static function configure(
        Level $logLevel = Level::Info,
        string $logDestination = 'php://stderr',
        LoggerInterface $logger = null,
        string $hostname = 'tcp://localhost',
        int|string $port = 7419,
        string $password = '',
    )
    {
        $config = get_defined_vars();
        static::$defaultInstance = new static(clientConfig: $config);
    }

    /**
     * Retrieve the global Dispatcher.
     */
    public static function instance()
    {
        if (isset(static::$defaultInstance)) {
            return static::$defaultInstance;
        }

        return (static::$defaultInstance = new static);
    }

    protected static function toJobPayload(string $jobClass, array $args, int $delaySeconds = null)
    {
        return JobPayload::build(
            jobType: $jobClass,
            args: $args,
            queue: $jobClass::$queue,
            retry: $jobClass::$retry,
            reserveFor: $jobClass::$reserveFor,
            delaySeconds: $delaySeconds
        );
    }
}
