<?php

namespace EnvoyMediaGroup\Columna;

use ErrorException;
use Exception;
use JsonException;
use SplFileObject;

/**
 * Bundles the following classes: 
 *   Reader
 *   ColumnDefinition
 *   Constraint
 *   ConstraintParser
 *   CsvException
 *   CsvSafeHelper
 *   DataTypeHelper
 *   ErrorHelper
 *   RleHelper
 */
class BundledReader
{
    public const LIB_MAJOR_VERSION = 1;
    public const FILE_EXTENSION = 'scf';
    public const FILE_STATUS_HAS_DATA = 'has data';
    public const FILE_STATUS_NO_DATA = 'no data';
    public const RESULT_STATUS_EMPTY = 'empty';
    public const RESULT_STATUS_SUCCESS = 'success';
    public const RESULT_STATUS_ERROR = 'error';
    /** @var float */
    protected $hrtime_start;
    /** @var array */
    protected $metadata;
    /** @var array */
    protected $results;
    /**
     * @param string $workload
     * @throws Exception
     */
    public function runFromWorkload(string $workload) : void
    {
        $args = json_decode($workload, true, 512, JSON_THROW_ON_ERROR);
        $this->run($args['date'], $args['metric'], $args['dimensions'], $args['constraints'], $args['do_aggregate'], $args['do_aggregate_meta'], $args['file']);
    }

    /**
     * @param string $date
     * @param string $metric
     * @param array $dimensions
     * @param array $constraints
     * @param bool $do_aggregate
     * @param bool $do_aggregate_meta
     * @param string $file_path
     * @throws Exception
     */
    public function run(string $date, string $metric, array $dimensions, array $constraints, bool $do_aggregate, bool $do_aggregate_meta, string $file_path)
    {
        try {
            $this->setErrorHandler();
            $this->runInner($date, $metric, $dimensions, $constraints, $do_aggregate, $do_aggregate_meta, $file_path);
            restore_error_handler();
        } catch (Exception $e) {
            $this->handleError($date, $metric, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param string $file_path
     * @throws JsonException
     * @return array
     */
    public function getFileMetadata(string $file_path) : array
    {
        $File = $this->openFile($file_path);
        $header = $File->fgets();
        $this->closeFile($File);
        return json_decode($header, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array
     */
    public function getMetadata() : array
    {
        return $this->metadata;
    }

    /**
     * @return array
     */
    public function getResults() : array
    {
        return $this->results;
    }

    /**
     * @throws JsonException
     * @return string
     */
    public function getResponsePayload() : string
    {
        $TmpFile = new SplFileObject('php://temp', 'r+b');
        $TmpFile->fwrite(json_encode($this->metadata, JSON_THROW_ON_ERROR) . "\n");
        foreach ($this->results as $row) {
            BundledCsvSafeHelper::fputcsvSafe($TmpFile, BundledCsvSafeHelper::convertValuesToStrings($row));
        }
        $TmpFile->rewind();
        $output = '';
        while ($TmpFile->valid()) {
            $output .= $TmpFile->fgets();
        }
        $output = trim($output);
        unset($TmpFile);
        return $output;
    }

    /**
     * @param string $date
     * @param string $metric
     * @param array $dimensions
     * @param array $constraints
     * @param bool $do_aggregate
     * @param bool $do_aggregate_meta
     * @param string $file_path
     * @throws Exception
     */
    protected function runInner(string $date, string $metric, array $dimensions, array $constraints, bool $do_aggregate, bool $do_aggregate_meta, string $file_path)
    {
        $this->hrtime_start = hrtime(true);
        $this->validateDate($date);
        //Other inputs are validated in the course of their use
        //The following vars will always be set after the try finally block because it has no catch.
        //  The null declarations satisfy IDE static analysis that these vars will not be undefined.
        $data_set = null;
        $required_columns = null;
        $selected_columns = null;
        $column_meta = null;
        $File = null;
        try {
            $File = $this->openFile($file_path);
            $header = $File->fgets();
            //includes trailing \n
            $file_meta = json_decode($header, true, 512, JSON_THROW_ON_ERROR);
            //trailing \n is ignored
            $this->validateFileMeta($file_meta, $date, $metric, $file_path);
            if ($file_meta["status"] === self::FILE_STATUS_NO_DATA) {
                $this->handleEmptyResults($date, $metric);
                return;
            }
            $column_meta = $file_meta["column_meta"];
            $constraints = (new BundledConstraintParser())->unserializeConstraints($constraints, $column_meta);
            list($selected_columns, $required_columns) = $this->generateSelectedAndRequiredColumns($metric, $dimensions, $constraints);
            $data_set = $this->readFileOnlyRequiredColumns($File, $column_meta, $header, $required_columns);
        } finally {
            $this->closeFile($File);
        }
        if ($data_set === []) {
            $this->handleEmptyResults($date, $metric);
            return;
        }
        $constraints = $this->addColumnIndexesToConstraints($constraints, $required_columns);
        $data_set = $this->applyConstraints($data_set, $constraints);
        if ($data_set === []) {
            $this->handleEmptyResults($date, $metric);
            return;
        }
        $metric_index = $required_columns[$metric];
        $data_set = $this->removeNonSelectedColumns($data_set, $selected_columns);
        $data_set = $this->prependMd5Hashes($data_set, $metric_index);
        $metric_index++;
        //offset for prepended md5. $selected_columns_meta handled in generateSelectedColumnsMeta().
        $metadata = $this->generateMetadata($data_set, $date, $metric, $metric_index, $selected_columns, $column_meta, $do_aggregate, $do_aggregate_meta);
        if ($do_aggregate === true) {
            if ($do_aggregate_meta === true) {
                $data_set = $this->aggregateOnMatchingDimensionsWithRowMeta($data_set, $metric_index);
            } else {
                $data_set = $this->aggregateOnMatchingDimensionsWithoutRowMeta($data_set, $metric_index);
            }
        }
        $data_set = $this->reindexRowsFromZero($data_set);
        $metadata["result_row_count"] = count($data_set);
        $metadata["ms_elapsed"] = $this->getMsElapsed();
        $this->results = $data_set;
        $this->metadata = $metadata;
    }

    /**
     * @param array $file_meta
     * @param string $date
     * @param string $metric
     * @param string $file_path
     * @throws Exception
     */
    protected function validateFileMeta(array $file_meta, string $date, string $metric, string $file_path) : void
    {
        $required_keys = ["date", "metric", "status", "lib_version"];
        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $file_meta)) {
                throw new Exception(self::class . '::' . __FUNCTION__ . " file meta does not include required key '{$key}'.");
            }
        }
        if ($file_meta["lib_version"] > self::LIB_MAJOR_VERSION) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " Writer major version " . "'{$file_meta["lib_version"]}' that created this file is greater than Reader major version '" . self::LIB_MAJOR_VERSION . "'.");
        }
        if ($file_meta["date"] !== $date) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " file '{$file_path}' does not cover date '{$date}'.");
        }
        if ($file_meta["metric"] !== $metric) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " file '{$file_path}' does not cover metric '{$metric}'.");
        }
        if (!in_array($file_meta["status"], [self::FILE_STATUS_HAS_DATA, self::FILE_STATUS_NO_DATA])) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid file status '{$file_meta["status"]}'.");
        }
        if ($file_meta["status"] === self::FILE_STATUS_HAS_DATA && !array_key_exists("column_meta", $file_meta)) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " file meta must include key 'column_meta' when data is non-empty.");
        }
    }

    /**
     * @param string $date
     * @throws Exception
     */
    protected function validateDate(string $date) : void
    {
        if (!(preg_match("/^(\\d{4})-(\\d{2})-(\\d{2})\$/", $date, $matches) === 1 && checkdate($matches[2], $matches[3], $matches[1]) === true)) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid date '{$date}'. Use Y-m-d format, e.g. '2021-02-17'.");
        }
    }

    /**
     * @param array $data_set
     * @return array
     */
    protected function reindexRowsFromZero(array $data_set) : array
    {
        $temp = [];
        foreach ($data_set as $row) {
            $temp[] = array_values($row);
        }
        return $temp;
    }

    /**
     * @param string $date
     * @param string $metric
     * @param string $message
     */
    protected function handleError(string $date, string $metric, string $message) : void
    {
        $this->results = [];
        $this->metadata = ["date" => $date, "metric" => $metric, "status" => self::RESULT_STATUS_ERROR, "error" => $message, "host" => $this->getHostId(), "ms_elapsed" => $this->getMsElapsed()];
    }

    /**
     * @param string $date
     * @param string $metric
     */
    protected function handleEmptyResults(string $date, string $metric) : void
    {
        $this->results = [];
        $this->metadata = ["date" => $date, "metric" => $metric, "status" => self::RESULT_STATUS_EMPTY, "host" => $this->getHostId(), "ms_elapsed" => $this->getMsElapsed()];
    }

    /**
     * @param string $metric
     * @param array $dimensions
     * @param array $constraints
     * @return array
     */
    protected function generateSelectedAndRequiredColumns(string $metric, array $dimensions, array $constraints) : array
    {
        $temp_selections = array_merge([$metric], $dimensions);
        sort($temp_selections);
        $constraint_columns = [];
        foreach ($constraints as $constraint_group) {
            foreach ($constraint_group as $constraint) {
                $constraint_columns[] = $constraint["name"];
            }
        }
        $temp_required_columns = array_unique(array_merge($temp_selections, $constraint_columns));
        sort($temp_required_columns);
        $required_columns = array_flip($temp_required_columns);
        //$required_columns is now ['column_name'] => [integer index of that column in each row]
        //These indexes will be sequential
        $selected_columns = [];
        foreach ($temp_selections as $selection) {
            $selected_columns[$required_columns[$selection]] = $selection;
        }
        //$selections is now [integer index of that column in each row] => ['selection_name']
        //These indexes may not be sequential if the $constraint_columns are different from the $dimensions
        return [$selected_columns, $required_columns];
    }

    /**
     * @param array $constraints
     * @param array $required_columns
     * @return array
     */
    protected function addColumnIndexesToConstraints(array $constraints, array $required_columns) : array
    {
        $temp = [];
        foreach ($constraints as $group_index => $constraint_group) {
            foreach ($constraint_group as $constraint_index => $constraint) {
                $item = $constraint;
                $item['column_index'] = $required_columns[$constraint['name']];
                $temp[$group_index][$constraint_index] = $item;
            }
        }
        return $temp;
    }

    /**
     * @param SplFileObject $File
     * @param array $column_meta
     * @param string $header
     * @param array $required_columns
     * @throws Exception
     * @return array
     */
    protected function readFileOnlyRequiredColumns(SplFileObject $File, array $column_meta, string $header, array $required_columns) : array
    {
        $data_set = [];
        $header_len = mb_strlen($header);
        foreach ($required_columns as $column => $column_index) {
            if (!isset($column_meta[$column])) {
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " required column '{$column}' does not exist in file " . "'{$File->getPathname()}'.");
            }
            $File->fseek($column_meta[$column]['offset'] + $header_len);
            $column_values = BundledCsvSafeHelper::fgetcsvSafe($File);
            $column_values = BundledRleHelper::rleUncompress($column_values);
            $column_values = BundledDataTypeHelper::applyDataTypeMultiple($column_values, $column_meta[$column]["definition"]);
            foreach ($column_values as $row_index => $value) {
                $data_set[$row_index][$column_index] = $value;
            }
        }
        return $data_set;
    }

    /**
     * @param array $data_set
     * @param array $constraints
     * @return array
     */
    protected function applyConstraints(array $data_set, array $constraints) : array
    {
        $temp = [];
        foreach ($data_set as $row) {
            if ($this->rowMatchesConstraints($row, $constraints)) {
                $temp[] = $row;
            }
        }
        return $temp;
    }

    /**
     * @param array $row
     * @param array $constraints
     * @return bool
     */
    protected function rowMatchesConstraints(array $row, array $constraints) : bool
    {
        //OR between groups, so return true on the first group that matches
        foreach ($constraints as $constraint_group) {
            if ($this->rowMatchesConstraintGroup($row, $constraint_group)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $row
     * @param array $constraint_group
     * @return bool
     */
    protected function rowMatchesConstraintGroup(array $row, array $constraint_group) : bool
    {
        //AND within groups, so only return true if they all match
        foreach ($constraint_group as $constraint) {
            if (!call_user_func($constraint['callable'], $row[$constraint['column_index']])) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $data_set
     * @param array $selected_columns
     * @return array
     */
    protected function removeNonSelectedColumns(array $data_set, array $selected_columns) : array
    {
        //Because both lists were alphabetized to start with, this will leave the keys in alpha order
        $temp = [];
        foreach ($data_set as $row) {
            $item = [];
            foreach ($selected_columns as $column_index => $column) {
                $item[$column_index] = $row[$column_index];
            }
            $temp[] = $item;
        }
        return $temp;
    }

    /**
     * @param array $data_set
     * @param int $metric_index
     * @return array
     */
    protected function prependMd5Hashes(array $data_set, int $metric_index) : array
    {
        $temp = [];
        foreach ($data_set as $row) {
            $key_array = $row;
            unset($key_array[$metric_index]);
            $md5 = md5(join('~', $key_array));
            $item = [0 => $md5];
            //Do this rather than unshift because keys may not be sequential and unshift would reset them.
            foreach ($row as $index => $value) {
                $item[$index + 1] = $value;
            }
            $temp[] = $item;
        }
        return $temp;
    }

    /**
     * @param array $data_set
     * @param string $date
     * @param string $metric
     * @param int $metric_index
     * @param array $selected_columns
     * @param array $column_meta
     * @param bool $do_aggregate
     * @param bool $do_aggregate_meta
     * @throws Exception
     * @return array
     */
    protected function generateMetadata(array $data_set, string $date, string $metric, int $metric_index, array $selected_columns, array $column_meta, bool $do_aggregate, bool $do_aggregate_meta) : array
    {
        $sum = 0;
        $count = 0;
        $first = current($data_set);
        $min = $first[$metric_index];
        $max = $first[$metric_index];
        foreach ($data_set as $row) {
            $value = $row[$metric_index];
            $sum += $value;
            $count++;
            if ($value < $min) {
                $min = $value;
            }
            if ($value > $max) {
                $max = $value;
            }
        }
        return ["date" => $date, "metric" => $metric, "status" => self::RESULT_STATUS_SUCCESS, "min" => $min, "max" => $max, "sum" => $sum, "matched_row_count" => $count, "column_meta" => $this->generateSelectedColumnsMeta($selected_columns, $column_meta), "is_aggregated" => $do_aggregate, "aggregate_includes_meta" => $do_aggregate && $do_aggregate_meta, "host" => $this->getHostId()];
    }

    /**
     * @return string
     */
    protected function getHostId() : string
    {
        return php_uname('n');
    }

    /**
     * @param array $selected_columns
     * @param array $column_meta
     * @return array
     */
    protected function generateSelectedColumnsMeta(array $selected_columns, array $column_meta) : array
    {
        $i = 0;
        //Provide the column definition for 'md5' first.
        $temp = [$i => ["definition" => ["axis_type" => BundledColumnDefinition::AXIS_TYPE_DIMENSION, "name" => "md5", "data_type" => BundledColumnDefinition::DATA_TYPE_STRING, "empty_value" => ""], "index" => $i]];
        $i++;
        foreach ($selected_columns as $name) {
            $item = ["definition" => $column_meta[$name]["definition"], "index" => $i];
            $temp[$i] = $item;
            $i++;
        }
        return $temp;
    }

    /**
     * @param array $data_set
     * @param int $metric_index
     * @return array
     */
    protected function aggregateOnMatchingDimensionsWithRowMeta(array $data_set, int $metric_index) : array
    {
        $temp = [];
        foreach ($data_set as $row) {
            $md5 = $row[0];
            $value = $row[$metric_index];
            if (isset($temp[$md5])) {
                $temp[$md5][$metric_index]["sum"] += $value;
                $temp[$md5][$metric_index]["min"] = min($value, $temp[$md5][$metric_index]["min"]);
                $temp[$md5][$metric_index]["max"] = max($value, $temp[$md5][$metric_index]["max"]);
                $temp[$md5][$metric_index]["cnt"] += 1;
            } else {
                $row[$metric_index] = ["sum" => $value, "min" => $value, "max" => $value, "cnt" => 1];
                $temp[$md5] = $row;
            }
        }
        return array_values($temp);
    }

    /**
     * @param array $data_set
     * @param int $metric_index
     * @return array
     */
    protected function aggregateOnMatchingDimensionsWithoutRowMeta(array $data_set, int $metric_index) : array
    {
        $temp = [];
        foreach ($data_set as $row) {
            $md5 = $row[0];
            if (isset($temp[$md5])) {
                $temp[$md5][$metric_index] += $row[$metric_index];
            } else {
                $temp[$md5] = $row;
            }
        }
        return array_values($temp);
    }

    /**
     * @throws ErrorException
     */
    protected function setErrorHandler() : void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line) : bool {
            if (!(error_reporting() & $severity)) {
                // This error code is not included in error_reporting
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, error_reporting());
    }

    /**
     * @param string $file_path
     * @throws Exception
     * @return SplFileObject
     */
    protected function openFile(string $file_path) : SplFileObject
    {
        $File = $this->openFileInner($file_path);
        //Retry once
        if (in_array($File->fread(1), [false, '', null])) {
            unset($File);
            usleep(50000);
            //50 ms
            $File = $this->openFileInner($file_path);
        }
        if (in_array($File->fread(1), [false, '', null])) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " opened file '{$file_path}' with lock but contents were " . "empty or invalid.");
        }
        $File->rewind();
        return $File;
    }

    /**
     * @param string $file_path
     * @throws Exception
     * @return SplFileObject
     */
    protected function openFileInner(string $file_path) : SplFileObject
    {
        $File = new SplFileObject($file_path, 'r');
        $File->flock(LOCK_SH);
        clearstatcache(true, $file_path);
        if (!file_exists($file_path)) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " file '{$file_path}' was removed while awaiting lock.");
        }
        return $File;
    }

    /**
     * @param SplFileObject|null $File
     * @return void
     */
    protected function closeFile(?SplFileObject &$File) : void
    {
        if (is_null($File)) {
            return;
        }
        $File->flock(LOCK_UN);
        $File = null;
    }

    /**
     * @return int
     */
    protected function getMsElapsed() : int
    {
        return intval(round((hrtime(true) - $this->hrtime_start) / 1000000));
    }
}

class BundledColumnDefinition
{
    public const AXIS_TYPE_METRIC = 'metric';
    public const AXIS_TYPE_DIMENSION = 'dimension';
    protected const VALID_AXIS_TYPES = [self::AXIS_TYPE_METRIC, self::AXIS_TYPE_DIMENSION];
    public const DATA_TYPE_STRING = 'string';
    public const DATA_TYPE_INT = 'int';
    public const DATA_TYPE_FLOAT = 'float';
    public const DATA_TYPE_BOOL = 'bool';
    public const DATA_TYPE_DATETIME = 'datetime';
    protected const DATA_TYPE_VALIDATION_MAP = [self::DATA_TYPE_STRING => 'string', self::DATA_TYPE_INT => 'integer', self::DATA_TYPE_FLOAT => 'double', self::DATA_TYPE_BOOL => 'boolean', self::DATA_TYPE_DATETIME => 'string'];
    /** @var string */
    protected $axis_type;
    /** @var string */
    protected $name;
    /** @var string */
    protected $data_type;
    /** @var int|null */
    protected $precision = null;
    /** @var mixed */
    protected $empty_value;
    /**
     * @param string $axis_type
     * @param string $name
     * @param string $data_type
     * @param int|null $precision
     * @param mixed $empty_value
     * @throws Exception
     */
    public function __construct(string $axis_type, string $name, string $data_type, ?int $precision, $empty_value)
    {
        $this->setAxisType($axis_type);
        $this->setName($name);
        $this->setDataType($data_type);
        $this->setPrecision($precision);
        $this->setEmptyValue($empty_value);
    }

    /**
     * @param string $axis_type
     * @throws Exception
     */
    protected function setAxisType(string $axis_type) : void
    {
        if (!in_array($axis_type, self::VALID_AXIS_TYPES)) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid axis_type '{$axis_type}'. Must be one of : '" . join("','", self::VALID_AXIS_TYPES) . "'.");
        }
        $this->axis_type = $axis_type;
    }

    /**
     * @param string $name
     * @throws Exception
     */
    protected function setName(string $name) : void
    {
        if ($name === '') {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " name cannot be empty.");
        }
        if (preg_match("/^[a-z][a-z0-9_]*\$/", $name) !== 1) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid name '{$name}'. Only lowercase letters, numbers, and underscores are permitted; must start with a letter.");
        }
        $this->name = $name;
    }

    /**
     * @param string $data_type
     * @throws Exception
     */
    protected function setDataType(string $data_type) : void
    {
        if (!array_key_exists($data_type, self::DATA_TYPE_VALIDATION_MAP)) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid data_type '{$data_type}'. Must be one of : '" . join("','", array_keys(self::DATA_TYPE_VALIDATION_MAP)) . "'.");
        }
        $this->data_type = $data_type;
    }

    /**
     * @param int|null $precision
     * @throws Exception
     */
    protected function setPrecision(?int $precision) : void
    {
        if (isset($precision)) {
            if ($this->data_type !== self::DATA_TYPE_FLOAT) {
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " precision may only be provided when data_type is 'float'; data_type is '{$this->data_type}'.");
            }
            if ($precision <= 0) {
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid precision {$precision}. Precision, if provided, must be greater than zero.");
            }
        }
        $this->precision = $precision;
    }

    /**
     * @param mixed $empty_value
     * @throws Exception
     */
    protected function setEmptyValue($empty_value) : void
    {
        if ($this->data_type === self::DATA_TYPE_FLOAT && is_int($empty_value)) {
            $empty_value = floatval($empty_value);
        }
        if (gettype($empty_value) !== self::DATA_TYPE_VALIDATION_MAP[$this->data_type]) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " value '{$empty_value}' does not match data_type '{$this->data_type}'.");
        }
        $this->empty_value = $empty_value;
    }

    /**
     * @return string
     */
    public function getAxisType() : string
    {
        return $this->axis_type;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDataType() : string
    {
        return $this->data_type;
    }

    /**
     * @return int|null
     */
    public function getPrecision() : ?int
    {
        return $this->precision;
    }

    /**
     * @return mixed
     */
    public function getEmptyValue()
    {
        return $this->empty_value;
    }

    /**
     * @return array
     */
    public function toArray() : array
    {
        return ["axis_type" => $this->axis_type, "name" => $this->name, "data_type" => $this->data_type, "precision" => $this->precision, "empty_value" => $this->empty_value];
    }

    /**
     * @param array $array
     * @throws Exception
     * @return BundledColumnDefinition
     */
    public static function fromArray(array $array) : BundledColumnDefinition
    {
        $expected_keys = ["axis_type", "name", "data_type", "precision", "empty_value"];
        if (array_keys($array) !== $expected_keys) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid array: " . print_r($array, true));
        }
        return new BundledColumnDefinition($array["axis_type"], $array["name"], $array["data_type"], $array["precision"], $array["empty_value"]);
    }
}

class BundledConstraint
{
    public const EQUALS = '=';
    public const NOT_EQUALS = '!=';
    public const GREATER_THAN = '>';
    public const GREATER_THAN_OR_EQUALS = '>=';
    public const LESS_THAN = '<';
    public const LESS_THAN_OR_EQUALS = '<=';
    public const IN = 'in';
    public const NOT_IN = 'not in';
    public const CONTAINS = 'contains';
    public const NOT_CONTAINS = 'not contains';
    public const CONTAINS_IN = 'contains in';
    public const CONTAINS_ALL = 'contains all';
    public const NOT_CONTAINS_IN = 'not contains in';
    public const BEGINS_WITH = 'begins with';
    public const NOT_BEGINS_WITH = 'not begins with';
    public const ENDS_WITH = 'ends with';
    public const NOT_ENDS_WITH = 'not ends with';
    public const REGEX = 'regex';
    public const NOT_REGEX = 'not regex';
    public const EMPTY = 'empty';
    public const NOT_EMPTY = 'not_empty';
    protected const VALID_COMPARATORS = [self::EQUALS, self::NOT_EQUALS, self::GREATER_THAN, self::GREATER_THAN_OR_EQUALS, self::LESS_THAN, self::LESS_THAN_OR_EQUALS, self::IN, self::NOT_IN, self::CONTAINS, self::NOT_CONTAINS, self::CONTAINS_IN, self::CONTAINS_ALL, self::NOT_CONTAINS_IN, self::BEGINS_WITH, self::NOT_BEGINS_WITH, self::ENDS_WITH, self::NOT_ENDS_WITH, self::REGEX, self::NOT_REGEX, self::EMPTY, self::NOT_EMPTY];
    /** @var string */
    protected $name;
    /** @var string */
    protected $comparator;
    /** @var mixed */
    protected $value;
    /**
     * @param string $name
     * @param string $comparator
     * @param $value
     * @throws Exception
     */
    public function __construct(string $name, string $comparator, $value)
    {
        $this->setName($name);
        $this->setComparator($comparator);
        $this->setValue($value);
    }

    /**
     * @param string $name
     * @throws Exception
     */
    protected function setName(string $name)
    {
        if ($name === '') {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " name cannot be empty.");
        }
        if (preg_match("/^[a-z][a-z0-9_]*\$/", $name) !== 1) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid name '{$name}'. Only lowercase letters, numbers, and underscores are permitted; must start with a letter.");
        }
        $this->name = $name;
    }

    /**
     * @param string $comparator
     * @throws Exception
     */
    protected function setComparator(string $comparator)
    {
        if (!in_array($comparator, self::VALID_COMPARATORS)) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid comparator '{$comparator}'.");
        }
        $this->comparator = $comparator;
    }

    /**
     * @param $value
     */
    protected function setValue($value)
    {
        //Value can be anything
        if ($this->comparator === self::EMPTY || $this->comparator === self::NOT_EMPTY) {
            $value = null;
            //Ignore value
        }
        $this->value = $value;
    }

    /**
     * @return array
     */
    public function toArray() : array
    {
        return ["name" => $this->name, "comparator" => $this->comparator, "value" => $this->value];
    }

    /**
     * @param array $array
     * @throws Exception
     * @return BundledConstraint
     */
    public static function fromArray(array $array) : BundledConstraint
    {
        $expected_keys = ["name", "comparator", "value"];
        if (array_keys($array) !== $expected_keys) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid array: " . print_r($array, true));
        }
        return new self($array["name"], $array["comparator"], $array["value"]);
    }
}

class BundledConstraintParser
{
    /**
     * @param array $constraints
     * @param array $column_meta
     * @throws Exception
     * @return array
     */
    public function unserializeConstraints(array $constraints, array $column_meta) : array
    {
        $this->validateConstraints($constraints, $column_meta);
        $temp = [];
        foreach ($constraints as $group_index => $constraint_group) {
            foreach ($constraint_group as $constraint_index => $constraint) {
                $temp[$group_index][$constraint_index] = [
                    "name" => $constraint["name"],
                    //"comparator" => $constraint["comparator"],
                    //"value" => $constraint["value"],
                    "callable" => $this->generateCallableFromConstraint($constraint, $column_meta[$constraint["name"]]["definition"]),
                ];
            }
        }
        return $temp;
    }

    /**
     * @param array $constraints
     * @param array $column_meta
     * @throws Exception
     */
    protected function validateConstraints(array $constraints, array $column_meta) : void
    {
        $i = 0;
        foreach ($constraints as $group_index => $constraint_group) {
            if ($group_index !== $i) {
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " constraint groups must be 0-indexed.");
            }
            $j = 0;
            foreach ($constraint_group as $constraint_index => $constraint) {
                if ($constraint_index !== $j) {
                    throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " constraint group items must be 0-indexed.");
                }
                if (array_keys($constraint) != ["name", "comparator", "value"]) {
                    throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " constraint did not contain name, comparator, and value.");
                }
                if (!isset($column_meta[$constraint["name"]])) {
                    throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " column '{$constraint["name"]}' not found in metadata of file.");
                }
                $j++;
            }
            $i++;
        }
    }

    /**
     * @param array $constraint
     * @param array $column_definition
     * @throws Exception
     * @return callable
     */
    protected function generateCallableFromConstraint(array $constraint, array $column_definition) : callable
    {
        if ($constraint['comparator'] === BundledConstraint::EMPTY) {
            $constraint['comparator'] = BundledConstraint::EQUALS;
            $constraint['value'] = $column_definition['empty_value'];
        } else {
            if ($constraint['comparator'] === BundledConstraint::NOT_EMPTY) {
                $constraint['comparator'] = BundledConstraint::NOT_EQUALS;
                $constraint['value'] = $column_definition['empty_value'];
            }
        }
        switch ($column_definition['data_type']) {
            case BundledColumnDefinition::DATA_TYPE_STRING:
                return $this->generateCallableFromConstraintString($constraint);
            case BundledColumnDefinition::DATA_TYPE_DATETIME:
                return $this->generateCallableFromConstraintDatetime($constraint);
            case BundledColumnDefinition::DATA_TYPE_INT:
                return $this->generateCallableFromConstraintInt($constraint);
            case BundledColumnDefinition::DATA_TYPE_FLOAT:
                return $this->generateCallableFromConstraintFloat($constraint, $column_definition['precision']);
            case BundledColumnDefinition::DATA_TYPE_BOOL:
                return $this->generateCallableFromConstraintBool($constraint);
            default:
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " invalid data type '{$constraint['data_type']}'.");
        }
    }

    /**
     * @param array $constraint
     * @throws Exception
     * @return callable
     */
    protected function generateCallableFromConstraintString(array $constraint) : callable
    {
        $target_value = $constraint['value'];
        if (is_array($target_value)) {
            $temp = [];
            foreach ($target_value as $k => $v) {
                $temp[$k] = mb_strtolower($v);
            }
            $target_value = $temp;
        } else {
            $target_value = mb_strtolower($target_value);
        }
        //We could validate that $target_value is valid for each case, but don't to save cycles
        switch ($constraint['comparator']) {
            case BundledConstraint::EQUALS:
                return function ($value) use($target_value) : bool {
                    return mb_strtolower($value) === $target_value;
                };
            case BundledConstraint::NOT_EQUALS:
                return function ($value) use($target_value) : bool {
                    return mb_strtolower($value) !== $target_value;
                };
            case BundledConstraint::GREATER_THAN:
                return function ($value) use($target_value) : bool {
                    return mb_strtolower($value) > $target_value;
                };
            case BundledConstraint::GREATER_THAN_OR_EQUALS:
                return function ($value) use($target_value) : bool {
                    return mb_strtolower($value) >= $target_value;
                };
            case BundledConstraint::LESS_THAN:
                return function ($value) use($target_value) : bool {
                    return mb_strtolower($value) < $target_value;
                };
            case BundledConstraint::LESS_THAN_OR_EQUALS:
                return function ($value) use($target_value) : bool {
                    return mb_strtolower($value) <= $target_value;
                };
            case BundledConstraint::IN:
                $target_value = array_flip($target_value);
                return function ($value) use($target_value) : bool {
                    return isset($target_value[mb_strtolower($value)]);
                };
            case BundledConstraint::NOT_IN:
                $target_value = array_flip($target_value);
                return function ($value) use($target_value) : bool {
                    return !isset($target_value[mb_strtolower($value)]);
                };
            case BundledConstraint::CONTAINS:
                return function ($value) use($target_value) : bool {
                    if ($target_value === '') {
                        return true;
                    }
                    return mb_stripos($value, $target_value) !== false;
                };
            case BundledConstraint::NOT_CONTAINS:
                return function ($value) use($target_value) : bool {
                    if ($target_value === '') {
                        return false;
                    }
                    return mb_stripos($value, $target_value) === false;
                };
            case BundledConstraint::CONTAINS_IN:
                return function ($value) use($target_value) : bool {
                    foreach ($target_value as $item) {
                        if ($item === '' || mb_stripos($value, $item) !== false) {
                            return true;
                        }
                    }
                    return false;
                };
            case BundledConstraint::CONTAINS_ALL:
                return function ($value) use($target_value) : bool {
                    foreach ($target_value as $item) {
                        if (mb_stripos($value, $item) === false) {
                            return false;
                        }
                    }
                    return true;
                };
            case BundledConstraint::NOT_CONTAINS_IN:
                return function ($value) use($target_value) : bool {
                    foreach ($target_value as $item) {
                        if ($item === '' || mb_stripos($value, $item) !== false) {
                            return false;
                        }
                    }
                    return true;
                };
            case BundledConstraint::BEGINS_WITH:
                return function ($value) use($target_value) : bool {
                    if ($target_value === '') {
                        return true;
                    }
                    return mb_strtolower(mb_substr($value, 0, mb_strlen($target_value))) === $target_value;
                };
            case BundledConstraint::NOT_BEGINS_WITH:
                return function ($value) use($target_value) : bool {
                    if ($target_value === '') {
                        return false;
                    }
                    return mb_strtolower(mb_substr($value, 0, mb_strlen($target_value))) !== $target_value;
                };
            case BundledConstraint::ENDS_WITH:
                return function ($value) use($target_value) : bool {
                    if ($target_value === '') {
                        return true;
                    }
                    return mb_strtolower(mb_substr($value, -mb_strlen($target_value))) === $target_value;
                };
            case BundledConstraint::NOT_ENDS_WITH:
                return function ($value) use($target_value) : bool {
                    if ($target_value === '') {
                        return false;
                    }
                    return mb_strtolower(mb_substr($value, -mb_strlen($target_value))) !== $target_value;
                };
            case BundledConstraint::REGEX:
                $target_value = $constraint['value'];
                //Don't do case-insensitive unless the regex calls for it
                return function ($value) use($target_value) : bool {
                    return preg_match($target_value, $value) === 1;
                };
            case BundledConstraint::NOT_REGEX:
                $target_value = $constraint['value'];
                //Don't do case-insensitive unless the regex calls for it
                return function ($value) use($target_value) : bool {
                    return preg_match($target_value, $value) !== 1;
                };
            default:
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " got invalid comparator '{$constraint['comparator']}'.");
        }
    }

    /**
     * @param array $constraint
     * @throws Exception
     * @return callable
     */
    protected function generateCallableFromConstraintDatetime(array $constraint) : callable
    {
        if (in_array($constraint['comparator'], [BundledConstraint::IN, BundledConstraint::NOT_IN, BundledConstraint::CONTAINS_IN, BundledConstraint::CONTAINS_ALL, BundledConstraint::NOT_CONTAINS_IN])) {
            return $this->generateCallableFromConstraintString($constraint);
        }
        $target_value = strtotime($constraint['value']);
        switch ($constraint['comparator']) {
            case BundledConstraint::EQUALS:
                return function ($value) use($target_value) : bool {
                    return strtotime($value) === $target_value;
                };
            case BundledConstraint::NOT_EQUALS:
                return function ($value) use($target_value) : bool {
                    return strtotime($value) !== $target_value;
                };
            case BundledConstraint::GREATER_THAN:
                return function ($value) use($target_value) : bool {
                    return strtotime($value) > $target_value;
                };
            case BundledConstraint::GREATER_THAN_OR_EQUALS:
                return function ($value) use($target_value) : bool {
                    return strtotime($value) >= $target_value;
                };
            case BundledConstraint::LESS_THAN:
                return function ($value) use($target_value) : bool {
                    return strtotime($value) < $target_value;
                };
            case BundledConstraint::LESS_THAN_OR_EQUALS:
                return function ($value) use($target_value) : bool {
                    return strtotime($value) <= $target_value;
                };
            default:
                return $this->generateCallableFromConstraintString($constraint);
        }
    }

    /**
     * @param array $constraint
     * @throws Exception
     * @return callable
     */
    protected function generateCallableFromConstraintInt(array $constraint) : callable
    {
        $target_value = $constraint['value'];
        if (is_array($target_value)) {
            $temp = [];
            foreach ($target_value as $k => $v) {
                $temp[$k] = intval($v);
            }
            $target_value = $temp;
        } else {
            $target_value = intval($target_value);
        }
        switch ($constraint['comparator']) {
            case BundledConstraint::EQUALS:
                return function ($value) use($target_value) : bool {
                    return $value === $target_value;
                };
            case BundledConstraint::NOT_EQUALS:
                return function ($value) use($target_value) : bool {
                    return $value !== $target_value;
                };
            case BundledConstraint::GREATER_THAN:
                return function ($value) use($target_value) : bool {
                    return $value > $target_value;
                };
            case BundledConstraint::GREATER_THAN_OR_EQUALS:
                return function ($value) use($target_value) : bool {
                    return $value >= $target_value;
                };
            case BundledConstraint::LESS_THAN:
                return function ($value) use($target_value) : bool {
                    return $value < $target_value;
                };
            case BundledConstraint::LESS_THAN_OR_EQUALS:
                return function ($value) use($target_value) : bool {
                    return $value <= $target_value;
                };
            case BundledConstraint::IN:
                $target_value = array_flip($target_value);
                return function ($value) use($target_value) : bool {
                    return isset($target_value[$value]);
                };
            case BundledConstraint::NOT_IN:
                $target_value = array_flip($target_value);
                return function ($value) use($target_value) : bool {
                    return !isset($target_value[$value]);
                };
            default:
                return $this->generateCallableFromConstraintString($constraint);
        }
    }

    /**
     * @param array $constraint
     * @param int|null $precision
     * @throws Exception
     * @return callable
     */
    protected function generateCallableFromConstraintFloat(array $constraint, ?int $precision) : callable
    {
        $target_value = $constraint['value'];
        if (isset($precision)) {
            if (is_array($target_value)) {
                foreach ($target_value as $i => $item) {
                    $target_value[$i] = round($item, $precision);
                }
            } else {
                $target_value = round($target_value, $precision);
            }
        }
        $constraint['value'] = $target_value;
        //Preserve the rounding if we pass this on to the int case
        switch ($constraint['comparator']) {
            case BundledConstraint::EQUALS:
                return function ($value) use($target_value, $precision) : bool {
                    return round($value, $precision) === $target_value;
                };
            case BundledConstraint::NOT_EQUALS:
                return function ($value) use($target_value, $precision) : bool {
                    return round($value, $precision) !== $target_value;
                };
            case BundledConstraint::GREATER_THAN:
                return function ($value) use($target_value, $precision) : bool {
                    return round($value, $precision) > $target_value;
                };
            case BundledConstraint::GREATER_THAN_OR_EQUALS:
                return function ($value) use($target_value, $precision) : bool {
                    return round($value, $precision) >= $target_value;
                };
            case BundledConstraint::LESS_THAN:
                return function ($value) use($target_value, $precision) : bool {
                    return round($value, $precision) < $target_value;
                };
            case BundledConstraint::LESS_THAN_OR_EQUALS:
                return function ($value) use($target_value, $precision) : bool {
                    return round($value, $precision) <= $target_value;
                };
            case BundledConstraint::IN:
                return function ($value) use($target_value, $precision) : bool {
                    $value = round($value, $precision);
                    foreach ($target_value as $item) {
                        if ($value === $item) {
                            return true;
                        }
                    }
                    return false;
                };
            case BundledConstraint::NOT_IN:
                return function ($value) use($target_value, $precision) : bool {
                    $value = round($value, $precision);
                    foreach ($target_value as $item) {
                        if ($value === $item) {
                            return false;
                        }
                    }
                    return true;
                };
            default:
                return $this->generateCallableFromConstraintString($constraint);
        }
    }

    /**
     * @param array $constraint
     * @throws Exception
     * @return callable
     */
    protected function generateCallableFromConstraintBool(array $constraint) : callable
    {
        $target_value = boolval($constraint['value']);
        switch ($constraint['comparator']) {
            case BundledConstraint::EQUALS:
                return function ($value) use($target_value) {
                    return boolval($value) === $target_value;
                };
            case BundledConstraint::NOT_EQUALS:
                return function ($value) use($target_value) {
                    return boolval($value) !== $target_value;
                };
            default:
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " only '" . BundledConstraint::EQUALS . "' and '" . BundledConstraint::NOT_EQUALS . "' comparators are supported for data_type 'bool'.");
        }
    }
}

class BundledCsvException extends \Exception
{
}

class BundledCsvSafeHelper
{
    public const CSV_ENCLOSURE = '"';
    public const CSV_SEPARATOR = ',';
    public const CSV_ESCAPE = "\\";
    public const CSV_EOL = "\n";
    /**
     * @param array $row
     * @throws JsonException
     * @return array
     */
    public static function convertValuesToStrings(array $row) : array
    {
        $new_row = [];
        foreach ($row as $key => $value) {
            //We can get arrays from per-row aggregate meta.
            if (is_array($value)) {
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            }
            if (is_bool($value)) {
                $value = intval($value);
            }
            if (!is_scalar($value)) {
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " got value with invalid type " . BundledErrorHelper::varDumpToString($value));
            }
            $new_row[$key] = strval($value);
        }
        return $new_row;
    }

    /**
     * @param SplFileObject $File
     * @param string[] $values
     * @throws Exception
     * @return int
     */
    public static function fputcsvSafe(SplFileObject $File, array $values) : int
    {
        $values_before_safe = $values;
        foreach ($values as $i => $value) {
            if (!is_string($value)) {
                throw new BundledCsvException(__CLASS__ . '::' . __FUNCTION__ . " received non-string value " . BundledErrorHelper::varDumpToString($value) . ". Convert values first.");
            }
            if (strpos($value, self::CSV_EOL) !== false) {
                throw new BundledCsvException(__CLASS__ . '::' . __FUNCTION__ . " value " . BundledErrorHelper::varDumpToString($value) . " contained EOL. Sanitize values first.");
            }
            if (strpos($value, '\\') !== false) {
                $values[$i] = addslashes($value);
            }
        }
        $offset_before_write = $File->ftell();
        $number_of_bytes_written = $File->fputcsv($values, self::CSV_SEPARATOR, self::CSV_ENCLOSURE, self::CSV_ESCAPE);
        if ($number_of_bytes_written === false) {
            throw new BundledCsvException(__CLASS__ . '::' . __FUNCTION__ . " fputcsv failed on '{$File->getPathname()}' line " . "{$File->key()}. Error: " . print_r(error_get_last(), true));
        }
        $File->fseek($offset_before_write);
        $read_back_values = self::fgetcsvSafe($File);
        //moves file pointer back to offset after write
        self::validateCsvReadBack($values_before_safe, $read_back_values);
        return $number_of_bytes_written;
    }

    /**
     * @param SplFileObject $File
     * @throws Exception
     * @return string[]
     */
    public static function fgetcsvSafe(SplFileObject $File) : array
    {
        $values = $File->fgetcsv(self::CSV_SEPARATOR, self::CSV_ENCLOSURE, self::CSV_ESCAPE);
        if ($values === false) {
            throw new BundledCsvException(__CLASS__ . '::' . __FUNCTION__ . " fgetcsv failed on '{$File->getPathname()}' line " . "{$File->key()}. Error: " . print_r(error_get_last(), true));
        }
        return self::valuesGetcsvSafe($values);
    }

    /**
     * @param string $string
     * @return string[]
     */
    public static function strGetcsvSafe(string $string) : array
    {
        $values = str_getcsv($string, self::CSV_SEPARATOR, self::CSV_ENCLOSURE, self::CSV_ESCAPE);
        return self::valuesGetcsvSafe($values);
    }

    /**
     * @param array $values
     * @return string[]
     */
    protected static function valuesGetcsvSafe(array $values) : array
    {
        if (count($values) === 1 && is_null(current($values))) {
            return [''];
        }
        foreach ($values as $i => $value) {
            if (strpos($value, '\\') !== false) {
                $values[$i] = stripslashes($value);
            }
        }
        return $values;
    }

    /**
     * @param array $values
     * @param array $read_back_values
     * @throws Exception
     * @return void
     */
    protected static function validateCsvReadBack(array $values, array $read_back_values) : void
    {
        if ($values === $read_back_values) {
            return;
        }
        $first_mismatch_index = null;
        foreach ($values as $i => $value) {
            if ($value !== $read_back_values[$i]) {
                $first_mismatch_index = $i;
                break;
            }
        }
        if (is_null($first_mismatch_index)) {
            throw new BundledCsvException(__CLASS__ . '::' . __FUNCTION__ . " failed, could not determine which value caused " . "the error.");
        }
        $mismatch_original_value = BundledErrorHelper::varDumpToString($values[$first_mismatch_index]);
        $mismatch_read_back_value = BundledErrorHelper::varDumpToString($read_back_values[$first_mismatch_index]);
        throw new BundledCsvException(__CLASS__ . '::' . __FUNCTION__ . " failed. First instance at " . "index {$first_mismatch_index}: original value {$mismatch_original_value}, read back value " . "{$mismatch_read_back_value}.");
    }
}

class BundledDataTypeHelper
{
    /**
     * @param $value
     * @param array $column_definition
     * @throws Exception
     * @return mixed
     */
    public static function applyDataTypeSingle($value, array $column_definition)
    {
        switch ($column_definition['data_type']) {
            case BundledColumnDefinition::DATA_TYPE_STRING:
            case BundledColumnDefinition::DATA_TYPE_DATETIME:
                return $value;
            //already a string
            case BundledColumnDefinition::DATA_TYPE_INT:
                return intval($value);
            case BundledColumnDefinition::DATA_TYPE_FLOAT:
                if (isset($column_definition['precision'])) {
                    return round($value, $column_definition['precision']);
                } else {
                    return floatval($value);
                }
            case BundledColumnDefinition::DATA_TYPE_BOOL:
                return boolval($value);
            default:
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " got invalid data type " . "'{$column_definition['data_type']}'.");
        }
    }

    /**
     * @param array $values
     * @param array $column_definition
     * @throws Exception
     * @return array
     */
    public static function applyDataTypeMultiple(array $values, array $column_definition) : array
    {
        switch ($column_definition['data_type']) {
            case BundledColumnDefinition::DATA_TYPE_STRING:
            case BundledColumnDefinition::DATA_TYPE_DATETIME:
                return $values;
            //already strings
            case BundledColumnDefinition::DATA_TYPE_INT:
                $temp = [];
                foreach ($values as $value) {
                    $temp[] = intval($value);
                }
                return $temp;
            case BundledColumnDefinition::DATA_TYPE_FLOAT:
                $temp = [];
                if (isset($column_definition['precision'])) {
                    foreach ($values as $value) {
                        $temp[] = round($value, $column_definition['precision']);
                    }
                } else {
                    foreach ($values as $value) {
                        $temp[] = floatval($value);
                    }
                }
                return $temp;
            case BundledColumnDefinition::DATA_TYPE_BOOL:
                $temp = [];
                foreach ($values as $value) {
                    $temp[] = boolval($value);
                }
                return $temp;
            default:
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " got invalid data type " . "'{$column_definition['data_type']}'.");
        }
    }
}

class BundledErrorHelper
{
    /**
     * @param mixed $var
     * @return string
     */
    public static function varDumpToString($var) : string
    {
        ob_start();
        var_dump($var);
        $export = ob_get_contents();
        ob_end_clean();
        $export = trim($export);
        $export = substr($export, strpos($export, "\n") + 1);
        return $export;
    }
}

class BundledRleHelper
{
    public const RLE_SEPARATOR = "\x1e";
    //Record Separator, chr(30), hex 036
    /**
     * @param string $column
     * @param array $values
     * @param int $threshold_percent
     * @throws Exception
     * @return array
     */
    public static function rleCompressIfThresholdMet(string $column, array $values, int $threshold_percent) : array
    {
        if ($threshold_percent <= 0) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " threshold percent must be a positive integer.");
        }
        if (empty($values)) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " values array was empty.");
        }
        if (count($values) === 1) {
            return $values;
        }
        $len_check_separator = ',';
        $len = mb_strlen(join($len_check_separator, $values));
        $compressed = BundledRleHelper::rleCompress($column, $values);
        $compressed_len = mb_strlen(join($len_check_separator, $compressed));
        $compression_savings_pct = 1 - $compressed_len / $len;
        $min_compression_pct = $threshold_percent / 100;
        if ($compression_savings_pct >= $min_compression_pct) {
            return $compressed;
        } else {
            return $values;
        }
    }

    /**
     * Compress from:
     *   ['foo','foo','foo','bar','','','baz','baz']
     * to:
     *   ['foo^3','bar','^2','baz^2']
     *
     * @param string $column
     * @param array $values
     * @throws Exception
     * @return array
     */
    public static function rleCompress(string $column, array $values) : array
    {
        if (empty($values)) {
            return [];
        }
        $last_value = null;
        $compressed_values = [];
        $value_count = 0;
        foreach ($values as $value) {
            if (mb_strpos($value, self::RLE_SEPARATOR) !== false) {
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " could not compress column '{$column}' value " . "because it contains RLE separator '" . self::RLE_SEPARATOR . "'. Value: {$value}");
            }
            if (isset($last_value) && $value !== $last_value) {
                $compressed_values[] = $last_value . ($value_count > 1 ? self::RLE_SEPARATOR . $value_count : "");
                $value_count = 0;
            }
            $last_value = $value;
            $value_count++;
        }
        $compressed_values[] = $last_value . ($value_count > 1 ? self::RLE_SEPARATOR . $value_count : "");
        if ($values !== self::rleUncompress($compressed_values)) {
            throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " on column '{$column}' values before and " . "after compression do not match.");
        }
        return $compressed_values;
    }

    /**
     * Uncompress from:
     *   ['foo^3','bar','^2','baz^2']
     * to:
     *   ['foo','foo','foo','bar','','','baz','baz']
     *
     * @param array $column_values
     * @return array
     */
    public static function rleUncompress(array $column_values) : array
    {
        $temp = [];
        if (mb_strpos(join('', $column_values), self::RLE_SEPARATOR) === false) {
            return $column_values;
        }
        foreach ($column_values as $compressed) {
            if (mb_strpos($compressed, self::RLE_SEPARATOR) === false) {
                $temp[] = $compressed;
            } else {
                list($value, $repetitions) = explode(self::RLE_SEPARATOR, $compressed);
                $repetitions = $repetitions ?? 1;
                for ($i = 0; $i < $repetitions; $i++) {
                    $temp[] = $value;
                }
            }
        }
        return $temp;
    }
}
