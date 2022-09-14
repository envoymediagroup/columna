<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\ColumnDefinition;
use EnvoyMediaGroup\Columna\ConstraintParser;
use EnvoyMediaGroup\Columna\Constraint;
use PHPUnit\Framework\TestCase;

class ConstraintParserTest extends TestCase {

    public function testEqualsTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::EQUALS,
                    "value" => 1,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 1;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testEqualsFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::EQUALS,
                    "value" => 1,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 2;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testNotEqualsTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::NOT_EQUALS,
                    "value" => 1,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 2;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testNotEqualsFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::NOT_EQUALS,
                    "value" => 1,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 1;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testGreaterThanTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::GREATER_THAN,
                    "value" => 3,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 4;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testGreaterThanFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::GREATER_THAN,
                    "value" => 3,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 2;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);

        $value = 3;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testGreaterThanOrEqualTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::GREATER_THAN_OR_EQUALS,
                    "value" => 3,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 3;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);

        $value = 4;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testGreaterThanOrEqualFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::GREATER_THAN_OR_EQUALS,
                    "value" => 3,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 2;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testLessThanTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::LESS_THAN,
                    "value" => 3,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 2;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testLessThanFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::LESS_THAN,
                    "value" => 3,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 4;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);

        $value = 3;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testLessThanOrEqualTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::LESS_THAN_OR_EQUALS,
                    "value" => 3,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 3;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);

        $value = 2;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testLessThanOrEqualFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::LESS_THAN_OR_EQUALS,
                    "value" => 3,
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 4;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testInTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::IN,
                    "value" => [3,4,5],
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 4;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);

        $value = 5;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testInFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::IN,
                    "value" => [3,4,5],
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 2;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);

        $value = 6;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testNotInTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::NOT_IN,
                    "value" => [3,4,5],
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 2;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);

        $value = 6;
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testNotInFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "account_id",
                    "comparator" => Constraint::NOT_IN,
                    "value" => [3,4,5],
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getAccountIdColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 3;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);

        $value = 5;
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testContainsTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::CONTAINS,
                    "value" => 'oba',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'foobar';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testContainsFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::CONTAINS,
                    "value" => 'oba',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'boffqux';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testNotContainsTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::NOT_CONTAINS,
                    "value" => 'oba',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'boffqux';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testNotContainsFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::NOT_CONTAINS,
                    "value" => 'oba',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'foobar';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testContainsInTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::CONTAINS_IN,
                    "value" => ['ham','oba','cheese'],
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'foobar';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testContainsInFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::CONTAINS_IN,
                    "value" => ['ham','oba','cheese'],
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'boffqux';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testNotContainsInTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::NOT_CONTAINS_IN,
                    "value" => ['ham','oba','cheese'],
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'boffqux';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testNotContainsInFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::NOT_CONTAINS_IN,
                    "value" => ['ham','oba','cheese'],
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'foobar';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testBeginsWithTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::BEGINS_WITH,
                    "value" => 'foo',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'foobar';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testBeginsWithFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::BEGINS_WITH,
                    "value" => 'foo',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'boffqux';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testNotBeginsWithTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::NOT_BEGINS_WITH,
                    "value" => 'ham',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'foobar';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testNotBeginsWithFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::NOT_BEGINS_WITH,
                    "value" => 'boff',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'boffqux';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testEndsWithTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::ENDS_WITH,
                    "value" => 'bar',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'foobar';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testEndsWithFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::ENDS_WITH,
                    "value" => 'bar',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'boffqux';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testNotEndsWithTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::NOT_ENDS_WITH,
                    "value" => 'ham',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'foobar';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testNotEndsWithFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::NOT_ENDS_WITH,
                    "value" => 'qux',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'boffqux';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testRegexTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::REGEX,
                    "value" => '/foo(bar|baz)qux/',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'hey yafoobazquxi what';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);

        $value = 'hey yafoobarquxi what';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testRegexFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::REGEX,
                    "value" => '/foo(bar|baz)qux/',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'hey yafoobanquxi what';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    public function testNotRegexTrue() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::NOT_REGEX,
                    "value" => '/foo(bar|baz)qux/',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'hey yafoobanquxi what';
        $result = call_user_func($callable,$value);
        $this->assertTrue($result);
    }

    public function testNotRegexFalse() {
        $Parser = new ConstraintParser();
        $constraints_arrays = [
            0 => [
                0 => [
                    "name" => "campaign_name",
                    "comparator" => Constraint::NOT_REGEX,
                    "value" => '/foo(bar|baz)qux/',
                ]
            ]
        ];
        $Constraints = $Parser->unserializeConstraints($constraints_arrays,$this->getCampaignNameColumnMeta());
        $callable = $Constraints[0][0]['callable'];

        $value = 'hey yafoobazquxi what';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);

        $value = 'hey yafoobarquxi what';
        $result = call_user_func($callable,$value);
        $this->assertFalse($result);
    }

    protected function getAccountIdColumnMeta(): array {
        return [
            'account_id' =>
                [
                    'definition' =>
                        [
                            'axis_type' => ColumnDefinition::AXIS_TYPE_DIMENSION,
                            'name' => 'account_id',
                            'data_type' => ColumnDefinition::DATA_TYPE_INT,
                            'precision' => NULL,
                            'empty_value' => 0,
                        ],
                    'index' => 0,
                    'offset' => 0,
                ],
        ];
    }

    protected function getCampaignNameColumnMeta(): array {
        return [
            'campaign_name' =>
                [
                    'definition' =>
                        [
                            'axis_type' => ColumnDefinition::AXIS_TYPE_DIMENSION,
                            'name' => 'campaign_name',
                            'data_type' => ColumnDefinition::DATA_TYPE_STRING,
                            'precision' => NULL,
                            'empty_value' => '',
                        ],
                    'index' => 0,
                    'offset' => 0,
                ],
        ];
    }

}