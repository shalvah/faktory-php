<?php

use Knuckles\Faktory\Client;
use Knuckles\Faktory\TcpClient;

function client($mockTcpClient) {
    $client = new Client();
    $tcpClient = new \ReflectionProperty($client, 'tcpClient');
    $tcpClient->setValue($mockTcpClient);
    return $client;
}

it('can push and retrieve jobs', function () {
    $mockTcpClient = Mockery::mock(TcpClient::class);
    $mockTcpClient->shouldReceive('send', 'PUSH');

    $client = client($mockTcpClient);
    $client->push([
        "jid" => "123861239abnadsa",
        "jobtype" => "SomeJobClass",
        "args" => [1, 2, true, "hello"],
    ]);
    expect();
})->skip();
