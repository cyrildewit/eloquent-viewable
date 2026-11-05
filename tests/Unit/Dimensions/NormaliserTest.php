<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Dimensions\Normaliser;

it('cleans a value up', function (?string $value, ?string $expected): void {
    expect(Normaliser::normalise($value))->toBe($expected);
})->with([
    'null' => [null, null],
    'empty' => ['', null],
    'blank' => ["  \t ", null],
    'trimmed' => ['  Google  ', 'Google'],
    'control characters' => ["Goo\x00gle\x1F\n", 'Google'],
    'only control characters' => ["\x1F\x07", null],
    'invalid UTF-8' => ["Caf\xE9", 'Caf?'],
    'unicode kept' => ['Zürich 🇨🇭', 'Zürich 🇨🇭'],
]);

it('cuts a value at 64 characters on a character boundary', function (): void {
    $value = str_repeat('é', 70);

    expect(Normaliser::normalise($value))->toBe(str_repeat('é', 64))
        ->and((string) Normaliser::normalise($value))->toHaveLength(Normaliser::MaxLength);
});

it('trims what the cut leaves at the end', function (): void {
    expect(Normaliser::normalise(str_repeat('a', 63).' tail'))->toBe(str_repeat('a', 63));
});

it('never produces the value rollups keep for other', function (): void {
    expect(Normaliser::normalise(Normaliser::Other))->toBe('other')
        ->and(Normaliser::Other)->not->toBe('other');
});
