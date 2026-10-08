<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions\Sources;

use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Support\Config;

/**
 * Names the referring host of a view and the medium it belongs to, from the
 * list shipped in `resources/dimensions/sources.php` with the hosts and
 * aliases in config laid over it.
 */
final readonly class SourceList
{
    /**
     * @param  array<string, array{string, string}>  $hosts  a source name and medium by host
     * @param  array<string, string>  $aliases  a source name by lowercased `utm_source` value
     */
    public function __construct(
        private array $hosts = [],
        private array $aliases = [],
    ) {}

    public static function shipped(): self
    {
        /** @var array{hosts: array<string, array{string, string}>, aliases: array<string, string>} $list */
        $list = require __DIR__.'/../../../resources/dimensions/sources.php';

        return new self($list['hosts'], $list['aliases']);
    }

    /** @throws InvalidConfiguration */
    public static function fromConfig(Config $config): self
    {
        $shipped = self::shipped();

        return new self(
            [...$shipped->hosts, ...$config->sourceHosts()],
            [...$shipped->aliases, ...$config->sourceAliases()],
        );
    }

    /**
     * The source name and medium of the host, or null when the list does not
     * know it. The host is matched in full, then with one label taken off the
     * front at a time, and only then against the `name.*` rules.
     *
     * @return ?array{string, string}
     */
    public function find(string $host): ?array
    {
        $candidates = $this->candidates(DimensionInput::withoutWww(strtolower($host)));

        foreach ($candidates as $candidate) {
            if (isset($this->hosts[$candidate])) {
                return $this->hosts[$candidate];
            }
        }

        foreach ($candidates as $candidate) {
            $wildcard = strtok($candidate, '.').'.*';

            if (isset($this->hosts[$wildcard])) {
                return $this->hosts[$wildcard];
            }
        }

        return null;
    }

    /**
     * The source name a `utm_source` or `ref` value stands for, or null when
     * it is not a known spelling.
     */
    public function alias(string $value): ?string
    {
        return $this->aliases[strtolower($value)] ?? null;
    }

    /**
     * The host and each of its parents down to two labels, longest first.
     *
     * @return list<string>
     */
    private function candidates(string $host): array
    {
        $labels = explode('.', $host);
        $candidates = [$host];

        while (count($labels) > 2) {
            array_shift($labels);

            $candidates[] = implode('.', $labels);
        }

        return $candidates;
    }
}
