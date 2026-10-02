<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Models;

use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Factories\ApartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Apartment extends Model implements Viewable
{
    /** @use HasFactory<ApartmentFactory> */
    use HasFactory, InteractsWithViews;

    #[\Override]
    protected $guarded = [];

    protected static function newFactory(): ApartmentFactory
    {
        return ApartmentFactory::new();
    }
}
