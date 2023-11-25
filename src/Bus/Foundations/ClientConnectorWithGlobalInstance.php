<?php

namespace Knuckles\Faktory\Bus\Foundations;

use Knuckles\Faktory\Connection\Client;
use Knuckles\Faktory\Utils\Logging;
use Monolog\Level;
use Psr\Log\LoggerInterface;

/**
 * A class which has a Client property,
 * and may be instantiated or used as a singleton.
 * Technically, these are two separate behaviours, which should be split up
 * (e.g. as traits), but PHP's OOP implementation gives less than satisfying results
 * (e.g. incompatible overridden method [covariance/contravariance], or poor autocomplete)
 */
abstract class ClientConnectorWithGlobalInstance
{
    protected Client $client;
    protected LoggerInterface $logger;

    protected function __construct(
        protected array $config = [],
        Client          $customClient = null
    )
    {
        $this->logger = Logging::makeLogger(
            $this->config["logLevel"], $this->config["logDestination"]
        );
        $clientConfig =
            array_merge(
                array_diff_key($this->config, ["logLevel" => true, "logDestination" => true]),
                ["logger" => $this->logger],
            );
        $this->client = $customClient ?: new Client(...$clientConfig);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    protected static array $defaultInstances = [];

    /**
     * Configure and return a local instance.
     */
    public static function make(
        Level           $logLevel = Level::Info,
        string          $logDestination = 'php://stderr',
        LoggerInterface $logger = null,
        string          $hostname = 'tcp://localhost',
        int|string      $port = 7419,
        string          $password = '',
        Client $customClient = null,
    ): static
    {
        $config = array_diff_key(get_defined_vars(), ['customClient' => true]);
        return new static(config: $config, customClient: $customClient);
    }

    /**
     * For convenience, we provide a global instance, accessible via the `instance()` method.
     * Retrieve the global instance.
     */
    public static function instance(): static
    {
        // We do this because static::$defaultInstances does not work;
        // even though `static` is resolved correctly,
        // somehow, `static::$var` is shared by all subclasses
        if (isset(self::$defaultInstances[static::class])) {
            return self::$defaultInstances[static::class];
        }

        return self::setDefaultInstance(static::make());
    }

    /**
     * Configure the global instance.
     *
     * Example:
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
        Level           $logLevel = Level::Info,
        string          $logDestination = 'php://stderr',
        LoggerInterface $logger = null,
        string          $hostname = 'tcp://localhost',
        int|string      $port = 7419,
        string          $password = '',
    )
    {
        $config = get_defined_vars();
        self::setDefaultInstance(new static(config: $config));
    }

    public static function reset(): void
    {
        static::configure();
    }

    protected static function setDefaultInstance(self $instance)
    {
        self::$defaultInstances[static::class] = $instance;
        return $instance;
    }
}
