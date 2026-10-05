<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Retention\Bots\BurstDetector;

it('marks the views of a visitor that opens more different viewables than the maximum within the window', function (): void {
    $detector = new BurstDetector(max: 2, seconds: 2);

    expect($detector->feed(1, 'bot', 'post|1', 100))->toBeEmpty()
        ->and($detector->feed(2, 'bot', 'post|2', 100))->toBeEmpty()
        ->and($detector->feed(3, 'person', 'post|3', 100))->toBeEmpty()
        ->and($detector->feed(4, 'bot', 'post|3', 101))->toBe([1 => 100, 2 => 100, 4 => 101])
        ->and($detector->feed(5, 'bot', 'post|4', 101))->toBe([5 => 101]);
});

it('leaves a visitor alone that opens viewables slower than the window', function (): void {
    $detector = new BurstDetector(max: 2, seconds: 2);

    foreach (range(1, 10) as $post) {
        expect($detector->feed($post, 'person', "post|{$post}", 100 + $post * 2))->toBeEmpty();
    }

    expect($detector->visitorsWithBursts(1))->toBeEmpty();
});

it('does not count the same viewable twice within the window', function (): void {
    $detector = new BurstDetector(max: 2, seconds: 2);

    foreach (range(1, 5) as $id) {
        expect($detector->feed($id, 'reader', 'post|1', 100))->toBeEmpty();
    }

    expect($detector->feed(6, 'reader', 'post|2', 100))->toBeEmpty();
});

it('counts separate bursts per visitor', function (): void {
    $detector = new BurstDetector(max: 1, seconds: 1);

    $detector->feed(1, 'bot', 'post|1', 100);
    $detector->feed(2, 'bot', 'post|2', 100);
    $detector->feed(3, 'bot', 'post|3', 100);
    $detector->feed(4, 'bot', 'post|1', 200);
    $detector->feed(5, 'bot', 'post|2', 200);
    $detector->feed(6, 'tabs', 'post|1', 300);
    $detector->feed(7, 'tabs', 'post|2', 300);

    expect($detector->visitorsWithBursts(1))->toBe(['bot', 'tabs'])
        ->and($detector->visitorsWithBursts(2))->toBe(['bot']);
});

it('keeps a numeric visitor a string', function (): void {
    $detector = new BurstDetector(max: 1, seconds: 1);

    $detector->feed(1, '42', 'post|1', 100);
    $detector->feed(2, '42', 'post|2', 100);

    expect($detector->visitorsWithBursts(1))->toBe(['42']);
});

it('forgets visitors whose window has passed and counts their return as a new burst', function (): void {
    $detector = new BurstDetector(max: 1, seconds: 1);

    $detector->feed(1, 'bot', 'post|1', 100);
    $detector->feed(2, 'bot', 'post|2', 100);

    foreach (range(3, 1000) as $id) {
        $detector->feed($id, "person {$id}", 'post|1', 200);
    }

    expect($detector->feed(1001, 'bot', 'post|3', 200))->toBeEmpty()
        ->and($detector->feed(1002, 'bot', 'post|4', 200))->toBe([1001 => 200, 1002 => 200])
        ->and($detector->visitorsWithBursts(2))->toBe(['bot']);
});
