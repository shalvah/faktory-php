<?php

namespace Knuckles\Faktory\Utils;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Logging
{
    public static function makeLogger(
        Level $logLevel = Level::Info, string $logDestination = 'php://stderr'): LoggerInterface
    {
        return new Logger(
            name: 'faktory-php',
            handlers: [new StreamHandler($logDestination, $logLevel)]
        );
    }
}
