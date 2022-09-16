<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\BundledReader;
use EnvoyMediaGroup\Columna\Constraint;
use EnvoyMediaGroup\Columna\ColumnDefinition;
use EnvoyMediaGroup\Columna\Writer;
use EnvoyMediaGroup\Columna\Reader;

/**
 * @covers Writer
 * @covers Reader
 */
class WriterAndReaderTest extends WriterAbstractTestCase {
    use \ReaderTestTrait;

    /**
     * @param Reader|BundledReader $Reader
     * @throws \JsonException
     * @throws \Throwable
     * @return void
     * @dataProvider readerProvider
     */
    public function testWritesAndReadsFileWithDataWithCardinalitySort($Reader) {
        $workload_array = $this->getWorkloadArray();

        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            $this->getRowBasedData(),
            self::TEST_OUTPUT_FILE,
            true,
            true
        );

        $expected_file = self::FIXTURES_DIR . 'clicks--has_data_with_csort.scf';
        $this->assertFileEqualsBrief($expected_file,self::TEST_OUTPUT_FILE);

        $Reader->runFromWorkload(json_encode($workload_array));
        $response = $Reader->getResponsePayload();

        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_aggregated_no_meta_with_csort.txt";
        $expected_response = $this->getExpectedResponseWithCorrectMsElapsed($response,$expected_response_file);
        $this->assertEquals($expected_response,$response);
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws \JsonException
     * @throws \Throwable
     * @return void
     * @dataProvider readerProvider
     */
    public function testWritesAndReadsFileWithDataWithoutCardinalitySort($Reader) {
        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_aggregated_no_meta_no_csort.txt";
        $workload_array = $this->getWorkloadArray();

        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            $this->getRowBasedData(),
            self::TEST_OUTPUT_FILE,
            true,
            false
        );

        $expected_file = self::FIXTURES_DIR . 'clicks--has_data_no_csort.scf';
        $this->assertFileEqualsBrief($expected_file,self::TEST_OUTPUT_FILE);

        $Reader->runFromWorkload(json_encode($workload_array));
        $response = $Reader->getResponsePayload();

//        //For updating the test files
//        file_put_contents($expected_response_file,$response);
//        print "Saved to:\n$expected_response_file\n";
//        exit;

        $expected_response = $this->getExpectedResponseWithCorrectMsElapsed($response,$expected_response_file);
        $this->assertEquals($expected_response,$response);
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws \JsonException
     * @throws \Throwable
     * @return void
     * @dataProvider readerProvider
     */
    public function testWritesAndReadsFileWithDataContainingNulls($Reader) {
        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_containing_nulls.txt";
        $workload_array = $this->getWorkloadArray();
        $workload_array["constraints"] = Array(
            0 => Array(
                0 => Array(
                    'name' => 'card_type',
                    'comparator' => Constraint::CONTAINS_IN,
                    'value' => ['diner','visa'],
                ),
            ),
        );
        $row_based_data = $this->getRowBasedData();
        //Smaller data set for this one
        $row_based_data = array_slice($row_based_data,0,10,true);
        $row_based_data[3][2] = null; //clicks is null
        $row_based_data[7][5] = null; //first_name is null

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

        $expected_file = self::FIXTURES_DIR . 'clicks--nulls_filled.scf';
        $this->assertFileEqualsBrief($expected_file,self::TEST_OUTPUT_FILE);

        $Reader->runFromWorkload(json_encode($workload_array));
        $response = $Reader->getResponsePayload();

//        //For updating the test files
//        file_put_contents($expected_response_file,$response);
//        print "Saved to:\n$expected_response_file\n";
//        exit;

        $expected_response = $this->getExpectedResponseWithCorrectMsElapsed($response,$expected_response_file);
        $this->assertEquals($expected_response,$response);
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws \JsonException
     * @throws \Throwable
     * @return void
     * @dataProvider readerProvider
     */
    public function testWritesAndReadsFileWithDataNotAlphabetized($Reader) {
        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_not_alphabetized.txt";
        $workload_array = $this->getWorkloadArray();
        $workload_array["dimensions"] = ["platform_id"];
        $workload_array["constraints"] = Array(
            0 => Array(
                0 => Array(
                    'name' => 'email',
                    'comparator' => Constraint::ENDS_WITH,
                    'value' => '@foo.com',
                ),
            ),
        );
        $row_based_headers = [
            'site_id',
            'clicks',
            'platform_id',
            'email',
        ];
        $row_based_data = [
            [
                13,
                4,
                5,
                'a@foo.com',
            ],
            [
                9,
                11,
                5,
                'b@foo.com',
            ],
            [
                47,
                1,
                7,
                'c@foo.com',
            ],
        ];
        $AllDimensionDefs = $this->getAllDimensionDefinitions();
        $DimensionDefs = [];
        foreach ($AllDimensionDefs as $DimensionDef) {
            if (in_array($DimensionDef->getName(),['site_id','platform_id','email']) ) {
                $DimensionDefs[] = $DimensionDef;
            }
        }

        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $DimensionDefs,
            $row_based_headers,
            $row_based_data,
            self::TEST_OUTPUT_FILE,
            true,
            false
        );

        $expected_file = self::FIXTURES_DIR . 'clicks--alpha_sorted.scf';
        $this->assertFileEqualsBrief($expected_file,self::TEST_OUTPUT_FILE);

        $Reader->runFromWorkload(json_encode($workload_array));
        $response = $Reader->getResponsePayload();

//        //For updating the test files
//        file_put_contents($expected_response_file,$response);
//        print "Saved to:\n$expected_response_file\n";
//        exit;

        $expected_response = $this->getExpectedResponseWithCorrectMsElapsed($response,$expected_response_file);
        $this->assertEquals($expected_response,$response);
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws \JsonException
     * @throws \Throwable
     * @return void
     * @dataProvider readerProvider
     */
    public function testWritesAndReadsFileWithNoData($Reader) {
        $row_based_data = [];
        $workload_array = $this->getWorkloadArray();

        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getAllDimensionDefinitions(),
            $this->getRowBasedHeaders(),
            $row_based_data,
            self::TEST_OUTPUT_FILE
        );
        $expected_file = self::FIXTURES_DIR . 'clicks--no_data.scf';
        $this->assertFileEqualsBrief($expected_file,self::TEST_OUTPUT_FILE);

        $Reader->runFromWorkload(json_encode($workload_array));
        $response = $Reader->getResponsePayload();

        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_empty_result.txt";
        $expected_response = $this->getExpectedResponseWithCorrectMsElapsed($response,$expected_response_file);
        $this->assertEquals($expected_response,$response);
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws \JsonException
     * @throws \Throwable
     * @return void
     * @dataProvider readerProvider
     */
    public function testWritesAndReadsFileWithValuesContainingEscapeCharactersAndBools($Reader) {
        $row_based_headers = [
            0 => "bar",
            1 => "foo0",
            2 => "foo1",
            3 => "foo2",
            4 => "foo3",
            5 => "foo4",
            6 => "foo5",
            7 => "foo6",
            8 => "foo7",
            9 => "foo8",
            10 => "foo9",
        ];
        $input_first_row = [
            0 => 123,
            1 => 'chapter 7 bankruptcy on credit for how long',
            2 => 'chapter 7 "bankruptcy" on credit for how long\\',
            3 => 'chapter 7 \\"bankruptcy" on credit for how long\\',
            4 => 'chapter 7 \"bankruptcy" /on, \/credit \for how long\\',
            5 => ',chapter 7 \"bankruptcy" /on, \/credit \for how long\\,',
            6 => '\,chapter 7 \"bankruptcy" /on, \/credit \for how long\\\,',
            7 => '\,chapter 7 \"bankruptcy" /on, \/credit \for how long\\\,',
            8 => json_encode(['\,chapter 7 \"bankruptcy" /on, \/credit \for how long\\']),
            9 => true,
            10 => false,
        ];
        $row_based_data = [
            0 => $input_first_row,
        ];

        $workload_array = Array(
            'date' => self::TEST_DATE,
            'metric' => 'bar',
            'dimensions' => Array("foo0","foo1","foo2","foo3","foo4","foo5","foo6","foo7","foo8","foo9"),
            'constraints' => Array(
                0 => Array(
                    0 => Array(
                        'name' => 'foo0',
                        'comparator' => Constraint::NOT_EQUALS,
                        'value' => '',
                    ),
                ),
            ),
            'do_aggregate' => false,
            'do_aggregate_meta' => false,
            'file' => self::TEST_OUTPUT_FILE,
        );

        $MetricDefinition = new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_METRIC,
            'bar',
            ColumnDefinition::DATA_TYPE_INT,
            null,
            0
        );
        $DimensionDefinitions = [
            "foo0" => new ColumnDefinition(ColumnDefinition::AXIS_TYPE_DIMENSION,"foo0",ColumnDefinition::DATA_TYPE_STRING,null,''),
            "foo1" => new ColumnDefinition(ColumnDefinition::AXIS_TYPE_DIMENSION,"foo1",ColumnDefinition::DATA_TYPE_STRING,null,''),
            "foo2" => new ColumnDefinition(ColumnDefinition::AXIS_TYPE_DIMENSION,"foo2",ColumnDefinition::DATA_TYPE_STRING,null,''),
            "foo3" => new ColumnDefinition(ColumnDefinition::AXIS_TYPE_DIMENSION,"foo3",ColumnDefinition::DATA_TYPE_STRING,null,''),
            "foo4" => new ColumnDefinition(ColumnDefinition::AXIS_TYPE_DIMENSION,"foo4",ColumnDefinition::DATA_TYPE_STRING,null,''),
            "foo5" => new ColumnDefinition(ColumnDefinition::AXIS_TYPE_DIMENSION,"foo5",ColumnDefinition::DATA_TYPE_STRING,null,''),
            "foo6" => new ColumnDefinition(ColumnDefinition::AXIS_TYPE_DIMENSION,"foo6",ColumnDefinition::DATA_TYPE_STRING,null,''),
            "foo7" => new ColumnDefinition(ColumnDefinition::AXIS_TYPE_DIMENSION,"foo7",ColumnDefinition::DATA_TYPE_STRING,null,''),
            "foo8" => new ColumnDefinition(ColumnDefinition::AXIS_TYPE_DIMENSION,"foo8",ColumnDefinition::DATA_TYPE_BOOL,null,false),
            "foo9" => new ColumnDefinition(ColumnDefinition::AXIS_TYPE_DIMENSION,"foo9",ColumnDefinition::DATA_TYPE_BOOL,null,false),
        ];

        $Writer = new Writer($this->getMockFileHelper());
        $Writer->writeFile(
            self::TEST_DATE,
            $MetricDefinition,
            $DimensionDefinitions,
            $row_based_headers,
            $row_based_data,
            self::TEST_OUTPUT_FILE
        );

        $Reader->runFromWorkload(json_encode($workload_array));
        $result_first_row = $Reader->getResults()[0];
        array_shift($result_first_row); //Ignore the md5 hash
        $this->assertSame(array_values($input_first_row),$result_first_row);

        $expected_response = <<<'END'
{"date":"2022-07-08","metric":"bar","status":"success","min":123,"max":123,"sum":123,"matched_row_count":1,"column_meta":[{"definition":{"axis_type":"dimension","name":"md5","data_type":"string","empty_value":""},"index":0},{"definition":{"axis_type":"metric","name":"bar","data_type":"int","precision":null,"empty_value":0},"index":1},{"definition":{"axis_type":"dimension","name":"foo0","data_type":"string","precision":null,"empty_value":""},"index":2},{"definition":{"axis_type":"dimension","name":"foo1","data_type":"string","precision":null,"empty_value":""},"index":3},{"definition":{"axis_type":"dimension","name":"foo2","data_type":"string","precision":null,"empty_value":""},"index":4},{"definition":{"axis_type":"dimension","name":"foo3","data_type":"string","precision":null,"empty_value":""},"index":5},{"definition":{"axis_type":"dimension","name":"foo4","data_type":"string","precision":null,"empty_value":""},"index":6},{"definition":{"axis_type":"dimension","name":"foo5","data_type":"string","precision":null,"empty_value":""},"index":7},{"definition":{"axis_type":"dimension","name":"foo6","data_type":"string","precision":null,"empty_value":""},"index":8},{"definition":{"axis_type":"dimension","name":"foo7","data_type":"string","precision":null,"empty_value":""},"index":9},{"definition":{"axis_type":"dimension","name":"foo8","data_type":"bool","precision":null,"empty_value":false},"index":10},{"definition":{"axis_type":"dimension","name":"foo9","data_type":"bool","precision":null,"empty_value":false},"index":11}],"is_aggregated":false,"aggregate_includes_meta":false,"host":"columna","result_row_count":1,"ms_elapsed":0.00970005989074707}
c0ea8d7333c06309284d25e192633236,123,"chapter 7 bankruptcy on credit for how long","chapter 7 \"bankruptcy\" on credit for how long\\","chapter 7 \\\"bankruptcy\" on credit for how long\\","chapter 7 \\\"bankruptcy\" /on, \\/credit \\for how long\\",",chapter 7 \\\"bankruptcy\" /on, \\/credit \\for how long\\,","\\,chapter 7 \\\"bankruptcy\" /on, \\/credit \\for how long\\\\,","\\,chapter 7 \\\"bankruptcy\" /on, \\/credit \\for how long\\\\,","[\"\\\\,chapter 7 \\\\\\\"bankruptcy\\\" \\/on, \\\\\\/credit \\\\for how long\\\\\"]",1,0
END;
        $expected_response = trim($expected_response);
        $response = $Reader->getResponsePayload();
        $response_ms_elapsed = $this->extractMsElapsedString($response);
        $expected_ms_elapsed_string = $this->extractMsElapsedString($expected_response);
        $expected_response = str_replace($expected_ms_elapsed_string,$response_ms_elapsed,$expected_response);

        $this->assertSame($expected_response,$response);
    }

    /**
     * @return array
     */
    protected function getWorkloadArray(): array {
        $array = $this->getWorkloadArrayWithoutFile();
        $array['file'] = self::TEST_OUTPUT_FILE;
        return $array;
    }

}