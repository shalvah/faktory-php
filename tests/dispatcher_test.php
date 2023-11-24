<?php

use Knuckles\Faktory\Bus\Dispatcher;
use Knuckles\Faktory\Connection\Client;
use Knuckles\Faktory\Tests\Fixtures\TestJob;


test('::make() configures and returns a local instance', function () {
    $dispatcher = Dispatcher::make(hostname: 'rattay');
    expect(
        $dispatcher->getClient()->getConfig()["hostname"]
    )->toEqual('rattay');
    expect(
        Dispatcher::instance()->getClient()->getConfig()["hostname"]
    )->not->toEqual('rattay');
});

test('Dispatcher::configure() configures the global instance', function () {
    Dispatcher::configure(
        hostname: 'skalitz'
    );
    expect(
        Dispatcher::instance()->getClient()->getConfig()["hostname"]
    )->toEqual('skalitz');
});

test('Dispatcher::instance() returns the same instance', function () {
    expect(Dispatcher::instance())->toEqual(Dispatcher::instance());
});

test('dispatch() sends a PUSH to the Faktory server', function () {
    $args = ['arg1', true, 100];
    $mockClient = Mockery::mock(Client::class);;
    $mockClient->shouldReceive('push')->withArgs(function ($actualPayload) use ($args) {
        expect($actualPayload)->toMatchArray([
            'jobtype' => TestJob::class,
            'args' => $args,
            'queue' => TestJob::$queue,
        ]);
        expect($actualPayload)->toHaveKeys(['jid']);
        return true;
    });
    $dispatcher = new Dispatcher(customClient: $mockClient);
    $dispatcher->dispatch(TestJob::class, $args);
});

test('dispatchMany() sends a PUSHB to the Faktory server', function () {
    $argsList = [['arg1', true, 100], ['argOne', false, 200]];
    $mockClient = Mockery::mock(Client::class);;
    $mockClient->shouldReceive('pushBulk')->withArgs(function ($actualPayload) use ($argsList) {
        expect($actualPayload[0])->toMatchArray([
            'jobtype' => TestJob::class,
            'args' => $argsList[0],
            'queue' => TestJob::$queue,
        ]);
        expect($actualPayload[1])->toMatchArray([
            'jobtype' => TestJob::class,
            'args' => $argsList[1],
            'queue' => TestJob::$queue,
        ]);
        expect($actualPayload[0])->toHaveKeys(['jid']);
        expect($actualPayload[1])->toHaveKeys(['jid']);
        return true;
    });
    $dispatcher = new Dispatcher(customClient: $mockClient);
    $dispatcher->dispatchMany(TestJob::class, $argsList);
});
