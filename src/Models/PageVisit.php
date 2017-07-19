<?php

namespace Cyrildewit\PageVisitsCounter\Models;

use Illuminate\Database\Eloquent\Model;
use Cyrildewit\PageVisitsCounter\Contracts\PageVisit as PageVisitContract;

class PageVisit extends Model implements PageVisitContract
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Constructor function of the PageVisit model.
     *
     * @param array $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('page-visits-counter.page_visits_table_name', 'page-visits'));
    }
}
