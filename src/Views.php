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

/**
 * Class Views.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class Views
{
    protected $viewable;

    public function __construct($viewable)
    {
        $this->viewable = $viewable;
    }

    public static function create($viewable, string $tag): self
    {
        return new static($viewable);
    }
}
