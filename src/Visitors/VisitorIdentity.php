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
 * identified by a fingerprint that rotates daily, weekly or monthly and no
 * cookie is set.
 */
final readonly class VisitorIdentity
{
    public const string Cookie = 'cookie';

    public const string Viewer = 'viewer';

    public const string Fingerprint = 'fingerprint';

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

        if ($viewer instanceof Model && $identity !== self::Cookie) {
            return $this->ofViewer($viewer);
        }

        if ($identity === self::Fingerprint) {
            return $this->fingerprint->of($visitor);
        }

        return $visitor->id();
    }

    /**
     * Keyed with the application key, so the column does not reveal the key
     * of the model on its own.
     *
     * @throws InvalidViewer
     */
    public function ofViewer(Model $viewer): string
    {
        return $this->ofViewerKey($viewer->getMorphClass(), ViewerKey::of($viewer));
    }

    /**
     * It returns the id ofViewer() gives for a viewer known only by its morph
     * type and key, such as one that was deleted.
     */
    public function ofViewerKey(string $type, int|string $key): string
    {
        return hash_hmac('sha256', "{$type}|{$key}", $this->encrypter->getKey());
    }
}
