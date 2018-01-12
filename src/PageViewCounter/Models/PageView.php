<?php

namespace CyrildeWit\PageViewCounter\Models;

use Illuminate\Database\Eloquent\Model;
use CyrildeWit\PageViewCounter\Contracts\PageView as PageViewContract;

class PageView extends Model implements PageViewContract
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Create a new PageView Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('page-view-counter.page_views_table_name'));
    }
}
