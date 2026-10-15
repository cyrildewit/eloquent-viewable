<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use Illuminate\Http\Request;

/**
 * How the visitor reached the page: the host of the referring page and the
 * query parameters of the page itself. Read from the request of the page, or
 * from what the beacon posts when the request is the beacon's own.
 */
final readonly class Arrival
{
    /**
     * @param  ?string  $referrer  the host of the referring page, lowercased
     * @param  array<string, string>  $landing
     */
    public function __construct(
        public ?string $referrer = null,
        public array $landing = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        $referrer = $request->headers->get('Referer');
        $landing = [];

        foreach ($request->query->all() as $name => $value) {
            if (is_string($value)) {
                $landing[$name] = $value;
            }
        }

        return new self(self::hostOf($referrer), $landing);
    }

    /**
     * Builds the arrival from a referring URL and a query string such as
     * `utm_source=newsletter&ref=hn`, keeping only `ref` and the `utm_`
     * parameters, which is what the beacon posts.
     */
    public static function fromUrls(?string $referrer, ?string $query): self
    {
        parse_str($query ?? '', $values);

        $landing = [];

        foreach ($values as $name => $value) {
            if (! is_string($value)) {
                continue;
            }

            if (self::isTracked((string) $name)) {
                $landing[(string) $name] = $value;
            }
        }

        return new self(self::hostOf($referrer), $landing);
    }

    /**
     * The lowercased host of a URL, or null when it has none, such as an empty
     * referrer or a path alone.
     */
    public static function hostOf(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return null;
        }

        return strtolower($host);
    }

    private static function isTracked(string $name): bool
    {
        if ($name === 'ref') {
            return true;
        }

        return str_starts_with($name, 'utm_');
    }
}
