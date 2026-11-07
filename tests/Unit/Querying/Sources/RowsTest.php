<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Sources\Rows;

describe('grouped', function (): void {
    it('keys the aggregate of each row by its group', function (): void {
        $rows = [
            (object) ['collection' => 'sidebar', 'aggregate' => 3],
            (object) ['collection' => 'feed', 'aggregate' => '1'],
        ];

        expect(Rows::grouped($rows, 'collection'))->toBe(['sidebar' => 3, 'feed' => 1]);
    });

    it('keys a null group as an empty string', function (): void {
        $rows = [
            (object) ['dimension' => null, 'aggregate' => '2'],
            (object) ['dimension' => 'spring', 'aggregate' => 5],
        ];

        expect(Rows::grouped($rows, 'dimension'))->toBe(['' => 2, 'spring' => 5]);
    });

    it('returns nothing without rows', function (): void {
        expect(Rows::grouped([], 'collection'))->toBeEmpty();
    });
});
