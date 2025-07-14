<?php

namespace App\Messenger\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * CircuitBreakerService
 *
 * Implements circuit breaker pattern for provider reliability
 * Prevents cascading failures by temporarily disabling failed providers
 */
class CircuitBreakerService
{
    protected const CACHE_PREFIX = 'messenger:circuit_breaker:';
    protected const DEFAULT_FAILURE_THRESHOLD = 5;
    protected const DEFAULT_TIMEOUT = 300; // 5 minutes
    protected const DEFAULT_HALF_OPEN_TIMEOUT = 60; // 1 minute

    protected array $config;

    public function __construct()
    {
        $this->config = config('messenger.circuit_breaker', [
            'failure_threshold' => self::DEFAULT_FAILURE_THRESHOLD,
            'timeout' => self::DEFAULT_TIMEOUT,
            'half_open_timeout' => self::DEFAULT_HALF_OPEN_TIMEOUT,
        ]);
    }

    /**
     * Check if provider is available
     */
    public function isAvailable(string $provider): bool
    {
        $state = $this->getState($provider);

        switch ($state['status']) {
            case 'closed':
                return true;

            case 'open':
                // Check if timeout has passed to move to half-open
                if ($this->shouldTransitionToHalfOpen($provider, $state)) {
                    $this->transitionToHalfOpen($provider);
                    return true;
                }
                return false;

            case 'half_open':
                return true;

            default:
                return true;
        }
    }

    /**
     * Record successful operation
     */
    public function recordSuccess(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state['status'] === 'half_open') {
            // Success in half-open state, transition to closed
            $this->transitionToClosed($provider);
            Log::info('Circuit breaker closed for provider', ['provider' => $provider]);
        } elseif ($state['status'] === 'closed') {
            // Reset failure count on success
            $this->resetFailureCount($provider);
        }
    }

    /**
     * Record failed operation
     */
    public function recordFailure(string $provider): void
    {
        $state = $this->getState($provider);
        $failureCount = $state['failure_count'] + 1;

        // Update failure count
        $this->setFailureCount($provider, $failureCount);
        $this->setLastFailureTime($provider, now());

        Log::warning('Circuit breaker recorded failure', [
            'provider' => $provider,
            'failure_count' => $failureCount,
            'threshold' => $this->config['failure_threshold'],
        ]);

        // Check if we should transition to open
        if ($failureCount >= $this->config['failure_threshold']) {
            $this->transitionToOpen($provider);
            Log::error('Circuit breaker opened for provider', [
                'provider' => $provider,
                'failure_count' => $failureCount,
            ]);
        }
    }

    /**
     * Get circuit breaker status for provider
     */
    public function getStatus(string $provider): string
    {
        return $this->getState($provider)['status'];
    }

    /**
     * Get failure count for provider
     */
    public function getFailureCount(string $provider): int
    {
        return $this->getState($provider)['failure_count'];
    }

    /**
     * Get last failure time for provider
     */
    public function getLastFailureTime(string $provider): ?Carbon
    {
        $timestamp = Cache::get($this->getKey($provider, 'last_failure'));
        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    /**
     * Reset circuit breaker for provider
     */
    public function reset(string $provider): void
    {
        Cache::forget($this->getKey($provider, 'status'));
        Cache::forget($this->getKey($provider, 'failure_count'));
        Cache::forget($this->getKey($provider, 'last_failure'));
        Cache::forget($this->getKey($provider, 'opened_at'));

        Log::info('Circuit breaker reset for provider', ['provider' => $provider]);
    }

    /**
     * Get circuit breaker state
     */
    protected function getState(string $provider): array
    {
        return [
            'status' => Cache::get($this->getKey($provider, 'status'), 'closed'),
            'failure_count' => Cache::get($this->getKey($provider, 'failure_count'), 0),
            'opened_at' => Cache::get($this->getKey($provider, 'opened_at')),
        ];
    }

    /**
     * Transition to closed state
     */
    protected function transitionToClosed(string $provider): void
    {
        Cache::put($this->getKey($provider, 'status'), 'closed', now()->addDay());
        Cache::forget($this->getKey($provider, 'failure_count'));
        Cache::forget($this->getKey($provider, 'opened_at'));
    }

    /**
     * Transition to open state
     */
    protected function transitionToOpen(string $provider): void
    {
        Cache::put($this->getKey($provider, 'status'), 'open', now()->addDay());
        Cache::put($this->getKey($provider, 'opened_at'), now()->timestamp, now()->addDay());
    }

    /**
     * Transition to half-open state
     */
    protected function transitionToHalfOpen(string $provider): void
    {
        Cache::put($this->getKey($provider, 'status'), 'half_open', now()->addDay());
        Cache::put($this->getKey($provider, 'opened_at'), now()->timestamp, now()->addDay());
    }

    /**
     * Check if should transition from open to half-open
     */
    protected function shouldTransitionToHalfOpen(string $provider, array $state): bool
    {
        if (!isset($state['opened_at'])) {
            return false;
        }

        $openedAt = Carbon::createFromTimestamp($state['opened_at']);
        $timeout = $this->config['timeout'];

        return $openedAt->addSeconds($timeout)->isPast();
    }

    /**
     * Set failure count
     */
    protected function setFailureCount(string $provider, int $count): void
    {
        Cache::put($this->getKey($provider, 'failure_count'), $count, now()->addDay());
    }

    /**
     * Reset failure count
     */
    protected function resetFailureCount(string $provider): void
    {
        Cache::forget($this->getKey($provider, 'failure_count'));
    }

    /**
     * Set last failure time
     */
    protected function setLastFailureTime(string $provider, Carbon $time): void
    {
        Cache::put($this->getKey($provider, 'last_failure'), $time->timestamp, now()->addDay());
    }

    /**
     * Get cache key
     */
    protected function getKey(string $provider, string $type): string
    {
        return self::CACHE_PREFIX . $provider . ':' . $type;
    }

    /**
     * Get all provider statuses
     */
    public function getAllStatuses(): array
    {
        $providers = array_keys(config('messenger.providers', []));
        $statuses = [];

        foreach ($providers as $provider) {
            $statuses[$provider] = [
                'status' => $this->getStatus($provider),
                'available' => $this->isAvailable($provider),
                'failure_count' => $this->getFailureCount($provider),
                'last_failure' => $this->getLastFailureTime($provider)?->toISOString(),
            ];
        }

        return $statuses;
    }

    /**
     * Force provider state for testing
     */
    public function forceState(string $provider, string $state, int $failureCount = 0): void
    {
        Cache::put($this->getKey($provider, 'status'), $state, now()->addDay());

        if ($failureCount > 0) {
            Cache::put($this->getKey($provider, 'failure_count'), $failureCount, now()->addDay());
        }

        if ($state === 'open') {
            Cache::put($this->getKey($provider, 'opened_at'), now()->timestamp, now()->addDay());
        }
    }
}
