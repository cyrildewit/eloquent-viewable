---
title: "Trending curves"
description: "A decay curve decides how much a view of a given age is worth in a trending ranking. Implement DecayCurve to give recent views a shape of your own."
sidebar:
  order: 8
---

A curve decides how much a view of a given age is worth. Implement `Querying\Ranking\DecayCurve`, for example to give
every view full weight for its first six hours before it starts to fade:

```php
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;

final readonly class PlateauDecay implements DecayCurve
{
    public function __construct(
        private CarbonInterval $plateau,
        private CarbonInterval $halfLife,
    ) {}

    public function weight(CarbonInterval $age): float
    {
        $faded = max(0, $age->totalSeconds - $this->plateau->totalSeconds);

        return 0.5 ** ($faded / $this->halfLife->totalSeconds);
    }

    public function horizon(): CarbonInterval
    {
        return $this->plateau->copy()->add($this->halfLife->copy()->times(8));
    }

    public function identity(): string
    {
        return "plateau:{$this->plateau->totalSeconds}:{$this->halfLife->totalSeconds}";
    }
}
```

Pass it to a call with `trending(curve: new PlateauDecay(...))`, or set `querying.trending.curve` to its class and bind
its arguments in a service provider:

```php
$this->app->bind(PlateauDecay::class, fn (): PlateauDecay => new PlateauDecay(
    CarbonInterval::hours(6),
    CarbonInterval::day(),
));
```

- `weight()` returns a number from 0 to 1. Anything else throws `InvalidDecay`.
- `horizon()` is how far back to read when the period has no start. Pick the age where the weight is close to 0.
- `identity()` keeps remembered rankings of two curves apart. Include every parameter.

The curve is all you write. The package turns its weights into SQL for every database driver and source, and for
`Views::fake()`. A score that isn't a sum of weights by age, such as views divided by the age of the post, can't be a
curve. Write that with `withViewsCount()` and `orderByRaw()` on your own query.
