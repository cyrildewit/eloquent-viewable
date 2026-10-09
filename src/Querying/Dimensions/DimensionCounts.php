<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Dimensions;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The views per value of one dimension, most first, then by value, so two
 * reads give the same order. Views without a value are counted apart, in
 * `none()`, and views folded away by a rollup's cap or by a limit in
 * `other()`. A share is rounded to three decimals and null when there were
 * no views.
 *
 * With `unique()`, every number counts distinct visitors, so they do not add
 * up: a visitor seen with two values counts once for each and once in the
 * total.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class DimensionCounts implements Arrayable, JsonSerializable
{
    /** @var array<string, int> */
    private array $counts;

    /** @param  array<string, int>  $counts */
    private function __construct(
        array $counts,
        private int $none,
        private int $other,
        private int $total,
    ) {
        $this->counts = $this->sorted($counts);
    }

    /**
     * Without a total, the counts, `none` and `other` are added up, which is
     * right for views but not for unique visitors.
     *
     * @param  array<string, int>  $counts
     */
    public static function from(array $counts, int $none = 0, int $other = 0, ?int $total = null): self
    {
        return new self($counts, $none, $other, $total ?? array_sum($counts) + $none + $other);
    }

    /**
     * @param  array{values: array<string, int>, none: int, other: int, total: int}  $array
     */
    public static function fromArray(array $array): self
    {
        return new self($array['values'], $array['none'], $array['other'], $array['total']);
    }

    /** @return array<string, int> */
    public function all(): array
    {
        return $this->counts;
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(strval(...), array_keys($this->counts));
    }

    public function get(string $value): int
    {
        return $this->counts[$value] ?? 0;
    }

    public function none(): int
    {
        return $this->none;
    }

    public function other(): int
    {
        return $this->other;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function share(string $value): ?float
    {
        if ($this->total === 0) {
            return null;
        }

        return round($this->get($value) / $this->total, 3);
    }

    /**
     * Keeps the first values and adds the rest to `other`. With unique
     * visitors that sum can count a visitor more than once.
     */
    public function limit(?int $limit): self
    {
        if ($limit === null) {
            return $this;
        }

        $kept = array_slice($this->counts, 0, $limit, preserve_keys: true);
        $folded = array_sum(array_diff_key($this->counts, $kept));

        return new self($kept, $this->none, $this->other + $folded, $this->total);
    }

    /**
     * Adds the counts of another read of the same dimension, such as the
     * rollups and the views table each holding part of a period.
     */
    public function add(self $counts): self
    {
        $added = $this->counts;

        foreach ($counts->counts as $value => $count) {
            $added[$value] = ($added[$value] ?? 0) + $count;
        }

        return new self($added, $this->none + $counts->none, $this->other + $counts->other, $this->total + $counts->total);
    }

    /** @return array{values: array<string, int>, none: int, other: int, total: int} */
    public function toArray(): array
    {
        return [
            'values' => $this->counts,
            'none' => $this->none,
            'other' => $this->other,
            'total' => $this->total,
        ];
    }

    /** @return array{values: array<string, int>, none: int, other: int, total: int} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function sorted(array $counts): array
    {
        ksort($counts, SORT_STRING);
        arsort($counts);

        return $counts;
    }
}
