<?php

use Knuckles\Faktory\Problems\CouldntConnect;
use Knuckles\Faktory\Problems\InvalidPassword;
use Knuckles\Faktory\Problems\MissingRequiredPassword;
use Knuckles\Faktory\Problems\UnexpectedResponse;
use Knuckles\Faktory\TcpClient;
use Knuckles\Faktory\Utils\Json;
use Monolog\Level;
use Monolog\Logger;

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

it('logs a warning when version is higher than expected', function () {
    $logger = Mockery::mock(Logger::class, ['info' => null, 'debug' => null]);
    $tcpClient = new class($logger) extends TcpClient {
        const SUPPORTED_FAKTORY_PROTOCOL_VERSION = 1;
    };

    $logger->shouldReceive('warning')->withArgs(function ($arg) {
        return str_contains($arg, "Expected Faktory protocol v1 or lower");
    });
    $tcpClient->connect();
    $this->addToAssertionCount(1); // Workaround so test is not marked risky
});

it('does not log a warning when version is lower than expected', function () {
    $logger = Mockery::mock(Logger::class, ['info' => null, 'debug' => null]);
    $tcpClient = new class($logger) extends TcpClient {
        const SUPPORTED_FAKTORY_PROTOCOL_VERSION = 3;
    };

    $logger->shouldNotReceive('warning');
    $tcpClient->connect();
});

test('->readLine() raises an error when the response is an ERR', function () {
    $tcpClient = tcpClient();
    $tcpClient->send("PUSH", "Invalid payload");
    expect(fn() => $tcpClient->readLine())->toThrow(UnexpectedResponse::class);
});

test('->sendAndRead() raises an error when the response is not OK', function () {
    expect(
        fn() => tcpClient()->sendAndRead("PUSH", "Invalid data")
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

// -- describe 'with a password protected Faktory server'
it('raises an error if password is required but empty', function () {
    $tcpClient = tcpClient(port: 7423);
    expect(fn() => $tcpClient->connect())->toThrow(MissingRequiredPassword::class);
});

it('raises an error if the wrong password is supplied', function () {
    $tcpClient = tcpClient(port: 7423, password: 'some_incorrect_password');
    expect(fn() => $tcpClient->connect())->toThrow(InvalidPassword::class);
});

it('connects if the correct password is supplied', function () {
    $tcpClient = tcpClient(port: 7423, password: 'my_special_password');
    expect($tcpClient->connect())->toBeTrue()
        ->and($tcpClient->isConnected())->toBeTrue();
});
// -- end

function tcpClient($port = 7419, $level = Level::Error, $password = '') {
    return new TcpClient(
        logger: Knuckles\Faktory\Client::makeLogger(logLevel: $level),
        port: $port,
        password: $password,
    );
}
