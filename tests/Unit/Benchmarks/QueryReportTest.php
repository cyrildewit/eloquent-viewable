<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Benchmarks\Querying\CountViewsBench;
use CyrildeWit\EloquentViewable\Benchmarks\Support\QueryReport;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Variant;

describe('plan', function (): void {
    it('takes the columns from the first row and casts every value to a string, keeping null', function (): void {
        $plan = QueryReport::plan([
            (object) ['id' => 1, 'select_type' => 'SIMPLE', 'partitions' => null, 'filtered' => 100.0],
            (object) ['id' => 2, 'select_type' => 'SUBQUERY', 'partitions' => 'p0', 'filtered' => 33.33],
        ]);

        expect($plan)->toBe([
            'columns' => ['id', 'select_type', 'partitions', 'filtered'],
            'rows' => [
                ['1', 'SIMPLE', null, '100'],
                ['2', 'SUBQUERY', 'p0', '33.33'],
            ],
        ]);
    });

    it('keeps a one-column plan as one value per row', function (): void {
        expect(QueryReport::plan([(object) ['QUERY PLAN' => 'Seq Scan on views']]))->toBe([
            'columns' => ['QUERY PLAN'],
            'rows' => [['Seq Scan on views']],
        ]);
    });

    it('keeps the columns empty for an empty result', function (): void {
        expect(QueryReport::plan([]))->toBe(['columns' => [], 'rows' => []]);
    });
});

describe('the report', function (): void {
    it('lays out a variant with its queries under the file header', function (): void {
        $report = new QueryReport('sqlite', false, 'read');
        $plan = ['columns' => ['id', 'detail'], 'rows' => [['3', 'SEARCH views USING INDEX views_viewable_viewed_at_index (viewable_type=? AND viewable_id=?)']]];

        $report->add(
            new Variant(CountViewsBench::class, 'benchCount', ['hot article', 'all time'], ['target' => 'hot', 'days' => null]),
            [['sql' => 'select count(*) as aggregate from "views" where "views"."viewable_type" = \'article\' and "views"."viewable_id" = 1', 'plan' => $plan]],
        );
        $report->add(new Variant(CountViewsBench::class, 'benchUniqueCount', ['cold article', 'past day'], ['target' => 'cold', 'days' => 1]), []);

        expect($report->toArray())->toBe([
            'schema_version' => 1,
            'driver' => 'sqlite',
            'analyzed' => false,
            'group' => 'read',
            'subjects' => [
                [
                    'class' => CountViewsBench::class,
                    'subject' => 'benchCount',
                    'set' => 'hot article,all time',
                    'params' => ['target' => 'hot', 'days' => null],
                    'queries' => [
                        [
                            'sql' => 'select count(*) as aggregate from "views" where "views"."viewable_type" = \'article\' and "views"."viewable_id" = 1',
                            'plan' => $plan,
                        ],
                    ],
                ],
                [
                    'class' => CountViewsBench::class,
                    'subject' => 'benchUniqueCount',
                    'set' => 'cold article,past day',
                    'params' => ['target' => 'cold', 'days' => 1],
                    'queries' => [],
                ],
            ],
        ]);
    });

    it('writes pretty-printed JSON with unescaped slashes and a trailing newline', function (): void {
        $report = new QueryReport('pgsql', true, 'read');

        $report->add(new Variant(CountViewsBench::class, 'benchCount', [], []), [
            ['sql' => 'select 1/2', 'plan' => ['columns' => ['QUERY PLAN'], 'rows' => [['Result'], [null]]]],
        ]);

        expect($report->toJson())->toBe(<<<'JSON'
            {
                "schema_version": 1,
                "driver": "pgsql",
                "analyzed": true,
                "group": "read",
                "subjects": [
                    {
                        "class": "CyrildeWit\\EloquentViewable\\Benchmarks\\Querying\\CountViewsBench",
                        "subject": "benchCount",
                        "set": "",
                        "params": {},
                        "queries": [
                            {
                                "sql": "select 1/2",
                                "plan": {
                                    "columns": [
                                        "QUERY PLAN"
                                    ],
                                    "rows": [
                                        [
                                            "Result"
                                        ],
                                        [
                                            null
                                        ]
                                    ]
                                }
                            }
                        ]
                    }
                ]
            }

            JSON);
    });
});
