# Laravel New Relic

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jackwh/laravel-new-relic.svg?style=flat-square)](https://packagist.org/packages/jackwh/laravel-new-relic)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/jackwh/laravel-new-relic/run-tests?label=tests)](https://github.com/jackwh/laravel-new-relic/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/jackwh/laravel-new-relic/Check%20&%20fix%20styling?label=code%20style)](https://github.com/jackwh/laravel-new-relic/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jackwh/laravel-new-relic.svg?style=flat-square)](https://packagist.org/packages/jackwh/laravel-new-relic)

<img src="https://banners.beyondco.de/New%20Relic%20for%20Laravel.png?theme=light&packageManager=composer+require&packageName=jackwh%2Flaravel-new-relic&pattern=circuitBoard&style=style_1&description=New+Relic+performance+monitoring%2C+optimised+for+Laravel+applications.&md=1&showWatermark=1&fontSize=125px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg"/>

This package makes it simple to set up and monitor your [Laravel](https://laravel.com) application with [New Relic APM](https://newrelic.com/products/application-monitoring).

New Relic provides some excellent low-level insights into your application. The [New Relic PHP agent](https://docs.newrelic.com/docs/apm/agents/php-agent/getting-started/introduction-new-relic-php/) is particularly useful in production environments, as it hooks in at a lower level than other monitoring services, and with little to no impact on performance.

> **New Relic has a fully-featured [free plan](https://newrelic.com/pricing)** which is ideal for growing Laravel applications. This package isn't affiliated with them — I just built it because I've found the service very helpful whilst scaling my app, and wanted a more tailored solution for Laravel.
> 
> Whilst New Relic can monitor a Laravel application out-of-the-box, this package reports transactions that are optimised for Laravel, and reported in a more consisted way.

## Installation

To monitor your application in production you'll need a [New Relic](https://newrelic.com) API account, and you should [install the PHP monitoring agent](https://docs.newrelic.com/docs/apm/agents/php-agent/installation/php-agent-installation-overview/). You don't need to install New Relic in your development environment (unless you really want to). If the extension isn't detected the package will simulate calls to the New Relic PHP agent, and log each one so you can test before deploying.

> If you're installing this on a server which is *already* being monitored by New Relic, **be aware this package reports transactions with different naming conventions than New Relic normally auto-detects**. If your existing New Relic data is very important to you, don't install this.

To install the package, add it to your Laravel project with Composer:

```bash
composer require jackwh/laravel-new-relic
```

Then publish the config file:

```bash
php artisan vendor:publish --provider="JackWH\LaravelNewRelic\LaravelNewRelicServiceProvider"
```

> **That's it, you're done! The package is ready to go, and configured out-of-the-box.**

## How It Works

#### The Service Provider

Laravel will auto-discover the `LaravelNewRelicServiceProvider` class, which binds `NewRelicTransactionHandler` and `NewRelicTransaction` classes as [scoped singletons](https://laravel.com/docs/9.x/container#binding-scoped) to the service container.

New Relic's transaction API only allows a single transaction to be active at a time. That's why the classes are loaded as singletons. Generally speaking, don't try to start a new transaction mid-way through the request lifecycle.

#### Loggable Environments
The package checks if New Relic is installed. If it's not found, you can log simulated transactions.

In a loggable environment, the package will simulate calls it would normally make to New Relic's methods (e.g. `newrelic_start_transaction()`). These are loaded from the `LoggableNewRelicFunctions.php` helper file. You can check your logs to see what's happening under the hood.

Don't worry if your logs don't show a "transaction ended" item, as New Relic automatically finishes them at the end of a request. This is only really important for long-running processes, like the queue handler.

> Once you're happy logging is working as expected, you can comment out `local` in the `config/new-relic.php` file.
> This is just intended to help you check the package is working before initial deployment, or when making changes
> which would affect New Relic transactions.

#### Live Environments
Assuming the New Relic extension is loaded, the package sets up hooks into Laravel to monitor requests at different stages of the lifecycle:
- **HTTP transactions** are handled with a global `NewRelicMiddleware` on each request
- **CLI requests** are filtered out for noise (so long-running calls like `php artisan horizon` won't skew your stats). 
- **Queued jobs** record a transaction automatically as each one starts and ends.
- **Artisan commands** are recorded as individual transactions.
- **Scheduled tasks** are monitored as each one is executed.

The package also registers a `php artisan new-relic:deploy` command, to notify New Relic of changes as part of your deployment process.

## Configuration

The [configuration file](config/new-relic.php) is documented in detail — read through each comment to understand how it will affect transaction reporting. A few settings worth pointing out here are below:

#### HTTP Requests
```php
'http'         => [
    'middleware' => \JackWH\LaravelNewRelic\Middleware\NewRelicMiddleware::class,

    // ...

    'rewrite' => [
        '/livewire/livewire.js'     => 'livewire.js',
        '/livewire/livewire.js.map' => 'livewire.js.map',
    ],

    // ...

    'ignore' => [
        'debugbar.**',
        'horizon.**',
        'telescope.**',
    ],
],
```

The built-in `NewRelicMiddleware` class should be fine for most use cases, but you can extend it with your own implementation if needed. 

The `rewrite` key is useful for routes which don't have names defined (often the case with packages that expose public resources, like Livewire). You can rewrite their names for consistency here.

We've set some sensible `ignore` rules by default, feel free to adjust as required.

#### Queue Handling
```php
'queue'        => [
    'record_transactions' => true,

    // ...

    'ignore' => [
        'connections' => ['sync'],
        'queues'      => [],
        'jobs'        => [],
    ],
],
```

By default the `sync` connection will be ignored. This means a new job starting on this queue won't interrupt the existing transaction that started at the beginning of the request. You can also filter out specific queues and jobs, too.

The New Relic PHP agent instruments Laravel queue workers itself: it opens, names and closes a transaction for every job. The package's own per-job transactions collide with that, and with agent 12.12 every log record written inside a job is lost. Set `record_transactions` to `false` to leave job transactions to the agent, and remove `queue:work` and `horizon:work` from `artisan.ignore`, because the agent only records jobs inside a transaction that is not ignored. The package then only closes the transaction of a timed-out job (the worker is killed with `SIGKILL`) and ignores the worker's idle time once it stops.

## Deployments

After each new deployment, you should notify New Relic so they can report on metric variances across multiple releases. The package includes a command to do this:

```php
php artisan new-relic:deploy [description] [revision]
```

If you don't provide a git revision hash, the package can attempt to auto-detect it by calling `git log --pretty="%H" -n1 HEAD`

---

### To-Do

1. Improve the loggable transactions, make it clearer that HTTP transactions will end automatically
2. Add some tests
3. Hopefully someone can confirm if this works with Octane?

### Contributing

All contributions are welcome! And if you found this useful, I'd love to know.

### Credits

- [Jack Webb-Heller](https://github.com/JackWH)
- [All Contributors](../../contributors)

### License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
