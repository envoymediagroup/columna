<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\FileHelper;
use EnvoyMediaGroup\Columna\ColumnDefinition;
use EnvoyMediaGroup\Columna\Writer;

/**
 * @covers Writer
 */
class WriterTest extends WriterAbstractTestCase {

    public function testWritesFileWithDataWithCardinalitySort() {
        $row_based_data = $this->getRowBasedData();

        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            $row_based_data,
            self::TEST_OUTPUT_FILE,
            true,
            true
        );

//        //For updating the test files
//        print "Saved output with data to file:\n" . self::TEST_OUTPUT_FILE . "\n";
//        exit;

        $expected_file = self::FIXTURES_DIR . 'clicks--has_data_with_csort.scf';
        $this->assertFileEqualsBrief($expected_file,self::TEST_OUTPUT_FILE);
    }

    public function testWritesFileWithDataWithoutCardinalitySort() {
        $row_based_data = $this->getRowBasedData();

        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            $row_based_data,
            self::TEST_OUTPUT_FILE,
            true,
            false
        );

//        //For updating the test files
//        print "Saved output with data to file:\n" . self::TEST_OUTPUT_FILE . "\n";
//        exit;

        $expected_file = self::FIXTURES_DIR . 'clicks--has_data_no_csort.scf';
        $this->assertFileEqualsBrief($expected_file,self::TEST_OUTPUT_FILE);
    }

    public function testWritesFileWithNoData() {
        $row_based_data = [];

        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            $row_based_data,
            self::TEST_OUTPUT_FILE,
            true,
            true
        );

        $expected_file = self::FIXTURES_DIR . 'clicks--no_data.scf';
        $this->assertFileEqualsBrief($expected_file,self::TEST_OUTPUT_FILE);
    }

    public function testThrowsOnRowBasedDataSchemaNotMatchingHeaders() {
        $row_based_data = $this->getRowBasedData();

        unset($row_based_data[50][4]);

        $this->expectExceptionMessage("row based data did not match expected headers");
        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            $row_based_data,
            self::TEST_OUTPUT_FILE,
            true,
            true
        );
    }

    public function testSanityChecksAfterTransposePassOnValidData() {
        $indexes_to_column_names = [
            0 => "foo",
            1 => "bar",
        ];
        $row_based_data = [
            0 => [
                0 => 1,
                1 => 9,
            ],
            1 => [
                0 => 2,
                1 => 8,
            ],
            2 => [
                0 => 3,
                1 => 7,
            ],
        ];
        $columnar_data = [
            "foo" => [1,2,3],
            "bar" => [9,8,7],
        ];
        $MetricDefinition = new ColumnDefinition(ColumnDefinition::AXIS_TYPE_METRIC,"foo",ColumnDefinition::DATA_TYPE_INT,null,0);

        $this->expectNotToPerformAssertions();
        $Writer = new Writer($this->getMockFileHelper());
        ReflectionHelper::setProtectedProperty($Writer,'MetricDefinition',$MetricDefinition);
        ReflectionHelper::setProtectedProperty($Writer,'indexes_to_column_names',$indexes_to_column_names);
        ReflectionHelper::invokeProtectedMethod($Writer,'sanityChecksAfterTranspose',[$row_based_data,$columnar_data]);
    }

    public function testSanityChecksAfterTransposeThrowsOnWrongColumnCount() {
        $indexes_to_column_names = [
            0 => "foo",
            1 => "bar",
        ];
        $row_based_data = [
            0 => [
                0 => 1,
                1 => 9,
            ],
            1 => [
                0 => 2,
                1 => 8,
            ],
            2 => [
                0 => 3,
                1 => 7,
            ],
        ];
        $columnar_data = [
            "foo" => [1,2,3],
            "bar" => [9,8,7],
            "baz" => [4,5,6],
        ];
        $MetricDefinition = new ColumnDefinition(ColumnDefinition::AXIS_TYPE_METRIC,"foo",ColumnDefinition::DATA_TYPE_INT,null,0);

        $this->expectExceptionMessage("did not have same column count");
        $Writer = new Writer($this->getMockFileHelper());
        ReflectionHelper::setProtectedProperty($Writer,'MetricDefinition',$MetricDefinition);
        ReflectionHelper::setProtectedProperty($Writer,'indexes_to_column_names',$indexes_to_column_names);
        ReflectionHelper::invokeProtectedMethod($Writer,'sanityChecksAfterTranspose',[$row_based_data,$columnar_data]);
    }

    public function testSanityChecksAfterTransposeThrowsOnWrongRowCount() {
        $indexes_to_column_names = [
            0 => "foo",
            1 => "bar",
        ];
        $row_based_data = [
            0 => [
                0 => 1,
                1 => 9,
            ],
            1 => [
                0 => 2,
                1 => 8,
            ],
            2 => [
                0 => 3,
                1 => 7,
            ],
        ];
        $columnar_data = [
            "foo" => [1,2,3],
            "bar" => [9,8,7,8,9],
        ];
        $MetricDefinition = new ColumnDefinition(ColumnDefinition::AXIS_TYPE_METRIC,"foo",ColumnDefinition::DATA_TYPE_INT,null,0);

        $this->expectExceptionMessage("did not have same row count");
        $Writer = new Writer($this->getMockFileHelper());
        ReflectionHelper::setProtectedProperty($Writer,'MetricDefinition',$MetricDefinition);
        ReflectionHelper::setProtectedProperty($Writer,'indexes_to_column_names',$indexes_to_column_names);
        ReflectionHelper::invokeProtectedMethod($Writer,'sanityChecksAfterTranspose',[$row_based_data,$columnar_data]);
    }

    public function testSanityChecksAfterTransposeThrowsOnWrongMetricTotal() {
        $indexes_to_column_names = [
            0 => "foo",
            1 => "bar",
        ];
        $row_based_data = [
            0 => [
                0 => 1,
                1 => 9,
            ],
            1 => [
                0 => 2,
                1 => 8,
            ],
            2 => [
                0 => 3,
                1 => 7,
            ],
        ];
        $columnar_data = [
            "foo" => [1,2,1],
            "bar" => [9,8,7],
        ];
        $MetricDefinition = new ColumnDefinition(ColumnDefinition::AXIS_TYPE_METRIC,"foo",ColumnDefinition::DATA_TYPE_INT,null,0);

        $this->expectExceptionMessage("metric totals before and after transpose did not match");
        $Writer = new Writer($this->getMockFileHelper());
        ReflectionHelper::setProtectedProperty($Writer,'MetricDefinition',$MetricDefinition);
        ReflectionHelper::setProtectedProperty($Writer,'indexes_to_column_names',$indexes_to_column_names);
        ReflectionHelper::invokeProtectedMethod($Writer,'sanityChecksAfterTranspose',[$row_based_data,$columnar_data]);
    }

    public function throwsOnInvalidDate() {
        $this->expectExceptionMessage("invalid date");
        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            "2021-54-13",
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            [],
            self::TEST_OUTPUT_FILE,
            true,
            true
        );
    }

    public function testThrowsOnInvalidMetricDefinition() {
        $InvalidDefinition = new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_DIMENSION, //Can't be a dimension
            "foo",
            ColumnDefinition::DATA_TYPE_INT,
            null,
            0
        );

        $this->expectExceptionMessage("metric definition did not have correct axis_type");
        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $InvalidDefinition,
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            [],
            self::TEST_OUTPUT_FILE,
            true,
            true
        );
    }

    public function testThrowsOnInvalidDimensionDefinitions() {
        $DimensionDefinitions = [
            new ColumnDefinition(
                ColumnDefinition::AXIS_TYPE_METRIC, //Can't be a metric
                "foo",
                ColumnDefinition::DATA_TYPE_INT,
                null,
                0
            )
        ];

        $this->expectExceptionMessage("dimension definition did not have correct axis_type");
        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $DimensionDefinitions,
            $this->getRowBasedHeaders(),
            [],
            self::TEST_OUTPUT_FILE,
            true,
            true
        );
    }

    public function throwsOnInvalidRowBasedHeaders() {
        $row_based_headers = $this->getRowBasedHeaders();
        unset($row_based_headers[33]);
        $row_based_data = $this->getRowBasedData();

        $this->expectExceptionMessage("row based headers did not match");
        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            $row_based_data,
            self::TEST_OUTPUT_FILE,
            true,
            true
        );
    }

    public function testThrowsOnInvalidRowBasedData() {
        $row_based_data = [
            0 => [
                0 => 1,
            ],
        ];

        $this->expectExceptionMessage("row based data did not match expected headers");
        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            $row_based_data,
            self::TEST_OUTPUT_FILE,
            true,
            true
        );
    }

    public function throwsOnInvalidOutputFilePath() {
        $this->expectExceptionMessage("does not exist or is not writable");
        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedData(),
            $this->getRowBasedHeaders(),
            "/foo/bar/baz.scf",
            true,
            true
        );
    }

    public function testThrowsOnInvalidTmpDirectory() {
        $this->expectExceptionMessage("does not exist or is not writable");
        new Writer(new FileHelper("/foo/bar/baz"));
    }

}