<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions;

use CyrildeWit\EloquentViewable\Dimensions\Contracts\CountryResolver;
use CyrildeWit\EloquentViewable\Dimensions\Countries\CloudflareCountry;
use CyrildeWit\EloquentViewable\Dimensions\Countries\HeaderCountry;
use Illuminate\Container\Container;
use InvalidArgumentException;

/**
 * The ISO 3166-1 alpha-2 code of the visitor's country, uppercased, from a
 * country resolver. `CloudflareCountry` reads the `CF-IPCountry` header and
 * `HeaderCountry` the header in the `header` option. The IP address is never
 * stored.
 */
final class Country extends Dimension
{
    private readonly CountryResolver $resolver;

    /**
     * @param  class-string<CountryResolver>  $resolver
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $resolver = CloudflareCountry::class,
        ?string $header = null,
        bool $personal = false,
        ?int $maxValues = self::MaxValues,
        ?string $json = null,
    ) {
        parent::__construct($personal, $maxValues, $json);

        $this->resolver = $this->resolver($resolver, $header);
    }

    public function resolve(DimensionInput $input): ?string
    {
        $country = strtoupper(trim($this->resolver->country($input) ?? ''));

        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            return null;
        }

        return $country;
    }

    /** @throws InvalidArgumentException */
    private function resolver(string $class, ?string $header): CountryResolver
    {
        $parameters = $header === null ? [] : ['header' => $header];
        $resolver = Container::getInstance()->make($class, $parameters);

        if (! $resolver instanceof CountryResolver) {
            $contract = CountryResolver::class;

            throw new InvalidArgumentException("The country resolver `{$class}` must implement `{$contract}`.");
        }

        if ($header === null) {
            return $resolver;
        }

        if (! $resolver instanceof HeaderCountry) {
            throw new InvalidArgumentException("The `header` option only applies to `HeaderCountry`, not to `{$class}`.");
        }

        return $resolver;
    }
}
