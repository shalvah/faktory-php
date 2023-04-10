<?php

use Knuckles\Faktory\Problems\UnexpectedResponse;
use Knuckles\Faktory\TcpClient;

it('can connect to the Faktory server', function () {
    $client = new TcpClient;
    expect($client->connect())->toBeTrue();
});

it('can push to and fetch from the Faktory server', function () {
    $client = client();

    $job = [
        "jid" => "123861239abnadsa",
        "jobtype" => "SomeJobClass",
        "args" => [1, 2, true, "hello"],
    ];
    expect($client->push($job))->toBeTrue();

    $fetched = $client->fetch(queues: "default");
    expect($fetched['created_at'])->not->toBeEmpty();
    unset($fetched['created_at']);

    expect($fetched['enqueued_at'])->not->toBeEmpty();
    unset($fetched['enqueued_at']);

    expect($fetched)->toEqual(array_merge($job, ['queue' => 'default', 'retry' => 25]));
});

it('raises an error when the response is not OK', function () {
    $invalidJob = ["jobtype" => null];
    expect(fn() => client()->push($invalidJob))->toThrow(UnexpectedResponse::class);
});

function client() {
    $client = new TcpClient(logLevel: \Monolog\Level::Error);
    $client->connect();
    return $client;
}
