<?php

namespace EnvoyMediaGroup\Columna;

use SplFileObject;
use Exception;
use JsonException;

abstract class WriterAbstract {

    /** @var $FileHelper */
    protected $FileHelper;
    /** @var string */
    protected $date;
    /** @var ColumnDefinition */
    protected $MetricDefinition;
    /** @var ColumnDefinition[] */
    protected $DimensionDefinitions;
    /** @var array */
    protected $indexes_to_column_names;
    /** @var string */
    protected $output_file_path;
    /** @var bool */
    protected $lock_output_file = true;

    /**
     * @param FileHelper|null $FileHelper
     */
    public function __construct(?FileHelper $FileHelper = null) {
        $this->FileHelper = $FileHelper ?? new FileHelper();
    }

    /**
     * @param string $date
     * @throws Exception
     */
    protected function setDate(string $date): void {
        if (!(
            preg_match("/^(\d{4})-(\d{2})-(\d{2})$/", $date, $matches) === 1 &&
            checkdate($matches[2], $matches[3], $matches[1]) === true
        )) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid date '{$date}'. Use Y-m-d format, e.g. '2021-02-17'.");
        }
        $this->date = $date;
    }

    /**
     * @param ColumnDefinition $MetricDefinition
     * @throws Exception
     */
    protected function setMetricDefinition(ColumnDefinition $MetricDefinition): void {
        if ($MetricDefinition->getAxisType() !== ColumnDefinition::AXIS_TYPE_METRIC) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." metric definition did not have correct axis_type.");
        }
        if (!in_array($MetricDefinition->getDataType(),[ColumnDefinition::DATA_TYPE_INT,ColumnDefinition::DATA_TYPE_FLOAT])) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." metric definition did not have correct data_type.");
        }
        $this->MetricDefinition = $MetricDefinition;
    }

    /**
     * @param array $DimensionDefinitions
     * @throws Exception
     */
    protected function setDimensionDefinitions(array $DimensionDefinitions): void {
        if (empty($DimensionDefinitions)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." must supply at least one dimension.");
        }

        $temp = [];
        foreach ($DimensionDefinitions as $DimensionDefinition) {
            if (!is_a($DimensionDefinition,ColumnDefinition::class)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." array item was not a ColumnDefinition.");
            }
            if ($DimensionDefinition->getAxisType() !== ColumnDefinition::AXIS_TYPE_DIMENSION) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." dimension definition did not have correct axis_type.");
            }
            if (isset($temp[$DimensionDefinition->getName()])) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." cannot specify dimension '{$DimensionDefinition->getName()}' more than once.");
            }
            $temp[$DimensionDefinition->getName()] = $DimensionDefinition;
        }

        ksort($temp);
        $this->DimensionDefinitions = $temp;
    }

    /**
     * @return void
     */
    protected function populateIndexesToColumnNames(): void {
        $all_axes = array_merge([$this->MetricDefinition->getName()],array_keys($this->DimensionDefinitions));
        sort($all_axes);
        $this->indexes_to_column_names = $all_axes;
    }

    /**
     * @param string $output_file_path
     * @throws Exception
     */
    protected function setOutputFilePath(string $output_file_path): void {
        if ($output_file_path === '') {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." output file path cannot be empty.");
        }
        if (file_exists($output_file_path)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." output file '{$output_file_path}' already exists.");
        }
        $dir = dirname($output_file_path);
        if (!is_writable($dir)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." output file directory '{$dir}' does not exist or is not writable.");
        }
        $this->output_file_path = $output_file_path;
    }

    /**
     * @param bool $lock_output_file
     * @return void
     */
    protected function setLockOutputFile(bool $lock_output_file): void {
        $this->lock_output_file = $lock_output_file;
    }

    /**
     * Write the meta on the first line, then transfer the rest line by line.
     * @param SplFileObject $TmpFile
     * @param SplFileObject $OutputFile
     * @param array $meta
     * @throws JsonException
     * @return void
     */
    protected function writeCombinedFileFromTmpFileAndMeta(SplFileObject $TmpFile, SplFileObject $OutputFile, array $meta): void {
        $OutputFile->fwrite(json_encode($meta,JSON_THROW_ON_ERROR) . "\n");
        $TmpFile->rewind();
        while (!$TmpFile->eof()) {
            $OutputFile->fwrite($TmpFile->fgets());
        }
    }

    /**
     * @throws JsonException
     * @return void
     */
    protected function writeOutputFileNoData(): void {
        $File = null;
        try {
            $File = $this->FileHelper->openFileForWrite($this->output_file_path,$this->lock_output_file);
            $File->fwrite(json_encode($this->generateMetaNoData(),JSON_THROW_ON_ERROR));
        } finally {
            $this->FileHelper->closeFileAndUnlockIfNeeded($File,$this->lock_output_file);
        }
    }

    /**
     * @return array
     */
    protected function generateMetaNoData(): array {
        return [
            "date" => $this->date,
            "metric" => $this->MetricDefinition->getName(),
            "status" => Reader::FILE_STATUS_NO_DATA,
            "lib_version" => Reader::LIB_MAJOR_VERSION,
        ];
    }

    /**
     * @param array $metric_values
     * @param array $column_byte_offsets
     * @throws Exception
     * @return array
     */
    protected function generateMetaWithData(array $metric_values, array $column_byte_offsets): array {
        return [
            "date" => $this->date,
            "metric" => $this->MetricDefinition->getName(),
            "status" => Reader::FILE_STATUS_HAS_DATA,
            "lib_version" => Reader::LIB_MAJOR_VERSION,
            "min" => ($this->MetricDefinition->getDataType() === 'int' ? intval(min($metric_values)) : floatval(min($metric_values))),
            "max" => ($this->MetricDefinition->getDataType() === 'int' ? intval(max($metric_values)) : floatval(max($metric_values))),
            "sum" => ($this->MetricDefinition->getDataType() === 'int' ? intval(array_sum($metric_values)) : floatval(array_sum($metric_values))),
            "count" => count($metric_values),
            "column_meta" => $this->generateColumnMeta($column_byte_offsets),
        ];
    }

    /**
     * @param array $column_byte_offsets
     * @throws Exception
     * @return array
     */
    protected function generateColumnMeta(array $column_byte_offsets): array {
        $column_meta = [];
        //Keyed by column name because that helps us in the Reader.
        //json forced ksort does not hurt us; columns are ksorted by column name anyway.
        foreach ($this->indexes_to_column_names as $index => $column) {
            $ColumnDefinition = $this->getColumnDefinition($column);
            $column_meta[$column] = $this->generateMetaForOneColumn($index, $ColumnDefinition, $column_byte_offsets);
        }
        return $column_meta;
    }

    /**
     * @param int $index
     * @param ColumnDefinition $ColumnDefinition
     * @param array $column_byte_offsets
     * @throws Exception
     * @return array
     */
    protected function generateMetaForOneColumn(int $index, ColumnDefinition $ColumnDefinition, array $column_byte_offsets):array {
        return [
            "definition" => $ColumnDefinition->toArray(),
            "index" => $index,
            "offset" => $column_byte_offsets[$ColumnDefinition->getName()],
        ];
    }

    /**
     * @param string $column
     * @throws Exception
     * @return ColumnDefinition
     */
    protected function getColumnDefinition(string $column): ColumnDefinition {
        if ($column === $this->MetricDefinition->getName()) {
            return $this->MetricDefinition;
        } else if (array_key_exists($column,$this->DimensionDefinitions)) {
            return $this->DimensionDefinitions[$column];
        } else {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." got unknown column name '{$column}'.");
        }
    }

    /**
     * @param int $hr_start
     * @return int
     */
    protected function millisecondsElapsed(int $hr_start): int {
        return intval(round((hrtime(true) - $hr_start) / 1000000));
    }

}