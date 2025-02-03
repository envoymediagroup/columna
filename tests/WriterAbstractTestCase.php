<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\ColumnDefinition;
use EnvoyMediaGroup\Columna\FileHelper;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use Exception;

class WriterAbstractTestCase extends TestCase {

    protected const TEST_DATE = '2022-07-08';
    protected const FIXTURES_DIR = '/app/tests/fixtures/';
    protected const TEST_OUTPUT_FILE = '/tmp/test_output_file.scf';
    protected const DIMENSIONS_FILE = self::FIXTURES_DIR . 'dimension_definitions.json';

    public function setUp(): void {
        parent::setUp();
        if (file_exists(self::TEST_OUTPUT_FILE)) {
            unlink(self::TEST_OUTPUT_FILE);
        }
    }

    public function tearDown(): void {
        if (file_exists(self::TEST_OUTPUT_FILE)) {
            unlink(self::TEST_OUTPUT_FILE);
        }
        parent::tearDown();
    }

    /**
     * @return FileHelper
     */
    protected function getMockFileHelper(): FileHelper {
        return new class extends FileHelper {
            //TODO No longer overrides anything. This method call can be removed.
        };
    }

    /**
     * Avoid barfing a mile of hex data in test output.
     * @param string $expected
     * @param string $actual
     * @return void
     */
    protected function assertFileEqualsBrief(string $expected, string $actual): void {
        $this->assertFileExists($expected);
        $this->assertFileExists($actual);
        if (file_get_contents($expected) !== file_get_contents($actual)) {
            $this->fail("File contents do not match.");
        }
    }

    /**
     * @throws Exception
     * @return ColumnDefinition
     */
    protected function getMetricDefinitionClicks(): ColumnDefinition {
        return new ColumnDefinition(
            ColumnDefinition::AXIS_TYPE_METRIC,
            'clicks',
            ColumnDefinition::DATA_TYPE_INT,
            null,
            0
        );
    }

    /**
     * @throws Exception
     * @return ColumnDefinition[]
     */
    protected function getAllDimensionDefinitions(): array {
        static $Defs;
        if (is_null($Defs)) {
            $array = json_decode(file_get_contents(self::DIMENSIONS_FILE),true);
            if (empty($array)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." could not load dimensions file.");
            }
            $Defs = [];
            foreach ($array as $row) {
                $Defs[] = ColumnDefinition::fromArray($row);
            }
        }
        return $Defs;
    }

    /**
     * @throws Exception
     * @return array
     */
    protected function getRowBasedHeaders(): array {
        static $headers_array;
        if (is_null($headers_array)) {
            $headers_array_file = self::FIXTURES_DIR . "/clicks--row_based_headers.json";
            if (!file_exists($headers_array_file)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." headers array file '{$headers_array_file}' does not exist.");
            }
            $headers_array = json_decode(file_get_contents($headers_array_file),true);
            if (empty($headers_array)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." headers array was empty.");
            }
        }
        return $headers_array;
    }

    /**
     * @throws Exception
     * @return array
     */
    protected function getRowBasedData(): array {
        static $results_array;
        if (is_null($results_array)) {
            $results_array_file = self::FIXTURES_DIR . "/clicks--row_based_data.json";
            if (!file_exists($results_array_file)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." results array file '{$results_array_file}' does not exist.");
            }
            $results_array = json_decode(file_get_contents($results_array_file),true);
            if (empty($results_array)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." results array was empty.");
            }
        }
        return $results_array;
    }

    /**
     * @throws Exception
     * @return ColumnDefinition[]
     */
    protected function getSubsetOfDimensionDefinitions(): array {
        static $Defs;
        if (is_null($Defs)) {
            $array = json_decode(file_get_contents(self::DIMENSIONS_FILE),true);
            if (empty($array)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." could not load dimensions file.");
            }
            $Defs = [];
            foreach ($array as $row) {
                if (in_array($row["name"],['user_id','email','site_id','platform_id','car_make','sequence_id'])) {
                    $Defs[] = ColumnDefinition::fromArray($row);
                }
            }
        }
        return $Defs;
    }

    /**
     * @throws Exception
     * @return array
     */
    protected function getSubsetOfRowBasedHeaders(): array {
        return [
            'clicks',
            'site_id',
            'platform_id',
            'sequence_id',
        ];
    }

}
