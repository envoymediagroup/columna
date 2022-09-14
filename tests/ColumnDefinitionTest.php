<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\ColumnDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers ColumnDefinition
 */
class ColumnDefinitionTest extends TestCase {

    public function testMetricInt() {
        $MetricDefinition = new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_METRIC,
            'clicks',
            ColumnDefinition::DATA_TYPE_INT,
            null,
            0
        );
        $this->assertEquals('metric',$MetricDefinition->getAxisType());
        $this->assertEquals('clicks',$MetricDefinition->getName());
        $this->assertEquals('int',$MetricDefinition->getDataType());
        $this->assertNull($MetricDefinition->getPrecision());
        $this->assertEquals(0,$MetricDefinition->getEmptyValue());
    }

    public function testMetricFloatNoPrecision() {
        $MetricDefinition = new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_METRIC,
            'ad_cost',
            ColumnDefinition::DATA_TYPE_FLOAT,
            null,
            0
        );
        $this->assertEquals('metric',$MetricDefinition->getAxisType());
        $this->assertEquals('ad_cost',$MetricDefinition->getName());
        $this->assertEquals('float',$MetricDefinition->getDataType());
        $this->assertNull($MetricDefinition->getPrecision());
        $this->assertEquals(0,$MetricDefinition->getEmptyValue());
    }

    public function testMetricFloatWithPrecision() {
        $MetricDefinition = new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_METRIC,
            'ad_cost',
            ColumnDefinition::DATA_TYPE_FLOAT,
            2,
            0
        );
        $this->assertEquals('metric',$MetricDefinition->getAxisType());
        $this->assertEquals('ad_cost',$MetricDefinition->getName());
        $this->assertEquals('float',$MetricDefinition->getDataType());
        $this->assertEquals(2,$MetricDefinition->getPrecision());
        $this->assertEquals(0,$MetricDefinition->getEmptyValue());
    }

    public function testDimension() {
        $DimensionDefinition = new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_DIMENSION,
            'network',
            ColumnDefinition::DATA_TYPE_STRING,
            null,
            ''
        );
        $this->assertEquals('dimension',$DimensionDefinition->getAxisType());
        $this->assertEquals('network',$DimensionDefinition->getName());
        $this->assertEquals('string',$DimensionDefinition->getDataType());
        $this->assertNull($DimensionDefinition->getPrecision());
        $this->assertEquals('',$DimensionDefinition->getEmptyValue());
    }

    public function testToArray() {
        $MetricDefinition = new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_METRIC,
            'clicks',
            ColumnDefinition::DATA_TYPE_INT,
            null,
            0
        );
        $array = $MetricDefinition->toArray();

        $expected = [
            "axis_type" => "metric",
            "name" => "clicks",
            "data_type" => "int",
            "precision" => null,
            "empty_value" => 0,
        ];
        $this->assertSame($expected,$array);
    }

    public function testFromArray() {
        $array = [
            "axis_type" => "metric",
            "name" => "clicks",
            "data_type" => "int",
            "precision" => null,
            "empty_value" => 0,
        ];
        $MetricDefinition = ColumnDefinition::fromArray($array);

        $this->assertEquals('metric',$MetricDefinition->getAxisType());
        $this->assertEquals('clicks',$MetricDefinition->getName());
        $this->assertEquals('int',$MetricDefinition->getDataType());
        $this->assertNull($MetricDefinition->getPrecision());
        $this->assertEquals(0,$MetricDefinition->getEmptyValue());
    }

    public function testFailsOnInvalidAxisType() {
        $this->expectExceptionMessage("invalid axis_type");
        new ColumnDefinition(
            "foobar",
            'network',
            ColumnDefinition::DATA_TYPE_STRING,
            null,
            ''
        );
    }

    public function testFailsOnInvalidName() {
        $this->expectExceptionMessage("invalid name");
        new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_DIMENSION,
            '1 foo bar baz',
            ColumnDefinition::DATA_TYPE_STRING,
            null,
            ''
        );
    }

    public function testFailsOnInvalidDataType() {
        $this->expectExceptionMessage("invalid data_type");
        new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_DIMENSION,
            'network',
            'foobar',
            null,
            ''
        );
    }

    public function testFailsOnInvalidPrecision() {
        $this->expectExceptionMessage("invalid precision");
        new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_METRIC,
            'ad_cost',
            ColumnDefinition::DATA_TYPE_FLOAT,
            -2,
            0
        );
    }

    public function testFailsOnPrecisionWithNonFloat() {
        $this->expectExceptionMessage("precision may only be provided when data_type is 'float'");
        new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_DIMENSION,
            'network',
            ColumnDefinition::DATA_TYPE_STRING,
            2,
            0
        );
    }

    public function testFailsOnInvalidEmptyValue() {
        $this->expectExceptionMessage("does not match data_type");
        new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_DIMENSION,
            'network',
            ColumnDefinition::DATA_TYPE_STRING,
            null,
            1.23
        );
    }

}