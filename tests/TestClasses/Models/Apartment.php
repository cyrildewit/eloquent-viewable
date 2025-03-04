<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\TestClasses\Models;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\InteractsWithViews;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Factories\ApartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Apartment extends Model implements Viewable
{
    use HasFactory, InteractsWithViews;

    protected $guarded = [];

    protected static function newFactory(): ApartmentFactory
    {
        return ApartmentFactory::new();
    }
}
