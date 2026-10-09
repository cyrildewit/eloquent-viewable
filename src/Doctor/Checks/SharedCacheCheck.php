<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreBursts;
use CyrildeWit\EloquentViewable\Recording\Guards\ThrottleVisitors;
use CyrildeWit\EloquentViewable\Support\Config;
use Generator;
use Illuminate\Contracts\Config\Repository;

/**
 * Cooldowns, the throttle, the burst guard and the fingerprint salt only work
 * when every server that records views reads the same cache, and the counts
 * `remember()` keeps are only flushed everywhere when the servers share it.
 */
class SharedCacheCheck implements Check
{
    /** @var list<string> */
    public const array ForgetfulDrivers = ['array', 'null'];

    /** @var list<string> */
    public const array LocalDrivers = ['file', 'apc', 'octane'];

    public function __construct(
        protected Config $config,
        protected Repository $repository,
    ) {}

    public function name(): string
    {
        return 'Shared cache stores';
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     */
    public function run(): Generator
    {
        yield from $this->cooldowns();

        if (in_array(ThrottleVisitors::class, $this->config->guards(), true)) {
            yield $this->store('Throttle', 'recording.throttle.store', $this->config->throttleCacheStore());
        }

        if (in_array(IgnoreBursts::class, $this->config->guards(), true)) {
            yield $this->store('Burst guard', 'recording.bursts.store', $this->config->burstCacheStore());
        }

        if ($this->hashesFingerprints()) {
            yield $this->store('Fingerprint salt', 'visitor.fingerprint.store', $this->config->fingerprintCacheStore());
        }

        if ($this->config->sampleEnabled()) {
            yield $this->store('Samples', 'doctor.sample.store', $this->config->sampleCacheStore());
        }

        yield $this->store('Remembered counts', 'querying.cache.store', $this->config->cacheStore(), critical: false);
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     */
    protected function cooldowns(): Generator
    {
        $store = $this->config->cooldownStore();

        if ($store === 'cache') {
            yield $this->store('Cooldowns', 'cooldown.cache.store', $this->config->cooldownCacheStore());

            return;
        }

        if ($store !== 'session') {
            return;
        }

        $driver = $this->repository->get('session.driver');

        if (! is_string($driver)) {
            return;
        }

        if ($driver === 'array') {
            yield Finding::failure(
                'Cooldowns: kept in the session, whose `array` driver forgets them after every request.',
                'Use another session driver, or set `cooldown.store` to `cache`.',
            );

            return;
        }

        yield Finding::pass("Cooldowns: kept in the `{$driver}` session.");
    }

    /**
     * The fingerprint identity hashes under the rotating salt, and so does the
     * burst guard when it keys on the network.
     *
     * @throws InvalidConfiguration
     */
    protected function hashesFingerprints(): bool
    {
        if ($this->config->visitorIdentity() === 'fingerprint') {
            return true;
        }

        if (! in_array(IgnoreBursts::class, $this->config->guards(), true)) {
            return false;
        }

        return in_array('network', $this->config->burstKeys(), true);
    }

    /**
     * A store that forgets everything after the request breaks what relies on
     * it. A store kept on one server only breaks it once there are more.
     */
    protected function store(string $label, string $key, ?string $name, bool $critical = true): Finding
    {
        $name ??= $this->defaultStore();
        $driver = $this->repository->get("cache.stores.{$name}.driver");

        if (! is_string($driver)) {
            return Finding::failure(
                "{$label}: the `{$name}` cache store is not defined in `config/cache.php`.",
                "Define the store, or name another one in `eloquent-viewable.{$key}`.",
            );
        }

        $fix = "Name a store every server shares, such as `redis` or `database`, in `eloquent-viewable.{$key}`.";

        if (in_array($driver, self::ForgetfulDrivers, true)) {
            $summary = "{$label}: the `{$name}` cache store uses the `{$driver}` driver, which forgets everything after the request.";

            return $critical
                ? Finding::failure($summary, $fix)
                : Finding::warning($summary, $fix);
        }

        if (in_array($driver, self::LocalDrivers, true)) {
            $summary = "{$label}: the `{$name}` cache store uses the `{$driver}` driver, which is not shared between servers.";

            return $critical
                ? Finding::warning($summary, "Ignore this on a single server. {$fix}")
                : Finding::advice($summary, "Ignore this on a single server. {$fix}");
        }

        return Finding::pass("{$label}: the `{$name}` cache store can be shared between servers.");
    }

    protected function defaultStore(): string
    {
        $default = $this->repository->get('cache.default');

        return is_string($default) ? $default : 'file';
    }
}
