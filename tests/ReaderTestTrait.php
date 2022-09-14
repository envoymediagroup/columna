<?php

use EnvoyMediaGroup\Columna\BundledReader;
use EnvoyMediaGroup\Columna\Constraint;
use EnvoyMediaGroup\Columna\Reader;

trait ReaderTestTrait {

    abstract protected function getWorkloadArray(): array;

    /**
     * @param string $response
     * @throws Exception
     * @return string
     */
    protected function extractMsElapsedString(string $response): string {
        $response_meta_line = explode("\n",$response)[0];
        $matches = [];
        preg_match("/(\"ms_elapsed\":.*?)[,}]/",$response_meta_line,$matches);
        if (empty($matches[1])) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." could not match ms_elapsed.");
        }
        return $matches[1];
    }

    /**
     * @param string $response
     * @param string $expected_response_file
     * @throws Exception
     * @return string
     */
    protected function getExpectedResponseWithCorrectMsElapsed(string $response, string $expected_response_file): string {
        $response_ms_elapsed = $this->extractMsElapsedString($response);
        $expected_response = file_get_contents($expected_response_file);
        $expected_ms_elapsed_string = $this->extractMsElapsedString($expected_response);
        return str_replace($expected_ms_elapsed_string,$response_ms_elapsed,$expected_response);
    }

    /**
     * @return array
     */
    protected function getWorkloadArrayWithoutFile(): array {
        return Array(
            'date' => '2022-07-08',
            'metric' => 'clicks',
            'dimensions' => Array(
                0 => 'platform_id',
                1 => 'car_make',
            ),
            'constraints' => Array(
                0 => Array(
                    0 => Array(
                        'name' => 'platform_id',
                        'comparator' => Constraint::GREATER_THAN,
                        'value' => 2,
                    ),
                    1 => Array(
                        'name' => 'site_id',
                        'comparator' => Constraint::IN,
                        'value' => Array(
                            0 => 1,
                            1 => 5,
                            2 => 6,
                            3 => 7,
                            4 => 11,
                        ),
                    ),
                    2 => Array(
                        'name' => 'card_type',
                        'comparator' => Constraint::CONTAINS_IN,
                        'value' => ['diner','visa'],
                    ),
                ),
            ),
            'do_aggregate' => true,
            'do_aggregate_meta' => false,
            'file' => self::FIXTURES_DIR . "clicks--has_data_with_csort.scf",
        );
    }

    /**
     * @return array
     */
    public function readerProvider(): array {
        return [
            'individual reader' => [new Reader()],
            'bundled reader' => [new BundledReader()],
        ];
    }

    /**
     * @return array
     */
    public function readerClassProvider(): array {
        return [
            'individual reader' => [Reader::class],
            'bundled reader' => [BundledReader::class],
        ];
    }

}