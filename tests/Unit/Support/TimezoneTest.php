<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Support\Timezone;

it('is a DateTimeZone', function (): void {
    $timezone = new Timezone('Europe/Amsterdam');

    expect($timezone)->toBeInstanceOf(DateTimeZone::class)
        ->and($timezone->getName())->toBe('Europe/Amsterdam');
});

it('reports the application timezone', function (): void {
    expect(Timezone::application()->getName())->toBe(date_default_timezone_get());
});

it('is built from a name, a DateTimeZone or itself', function (): void {
    $timezone = new Timezone('Australia/Sydney');

    expect(Timezone::from('Australia/Sydney')->getName())->toBe('Australia/Sydney')
        ->and(Timezone::from(new DateTimeZone('Australia/Sydney')))->toBeInstanceOf(Timezone::class)
        ->and(Timezone::from(new DateTimeZone('Australia/Sydney'))->getName())->toBe('Australia/Sydney')
        ->and(Timezone::from($timezone))->toBe($timezone);
});

it('rejects anything that is not an identifier', function (DateTimeZone|string $timezone, string $name): void {
    expect(fn (): Timezone => Timezone::from($timezone))
        ->toThrow(InvalidTimezone::class, "`{$name}` is not a timezone identifier");
})->with([
    'an unknown name' => ['Mars/Olympus', 'Mars/Olympus'],
    'an offset' => ['+05:30', '+05:30'],
    'an abbreviation' => ['CEST', 'CEST'],
    'an offset zone object' => [new DateTimeZone('+02:00'), '+02:00'],
]);
