<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Models;

use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $rollup
 * @property string $tier
 * @property string $bucket_start
 * @property string $grouping
 * @property string $viewable_type
 * @property int|string|null $viewable_id
 * @property ?string $collection
 * @property ?string $dimension
 * @property int $views
 * @property int $unique_visitors
 */
class ViewRollup extends Model
{
    #[\Override]
    protected $guarded = [];

    #[\Override]
    public $timestamps = false;

    #[\Override]
    public function getTable(): string
    {
        return $this->table ?? $this->config()->rollupTable();
    }

    #[\Override]
    public function getConnectionName(): ?string
    {
        return parent::getConnectionName() ?? $this->config()->viewConnection();
    }

    private function config(): Config
    {
        return Container::getInstance()->make(Config::class);
    }
}
