<?php

namespace Knuckles\Faktory\Bus;

use Knuckles\Faktory\Bus\Foundations\ClientConnectorWithGlobalInstance;
use Knuckles\Faktory\Bus\Foundations\DispatcherInterface;
use Knuckles\Faktory\Bus\Utilities\PayloadBuilder;

class Dispatcher extends ClientConnectorWithGlobalInstance implements DispatcherInterface
{
    public function dispatch(string $jobClass, array $args = [], int $delaySeconds = null)
    {
        $jobPayload = static::toJobPayload($jobClass, $args, $delaySeconds);
        return $this->client->push($jobPayload);
    }

    public function dispatchMany(string $jobClass, array $argumentsListing, int $delaySeconds = null)
    {
        $basicPayload = static::toJobPayload($jobClass, args: [], delaySeconds: $delaySeconds);

        $jobPayloads = [];
        foreach ($argumentsListing as $index => $arguments) {
            $jobPayloads[] = array_merge($basicPayload, [
                "jid" => "{$basicPayload['jid']}_{$index}",
                "args" => $arguments,
            ]);
        }

        return $this->client->pushBulk($jobPayloads);
    }

    protected static function toJobPayload(string $jobClass, array $args, int $delaySeconds = null)
    {
        return PayloadBuilder::build(
            jobType: $jobClass,
            args: $args,
            queue: $jobClass::$queue,
            retry: $jobClass::$retry,
            reserveFor: $jobClass::$reserveFor,
            delaySeconds: $delaySeconds
        );
    }
}
