<?php

declare(strict_types=1);

namespace JackWH\LaravelNewRelic\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JackWH\LaravelNewRelic\NewRelicTransaction;
use JackWH\LaravelNewRelic\NewRelicTransactionHandler;

class NewRelicMiddleware
{
    protected ?Authenticatable $user = null;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Ensure New Relic is enabled before continuing.
        if (! app(NewRelicTransactionHandler::class)::newRelicEnabled()) {
            return $next($request);
        }

        // Set the default transaction name for New Relic.
        // This will be executed before the request is handled, so we can't
        // yet resolve users or route names. We'll come back to that later.
        app(NewRelicTransaction::class)
            ->setName($this->requestName($request))
            // Record the IP address, if configured.
            ->addParameter(
                'ip_address',
                config('new-relic.http.visitors.record_ip_address') ? $request->ip() : null
            );

        // Tell the application to handle the incoming request before continuing...
        $response = $next($request);

        // Skip further New Relic configuration if required.
        if ((request()->is($this->ignoredRoutes()))
            || (request()->routeIs($this->ignoredRoutes()))
            || (request()->fullUrlIs($this->ignoredRoutes()))) {
            app(NewRelicTransaction::class)->ignore();

            return $response;
        }

        // With the response now prepared, we can access the authenticated user.
        if (config('new-relic.http.visitors.record_user_id')) {
            $this->user = Auth::user();
        }

        // Add custom parameters to the transaction.
        app(NewRelicTransaction::class)
            ->addParameter(
                'user_type',
                $this->user instanceof Authenticatable ? 'User' : config('new-relic.http.visitors.guest_label')
            )->addParameter(
                'user_id',
                $this->user?->getAuthIdentifier(),
            );

        // If the request name resolves differently, update it.
        app(NewRelicTransaction::class)->setName($this->requestName($request));

        // Return the previous response and continue.
        return $response;
    }

    /**
     * Get the name of the current request. This may change during the request lifecycle,
     * so this method is called twice, both before and after the request is handled.
     */
    protected function requestName(Request $request): string
    {
        return config('new-relic.http.prefix') . (
            $this->getCustomTransactionName($request)
                ?? $this->getLivewireTransactionName($request)
                ?? $request->route()?->getName()
                ?? $request->route()?->getActionName()
                ?? $request->path()
        );
    }

    /**
     * An array of routes where this middleware shouldn't be applied.
     *
     * @return array<int, string>
     */
    protected function ignoredRoutes(): array
    {
        return array_merge(config('new-relic.http.ignore'), [
            //
        ]);
    }

    /**
     * Rewrite any custom transaction names, by path => name.
     *
     * @return array<string, string>
     */
    protected function mapCustomTransactionNames(): array
    {
        return array_merge(config('new-relic.http.rewrite'), [
            //
        ]);
    }

    /**
     * Get a custom name for a transaction by the currently-requested URI.
     */
    protected function getCustomTransactionName(Request $request): ?string
    {
        return collect($this->mapCustomTransactionNames())
            ->mapWithKeys(static fn (string $name, string $path): array => [
                (Str::of($path)->trim('/')->toString() ?: '/') => $name,
            ])->get(
                Str::of($request->path())->trim('/')->toString() ?: '/'
            );
    }

    /**
     * If the current request is to Livewire's messaging endpoint, set a custom name from the component.
     */
    protected function getLivewireTransactionName(Request $request): ?string
    {
        if (! $request->routeIs('livewire.message')) {
            return null;
        }

        return 'livewire.' . $request->route()->parameter('name', 'message');
    }
}
