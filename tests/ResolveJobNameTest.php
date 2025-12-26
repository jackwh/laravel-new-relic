<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Job;
use JackWH\LaravelNewRelic\NewRelicTransactionHandler;

/**
 * Test the job name resolution logic that prevents empty transaction names
 * from appearing as "OtherTransaction/php/artisan" in New Relic.
 */

/**
 * Create a test handler that exposes the protected resolveJobName method.
 */
function createTestHandler(): object
{
    return new class extends NewRelicTransactionHandler {
        public function testResolveJobName(Job $job): string
        {
            return $this->resolveJobName($job);
        }
    };
}

/**
 * Base job stub with all required Job interface methods implemented.
 */
abstract class BaseJobStub implements Job
{
    public function getJobId(): string|int|null
    {
        return '123';
    }

    public function getRawBody(): string
    {
        return '{}';
    }

    public function uuid(): ?string
    {
        return null;
    }

    public function fire(): void {}

    public function delete(): void {}

    public function isDeleted(): bool
    {
        return false;
    }

    public function release($delay = 0): void {}

    public function isReleased(): bool
    {
        return false;
    }

    public function isDeletedOrReleased(): bool
    {
        return false;
    }

    public function hasFailed(): bool
    {
        return false;
    }

    public function markAsFailed(): void {}

    public function fail($e = null): void {}

    public function maxTries(): ?int
    {
        return null;
    }

    public function maxExceptions(): ?int
    {
        return null;
    }

    public function shouldFailOnTimeout(): bool
    {
        return false;
    }

    public function backoff(): int|array|null
    {
        return null;
    }

    public function timeout(): ?int
    {
        return null;
    }

    public function retryUntil(): ?int
    {
        return null;
    }

    public function getConnectionName(): string
    {
        return 'database';
    }

    public function payload(): array
    {
        return [];
    }

    public function attempts(): int
    {
        return 1;
    }
}

it('resolves job name from resolveQueuedJobClass when available', function (): void {
    $handler = createTestHandler();

    $job = new class extends BaseJobStub {
        public function resolveQueuedJobClass(): string
        {
            return 'App\Jobs\ProcessPayment';
        }

        public function resolveName(): string
        {
            return 'Should Not Be Called';
        }

        public function getName(): string
        {
            return 'Should Not Be Called';
        }

        public function getQueue(): string
        {
            return 'default';
        }
    };

    expect($handler->testResolveJobName($job))->toBe('App\Jobs\ProcessPayment');
});

it('falls back to resolveName when resolveQueuedJobClass returns empty', function (): void {
    $handler = createTestHandler();

    $job = new class extends BaseJobStub {
        public function resolveQueuedJobClass(): string
        {
            return '';
        }

        public function resolveName(): string
        {
            return 'App\Jobs\SendEmail';
        }

        public function getName(): string
        {
            return 'Should Not Be Called';
        }

        public function getQueue(): string
        {
            return 'emails';
        }
    };

    expect($handler->testResolveJobName($job))->toBe('App\Jobs\SendEmail');
});

it('falls back to getName when both resolveQueuedJobClass and resolveName return empty', function (): void {
    $handler = createTestHandler();

    $job = new class extends BaseJobStub {
        public function resolveQueuedJobClass(): string
        {
            return '';
        }

        public function resolveName(): string
        {
            return '';
        }

        public function getName(): string
        {
            return 'SomeStringBasedJob';
        }

        public function getQueue(): string
        {
            return 'low-priority';
        }
    };

    expect($handler->testResolveJobName($job))->toBe('SomeStringBasedJob');
});

it('returns Unknown Job with queue name when all resolution methods return empty', function (): void {
    $handler = createTestHandler();

    $job = new class extends BaseJobStub {
        public function resolveQueuedJobClass(): string
        {
            return '';
        }

        public function resolveName(): string
        {
            return '';
        }

        public function getName(): string
        {
            return '';
        }

        public function getQueue(): string
        {
            return 'default';
        }
    };

    expect($handler->testResolveJobName($job))->toBe('Unknown Job [default]');
});

it('handles job without resolveQueuedJobClass method', function (): void {
    $handler = createTestHandler();

    // Job stub that intentionally does NOT have resolveQueuedJobClass method
    $job = new class extends BaseJobStub {
        public function resolveName(): string
        {
            return 'CustomJobName';
        }

        public function getName(): string
        {
            return 'Illuminate\Queue\CallQueuedHandler@call';
        }

        public function getQueue(): string
        {
            return 'default';
        }
    };

    expect($handler->testResolveJobName($job))->toBe('CustomJobName');
});

it('prioritizes resolveQueuedJobClass over resolveName', function (): void {
    $handler = createTestHandler();

    $job = new class extends BaseJobStub {
        public function resolveQueuedJobClass(): string
        {
            return 'App\Jobs\ActualJobClass';
        }

        public function resolveName(): string
        {
            throw new \RuntimeException('resolveName should not be called when resolveQueuedJobClass returns a value');
        }

        public function getName(): string
        {
            throw new \RuntimeException('getName should not be called when resolveQueuedJobClass returns a value');
        }

        public function getQueue(): string
        {
            return 'default';
        }
    };

    expect($handler->testResolveJobName($job))->toBe('App\Jobs\ActualJobClass');
});

it('handles different queue names in fallback', function (): void {
    $handler = createTestHandler();

    $job = new class extends BaseJobStub {
        public function resolveQueuedJobClass(): string
        {
            return '';
        }

        public function resolveName(): string
        {
            return '';
        }

        public function getName(): string
        {
            return '';
        }

        public function getQueue(): string
        {
            return 'high-priority-tasks';
        }
    };

    expect($handler->testResolveJobName($job))->toBe('Unknown Job [high-priority-tasks]');
});