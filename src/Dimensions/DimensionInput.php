<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;
use Illuminate\Database\Eloquent\Model;

/**
 * What a dimension resolves its value from. It is built once per view and
 * shared by every dimension. In a request the referrer and the landing page
 * come from the request itself, and from the beacon they come from the page
 * the beacon ran on.
 */
final readonly class DimensionInput
{
    /**
     * @param  ?array<string, mixed>  $context
     * @param  ?string  $referrer  the host of the referring page, lowercased
     * @param  array<string, string>  $landing  the query parameters of the page that was viewed
     * @param  list<string>  $appHosts  the hosts of the application itself
     * @param  array<string, string>  $headers  the request headers, keyed by lowercased name
     */
    public function __construct(
        public Visitor $visitor,
        public ?Viewable $viewable = null,
        public ?string $collection = null,
        public ?array $context = null,
        public ?string $referrer = null,
        public array $landing = [],
        public array $appHosts = [],
        private array $headers = [],
    ) {}

    /**
     * Builds an input for a unit test of a dimension of your own, without a
     * request.
     *
     * @param  ?array<string, mixed>  $context
     * @param  array<string, string>  $landing
     * @param  list<string>  $appHosts
     * @param  array<string, string>  $headers
     */
    public static function fake(
        ?string $userAgent = null,
        ?string $referrer = null,
        array $landing = [],
        array $headers = [],
        ?string $ip = null,
        ?Model $viewer = null,
        ?Viewable $viewable = null,
        ?string $collection = null,
        ?array $context = null,
        array $appHosts = [],
    ): self {
        return new self(
            visitor: new FakeVisitor($userAgent, $ip, $viewer),
            viewable: $viewable,
            collection: $collection,
            context: $context,
            referrer: $referrer === null ? null : strtolower($referrer),
            landing: $landing,
            appHosts: $appHosts,
            headers: array_change_key_case($headers),
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * The trimmed query parameter of the page that was viewed, or null when it
     * is missing or blank.
     */
    public function landing(string $parameter): ?string
    {
        $value = trim($this->landing[$parameter] ?? '');

        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * The referring host, or null when there is none or the visitor came from
     * another page of the application.
     */
    public function externalReferrer(): ?string
    {
        if ($this->referrer === null) {
            return null;
        }

        $host = self::withoutWww($this->referrer);

        foreach ($this->appHosts as $appHost) {
            if (self::withoutWww(strtolower($appHost)) === $host) {
                return null;
            }
        }

        return $this->referrer;
    }

    public static function withoutWww(string $host): string
    {
        if (! str_starts_with($host, 'www.')) {
            return $host;
        }

        return substr($host, 4);
    }
}
