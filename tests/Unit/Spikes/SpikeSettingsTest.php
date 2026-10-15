<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;
use CyrildeWit\EloquentViewable\Spikes\SpikeSettings;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Config\Repository;

it('takes the defaults for every option left out', function (): void {
    expect(SpikeSettings::fromOptions(Post::class, []))
        ->class->toBe(Post::class)
        ->hours->toBe(1)
        ->seasonality->toBe(Seasonality::Week)
        ->samples->toBe(4)
        ->threshold->toBe(3.0)
        ->minimum->toBe(10)
        ->drops->toBeFalse()
        ->and(SpikeSettings::fromOptions(Post::class, [])->cooldown->shorthand())->toBe('6h');
});

it('reads every option', function (): void {
    $settings = SpikeSettings::fromOptions(Post::class, [
        'window' => '1d',
        'seasonality' => 'week',
        'samples' => 8,
        'threshold' => 2,
        'minimum' => 100,
        'drops' => true,
        'cooldown' => '1d',
    ]);

    expect($settings)
        ->hours->toBe(24)
        ->samples->toBe(8)
        ->threshold->toBe(2.0)
        ->minimum->toBe(100)
        ->drops->toBeTrue()
        ->and($settings->cooldown->shorthand())->toBe('1d');
});

it('reads every watched class from the config', function (): void {
    $config = new Config(new Repository(['eloquent-viewable' => ['spikes' => ['types' => [Post::class => ['window' => '3h']]]]]));

    expect(SpikeSettings::fromConfig($config))->toHaveCount(1)
        ->and(SpikeSettings::fromConfig($config)[0]->hours)->toBe(3);
});

it('refuses an option it cannot use', function (string $option, mixed $value, string $expected): void {
    SpikeSettings::fromOptions(Post::class, [$option => $value]);
})->with([
    'window in minutes' => ['window', '30min', 'whole hours or days'],
    'window not a string' => ['window', 1, 'whole hours or days'],
    'window longer than the season' => ['window', '8d', 'no longer than a week'],
    'unknown seasonality' => ['seasonality', 'month', '`day` or `week`'],
    'samples below one' => ['samples', 0, 'a positive integer'],
    'samples not an integer' => ['samples', '4', 'a positive integer'],
    'threshold of zero' => ['threshold', 0, 'a number above 0'],
    'threshold not a number' => ['threshold', '3', 'a number above 0'],
    'minimum below one' => ['minimum', -1, 'a positive integer'],
    'drops not a boolean' => ['drops', 1, 'true or false'],
    'cooldown not a duration' => ['cooldown', 'soon', 'a duration such as `6h`'],
    'cooldown not a string' => ['cooldown', 6, 'a duration such as `6h`'],
])->throws(InvalidConfiguration::class);

it('refuses a window longer than a day when comparing with days', function (): void {
    SpikeSettings::fromOptions(Post::class, ['window' => '2d', 'seasonality' => 'day']);
})->throws(InvalidConfiguration::class, 'The `window` option of `'.Post::class.'` in `eloquent-viewable.spikes.types` must be no longer than a day, `"2d"` given.');
