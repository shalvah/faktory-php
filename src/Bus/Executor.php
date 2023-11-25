<?php

namespace Knuckles\Faktory\Bus;

use Knuckles\Faktory\Bus\Foundations\ClientConnectorWithGlobalInstance;
use Knuckles\Faktory\Bus\Foundations\ExecutorInterface;
use Knuckles\Faktory\Bus\Utilities\PayloadBuilder;
use Knuckles\Faktory\Job;
use Throwable;

class Executor extends ClientConnectorWithGlobalInstance implements ExecutorInterface
{
    public function start(array $queues = []): void
    {
        $this->client->connect();

        $queuesListening = empty($queues)
            ? "all queues" : ("queues" . implode(",", $queues));
        $this->logger->info(sprintf("Listening on %s", $queuesListening));

        while (true) {
            $jobPayload = $this->getNextJob($queues);
            $this->processAndReport($jobPayload);
        }
    }

    /**
     * Process a retrieved job, and ACK or FAIL it to Faktory.
     */
    public function processAndReport(array $jobPayload): bool
    {
        try {
            $this->process($jobPayload);
            $this->reportSuccess($jobPayload);
            return true;
        } catch (Throwable $e) {
            $this->reportFailure($jobPayload, $e);
            return false;
        }
    }

    /**
     * Process a retrieved job. This merely instantiates the job and executes it.
     * Any exceptions thrown by the job are not handled.
     */
    public function process(array $jobPayload): void
    {
        $jobInstance = $this->instantiateJob($jobPayload);
        $jobInstance->process(...$jobPayload['args']);
    }

    /**
     * Manually fetch the next job to be executed from the specified queues.
     * Faktory will block for a few seconds if no job available, then return null.
     * The $retryUntilAvailable parameter forces the executor to try again when this happens.
     */
    public function getNextJob(array $queues = ["default"], bool $retryUntilAvailable = true): array|null
    {
        while (true) {
            $job = $this->client->fetch(...$queues);
            if ($job || !$retryUntilAvailable) return $job;
        }
    }

    protected function reportSuccess(array $jobPayload)
    {
        $this->logger->info(sprintf("Processed job result=success class=%s id=%s", $jobPayload['jobtype'], $jobPayload['jid']));
        $this->client->ack(PayloadBuilder::successPayload($jobPayload));
    }

    protected function reportFailure(array $jobPayload, Throwable $e)
    {
        $this->logger->info(sprintf("Processed job result=failure class=%s id=%s", $jobPayload['jobtype'], $jobPayload['jid']));
        $this->client->fail(PayloadBuilder::failurePayload($jobPayload, $e));
    }

    protected function instantiateJob(array $jobPayload): Job
    {
        $class = $jobPayload['jobtype'];
        return new $class; // todo maybe pass tools like logger
    }
}
