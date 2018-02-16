<?php

return [

    'models' => [

        /*
         * When using the "Visitable" trait from this package, it needs to
         * know which model should be used to retrieve and store the visits.
         *
         * We have created a simple default Eloquent model that could be used:
         * `CyrildeWit\EloquentVisitable\Models\ModelVisit::class`. But if you
         * need to extend it, you can easily change the below value.
         *
         * The model you want to use as a Visit model needs to implement the
         * `CyrildeWit\EloquentVisitable\Contracts\VisitContract`
         */
        'visit' => CyrildeWit\EloquentVisitable\Models\ModelVisit::class,

    ],

    'table_names' => [

        /*
         * When using the "Visitable" trait from this package, it needs to
         * know which table should be used to retrieve and store the visits.
         */
        'visits' => 'visits',

    ],

];
