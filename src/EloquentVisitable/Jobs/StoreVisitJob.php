<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Visitable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentVisitable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use CyrildeWit\EloquentVisitable\Models\Visit;

/**
 * This is the store visit job.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class StoreVisitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $visit;

    /**
     * Create a new job instance.
     *
     * @param  \CyrildeWit\EloquentVisitable\Models\Visit  $visit
     * @return void
     */
    public function __construct(Visit $visit)
    {
        $this->visit = $visit;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Save the given visit in the database.
        $this->visit->save();
    }
}
