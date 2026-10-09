<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use CyrildeWit\EloquentViewable\Contracts\ViewerCanOptOut;
use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreOptedOutViewers;
use CyrildeWit\EloquentViewable\Support\Config;
use Generator;
use Illuminate\Contracts\Config\Repository;

/**
 * Settings that are valid on their own but undo each other.
 */
class ConfigurationCheck implements Check
{
    public function __construct(
        protected Config $config,
        protected Repository $repository,
        protected RollupPolicy $rollups,
    ) {}

    public function name(): string
    {
        return 'Configuration';
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     */
    public function run(): Generator
    {
        $conflicts = [
            ...$this->queue(),
            ...$this->beacon(),
            ...$this->source(),
            ...$this->cooldowns(),
            ...$this->optOut(),
        ];

        if ($conflicts === []) {
            yield Finding::pass('No settings undo each other.');

            return;
        }

        yield from $conflicts;
    }

    /**
     * @return list<Finding>
     *
     * @throws InvalidConfiguration
     */
    protected function queue(): array
    {
        if (! $this->config->queueEnabled()) {
            return [];
        }

        $connection = $this->config->queueConnection() ?? $this->repository->get('queue.default');

        if (! is_string($connection)) {
            return [];
        }

        if ($this->repository->get("queue.connections.{$connection}.driver") !== 'sync') {
            return [];
        }

        return [Finding::advice(
            "Views are queued on the `{$connection}` connection, whose `sync` driver stores them during the request anyway.",
            'Name a connection a worker processes in `recording.queue.connection`, or turn `recording.queue.enabled` off.',
        )];
    }

    /** @return list<Finding> */
    protected function beacon(): array
    {
        if (! $this->config->beaconEnabled()) {
            return [];
        }

        if (in_array('web', $this->config->beaconMiddleware(), true)) {
            return [];
        }

        return [Finding::warning(
            'The beacon route runs without the `web` middleware, so it has no session or cookies for cooldowns, the visitor cookie and the signed-in viewer.',
            'Add `web` to `recording.beacon.middleware`, or a group of your own that starts the session and checks the CSRF token.',
        )];
    }

    /**
     * @return list<Finding>
     *
     * @throws InvalidConfiguration
     */
    protected function source(): array
    {
        if ($this->config->sourceDriver() !== 'rollup') {
            return [];
        }

        if ($this->rollups->isEnabled()) {
            return [];
        }

        return [Finding::warning(
            'Counts read through the `rollup` source, but no rollups are configured, so every count reads the views table.',
            'Configure `retention.rollups.tiers`, or set `querying.source.driver` back to `database`.',
        )];
    }

    /**
     * @return list<Finding>
     *
     * @throws InvalidConfiguration
     */
    protected function cooldowns(): array
    {
        if (in_array(EnforceCooldown::class, $this->config->guards(), true)) {
            return [];
        }

        return [Finding::advice(
            '`EnforceCooldown` is not listed in `recording.guards`, so `cooldown()` does nothing.',
            'List it, unless no view is recorded with a cooldown.',
        )];
    }

    /**
     * Only the model of the guard the viewer is read from is known here, so
     * a viewer passed to `viewedBy()` of another class is not looked at.
     *
     * @return list<Finding>
     *
     * @throws InvalidConfiguration
     */
    protected function optOut(): array
    {
        if (in_array(IgnoreOptedOutViewers::class, $this->config->guards(), true)) {
            return [];
        }

        $model = $this->viewerModel();

        if ($model === null || ! is_subclass_of($model, ViewerCanOptOut::class)) {
            return [];
        }

        return [Finding::warning(
            "`{$model}` implements `ViewerCanOptOut`, but `IgnoreOptedOutViewers` is not listed in `recording.guards`, so views of people who opted out are still recorded.",
            'List `IgnoreOptedOutViewers` in `recording.guards`.',
        )];
    }

    /**
     * The model the user provider of the viewer's guard signs people in as.
     */
    protected function viewerModel(): ?string
    {
        $guard = $this->config->viewerGuard() ?? $this->repository->get('auth.defaults.guard');
        $provider = is_string($guard) ? $this->repository->get("auth.guards.{$guard}.provider") : null;
        $model = is_string($provider) ? $this->repository->get("auth.providers.{$provider}.model") : null;

        return is_string($model) ? $model : null;
    }
}
