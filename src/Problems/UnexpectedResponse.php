<?php

namespace Knuckles\Faktory\Problems;

class UnexpectedResponse extends \Exception
{
    public static function from($operation, $response)
    {
        return new self("$operation returned an unexpected response: \"$response\"");
    }
}
