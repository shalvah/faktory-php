<?php

namespace Knuckles\Faktory\Bus\Foundations;


interface ExecutorInterface
{
    /**
     * Start the executor. It will fetch jobs from the specified queues,
     * and keep executing indefinitely, waiting until new jobs are added.
     */
    public function start(array $queues = []): void;
}
