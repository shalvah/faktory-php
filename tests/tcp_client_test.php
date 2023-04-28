<?php

use Knuckles\Faktory\Problems\CouldntConnect;
use Knuckles\Faktory\Problems\UnexpectedResponse;
use Knuckles\Faktory\TcpClient;
use Knuckles\Faktory\Utils\Json;
use Monolog\Level;

it('raises an error if Faktory server is unreachable', function () {
    $previous = set_error_handler(fn () => null, E_WARNING); // Disable PHPUnit's default
    expect(fn() => tcpClient(port: 7400)->connect())->toThrow(CouldntConnect::class);
    set_error_handler($previous);
});

it('connects to and disconnects from the Faktory server', function () {
    $tcpClient = tcpClient();
    expect($tcpClient->connect())->toBeTrue()
        ->and($tcpClient->isConnected())->toBeTrue();

    $tcpClient->disconnect();
    expect($tcpClient->isConnected())->toBeFalse();
});

it('can send commands to and read from the Faktory server', function () {
    $tcpClient = tcpClient();

    $tcpClient->send("FLUSH");
    expect($tcpClient->readLine())->toStartWith("OK");

    $job = [
        "jid" => "123861239abnadsa",
        "jobtype" => "SomeJobClass",
        "args" => [1, 2, true, "hello"],
    ];
    $tcpClient->send("PUSH", Json::stringify($job));
    expect($tcpClient->readLine())->toStartWith("OK");

    $tcpClient->send("FETCH", "default");
    $fetched = $tcpClient->readLine(skipLines: 1);
    expect($fetched['created_at'])->not->toBeEmpty();
    unset($fetched['created_at']);

    expect($fetched['enqueued_at'])->not->toBeEmpty();
    unset($fetched['enqueued_at']);

    expect($fetched)->toEqual(array_merge($job, ['queue' => 'default', 'retry' => 25]));
});

it('->send and ->readLine() do not raise an error when the response is not OK', function () {
    $tcpClient = tcpClient();
    $tcpClient->send("PUSH", "Something");
    expect($tcpClient->readLine())->toStartWith("ERR");
});

it('->operation() raises an error when the response is not OK', function () {
    expect(
        fn() => tcpClient()->operation("PUSH", "Anything")
    )->toThrow(UnexpectedResponse::class);
});

it('automatically connects if not connected before sending a command', function () {
    $tcpClient = tcpClient();
    expect($tcpClient->isConnected())->toBeFalse();
    $tcpClient->send("PUSH", JSON::stringify([
        "jid" => "abc", "jobtype" => "SomeJobClass",
    ]));
    expect($tcpClient->isConnected())->toBeTrue();
});

function tcpClient($port = 7419, $level = Level::Error) {
    return new TcpClient(
        workerInfo: ["v" => 2],
        logger: Knuckles\Faktory\Client::makeLogger(logLevel: $level),
        port: $port,
    );
}
