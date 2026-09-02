<?php

declare(strict_types=1);

namespace JackWH\LaravelNewRelic;

/**
 * This class is an abstraction of a New Relic transaction.
 * It's used to build a transaction within a Laravel request,
 * before its details are passed to New Relic's native functions.
 */
class NewRelicTransaction
{
    public bool $isIgnored = false;

    public string $name = 'transaction';

    /** @var array<string, int|float|string> */
    public array $parameters = [];

    public bool $isBackground = false;

    /**
     * Set up a new transaction, with some sensible defaults.
     */
    public function __construct(public bool $isActive = true)
    {
        $this->isBackground = app()->runningInConsole();
    }

    /**
     * Tell New Relic the transaction is running in the CLI.
     */
    public function background(): self
    {
        if ($this->isIgnored()) {
            return $this;
        }

        newrelic_background_job();

        $this->isBackground = true;

        return $this;
    }

    /**
     * Ignore this transaction from further reporting.
     */
    public function ignore(): self
    {
        if (!$this->isIgnored()) {
            newrelic_ignore_transaction();

            $this->name = 'transaction';
            $this->isIgnored = true;
            $this->isActive = false;
        }

        return $this;
    }

    /**
     * Check if the transaction is being ignored.
     */
    public function isIgnored(): bool
    {
        return $this->isIgnored;
    }

    /**
     * Set the name for this transaction.
     */
    public function setName(string $name): self
    {
        $name = trim($name, '/ ');

        if ($this->name === $name || $this->isActive($name)) {
            return $this;
        }

        $this->name = $name;
        newrelic_name_transaction($this->name);

        return $this;
    }

    /**
     * Add a custom parameter to the transaction.
     */
    public function addParameter(
        string $key,
        int|float|string|null $value
    ): self {
        if ($this->isIgnored()) {
            return $this;
        }

        if ($value !== null && (!$this->hasParameter($key, $value))) {
            newrelic_add_custom_parameter($key, $value);
            $this->parameters[$key] = $value;
        }

        return $this;
    }

    /**
     * Check if the transaction has a specific parameter set.
     */
    public function hasParameter(
        string $key,
        int|float|string|null $value
    ): bool {
        return array_key_exists($key, $this->parameters)
            && ($value === null || $this->parameters[$key] === $value);
    }

    /**
     * Start the transaction with a given name.
     */
    public function start(string $name): self
    {
        // If the same transaction is already active, continue.
        if ($this->isActive($name)) {
            return $this;
        }

        $this->isIgnored = false;
        $this->isActive = true;

        $this->end();
        newrelic_start_transaction(config('new-relic.app_name'));
        $this->setName($name);

        return $this;
    }

    /**
     * End the transaction, and reset it back to its default state.
     * Specify $ifNamed to end it only if it has a specific name.
     */
    public function end(?string $ifNamed = null): self
    {
        if ($this->isActive($ifNamed)) {
            newrelic_end_transaction();

            $this->name = 'transaction';
            $this->parameters = [];
            $this->isBackground = app()->runningInConsole();
            $this->isActive = false;
            $this->isIgnored = false;
        }

        return $this;
    }

    /**
     * Check if the transaction is active, optionally filtered to a specific
     * name.
     */
    public function isActive(?string $withName = null): bool
    {
        return $this->isActive
            && (!$this->isIgnored)
            && ($withName === null || $this->name === $withName);
    }

    /**
     * Get a unique identifier for this NewRelicTransaction instance.
     * This is used in loggable environments, to give an insight into
     * which transactions are being started/stopped at a given moment.
     */
    public function identifier(bool $withObjId = true): string
    {
        return $this->name . ($withObjId ? ' [' . spl_object_id(
            $this
        ) . ']' : '');
    }

}
