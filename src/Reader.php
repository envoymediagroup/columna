<?php

namespace EnvoyMediaGroup\Columna;

use Exception;
use JsonException;
use ErrorException;
use SplFileObject;

class Reader {

    public const LIB_MAJOR_VERSION = 1;
    public const FILE_EXTENSION    = 'scf';

    public const FILE_STATUS_HAS_DATA     = 'has data';
    public const FILE_STATUS_NO_DATA      = 'no data';

    public const RESULT_STATUS_EMPTY   = 'empty';
    public const RESULT_STATUS_SUCCESS = 'success';
    public const RESULT_STATUS_ERROR   = 'error';

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
    public function runFromWorkload(string $workload): void {
        $args = json_decode($workload,true,512,JSON_THROW_ON_ERROR);
        $this->run(
            $args['date'],
            $args['metric'],
            $args['dimensions'],
            $args['constraints'],
            $args['do_aggregate'],
            $args['do_aggregate_meta'],
            $args['file']
        );
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
    public function run(
        string $date,
        string $metric,
        array  $dimensions,
        array  $constraints,
        bool   $do_aggregate,
        bool   $do_aggregate_meta,
        string $file_path
    ) {
        try {
            $this->setErrorHandler();
            $this->runInner($date,$metric,$dimensions,$constraints,$do_aggregate,$do_aggregate_meta,$file_path);
            restore_error_handler();
        } catch (Exception $e) {
            $this->handleError($date,$metric,$e->getMessage());
            throw $e;
        }
    }

    /**
     * @param string $file_path
     * @throws JsonException
     * @return array
     */
    public function getFileMetadata(string $file_path): array {
        $File = $this->openFile($file_path);
        $header = $File->fgets();
        $this->closeFile($File);
        return json_decode($header,true,512,JSON_THROW_ON_ERROR);
    }

    /**
     * @return array
     */
    public function getMetadata(): array {
        return $this->metadata;
    }

    /**
     * @return array
     */
    public function getResults(): array {
        return $this->results;
    }

    /**
     * @throws JsonException
     * @return string
     */
    public function getResponsePayload(): string {
        $TmpFile = new SplFileObject('php://temp', 'r+b');
        $TmpFile->fwrite(json_encode($this->metadata,JSON_THROW_ON_ERROR)."\n");
        foreach ($this->results as $row) {
            CsvSafeHelper::fputcsvSafe($TmpFile, CsvSafeHelper::convertValuesToStrings($row));
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
    protected function runInner(
        string  $date,
        string  $metric,
        array   $dimensions,
        array   $constraints,
        bool    $do_aggregate,
        bool    $do_aggregate_meta,
        string  $file_path
    ) {
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
            $header = $File->fgets(); //includes trailing \n
            $file_meta = json_decode($header,true,512,JSON_THROW_ON_ERROR); //trailing \n is ignored
            $this->validateFileMeta($file_meta,$date,$metric,$file_path);

            if ($file_meta["status"] === self::FILE_STATUS_NO_DATA) {
                $this->handleEmptyResults($date,$metric);
                return;
            }

            $column_meta = $file_meta["column_meta"];
            $constraints = (new ConstraintParser())->unserializeConstraints($constraints,$column_meta);
            list($selected_columns,$required_columns) = $this->generateSelectedAndRequiredColumns($metric,$dimensions,$constraints);
            $data_set = $this->readFileOnlyRequiredColumns($File,$column_meta,$header,$required_columns);
        } finally {
            $this->closeFile($File);
        }

        if ($data_set === []) {
            $this->handleEmptyResults($date,$metric);
            return;
        }

        $constraints = $this->addColumnIndexesToConstraints($constraints,$required_columns);
        $data_set = $this->applyConstraints($data_set,$constraints);

        if ($data_set === []) {
            $this->handleEmptyResults($date,$metric);
            return;
        }

        $metric_index = $required_columns[$metric];
        $data_set = $this->removeNonSelectedColumns($data_set,$selected_columns);
        $data_set = $this->prependMd5Hashes($data_set,$metric_index);
        $metric_index++; //offset for prepended md5. $selected_columns_meta handled in generateSelectedColumnsMeta().
        $metadata = $this->generateMetadata($data_set,$date,$metric,$metric_index,$selected_columns,$column_meta,$do_aggregate,$do_aggregate_meta);

        if ($do_aggregate === true) {
            if ($do_aggregate_meta === true) {
                $data_set = $this->aggregateOnMatchingDimensionsWithRowMeta($data_set,$metric_index);
            } else {
                $data_set = $this->aggregateOnMatchingDimensionsWithoutRowMeta($data_set,$metric_index);
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
    protected function validateFileMeta(
        array $file_meta,
        string $date,
        string $metric,
        string $file_path
    ): void {
        $required_keys = [
            "date",
            "metric",
            "status",
            "lib_version",
        ];
        foreach ($required_keys as $key) {
            if (!array_key_exists($key,$file_meta)) {
                throw new Exception(self::class.'::'.__FUNCTION__." file meta does not include required key '{$key}'.");
            }
        }

        if ($file_meta["lib_version"] > self::LIB_MAJOR_VERSION) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." Writer major version " .
                "'{$file_meta["lib_version"]}' that created this file is greater than Reader major version '" . self::LIB_MAJOR_VERSION . "'.");
        }

        if ($file_meta["date"] !== $date) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' does not cover date '{$date}'.");
        }

        if ($file_meta["metric"] !== $metric) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' does not cover metric '{$metric}'.");
        }

        if (!in_array($file_meta["status"],[
            self::FILE_STATUS_HAS_DATA,
            self::FILE_STATUS_NO_DATA,
        ])) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid file status '{$file_meta["status"]}'.");
        }

        if ($file_meta["status"] === self::FILE_STATUS_HAS_DATA && !array_key_exists("column_meta",$file_meta)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." file meta must include key 'column_meta' when data is non-empty.");
        }
    }

    /**
     * @param string $date
     * @throws Exception
     */
    protected function validateDate(string $date): void {
        if (!(
            preg_match("/^(\d{4})-(\d{2})-(\d{2})$/", $date, $matches) === 1 &&
            checkdate($matches[2], $matches[3], $matches[1]) === true
        )) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid date '{$date}'. Use Y-m-d format, e.g. '2021-02-17'.");
        }
    }

    /**
     * @param array $data_set
     * @return array
     */
    protected function reindexRowsFromZero(array $data_set): array {
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
    protected function handleError(string $date, string $metric, string $message): void {
        $this->results = [];
        $this->metadata = [
            "date" => $date,
            "metric" => $metric,
            "status" => self::RESULT_STATUS_ERROR,
            "error" => $message,
            "host" => $this->getHostId(),
            "ms_elapsed" => $this->getMsElapsed(),
        ];
    }

    /**
     * @param string $date
     * @param string $metric
     */
    protected function handleEmptyResults(string $date ,string $metric): void {
        $this->results = [];
        $this->metadata = [
            "date" => $date,
            "metric" => $metric,
            "status" => self::RESULT_STATUS_EMPTY,
            "host" => $this->getHostId(),
            "ms_elapsed" => $this->getMsElapsed(),
        ];
    }

    /**
     * @param string $metric
     * @param array $dimensions
     * @param array $constraints
     * @return array
     */
    protected function generateSelectedAndRequiredColumns(string $metric, array $dimensions, array $constraints): array {
        $temp_selections = array_merge([$metric],$dimensions);
        sort($temp_selections);

        $constraint_columns = [];
        foreach ($constraints as $constraint_group) {
            foreach ($constraint_group as $constraint) {
                $constraint_columns[] = $constraint["name"];
            }
        }

        $temp_required_columns = array_unique(array_merge($temp_selections,$constraint_columns));
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

        return [$selected_columns,$required_columns];
    }

    /**
     * @param array $constraints
     * @param array $required_columns
     * @return array
     */
    protected function addColumnIndexesToConstraints(array $constraints, array $required_columns): array {
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
    protected function readFileOnlyRequiredColumns(
        SplFileObject $File,
        array $column_meta,
        string $header,
        array $required_columns
    ): array {
        $data_set = [];
        $header_len = mb_strlen($header);

        foreach ($required_columns as $column => $column_index) {
            if (!isset($column_meta[$column])) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." required column '{$column}' does not exist in file " .
                    "'{$File->getPathname()}'.");
            }

            $File->fseek($column_meta[$column]['offset'] + $header_len);
            $column_values = CsvSafeHelper::fgetcsvSafe($File);
            $column_values = RleHelper::rleUncompress($column_values);
            $column_values = DataTypeHelper::applyDataTypeMultiple($column_values,$column_meta[$column]["definition"]);

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
    protected function applyConstraints(array $data_set, array $constraints): array {
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
    protected function rowMatchesConstraints(array $row, array $constraints): bool {
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
    protected function rowMatchesConstraintGroup(array $row, array $constraint_group): bool {
        //AND within groups, so only return true if they all match
        foreach ($constraint_group as $constraint) {
            if (!call_user_func($constraint['callable'],$row[$constraint['column_index']])) {
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
    protected function removeNonSelectedColumns(array $data_set, array $selected_columns): array {
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
    protected function prependMd5Hashes(array $data_set, int $metric_index): array {
        $temp = [];

        foreach ($data_set as $row) {
            $key_array = $row;
            unset($key_array[$metric_index]);
            $md5 = md5(join('~',$key_array));

            $item = [
                0 => $md5,
            ];

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
    protected function generateMetadata(
        array  $data_set,
        string $date,
        string $metric,
        int    $metric_index,
        array  $selected_columns,
        array  $column_meta,
        bool   $do_aggregate,
        bool   $do_aggregate_meta
    ): array {
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

        return [
            "date" => $date,
            "metric" => $metric,
            "status" => self::RESULT_STATUS_SUCCESS,
            "min" => $min,
            "max" => $max,
            "sum" => $sum,
            "matched_row_count" => $count,
            "column_meta" => $this->generateSelectedColumnsMeta($selected_columns,$column_meta),
            "is_aggregated" => $do_aggregate,
            "aggregate_includes_meta" => ($do_aggregate && $do_aggregate_meta),
            "host" => $this->getHostId(),
            //"result_row_count" is added after we do optional aggregation step
            //"ms_elapsed" is added last
        ];
    }

    /**
     * @return string
     */
    protected function getHostId(): string {
        return php_uname('n');
    }

    /**
     * @param array $selected_columns
     * @param array $column_meta
     * @return array
     */
    protected function generateSelectedColumnsMeta(array $selected_columns, array $column_meta): array {
        $i = 0;

        //Provide the column definition for 'md5' first.
        $temp = [
            $i => [
                "definition" => [
                    "axis_type" => ColumnDefinition::AXIS_TYPE_DIMENSION,
                    "name" => "md5",
                    "data_type" => ColumnDefinition::DATA_TYPE_STRING,
                    "empty_value" => "",
                ],
                "index" => $i,
            ],
        ];
        $i++;

        foreach ($selected_columns as $name) {
            $item = [
                "definition" => $column_meta[$name]["definition"],
                "index" => $i,
            ];
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
    protected function aggregateOnMatchingDimensionsWithRowMeta(array $data_set, int $metric_index): array {
        $temp = [];

        foreach ($data_set as $row) {
            $md5 = $row[0];
            $value = $row[$metric_index];

            if (isset($temp[$md5])) {
                $temp[$md5][$metric_index]["sum"] += $value;
                $temp[$md5][$metric_index]["min"] = min($value,$temp[$md5][$metric_index]["min"]);
                $temp[$md5][$metric_index]["max"] = max($value,$temp[$md5][$metric_index]["max"]);
                $temp[$md5][$metric_index]["cnt"] += 1;
            } else {
                $row[$metric_index] = [
                    "sum" => $value,
                    "min" => $value,
                    "max" => $value,
                    "cnt" => 1,
                ];
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
    protected function aggregateOnMatchingDimensionsWithoutRowMeta(array $data_set, int $metric_index): array {
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
    protected function setErrorHandler(): void {
        set_error_handler(
            function(
                int $severity,
                string $message,
                string $file,
                int $line
            ): bool {
                if (!(error_reporting() & $severity)) {
                    // This error code is not included in error_reporting
                    return false;
                }
                throw new ErrorException($message, 0, $severity, $file, $line);
            },
            error_reporting()
        );
    }

    /**
     * @param string $file_path
     * @throws Exception
     * @return SplFileObject
     */
    protected function openFile(string $file_path): SplFileObject {
        $File = $this->openFileInner($file_path);

        //Retry once
        if (in_array($File->fread(1),[false,'',null])) {
            unset($File);
            usleep(50000); //50 ms
            $File = $this->openFileInner($file_path);
        }

        if (in_array($File->fread(1),[false,'',null])) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." opened file '{$file_path}' with lock but contents were " .
                "empty or invalid.");
        }

        $File->rewind();
        return $File;
    }

    /**
     * @param string $file_path
     * @throws Exception
     * @return SplFileObject
     */
    protected function openFileInner(string $file_path): SplFileObject {
        $File = new SplFileObject($file_path,'r');
        $File->flock(LOCK_SH);
        clearstatcache(true,$file_path);
        if (!file_exists($file_path)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' was removed while awaiting lock.");
        }
        return $File;
    }

    /**
     * @param SplFileObject|null $File
     * @return void
     */
    protected function closeFile(?SplFileObject &$File): void {
        if (is_null($File)) {
            return;
        }
        $File->flock(LOCK_UN);
        $File = null;
    }

    /**
     * @return int
     */
    protected function getMsElapsed(): int {
        return intval(round((hrtime(true) - $this->hrtime_start) / 1000000));
    }

}
