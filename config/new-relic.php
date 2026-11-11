<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | New Relic configuration for Laravel applications
    |--------------------------------------------------------------------------
    |
    | Please consult the docs before configuring, especially if you already
    | have data for this application in your New Relic account. This package
    | makes some opinionated choices about naming conventions and filtering,
    | which vary from how New Relic reports Laravel transactions normally.
    | It attempts to normalise transaction names in a more consistent
    | Laravel-style format. Links to docs and guidance is provided below:
    |
    |       Package:   https://github.com/JackWH/laravel-new-relic/README.md
    |     New Relic:   https://docs.newrelic.com/docs/agents/php-agent/
    |
    |--------------------------------------------------------------------------
    |
    | Set up some basic details about your New Relic installation.
    |
    | app_name: This will be determined automatically from the New Relic agent,
    |           but you can customise if you need to. Note that doing so will
    |           change the APM name for Laravel requests in New Relic's UI.
    |
    | api_key:  Optional, but required to use the New Relic deployments feature.
    |
    */
    'app_name' => ini_get('newrelic.appname') ?: env('APP_NAME', 'laravel'),

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | environments: Set which environments you'd expect New Relic to be
    |               installed in. The package will only run in these
    |               environments.
    |
    | loggable:     When testing this package in one of these environments,
    |               we can bootstrap the New Relic functions, and write logs
    |               indicating what would be happening instead. Useful for
    |               ensuring this package is set up properly before deploying.
    |               Comment out 'local' after testing to avoid excessive logging.
    |
    */
    'environments' => [
        'production',
    ],
    'loggable' => [
        'local',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Requests
    |--------------------------------------------------------------------------
    |
    | middleware: For HTTP requests, we'll apply the package NewRelicMiddleware
    |             to create transactions in New Relic. If you want to customise
    |             the default middleware, extend it and provide your custom
    |             classname. Middleware will be applied automatically, but you
    |             may want to set its priority in app/Http/Kernel.php's
    |             $middlewarePriory array, to apply it as early in the stack
    |             as possible. If you want to capture user attributes, it
    |             must come *after* \Illuminate\Auth\Middleware\Authorize.
    |
    | visitors:   If 'record_user_id' is true, we'll save the user's ID in the
    |             transaction. If no user is logged in, the transaction will
    |             show with as "Guest" (or your preferred 'guest_label'). Set
    |             guest_label to null if you don't want to report this. You
    |             can also optionally log the visitor's IP address too.
    |
    | rewrite:    Specify any paths which should be given a custom transaction
    |             name in New Relic. This is useful for when you have a route
    |             which doesn't have a name (for example, in a third-party
    |             package), and want to name it consistently without falling
    |             back to the full controller class and action (see below).
    |
    | prefix:     HTTP transactions will be identified in New Relic by the name
    |             of their route, or if the route doesn't have a name, by their
    |             controller action. Here you can also specify an optional
    |             prefix to identify HTTP requests. For example, "Route " would
    |             label HTTP requests in New Relic as "Route admin.dashboard".
    |
    | ignore:     Set which HTTP requests should be ignored by New Relic.
    |
    */
    'http' => [
        'middleware' => \JackWH\LaravelNewRelic\Middleware\NewRelicMiddleware::class,

        'visitors' => [
            'record_user_id' => true,
            'record_ip_address' => false,
            'guest_label' => 'Guest',
        ],

        'rewrite' => [
            '/livewire/livewire.js' => 'livewire.js',
            '/livewire/livewire.js.map' => 'livewire.js.map',
        ],

        'prefix' => '',

        'ignore' => [
            'debugbar.**',
            'horizon.**',
            'telescope.**',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Artisan Commands
    |--------------------------------------------------------------------------
    |
    | prefix:   Artisan commands will be identified in New Relic by the base
    |           command name (i.e. "php artisan migrate:rollback --force" will
    |           appear as "migrate:rollback". You can specify an optional
    |           prefix to label Artisan commands with, for example, "Artisan "
    |           would label the command as "Artisan migrate:rollback".
    |
    | ignore:   If you want to New Relic to ignore transactions for specific
    |           Artisan commands, enter the command names here. Some sensible
    |           defaults have been set already, to prevent New Relic skewing
    |           runtime stats for background, noisy, or long-running processes.
    |
    */
    'artisan' => [
        'prefix' => '',

        'ignore' => [
            'db',
            'dusk',
            'horizon',
            'horizon:supervisor',
            'horizon:work',
            'queue:listen',
            'queue:work',
            'schedule:run',
            'schedule:finish',
            'schedule:work',
            'serve',
            'telescope:stream',
            'test',
            'tinker',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Handling
    |--------------------------------------------------------------------------
    |
    | record_transactions: Whether the package starts and ends a transaction
    |           around each queued job. The New Relic PHP agent has its own
    |           Laravel queue instrumentation that does the same, and the two
    |           collide: with agent 12.12, log records written inside jobs
    |           are lost. Set this to false to leave job transactions to the
    |           agent. Then also remove `queue:work` and `horizon:work` from
    |           'artisan.ignore', because the agent only records jobs inside
    |           a transaction that is not ignored. The package still closes
    |           the transaction of a timed-out job and ignores the worker's
    |           idle time once it stops.
    |
    | prefix:   Queued jobs will be identified in New Relic by calling the
    |           \Illuminate\Queue\Jobs\Job->resolveName() method, or if
    |           unavailable, using Illuminate\Queue\Jobs\Job->getName().
    |           You can specify an optional prefix to label queued jobs with,
    |           e.g "Queue " would label a job as "Queue App\Jobs\ExampleJob".
    |
    | ignore:   If you want New Relic to ignore transactions for specific
    |           connections, queue names, or job classes, enter them here.
    |
    */
    'queue' => [
        'record_transactions' => true,

        'prefix' => '',

        'ignore' => [
            'connections' => ['sync'],
            'queues' => [],
            'jobs' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled Tasks
    |--------------------------------------------------------------------------
    |
    | prefix:   Scheduled tasks will be identified in New Relic by the task's
    |           name, e.g. $schedule->command('db:backup')->name('Backup DB').
    |           If unavailable, the task's command name will be used instead.
    |           You can specify an optional prefix to label scheduled tasks
    |           with, e.g "Task " would label as "Task Backup DB" (or just
    |           "Task db:backup" if no name has been set in the scheduler).
    |
    | ignore:   If you want New Relic to ignore transactions for specific
    |           scheduled tasks, enter the task names/descriptions here.
    |           By using task names, closure-based tasks can be ignored too.
    |           Use $schedule->command('...')->name('example') to set a name
    |           for a task in app/Console/Kernel.php. Tasks will be ignored
    |           if their name matches this configuration value, but also if
    |           a scheduled task executes a command or job which was already
    |           ignored in the Artisan or Queue configuration sections.
    |
    */
    'scheduler' => [
        'prefix' => '',

        'ignore' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployments
    |--------------------------------------------------------------------------
    |
    | Provide a New Relic API key and your APM application ID to use the
    | Deployment Notification command.
    |
    | endpoint:     If you have an EU account, you might need to change this to
    |               https://api.eu.newrelic.com/v2/ (check your account)
    |
    | user:         The user email address to log the deployment with.
    |
    | detect_hash:  If true, and no git commit hash is passed to the command,
    |               the package will attempt to auto-detect the revision.
    */
    'deployments' => [
        'api_key' => env('NEW_RELIC_API_KEY'),
        'app_id' => env('NEW_RELIC_APP_ID'),
        'endpoint' => env('NEW_RELIC_API_ENDPOINT', 'https://api.newrelic.com/v2/'),

        'user' => 'you@example.com',

        'detect_hash' => true,
    ],

];
