<?php

namespace Knuckles\Faktory\Problems;

class InvalidPassword extends \Exception
{
    public static function forServer(string $address)
    {
        return new self("Authentication failed: Invalid password provided for the Faktory server $address");
    }
}
