<?php

use Knuckles\Faktory\Connection\Client;

function client($logLevel = \Monolog\Level::Debug) {
    $client = new Client(
        logLevel: $logLevel
    );
    return $client;
}

beforeAll(function () {
    client(logLevel: \Monolog\Level::Error)->flush();
});

it('can PUSH, FETCH, FAIL and ACK jobs', function () {
    $client = client();
    $jobHash = [
        "jobtype" => "SomeJobClass",
        "args" => [1, 2, true, "hello"],
        "reserve_for" => 3,
        "retry" => 2,
    ];

    expect(
        $client->push($jobHash + ["jid" => uniqid('job_'), "queue" => "q1"])
    )->toEqual(true);
    $retrieved = $client->fetch("q1");

    expect($retrieved["jobtype"])->toEqual($jobHash["jobtype"])
        ->and($retrieved["args"])->toEqual($jobHash["args"]);

    expect(
        $client->fail([
            'jid' => $retrieved['jid'],
            "errtype" => InvalidArgumentException::class,
            "message" => "Something bad",
        ])
    )->toEqual(true);

    expect(
        $client->push($jobHash + ["jid" => uniqid('job_'), "queue" => "q2"])
    )->toEqual(true);
    $retrieved = $client->fetch("q2");

    expect($client->ack([
        'jid' => $retrieved['jid'],
    ]))->toEqual(true);

});

it('can push jobs in bulk', function () {
    $client = client();
    $queue = "bulk_queue_1";
    $jobHash = [
        "jobtype" => "SomeJobClass",
        "args" => [1, 2, true, "hello"],
        "reserve_for" => 3,
        "queue" => $queue,
    ];

    $jobs = [
        $jobHash + ["jid" => uniqid('job_')],
        $jobHash + ["jid" => uniqid('job_')],
        $jobHash + ["jid" => "a"], // invalid job ID
    ];
    # Returns jobs which failed to push
    expect($client->pushBulk($jobs))->toHaveCount(1)->toHaveKey("a");

    $job1 = $client->fetch($queue);
    expect($job1["jid"])->toEqual($jobs[0]["jid"]);

    $job2 = $client->fetch($queue);
    expect($job2["jid"])->toEqual($jobs[1]["jid"]);

    expect($client->fetch($queue))->toBeNull();
});
