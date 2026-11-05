<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates and drops the state table', function (): void {
    config()->set('database.connections.bare', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('eloquent-viewable.models.view.connection', 'bare');

    $migration = require __DIR__.'/../../../database/migrations/create_view_state_table.php.stub';
    $schema = Schema::connection('bare');

    $migration->up();

    expect($schema->getColumnListing('view_state'))->toEqualCanonicalizing(['name', 'value']);

    $migration->down();

    expect($schema->hasTable('view_state'))->toBeFalse();
});
