<?php

namespace Knuckles\Faktory\Problems;

class CouldntConnect extends \Exception
{
    public static function to($address, $errorMessage, $errorCode)
    {
        $message = "Failed to connect to Faktory on $address: $errorMessage (error code $errorCode)";

        return new self($message);
    }
}
