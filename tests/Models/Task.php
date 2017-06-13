<?php

namespace Cyrildewit\PageVisitsCounter\Test\Models;

use Illuminate\Database\Eloquent\Model;
use Cyrildewit\PageVisitsCounter\Traits\HasPageVisitsCounter;

class Task extends Model
{
    use HasPageVisitsCounter;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tasks';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
