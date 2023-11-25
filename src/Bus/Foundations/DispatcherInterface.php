<?php

namespace Knuckles\Faktory\Bus\Foundations;

interface DispatcherInterface
{
    public function dispatch(string $jobClass, array $args = [], int $delaySeconds = null);

    public function dispatchMany(string $jobClass, array $argumentsListing, int $delaySeconds = null);
}
