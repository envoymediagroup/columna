<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\BundledReader;
use EnvoyMediaGroup\Columna\Constraint;
use Exception;
use EnvoyMediaGroup\Columna\Reader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers Reader
 */
class ReaderTest extends TestCase {
    use \ReaderTestTrait;

    protected const FIXTURES_DIR = '/app/tests/fixtures/';

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testFileHasDataAndResultHasDataWithAggregationNoMeta($Reader) {
        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_aggregated_no_meta_with_csort.txt";
        $workload_array = $this->getWorkloadArray();
        $workload_array["do_aggregate"] = true;
        $workload_array["do_aggregate_meta"] = false;

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
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testFileHasDataAndResultHasDataWithAggregationAndMeta($Reader) {
        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_aggregated_with_meta.txt";
        $workload_array = $this->getWorkloadArray();
        $workload_array["do_aggregate"] = true;
        $workload_array["do_aggregate_meta"] = true;

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
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testFileHasDataAndResultHasDataWithoutAggregation($Reader) {
        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_not_aggregated.txt";
        $workload_array = $this->getWorkloadArray();
        $workload_array["do_aggregate"] = false;
        $workload_array["do_aggregate_meta"] = false;

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
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testFileHasDataAndResultHasDataWithoutAggregationIgnoresAggregateMeta($Reader) {
        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_not_aggregated.txt";
        $workload_array = $this->getWorkloadArray();
        $workload_array["do_aggregate"] = false;
        $workload_array["do_aggregate_meta"] = true;

        $Reader->runFromWorkload(json_encode($workload_array));
        $response = $Reader->getResponsePayload();

//        //For updating the test files
//        file_put_contents($expected_response_file,$response);
//        print "Saved to:\n$expected_response_file\n";
//        exit;

        //Same expected results as the test above, so we use the same file.
        $expected_response = $this->getExpectedResponseWithCorrectMsElapsed($response,$expected_response_file);
        $this->assertEquals($expected_response,$response);
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testFileHasDataAndResultIsEmpty($Reader) {
        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_empty_result.txt";
        $workload_array = $this->getWorkloadArray();
        $workload_array["constraints"][0][0] = [
            "name" => "platform_id",
            "comparator" => Constraint::EQUALS,
            "value" => 9,
        ];

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
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testFileHasNoData($Reader) {
        $expected_response_file = self::FIXTURES_DIR . "clicks--reader_response_empty_result.txt";
        $workload_array = $this->getWorkloadArray();
        $workload_array["file"] = self::FIXTURES_DIR . 'clicks--no_data.scf';

        $Reader->runFromWorkload(json_encode($workload_array));
        $response = $Reader->getResponsePayload();

        $expected_response = $this->getExpectedResponseWithCorrectMsElapsed($response,$expected_response_file);
        $this->assertEquals($expected_response,$response);
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testErrorResponse($Reader) {
        $workload_array = $this->getWorkloadArray();
        $workload_array["constraints"][0][0] = [
            "name" => "foobar",
            "comparator" => Constraint::GREATER_THAN,
            "value" => 0,
        ];

        //Not using expectedExceptionMessage because I also want to check contents of getResults() and getMetadata().
        $thrown = false;
        try {
            $Reader->runFromWorkload(json_encode($workload_array));
        } catch (\Throwable $e) {
            $thrown = true;
            $this->assertStringContainsString("column 'foobar' not found",$e->getMessage());
        }

        $this->assertTrue($thrown);
        $this->assertEquals([],$Reader->getResults());
        $error = 'EnvoyMediaGroup\\Columna\\ConstraintParser::validateConstraints column \'foobar\' not found in metadata of file.';
        if (is_a($Reader,BundledReader::class)) {
            $error = str_replace("ConstraintParser","BundledConstraintParser",$error);
        }

        $metadata = $Reader->getMetadata();
        $expected = [
            'date' => '2022-07-08',
            'metric' => 'clicks',
            'status' => 'error',
            'error' => $error,
            'host' => 'columna',
            'ms_elapsed' => $metadata['ms_elapsed'],
        ];
        $this->assertEquals($expected,$metadata);
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testGetFileMetadata($Reader) {
        $file = self::FIXTURES_DIR . 'clicks--no_data.scf';
        $result = $Reader->getFileMetadata($file);
        $expected = [
            "date" => "2022-07-08",
            "metric" => "clicks",
            "status" => "no data",
            "lib_version" => 1,
        ];
        $this->assertEquals($expected,$result);
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testThrowsOnMissingFile($Reader) {
        $workload_array = $this->getWorkloadArray();
        $workload_array["file"] = "/tmp/foobar.nonce";
        $workload_array["date"] = "2001-01-01";

        $this->expectExceptionMessage("Failed to open stream: No such file or directory");
        $Reader->runFromWorkload(json_encode($workload_array));
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testThrowsOnMalformedFile($Reader) {
        $workload_array = $this->getWorkloadArray();
        $workload_array["file"] = self::FIXTURES_DIR . 'clicks--malformed.scf';

        $this->expectException("JsonException");
        $Reader->runFromWorkload(json_encode($workload_array));
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testThrowsOnFileHasWrongDate($Reader) {
        $workload_array = $this->getWorkloadArray();
        $workload_array["date"] = "2021-10-15";

        $this->expectExceptionMessage("does not cover date");
        $Reader->runFromWorkload(json_encode($workload_array));
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testThrowsOnInvalidDate($Reader) {
        $workload_array = $this->getWorkloadArray();
        $workload_array["date"] = "2021-13-99";

        $this->expectExceptionMessage("invalid date '2021-13-99'");
        $Reader->runFromWorkload(json_encode($workload_array));
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testThrowsOnInvalidMetric($Reader) {
        $workload_array = $this->getWorkloadArray();
        $workload_array["metric"] = "foobar";

        $this->expectExceptionMessage("does not cover metric 'foobar'");
        $Reader->runFromWorkload(json_encode($workload_array));
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testThrowsOnFileVersionGreaterThanReaderVersion($Reader) {
        $workload_array = $this->getWorkloadArray();
        $workload_array["file"] = self::FIXTURES_DIR . "clicks--no_data_future_version.scf";

        $this->expectExceptionMessage("greater than Reader major version");
        $Reader->runFromWorkload(json_encode($workload_array));
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testThrowsOnInvalidDimension($Reader) {
        $workload_array = $this->getWorkloadArray();
        $workload_array["dimensions"][] = "bazqux";

        $this->expectExceptionMessage("required column 'bazqux' does not exist");
        $Reader->runFromWorkload(json_encode($workload_array));
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testThrowsOnInvalidConstraint($Reader) {
        $workload_array = $this->getWorkloadArray();
        $workload_array["constraints"][0][] = ["not" => "valid"];

        $this->expectExceptionMessage("constraint did not contain name, comparator, and value");
        $Reader->runFromWorkload(json_encode($workload_array));
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testThrowsOnInvalidFile($Reader) {
        $workload_array = $this->getWorkloadArray();
        $workload_array["file"] = 103.2;

        $this->expectExceptionMessage("Failed to open stream: No such file or directory");
        $Reader->runFromWorkload(json_encode($workload_array));
    }

    /**
     * @param Reader|BundledReader $Reader
     * @throws Exception
     * @dataProvider readerProvider
     */
    public function testThrowsOnInvalidWorkload($Reader) {
        $this->expectException("JsonException");
        $Reader->runFromWorkload("This is not json");
    }

    /**
     * @param $reader_class
     * @throws Exception
     * @return void
     * @dataProvider readerClassProvider
     */
    public function testThrowsErrorExceptionOnWarning($reader_class) {
        /** @var Reader|BundledReader|MockObject $Reader */
        $Reader = $this->getMockBuilder($reader_class)
            ->onlyMethods(['run'])
            ->getMock();
        $Reader->expects($this->once())->method('run')->willReturnCallback(
            function() { trigger_error("foobar warning", E_USER_WARNING); }
        );

        $this->expectException("ErrorException");
        $this->expectExceptionMessage("foobar warning");
        $Reader->runFromWorkload(json_encode($this->getWorkloadArray()));
    }

    /**
     * @return array
     */
    protected function getWorkloadArray(): array {
        $array = $this->getWorkloadArrayWithoutFile();
        $array['file'] = self::FIXTURES_DIR . "clicks--has_data_with_csort.scf";
        return $array;
    }

}