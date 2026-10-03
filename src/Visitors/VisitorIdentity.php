<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Visitors;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Eloquent\Model;

/**
 * The value of the `visitor` column, which `unique()` counts and a cooldown
 * is keyed on. With `visitor.identity` set to `viewer` it is derived from the
 * signed-in model instead of the cookie, so one account is one visitor on
 * every device and on an API without a cookie. With `fingerprint` a guest is
 * identified by a daily fingerprint and no cookie is set.
 */
final readonly class VisitorIdentity
{
    public const string COOKIE = 'cookie';

    public const string VIEWER = 'viewer';

    public const string FINGERPRINT = 'fingerprint';

    public function __construct(
        private Config $config,
        private Encrypter $encrypter,
        private Fingerprint $fingerprint,
    ) {}

    /**
     * @throws InvalidConfiguration
     * @throws InvalidViewer
     */
    public function of(Visitor $visitor, ?Model $viewer): string
    {
        $identity = $this->config->visitorIdentity();

        if ($viewer instanceof Model && $identity !== self::COOKIE) {
            return $this->ofViewer($viewer);
        }

        // Only the cookie id is read from the visitor here, so a fingerprint
        // never queues the cookie.
        return $identity === self::FINGERPRINT ? $this->fingerprint->of($visitor) : $visitor->id();
    }

    /**
     * Keyed with the application key, so the column does not reveal the key
     * of the model on its own.
     *
     * @throws InvalidViewer
     */
    public function ofViewer(Model $viewer): string
    {
        return hash_hmac('sha256', $viewer->getMorphClass().'|'.ViewerKey::of($viewer), $this->encrypter->getKey());
    }
}
