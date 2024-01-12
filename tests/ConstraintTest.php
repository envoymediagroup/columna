<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\Constraint;

class ConstraintTest extends ConstraintTestAbstract {

    public function testBeginsWithHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'card_type',
                    'comparator' => Constraint::BEGINS_WITH,
                    'value' => 'diners',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 2665;
        $this->assertEquals($expected_total,$total);
    }

    public function testBeginsWithNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'card_type',
                    'comparator' => Constraint::BEGINS_WITH,
                    'value' => 'foo',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testContainsInHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::CONTAINS_IN,
                    'value' => ['amazon','google'],
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 233;
        $this->assertEquals($expected_total,$total);
    }

    public function testContainsInNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::CONTAINS_IN,
                    'value' => ['farkle','snoob'],
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testContainsAllHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::CONTAINS_ALL,
                    'value' => ['goog','google'],
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 180;
        $this->assertEquals($expected_total,$total);
    }

    public function testContainsAllNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::CONTAINS_IN,
                    'value' => ['farkle','snoob'],
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testContainsHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::CONTAINS,
                    'value' => 'amazon',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 53;
        $this->assertEquals($expected_total,$total);
    }

    public function testContainsNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::CONTAINS,
                    'value' => 'snoob',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testEmptyHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'is_enabled',
                    'comparator' => Constraint::EMPTY,
                    'value' => '',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 2700;
        $this->assertEquals($expected_total,$total);
    }

    public function testEmptyNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'last_name',
                    'comparator' => Constraint::EMPTY,
                    'value' => '',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testEndsWithHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::ENDS_WITH,
                    'value' => '.jp',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 235;
        $this->assertEquals($expected_total,$total);
    }

    public function testEndsWithNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::ENDS_WITH,
                    'value' => '.xyz',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testEqualsHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'platform_id',
                    'comparator' => Constraint::EQUALS,
                    'value' => 3,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 998;
        $this->assertEquals($expected_total,$total);
    }

    public function testEqualsNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'platform_id',
                    'comparator' => Constraint::EQUALS,
                    'value' => 14,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testGreaterThanOrEqualsHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::GREATER_THAN_OR_EQUALS,
                    'value' => 60,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 1486;
        $this->assertEquals($expected_total,$total);
    }

    public function testGreaterThanOrEqualsNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::GREATER_THAN_OR_EQUALS,
                    'value' => 71,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testGreaterThanHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::GREATER_THAN,
                    'value' => 60,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 1365;
        $this->assertEquals($expected_total,$total);
    }

    public function testGreaterThanNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::GREATER_THAN,
                    'value' => 70,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testInHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::IN,
                    'value' => [50,60,70],
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 403;
        $this->assertEquals($expected_total,$total);
    }

    public function testInNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::IN,
                    'value' => [0,10,101],
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testLessThanOrEqualsHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::LESS_THAN_OR_EQUALS,
                    'value' => 50,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 2796;
        $this->assertEquals($expected_total,$total);
    }

    public function testLessThanOrEqualsNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::LESS_THAN_OR_EQUALS,
                    'value' => 29,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testLessThanHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::LESS_THAN,
                    'value' => 37,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 899;
        $this->assertEquals($expected_total,$total);
    }

    public function testLessThanNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::LESS_THAN,
                    'value' => 30,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotBeginsWithHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'card_type',
                    'comparator' => Constraint::NOT_BEGINS_WITH,
                    'value' => 'diners',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 2809;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotBeginsWithNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'event_datetime',
                    'comparator' => Constraint::NOT_BEGINS_WITH,
                    'value' => '202',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotContainsInHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::NOT_CONTAINS_IN,
                    'value' => ['amazon','google'],
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 5241;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotContainsInNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::NOT_CONTAINS_IN,
                    'value' => ['http:','https:'],
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotContainsHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::NOT_CONTAINS,
                    'value' => 'amazon',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 5421;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotContainsNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::NOT_CONTAINS,
                    'value' => 'ttp',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotEmptyHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'is_enabled',
                    'comparator' => Constraint::NOT_EMPTY,
                    'value' => '',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 2774;
        $this->assertEquals($expected_total,$total);
    }

    //TODO test data set currently does not cover this scenario.
//    public function testNotEmptyNoData() {
//        $workload_array = $this->getWorkloadArray();
//        $workload_array['constraints'] = [
//            [
//                [
//                    'name' => 'last_name',
//                    'comparator' => Constraint::NOT_EMPTY,
//                    'value' => '',
//                ],
//            ]
//        ];
//
//        $total = $this->runQueryAndGetMetricTotal($workload_array);
//
//        $expected_total = 0;
//        $this->assertEquals($expected_total,$total);
//    }

    public function testNotEndsWithHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'url',
                    'comparator' => Constraint::NOT_ENDS_WITH,
                    'value' => '.jp',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 5239;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotEndsWithNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'event_datetime',
                    'comparator' => Constraint::NOT_ENDS_WITH,
                    'value' => 'Z',
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotEqualsHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'platform_id',
                    'comparator' => Constraint::NOT_EQUALS,
                    'value' => 3,
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 4476;
        $this->assertEquals($expected_total,$total);
    }

    //TODO test data set currently does not cover this scenario.
//    public function testNotEqualsNoData() {
//        $workload_array = $this->getWorkloadArray();
//        $workload_array['constraints'] = [
//            [
//                [
//                    'name' => 'platform_id',
//                    'comparator' => Constraint::NOT_EQUALS,
//                    'value' => 14,
//                ],
//            ]
//        ];
//
//        $total = $this->runQueryAndGetMetricTotal($workload_array);
//
//        $expected_total = 0;
//        $this->assertEquals($expected_total,$total);
//    }

    public function testNotInHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'sequence_id',
                    'comparator' => Constraint::NOT_IN,
                    'value' => [50,60,70],
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 5071;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotInNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'platform_id',
                    'comparator' => Constraint::NOT_IN,
                    'value' => [1,2,3,4,5],
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotRegexHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'email',
                    'comparator' => Constraint::NOT_REGEX,
                    'value' => "/@.*(\.com|\.gov)$/", //has an @ in it, ends with .com or .gov
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 2016;
        $this->assertEquals($expected_total,$total);
    }

    public function testNotRegexNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['dimensions'] = ['email'];
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'email',
                    'comparator' => Constraint::NOT_REGEX,
                    'value' => "/@[^\.]+\./", //has an @ in it, followed by some non-dot characters, followed by a dot.
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

    public function testRegexHasData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'email',
                    'comparator' => Constraint::REGEX,
                    'value' => "/@.*(\.com|\.gov)$/", //has an @ in it, ends with .com or .gov
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 3458;
        $this->assertEquals($expected_total,$total);
    }

    public function testRegexNoData() {
        $workload_array = $this->getWorkloadArray();
        $workload_array['dimensions'] = ['email'];
        $workload_array['constraints'] = [
            [
                [
                    'name' => 'email',
                    'comparator' => Constraint::REGEX,
                    'value' => "/@[^\.]+\^/", //has an @ in it, followed by some non-dot characters, followed by a caret.
                ],
            ]
        ];

        $total = $this->runQueryAndGetMetricTotal($workload_array);

        $expected_total = 0;
        $this->assertEquals($expected_total,$total);
    }

}