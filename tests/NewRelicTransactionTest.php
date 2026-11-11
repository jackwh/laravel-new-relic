<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use JackWH\LaravelNewRelic\NewRelicTransaction;
use JackWH\LaravelNewRelic\NewRelicTransactionHandler;

beforeEach(function (): void {
    config()->set('new-relic.loggable', ['testing']);
    NewRelicTransactionHandler::newRelicEnabled();

    $this->newRelicCalls = [];
    app('events')->listen(MessageLogged::class, function (MessageLogged $logged): void {
        $this->newRelicCalls[] = $logged->message;
    });
});

it('does not send the empty name the root path trims to', function (): void {
    app(NewRelicTransaction::class)->setName('/');

    expect(implode("\n", $this->newRelicCalls))->not->toContain('is now named');
});

it('names the transaction after the root path once the route is known', function (): void {
    app(NewRelicTransaction::class)->setName('/')->setName('home');

    expect(app(NewRelicTransaction::class)->name)->toBe('home')
        ->and(implode("\n", $this->newRelicCalls))->toContain('is now named "home"');
});
