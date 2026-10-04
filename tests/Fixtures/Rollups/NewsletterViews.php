<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Rollups;

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Rollup;
use Illuminate\Database\Eloquent\Builder;

final class NewsletterViews extends Rollup
{
    #[\Override]
    public string $name = 'newsletter';

    /** @return array<string, string|null> */
    public function tiers(): array
    {
        return ['day' => null, 'month' => null];
    }

    /** @param  Builder<View>  $views */
    public function filter(Builder $views): void
    {
        $views->getQuery()->where('context->source', 'newsletter');
    }

    public function dimension(): string
    {
        return 'context->campaign';
    }
}
