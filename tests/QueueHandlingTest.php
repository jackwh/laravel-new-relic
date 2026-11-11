<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Queue\Jobs\SyncJob;
use JackWH\LaravelNewRelic\NewRelicTransactionHandler;

beforeEach(function (): void {
    // The loggable New Relic functions report every agent call to the log.
    config()->set('new-relic.loggable', ['testing']);
    NewRelicTransactionHandler::newRelicEnabled();

    $this->newRelicCalls = [];
    app('events')->listen(MessageLogged::class, function (MessageLogged $logged): void {
        $this->newRelicCalls[] = $logged->message;
    });
});

function queueJob(): SyncJob
{
    return new SyncJob(app(), json_encode(['displayName' => 'App\\Jobs\\SendInvoice', 'job' => 'Handler@call', 'data' => []]), 'redis', 'default');
}

function recordsNoTransactions(): void
{
    config()->set('new-relic.queue.record_transactions', false);
}

it('starts a transaction for each job by default', function (): void {
    app(NewRelicTransactionHandler::class)->queueHandling();

    event(new JobProcessing('redis', queueJob()));

    expect(implode("\n", $this->newRelicCalls))->toContain('started for application');
});

it('leaves job transactions to the agent when record_transactions is false', function (): void {
    recordsNoTransactions();
    app(NewRelicTransactionHandler::class)->queueHandling();

    event(new JobProcessing('redis', queueJob()));

    expect(implode("\n", $this->newRelicCalls))->not->toContain('started for application');
});

it('ignores the idle worker transaction when the worker stops', function (): void {
    recordsNoTransactions();
    app(NewRelicTransactionHandler::class)->queueHandling();

    event(new WorkerStopping(0));

    expect(implode("\n", $this->newRelicCalls))->toContain('ignored.');
});

it('ends the transaction of a timed-out job', function (): void {
    recordsNoTransactions();
    app(NewRelicTransactionHandler::class)->queueHandling();

    event(new JobTimedOut('redis', queueJob()));

    expect(implode("\n", $this->newRelicCalls))->toContain('ended.');
});
