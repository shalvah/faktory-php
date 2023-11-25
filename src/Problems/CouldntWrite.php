<?php

namespace Knuckles\Faktory\Problems;

use Throwable;

class CouldntWrite extends \Exception
{
    public static function to($address, ?Throwable $exception = null)
    {
        $message = "Could not write to Faktory on $address; ".
            "the connection may have been closed by the Faktory server.";

        if ($exception) {
            $message .= "\nError message: {$exception->getMessage()}";
        }
        return new self($message, previous: $exception);
    }
}
