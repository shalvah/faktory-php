<?php

namespace Knuckles\Faktory\Bus\Utilities;

class PayloadBuilder
{
    /**
     * Generate the payload array sent to Faktory to enqueue a job.
     */
    public static function build(
        string $jobType,
        array $args,
        ?string $queue = null,
        ?int $retry = null,
        ?int $reserveFor = null,
        ?int $delaySeconds = null,
    ): array
    {
        $payload = [
            'jid' => 'job_' . bin2hex(random_bytes(12)),
            'jobtype' => $jobType,
            'args' => $args,
        ];

        if ($queue) {
            $payload['queue'] = $queue;
        }
        if (is_null($retry)) { // 0 is a possible value, meaning no retries
            $payload['retry'] = $retry;
        }
        if ($reserveFor) {
            $payload['reserve_for'] = $reserveFor;
        }

        if ($delaySeconds) {
            $executionTime = (new \DateTimeImmutable('@'.(time() + $delaySeconds)));
            $payload['at'] = $executionTime->format(\DateTimeInterface::ATOM);
        }

        return $payload;
    }

    /**
     * Generate the payload array sent to Faktory to report a job's failure.
     */
    public static function successPayload(array $originalJobPayload): array
    {
        return ["jid" => $originalJobPayload["jid"]];
    }

    /**
     * Generate the payload array sent to Faktory to report a job's failure.
     */
    public static function failurePayload(array $originalJobPayload, \Throwable $exception,): array
    {
        return [
            "jid" => $originalJobPayload["jid"],
            "errtype" => $exception::class,
            "message" => $exception->getMessage(),
            "backtrace" => explode("\n", $exception->getTraceAsString()),
        ];
    }
}
