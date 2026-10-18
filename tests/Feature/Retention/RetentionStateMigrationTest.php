<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('keeps the state table on rollback while the rollups table exists', function (): void {
    config()->set('eloquent-viewable.milestones.table', 'missing_milestones');

    new CreateViewRetentionStateTable()->down();

    expect(Schema::hasTable('view_retention_state'))->toBeTrue();
});

it('keeps the state table on rollback while the milestones table exists', function (): void {
    config()->set('eloquent-viewable.retention.rollups.table', 'missing_rollups');

    new CreateViewRetentionStateTable()->down();

    expect(Schema::hasTable('view_retention_state'))->toBeTrue();
});
