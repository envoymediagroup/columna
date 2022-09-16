<?php

namespace EnvoyMediaGroup\Columna;

use JsonException;
use Throwable;
use Exception;
use SplFileObject;

class Writer extends WriterAbstract {

    protected const MIN_ALLOWED_COMPRESSION_PERCENT = 30;

    /**
     * @param string $date
     * @param ColumnDefinition $MetricDefinition
     * @param ColumnDefinition[] $DimensionDefinitions
     * @param string[] $row_based_headers
     * @param array $row_based_data
     * @param string $output_file_path
     * @param bool $do_rle_compression
     * @param bool $do_cardinality_sort
     * @param bool $lock_output_file
     * @throws Throwable
     * @return array
     */
    public function writeFile(
        string           $date,
        ColumnDefinition $MetricDefinition,
        array            $DimensionDefinitions,
        array            $row_based_headers,
        array            $row_based_data,
        string           $output_file_path,
        bool             $do_rle_compression = true,
        bool             $do_cardinality_sort = false,
        bool             $lock_output_file = true
    ): array {
        $hr_start = hrtime(true);
        $this->setDate($date);
        $this->setMetricDefinition($MetricDefinition);
        $this->setDimensionDefinitions($DimensionDefinitions);
        $this->populateIndexesToColumnNames();
        $this->setOutputFilePath($output_file_path);
        $this->setLockOutputFile($lock_output_file);

        if (empty($row_based_data)) {
            $this->writeOutputFileNoData();
            return [
                "status" => Reader::FILE_STATUS_NO_DATA,
                "write_time_ms" => $this->millisecondsElapsed($hr_start),
            ];
        }

        if ($do_rle_compression === false) {
            $do_cardinality_sort = false;
        }

        [$row_based_headers,$row_based_data] = $this->alphaSortHeadersAndData($row_based_headers,$row_based_data);
        $this->validateRowBasedHeaders($row_based_headers);
        $this->validateRowBasedData($row_based_data);
        $row_based_data = $this->replaceNullsWithEmptyValues($row_based_data);
        $row_based_data = $this->convertRowsToStrings($row_based_data);
        if ($do_cardinality_sort) {
            $this->sortByCardinality($row_based_data);
        }
        $columnar_data = $this->transposeRowsToColumns($row_based_data);
        if ($do_rle_compression) {
            $columnar_data = $this->compressColumnarData($columnar_data);
        }

        $this->writeOutputFileWithData($columnar_data);

        return [
            "status" => Reader::FILE_STATUS_HAS_DATA,
            "write_time_ms" => $this->millisecondsElapsed($hr_start),
        ];
    }

    /**
     * @param array $associative_data
     * @throws Exception
     * @return array
     */
    public function separateHeadersAndData(array $associative_data): array {
        // Extract the headers and remove them from the data set
        $headers = array_keys(current($associative_data));
        $data = [];
        foreach ($associative_data as $row) {
            if (array_keys($row) !== $headers) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." data did not have a consistent set of keys.");
            }
            $data[] = array_values($row);
        }
        return [$headers,$data];
    }

    /**
     * @param array $row_based_data
     * @throws JsonException
     * @return array
     */
    protected function convertRowsToStrings(array $row_based_data): array {
        $new_array = [];
        foreach ($row_based_data as $i => $row) {
            $new_array[$i] = CsvSafeHelper::convertValuesToStrings($row);
        }
        return $new_array;
    }

    /**
     * @param array $row_based_data
     * @throws Exception
     * @return array
     */
    protected function replaceNullsWithEmptyValues(array $row_based_data): array {
        foreach ($row_based_data as &$row) {
            foreach ($row as $j => &$value) {
                if (is_null($value)) {
                    $Column = $this->getColumnDefinition($this->indexes_to_column_names[$j]);
                    $value = $Column->getEmptyValue();
                }
            }
        }
        return $row_based_data;
    }

    /**
     * @param array $row_based_data
     * @throws Exception
     */
    protected function sortByCardinality(array &$row_based_data): void {
        CardinalitySortHelper::sortByCardinality($this->indexes_to_column_names,$row_based_data);
    }

    /**
     * @param array $row_based_data
     * @throws Exception
     * @return array
     */
    protected function transposeRowsToColumns(array $row_based_data): array {
        //Column names are already in alpha order in each row, so this array will be built with keys in alpha order.
        $columnar_data = [];
        foreach ($row_based_data as $row_index => $row) {
            foreach ($row as $column_index => $value) {
                $columnar_data[$this->indexes_to_column_names[$column_index]][$row_index] = $value;
            }
        }
        $this->sanityChecksAfterTranspose($row_based_data,$columnar_data);
        return $columnar_data;
    }

    /**
     * @param array $row_based_data
     * @param array $columnar_data
     * @throws Exception
     */
    protected function sanityChecksAfterTranspose(
        array $row_based_data,
        array $columnar_data
    ): void {
        $before_row_count = count($row_based_data);
        $metric_index = array_flip($this->indexes_to_column_names)[$this->MetricDefinition->getName()];
        $metric_total_before = array_sum(array_column($row_based_data,$metric_index));
        $metric_total_after = array_sum($columnar_data[$this->MetricDefinition->getName()]);

        if (count($columnar_data) !== count(reset($row_based_data))) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." column based data did not have same column count as row " .
                "based data.");
        }

        foreach ($columnar_data as $column_name => $values) {
            if (count($values) !== $before_row_count) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." column based data in column '{$column_name}' did not " .
                    "have same row count as row based data.");
            }
        }

        if ($metric_total_before !== $metric_total_after) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." metric totals before and after transpose did not match. " .
                "Before: {$metric_total_before}, after: {$metric_total_after}.");
        }
    }

    /**
     * @param array $columnar_data
     * @throws Exception
     * @return array
     */
    protected function compressColumnarData(array $columnar_data): array {
        foreach ($columnar_data as $column => $values) {
            $columnar_data[$column] = RleHelper::rleCompressIfThresholdMet(
                $column,
                $values,
                self::MIN_ALLOWED_COMPRESSION_PERCENT
            );
        }
        return $columnar_data;
    }

    /**
     * @param string[] $row_based_headers
     * @throws Exception
     * @return void
     */
    protected function validateRowBasedHeaders(array $row_based_headers): void {
        if ($row_based_headers !== $this->indexes_to_column_names) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." row based headers did not match Metric/Dimension " .
                "definitions or were not in alphabetical order.");
        }
    }

    /**
     * @param array $row_based_data
     * @throws Exception
     */
    protected function validateRowBasedData(array $row_based_data): void {
        if (empty($row_based_data)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." input array was empty.");
        }

        foreach ($row_based_data as $i => $row) {
            if (array_keys($row) !== array_keys($this->indexes_to_column_names)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." row based data did not match expected headers " .
                    "at row {$i}.");
            }
            foreach ($row as $column => $value) {
                if (!(is_scalar($value) || is_null($value))) { //Nulls handled elsewhere
                    $type = gettype($value);
                    throw new Exception(__CLASS__.'::'.__FUNCTION__." values of type '{$type}' are not currently " .
                        "supported. Supported types: bool, int, float, string. " .
                        "Bad value was row {$i}, column '{$column}': " . ErrorHelper::varDumpToString($value));
                }
            }
        }
    }

    /**
     * @param array $row_based_headers
     * @param array $row_based_data
     * @return array
     */
    protected function alphaSortHeadersAndData(array $row_based_headers, array $row_based_data): array {
        $headers_flip = array_flip($row_based_headers);
        ksort($headers_flip);
        $new_headers = array_keys($headers_flip);

        //Skip if already sorted.
        if ($row_based_headers === $new_headers) {
            return [$row_based_headers,$row_based_data];
        }

        $new_data = [];
        foreach ($row_based_data as $row) {
            $new_row = [];
            foreach ($new_headers as $i => $key) {
                $new_row[$i] = $row[$headers_flip[$key]];
            }
            $new_data[] = $new_row;
        }

        return [$new_headers,$new_data];
    }

    /**
     * @param array $columnar_data
     * @throws Throwable
     */
    protected function writeOutputFileWithData(array $columnar_data): void {
        $TmpFile = null;
        $OutputFile = null;
        try {
            $TmpFile = $this->FileHelper->openNewTmpFileForReadAndWrite();
            $column_byte_offsets = $this->writeTmpFileWithoutMetaAndGenerateOffsets($TmpFile,$columnar_data);
            $meta = $this->generateMetaWithData($columnar_data[$this->MetricDefinition->getName()], $column_byte_offsets);
            $OutputFile = $this->FileHelper->openFileForWrite($this->output_file_path,$this->lock_output_file);
            $this->writeCombinedFileFromTmpFileAndMeta($TmpFile,$OutputFile,$meta);
        } finally {
            $this->FileHelper->closeAndDeleteFile($TmpFile);
            $this->FileHelper->closeFileAndUnlockIfNeeded($OutputFile,$this->lock_output_file);
        }
    }

    /**
     * @param SplFileObject $TmpFile
     * @param array $columnar_data
     * @throws Exception
     * @return array
     */
    protected function writeTmpFileWithoutMetaAndGenerateOffsets(SplFileObject $TmpFile, array $columnar_data): array {
        $offset = 0;
        $column_byte_offsets = [];

        foreach ($columnar_data as $column => $values) {
            $column_byte_offsets[$column] = $offset;
            try {
                $offset += CsvSafeHelper::fputcsvSafe($TmpFile, $values);
            } catch (CsvException $e) {
                throw new Exception("Failed on column '{$column}': " . $e->getMessage(),$e->getCode(),$e);
            }
        }
        $TmpFile->rewind();

        return $column_byte_offsets;
    }

}