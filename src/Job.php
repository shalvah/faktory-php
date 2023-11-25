<?php

namespace Knuckles\Faktory;

use Knuckles\Faktory\Bus\Dispatcher;

abstract class Job
{
    public static ?string $queue = null;

    public static int $retry = 25;

    public static int $reserveFor = 1800;

    public static array $custom = [];

    /**
     * Usage:
     *
     *     SendWelcomeEmail::dispatch($userId);
     *
     * PS: named args don't work (Faktory requirement—args must be array)
     */
    public static function dispatch(...$args)
    {
        Dispatcher::instance()->dispatch(static::class, $args);
    }

    /*
     * Usage:
     *
     *     SendWelcomeEmail::dispatchIn(seconds: 60, $userId);
     */
    public static function dispatchIn(?int $seconds, array $args = [])
    {
        Dispatcher::instance()->dispatch(static::class, $args, delaySeconds: $seconds);
    }

    public static function dispatchMany(array $args, ?int $inSeconds = null)
    {
        Dispatcher::instance()->dispatchMany(static::class, $args, delaySeconds: $inSeconds);
    }
}
