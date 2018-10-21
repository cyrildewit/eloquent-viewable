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

namespace CyrildeWit\EloquentViewable\Jobs;

use Illuminate\Bus\Queueable;
use CyrildeWit\EloquentViewable\View;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Class ProcessView.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ProcessView implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var \CyrildeWit\EloquentViewable\View
     */
    public $view;

    /**
     * Create a new job instance.
     *
     * @param  \CyrildeWit\EloquentViewable\View  $view
     * @return void
     */
    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Save the new view in the database.
        $this->view->save();
    }
}
