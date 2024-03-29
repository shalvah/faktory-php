<?php

require 'vendor/autoload.php';

use Knuckles\Faktory\Bus\Dispatcher;
use Knuckles\Faktory\Connection\Client;
use Knuckles\Faktory\Job;
use Knuckles\Faktory\Problems\CouldntConnect;
use Knuckles\Faktory\Tests\Fixtures\TestJob;
use Monolog\Level;

// Simplest usage: configure the global Dispatcher
Dispatcher::configure(
    logLevel: \Monolog\Level::Debug,
    hostname: 'tcp://martha',
);

// Uses the global Dispatcher
TestJob::dispatchIn(seconds: 60, args: ['arg1', true]);


// Otherwise, create and use a local Dispatcher
$dispatcher = Dispatcher::make(
    logLevel: \Monolog\Level::Debug,
    hostname: 'tcp://martha',
);
$dispatcher->dispatch(TestJob::class, ['arg3', false], delaySeconds: 60);

TestJob::dispatchMany([['arg1', true], ['arg1also', 'arg2']]);

return;
$client = new Client(
    hostname: 'tcp://localhost',
    logLevel: Level::Debug,
);

set_error_handler(function ($code, $message) {
    throw new ErrorException($message, $code);
});

ray($client->info());

try {
    $job1 = [
        "jid" => "test_job_1",
        "jobtype" => "SomeJobClass",
        "args" => [1, 2, true, "hello"],
    ];
    $job2 = [
        "jid" => "test_job_2",
    ];
    $job3 = [
        "jid" => "test_job_3",
        "jobtype" => "SomeJobClass",
    ];
    dump($client->pushBulk($job1, $job2, $job3));
} catch (CouldntConnect $e) {
    dump($e->getMessage());
    dump($e::class);
}

