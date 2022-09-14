<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\Reader;
use PHPUnit\Framework\TestCase;
use Exception;

abstract class ConstraintTestAbstract extends TestCase {

    protected const METRIC_NAME = 'clicks';
    protected const FIXTURES_DIR = '/app/tests/fixtures/';

    /**
     * @return array
     */
    protected function getWorkloadArray(): array {
        return Array(
            'date' => '2022-07-08',
            'metric' => self::METRIC_NAME,
            'dimensions' => Array(
                'platform_id',
            ),
            'constraints' => Array(
                //Specified in each test
            ),
            'do_aggregate' => true,
            'do_aggregate_meta' => false,
            'file' => self::FIXTURES_DIR . "clicks--has_data_with_csort.scf",
        );
    }

    /**
     * @param array $workload_array
     * @throws Exception
     * @return int
     */
    protected function runQueryAndGetMetricTotal(array $workload_array): int {
        $Reader = new Reader();
        $Reader->runFromWorkload(json_encode($workload_array));
        $response = $Reader->getResponsePayload();
        return $this->extractValueFromResponsePayload($response);
    }

    /**
     * @param string $response
     * @throws Exception
     * @return int
     */
    protected function extractValueFromResponsePayload(string $response): int {
        $lines = explode("\n",$response);
        $header_data = json_decode($lines[0],true);

        if ($header_data["status"] === Reader::RESULT_STATUS_EMPTY) {
            return 0;
        }

        $column_meta = $header_data['column_meta'];
        $index = null;
        foreach ($column_meta as $column) {
            if ($column['definition']['name'] === self::METRIC_NAME) {
                $index = $column['index'];
                break;
            }
        }

        if (is_null($index)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." could not find metric '".self::METRIC_NAME."' in Reader output.");
        }

        $total = 0;
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue;
            }
            $value = str_getcsv($line)[$index];
            if (ctype_digit($value) !== true) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." value was not an integer, got {$value}.");
            }
            $total += intval($value);
        }

        return $total;
    }

}