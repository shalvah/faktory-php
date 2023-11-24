<?php

namespace Knuckles\Faktory\Tests\Fixtures;

use Knuckles\Faktory\Job;

class TestJob extends Job
{
    public static ?string $queue = 'test';

    public function __construct(
        public string $arg1,
        public bool   $arg2,
        public ?int   $arg3 = null,
    ) {}
}
