<?php

use Knuckles\Faktory\TcpClient;

it('can connect to the Faktory server', function () {
    $client = new TcpClient;
    expect($client->connect())->toBeTrue();
});

