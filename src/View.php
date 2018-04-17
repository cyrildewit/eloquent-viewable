<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;

/**
 * Class View.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class View extends Model implements ViewContract
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Create a new View instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('eloquent-viewable.models.view.table_name', 'views'));

        if ($connection = config('eloquent-viewable.models.view.connection', null)) {
            $this->setConnection($connection);
        }
    }

    /**
     * Get all of the owning viewable models.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }
}
