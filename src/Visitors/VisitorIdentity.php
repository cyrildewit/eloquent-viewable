<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Visitors;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;

/**
 * The value of the `visitor` column, which `unique()` counts and a cooldown
 * is keyed on. With `visitor.identity` set to `viewer` it is derived from the
 * signed-in model instead of the cookie, so one account is one visitor on
 * every device and on an API without a cookie.
 */
final readonly class VisitorIdentity
{
    public const string COOKIE = 'cookie';

    public const string VIEWER = 'viewer';

    public function __construct(
        private Config $config,
        private Repository $app,
    ) {}

    /**
     * @throws InvalidConfiguration
     * @throws InvalidViewer
     */
    public function of(Visitor $visitor, ?Model $viewer): string
    {
        if ($viewer instanceof Model && $this->config->visitorIdentity() === self::VIEWER) {
            return $this->ofViewer($viewer);
        }

        return $visitor->id();
    }

    /**
     * Keyed with `app.key`, so the column does not reveal the key of the
     * model on its own.
     *
     * @throws InvalidConfiguration
     * @throws InvalidViewer
     */
    public function ofViewer(Model $viewer): string
    {
        return hash_hmac('sha256', $viewer->getMorphClass().'|'.ViewerKey::of($viewer), $this->appKey());
    }

    /** @throws InvalidConfiguration */
    private function appKey(): string
    {
        $key = $this->app->get('app.key');

        if (! is_string($key) || $key === '') {
            throw InvalidConfiguration::missingAppKey();
        }

        return $key;
    }
}
