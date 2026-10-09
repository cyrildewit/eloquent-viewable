<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Bots;

/**
 * Finds the views that sit in a burst: a visitor opening more than `max`
 * different viewables within `seconds`, the rule `IgnoreBursts` refuses on.
 * Feed it views in the order they were viewed. A visitor is forgotten once
 * their last view falls out of the window, so memory stays bounded by how many
 * visitors are active at the same moment, not by the size of the table.
 */
final class BurstDetector
{
    private const int SweepEvery = 1000;

    /**
     * Keyed by visitor. PHP turns a numeric visitor into an integer key.
     *
     * @var array<array-key, list<array{id: int|string, viewable: string, at: int, marked: bool}>>
     */
    private array $windows = [];

    /** @var array<array-key, int> */
    private array $lastSeen = [];

    /** @var array<array-key, int> */
    private array $bursts = [];

    /** @var array<array-key, true> */
    private array $bursting = [];

    private int $fed = 0;

    public function __construct(
        private readonly int $max,
        private readonly int $seconds,
    ) {}

    /**
     * It returns the views that became part of a burst with this one, keyed by
     * id, with the moment they were viewed.
     *
     * @return array<int|string, int>
     */
    public function feed(int|string $id, string $visitor, string $viewable, int $at): array
    {
        $window = [];

        foreach ($this->windows[$visitor] ?? [] as $view) {
            if ($at - $view['at'] < $this->seconds) {
                $window[] = $view;
            }
        }

        $window[] = ['id' => $id, 'viewable' => $viewable, 'at' => $at, 'marked' => false];

        $marked = $this->distinct($window) > $this->max
            ? $this->mark($visitor, $window)
            : $this->calm($visitor);

        $this->windows[$visitor] = $window;
        $this->lastSeen[$visitor] = $at;

        if (++$this->fed % self::SweepEvery === 0) {
            $this->sweep($at);
        }

        return $marked;
    }

    /**
     * It returns the visitors with at least this many separate bursts.
     *
     * @return list<string>
     */
    public function visitorsWithBursts(int $minimum): array
    {
        $visitors = [];

        foreach ($this->bursts as $visitor => $bursts) {
            if ($bursts >= $minimum) {
                $visitors[] = (string) $visitor;
            }
        }

        return $visitors;
    }

    /** @param  list<array{id: int|string, viewable: string, at: int, marked: bool}>  $window */
    private function distinct(array $window): int
    {
        return count(array_unique(array_column($window, 'viewable')));
    }

    /**
     * @param  list<array{id: int|string, viewable: string, at: int, marked: bool}>  $window
     * @return array<int|string, int>
     */
    private function mark(string $visitor, array &$window): array
    {
        if (! isset($this->bursting[$visitor])) {
            $this->bursting[$visitor] = true;
            $this->bursts[$visitor] = ($this->bursts[$visitor] ?? 0) + 1;
        }

        $marked = [];

        foreach ($window as $index => $view) {
            if (! $view['marked']) {
                $window[$index]['marked'] = true;
                $marked[$view['id']] = $view['at'];
            }
        }

        return $marked;
    }

    /** @return array{} */
    private function calm(string $visitor): array
    {
        unset($this->bursting[$visitor]);

        return [];
    }

    private function sweep(int $now): void
    {
        foreach ($this->lastSeen as $visitor => $at) {
            if ($now - $at >= $this->seconds) {
                unset($this->windows[$visitor], $this->lastSeen[$visitor], $this->bursting[$visitor]);
            }
        }
    }
}
