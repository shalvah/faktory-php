<?php

namespace Knuckles\Faktory\Bus;

class JobPayload
{
    public static function build(
        string $jobType,
        array $args,
        ?string $queue,
        ?string $retry,
        ?string $reserveFor,
        ?int $delaySeconds,
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
        if ($retry) {
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

    public static function failurePayload(
        string $jid,
        \Throwable $exception,
    ): array
    {
        return [
            "jid" => $jid,
            "errtype" => $exception::class,
            "message" => $exception->getMessage(),
            "backtrace" => explode("\n", $exception->getTraceAsString()),
        ];
    }

    public function toArray()
    {

    }
}
