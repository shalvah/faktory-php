<?php

use Knuckles\Faktory\Bus\Executor;
use Knuckles\Faktory\Bus\Utilities\PayloadBuilder;
use Knuckles\Faktory\Connection\Client;
use Knuckles\Faktory\Tests\Fixtures\TestJob;

beforeEach(function () {
    Executor::reset();
});

test('::make() configures and returns a local instance', function () {
    $executor = Executor::make(hostname: 'rattay');
    expect(
        $executor->getClient()->getConfig()["hostname"]
    )->toEqual('rattay');
    expect(
        Executor::instance()->getClient()->getConfig()["hostname"]
    )->not->toEqual('rattay');
});

test('Executor::configure() configures the global instance', function () {
    Executor::configure(hostname: 'skalitz');
    expect(
        Executor::instance()->getClient()->getConfig()["hostname"]
    )->toEqual('skalitz');
});

test('Executor::instance() returns the same instance', function () {
    expect(Executor::instance())->toBeInstanceOf(Executor::class);
    expect(Executor::instance())->toEqual(Executor::instance());
});

describe('processAndReport()', function () {
    it('ACKs the job and returns true if the job executes successfully', function () {
        $jobPayload = PayloadBuilder::build(TestJob::class, ['43', false]);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('ack')->with(['jid' => $jobPayload['jid']]);

        $executor = Executor::make(customClient: $mockClient);
        expect($executor->processAndReport($jobPayload))->toEqual(true);
    });

    it('FAILs the job and returns false if the job throws an error', function () {
        $jobPayload = PayloadBuilder::build(TestJob::class, ['43', true]);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('fail')->withArgs(function ($payload) use ($jobPayload) {
            expect($payload)->toMatchArray([
                'jid' => $jobPayload['jid'],
                'errtype' => TestJob::$errorClass,
                'message' => TestJob::$errorMessage,
            ]);
            expect($payload)->toHaveKeys(['backtrace']);
            return true;
        });

        $executor = Executor::make(customClient: $mockClient);
        expect($executor->processAndReport($jobPayload))->toEqual(false);
    });
});

describe("getNextJob()", function () {
    it('returns the next job', function () {
        $queuesToCheck = [TestJob::$queue, 'other_queue'];
        $jobPayload = PayloadBuilder::build(TestJob::class, ['43', false]);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('fetch')->with(...$queuesToCheck)
            ->andReturn($jobPayload);

        $executor = Executor::make(customClient: $mockClient);
        expect($executor->getNextJob($queuesToCheck, retryUntilAvailable: false))
            ->toEqual($jobPayload);
    });

    it('retries if no job is available, and $retryUntilAvailable is true', function () {
        $queuesToCheck = [TestJob::$queue, 'other_queue'];
        $jobPayload = PayloadBuilder::build(TestJob::class, ['43', false]);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('fetch')->with(...$queuesToCheck)
            ->andReturn(null)->twice();
        $mockClient->shouldReceive('fetch')->with(...$queuesToCheck)
            ->andReturn($jobPayload);

        $executor = Executor::make(customClient: $mockClient);
        expect($executor->getNextJob($queuesToCheck))->toEqual($jobPayload);
    });

    it('returns null if no job is available, and $retryUntilAvailable is false', function () {
        $queuesToCheck = [TestJob::$queue, 'other_queue'];

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('fetch')->with(...$queuesToCheck)
            ->andReturn(null);

        $executor = Executor::make(customClient: $mockClient);
        expect($executor->getNextJob($queuesToCheck, retryUntilAvailable: false))->toEqual(null);
    });
});

