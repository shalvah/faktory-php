<?php

use Knuckles\Faktory\Problems\CouldntConnect;
use Knuckles\Faktory\Problems\UnexpectedResponse;
use Knuckles\Faktory\TcpClient;
use Knuckles\Faktory\Utils\Json;
use Monolog\Level;

const PORT = 7400;

it('raises an error if Faktory server is unreachable', function () {
    $previous = set_error_handler(fn () => null, E_WARNING); // Disable PHPUnit's default
    expect(fn() => client()->connect())->toThrow(CouldntConnect::class);
    set_error_handler($previous);
});

it('connects to and disconnects from the Faktory server', function () {
    exec("docker run -p ".PORT.":7419 -d --name faktory-test contribsys/faktory:latest", $output);
    sleep(1);

    $tcpClient = client();
    expect($tcpClient->connect())->toBeTrue()
        ->and($tcpClient->isConnected())->toBeTrue();

    $tcpClient->disconnect();
    expect($tcpClient->isConnected())->toBeFalse();
})->depends('it raises an error if Faktory server is unreachable');

it('can send commands to and read from the Faktory server', function () {
    $client = client();

    $client->send("FLUSH");
    expect($client->readLine())->toStartWith("OK");

    $job = [
        "jid" => "123861239abnadsa",
        "jobtype" => "SomeJobClass",
        "args" => [1, 2, true, "hello"],
    ];
    $client->send("PUSH", Json::stringify($job));
    expect($client->readLine())->toStartWith("OK");

    $client->send("FETCH", "default");
    $fetched = Json::parse($client->readLine(skipLines: 1));
    expect($fetched['created_at'])->not->toBeEmpty();
    unset($fetched['created_at']);

    expect($fetched['enqueued_at'])->not->toBeEmpty();
    unset($fetched['enqueued_at']);

    expect($fetched)->toEqual(array_merge($job, ['queue' => 'default', 'retry' => 25]));
})->depends('it connects to and disconnects from the Faktory server');

it('->send and ->readLine() do not raise an error when the response is not OK', function () {
    $client = client();
    $client->send("PUSH", "Something");
    expect($client->readLine())->toStartWith("ERR");
})->depends('it connects to and disconnects from the Faktory server');

it('->operation() raises an error when the response is not OK', function () {
    expect(
        fn() => client()->operation("PUSH", "Anything")
    )->toThrow(UnexpectedResponse::class);
})->depends('it connects to and disconnects from the Faktory server');

it('automatically connects if not connected before sending a command', function () {
    $client = client();
    expect($client->isConnected())->toBeFalse();
    $client->send("PUSH", JSON::stringify([
        "jid" => "abc", "jobtype" => "SomeJobClass",
    ]));
    expect($client->isConnected())->toBeTrue();
});

function client($level = Level::Error) {
    return new TcpClient(
        workerInfo: ["v" => 2],
        logger: Knuckles\Faktory\Faktory::makeLogger(logLevel: $level),
        port: PORT,
    );
}

afterAll(function () {
    exec("docker rm -f faktory-test", $output);
});
