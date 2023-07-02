<?php

namespace Knuckles\Faktory\Problems;

class MissingRequiredPassword extends \Exception
{
    public static function forServer(string $address)
    {
        return new self("Faktory server $address requires password, but none was provided.");
    }
}
