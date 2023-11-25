<?php

namespace Knuckles\Faktory\Tests\Fixtures;

use InvalidArgumentException;
use Knuckles\Faktory\Job;

class TestJob extends Job
{
    public static $errorClass = InvalidArgumentException::class;
    public static $errorMessage = "You told me to fail!";

    public static ?string $queue = 'test';

    public function process(string $arg1, bool $shouldFail = false, ?int $arg3 = null)
    {
        if ($shouldFail) {
            throw new self::$errorClass(self::$errorMessage);
        }
        dump("YAYAYAYA");
    }
}
