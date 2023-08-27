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
        "jid" => uniqid('job_'),
        "jobtype" => "SomeJobClass",
        "args" => [1, 2, true, "hello"],
        "queue" => "analytics",
        "reserve_for" => 3,
        "retry" => 2,
    ];

    expect(
        $client->push($jobHash)
    )->toEqual(true);
    $retrieved = $client->fetch("analytics");

    expect($retrieved["jid"])->toEqual($jobHash["jid"])
        ->and($retrieved["jobtype"])->toEqual($jobHash["jobtype"])
        ->and($retrieved["args"])->toEqual($jobHash["args"])
        ->and($retrieved["queue"])->toEqual($jobHash["queue"]);

    expect(
        $client->fail($jobHash['jid'], new InvalidArgumentException("Something bad"))
    )->toEqual(true);

    sleep(10);
    $retrieved = $client->fetch("analytics");
    dump($retrieved);
    dump($client->info());

    expect($retrieved["jid"])->toEqual($jobHash["jid"])
        ->and($retrieved["jobtype"])->toEqual($jobHash["jobtype"])
        ->and($retrieved["args"])->toEqual($jobHash["args"])
        ->and($retrieved["queue"])->toEqual($jobHash["queue"])
        ->and($retrieved["failure"])->toEqual(null);

});

it('can push jobs in bulk', function () {
});
