<?php

namespace EnvoyMediaGroup\Columna;

use JsonException;
use Throwable;
use Exception;
use SplFileObject;

class CombinedWriter extends WriterAbstract {

    protected const CHUNK_SIZE = 100;

    /**
     * @param string $date
     * @param ColumnDefinition $MetricDefinition
     * @param ColumnDefinition[] $DimensionDefinitions
     * @param array $partial_file_paths
     * @param string $output_file_path
     * @param bool $lock_output_file
     * @throws JsonException
     * @throws Throwable
     * @return array
     */
    public function writeCombinedFile(
        string           $date,
        ColumnDefinition $MetricDefinition,
        array            $DimensionDefinitions,
        array            $partial_file_paths,
        string           $output_file_path,
        bool             $lock_output_file = true
    ): array {
        $hr_start = hrtime(true);
        $this->setDate($date);
        $this->setMetricDefinition($MetricDefinition);
        $this->setDimensionDefinitions($DimensionDefinitions);
        $this->populateIndexesToColumnNames();
        $this->setOutputFilePath($output_file_path);
        $this->setLockOutputFile($lock_output_file);
        $this->validatePartialFilePaths($partial_file_paths);
        $combined_file = $this->combineFilesRecursivelyByChunks($partial_file_paths);

        if (is_a($combined_file,EmptyFile::class)) {
            $this->writeOutputFileNoData();
            return $this->getNoDataResponse($hr_start);
        }

        $combined_file = is_a($combined_file,SplFileObject::class) ? $combined_file->getPathname() : $combined_file;
        $this->moveCombinedFileToOutputFile($combined_file,$partial_file_paths);
        return $this->getHasDataResponse($hr_start);
    }

    /**
     * @param int $hr_start
     * @return array
     */
    protected function getNoDataResponse(int $hr_start): array {
        return [
            "status" => Reader::FILE_STATUS_NO_DATA,
            "write_time_ms" => $this->millisecondsElapsed($hr_start),
        ];
    }

    /**
     * @param int $hr_start
     * @return array
     */
    protected function getHasDataResponse(int $hr_start): array {
        return [
            "status" => Reader::FILE_STATUS_HAS_DATA,
            "write_time_ms" => $this->millisecondsElapsed($hr_start),
        ];
    }

    /**
     * @param string[]|SplFileObject[] $partial_files
     * @throws JsonException
     * @throws Throwable
     * @return string|SplFileObject
     */
    protected function combineFilesRecursivelyByChunks(array $partial_files, bool $recur = false) {
        if (count($partial_files) === 1) {
            return current($partial_files);
        }

        if (count($partial_files) > $this->getChunkSize()) {
            $chunks = array_chunk($partial_files, $this->getChunkSize());
            $more_partial_files = [];
            foreach ($chunks as $chunk) {
                $more_partial_files[] = $this->combineFiles($chunk,$recur);
            }
            return $this->combineFilesRecursivelyByChunks($more_partial_files,true);
        }

        return $this->combineFiles($partial_files,$recur);
    }

    /**
     * @return int
     */
    protected function getChunkSize(): int {
        return self::CHUNK_SIZE;
    }

    /**
     * @param string[] $partial_file_paths
     * @throws Exception
     * @return void
     */
    protected function validatePartialFilePaths(array $partial_file_paths): void {
        if (empty($partial_file_paths)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." must pass at least one partial file path.");
        }

        $temp = [];
        foreach ($partial_file_paths as $file_path) {
            if (!is_string($file_path)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." must pass an array of strings for partial file paths.");
            }
            if ($file_path === '') {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." file path cannot be empty.");
            }
            if (array_key_exists($file_path,$temp)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." cannot specify file '{$file_path}' more than once.");
            }
            $temp[$file_path] = true;
        }
    }

    /**
     * @param SplFileObject[] $Partial_Files
     * @throws JsonException
     * @return array
     */
    protected function readFileMetas(array $Partial_Files): array {
        $metas = [];
        foreach ($Partial_Files as $file_path => $Partial_File) {
            $line = $Partial_File->fgets();
            $meta = json_decode($line,true,512,JSON_THROW_ON_ERROR);
            $this->validateFileMeta($file_path,$meta);
            $meta["header_length"] = strlen($line);
            $metas[$file_path] = $meta;
        }
        return $metas;
    }

    /**
     * @param string $file_path
     * @param array $file_meta
     * @throws Exception
     * @return void
     */
    protected function validateFileMeta(string $file_path, array $file_meta): void {
        $always_expected_keys = ["date","metric","status","lib_version"];
        $has_data_expected_keys = [
            "date","metric","status","lib_version","min","max","sum","count","column_meta"
        ];
        $column_meta_expected_keys = ["definition","index","offset"];

        foreach ($always_expected_keys as $key) {
            if (!array_key_exists($key,$file_meta)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' had invalid metadata, " .
                    "missing key '{$key}'.");
            }
        }

        if ($file_meta["date"] !== $this->date) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' did not have correct date. " .
                "Expected '{$this->date}', got '{$file_meta['date']}'.");
        }

        if ($file_meta["metric"] !== $this->MetricDefinition->getName()) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' did not have correct metric. " .
                "Expected '{$this->MetricDefinition->getName()}', got '{$file_meta['metric']}'.");
        }

        if ($file_meta["lib_version"] !== Reader::LIB_MAJOR_VERSION) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' did not have correct library " .
                "version. Expected " . Reader::LIB_MAJOR_VERSION . ", got {$file_meta['lib_version']}.");
        }

        if ($file_meta["status"] === Reader::FILE_STATUS_NO_DATA) {
            return;
        }

        if ($file_meta["status"] !== Reader::FILE_STATUS_HAS_DATA) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid file status '{$file_meta["status"]}'.");
        }

        foreach ($has_data_expected_keys as $key) {
            if (!array_key_exists($key,$file_meta)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' had invalid metadata, " .
                    "missing key '{$key}'.");
            }
        }

        $file_column_names = array_keys($file_meta["column_meta"]);
        foreach ($this->indexes_to_column_names as $index => $column_name) {
            if ($file_column_names[$index] !== $column_name) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' did not have expected " .
                    "columns. At index {$index}, expected '{$column_name}', got '{$file_column_names[$index]}'.");
            }
        }

        foreach ($file_meta["column_meta"] as $column_name => $column_meta) {
            foreach ($column_meta_expected_keys as $key) {
                if (!array_key_exists($key,$column_meta)) {
                    throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' column '{$column_name}' " .
                        " had invalid column_meta, missing key '{$key}'.");
                }
            }

            if (!is_int($column_meta["index"])) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' column '{$column_name}' " .
                    " had invalid column_meta, index was not an integer.");
            }

            if (!is_int($column_meta["offset"])) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' column '{$column_name}' " .
                    " had invalid column_meta, offset was not an integer.");
            }

            $expected_definition = $this->getColumnDefinition($column_name)->toArray();
            if ($column_meta["definition"] != $expected_definition) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." file '{$file_path}' column '{$column_name}' " .
                    "had invalid column_meta, expected definition " . json_encode($expected_definition) . ", " .
                    "got definition " . json_encode($column_meta["definition"]));
            }
        }
    }

    /**
     * @param string[]|SplFileObject[] $partial_files
     * @throws Throwable
     * @return SplFileObject|EmptyFile
     */
    protected function combineFiles(array $partial_files, bool $recur) {
        $TmpFile = null;
        $PartialFiles = null;
        try {
            $PartialFiles = $this->openPartialFilesToCombine($partial_files);

            if (empty($PartialFiles)) {
                return new EmptyFile();
            }

            $file_metas = $this->readFileMetas($PartialFiles);
            $this->removeFilesWithNoData($PartialFiles,$file_metas);

            if (empty($PartialFiles)) {
                return new EmptyFile();
            }

            $TmpFile = $this->FileHelper->openNewTmpFileForReadAndWrite();
            $column_byte_offsets = $this->writeTmpFileWithoutMetaAndGenerateOffsets($TmpFile,$PartialFiles,$file_metas);
            $combined_meta = $this->generateCombinedMeta($file_metas,$column_byte_offsets);
            $PartialOutputFile = $this->FileHelper->openNewTmpFileForReadAndWrite();
            $this->writeCombinedFileFromTmpFileAndMeta($TmpFile,$PartialOutputFile,$combined_meta);

            return $PartialOutputFile;
        } finally {
            $this->FileHelper->closeAndDeleteFile($TmpFile);

            if ($recur === true) {
                $this->FileHelper->closeAndDeleteFiles($PartialFiles);
            } else {
                $this->FileHelper->closeFiles($PartialFiles);
            }
        }
    }

    /**
     * @param array $PartialFiles
     * @param array $file_metas
     * @return void
     */
    protected function removeFilesWithNoData(array &$PartialFiles, array &$file_metas): void {
        foreach ($file_metas as $file_path => $file_meta) {
            if ($file_meta["status"] === Reader::FILE_STATUS_NO_DATA) {
                unset($PartialFiles[$file_path]);
                unset($file_metas[$file_path]);
            }
        }
    }

    /**
     * @param string[]|SplFileObject[] $files
     * @throws Exception
     * @return SplFileObject[]
     */
    protected function openPartialFilesToCombine(array $files): array {
        $PartialFiles = [];
        foreach ($files as $file) {
            if (is_string($file)) {
                $PartialFiles[$file] = $this->FileHelper->openFileForReadWithoutLock($file);
            } else if (is_a($file,EmptyFile::class)) {
                continue;
            } else if (is_a($file,SplFileObject::class)) {
                $file->rewind();
                $PartialFiles[$file->getPathname()] = $file;
            } else {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid input.");
            }
        }
        return $PartialFiles;
    }

    /**
     * @param SplFileObject $TmpFile
     * @param SplFileObject[] $PartialFiles
     * @param array $file_metas
     * @throws Exception
     * @return array
     */
    protected function writeTmpFileWithoutMetaAndGenerateOffsets(
        SplFileObject $TmpFile,
        array         $PartialFiles,
        array         $file_metas
    ): array {
        $offset = 0;
        $column_byte_offsets = [];
        $file_max = count($PartialFiles) - 1;

        foreach ($this->indexes_to_column_names as $column) {
            $column_byte_offsets[$column] = $offset;
            $file_i = 0;
            foreach ($PartialFiles as $file_path => $PartialFile) {
                $this->validateColumnOffset($PartialFile,$file_metas[$file_path],$column);
                $line = rtrim($PartialFile->fgets());
                $line .= ($file_i < $file_max) ? CsvSafeHelper::CSV_SEPARATOR : "\n";
                $offset += $TmpFile->fwrite($line);
                $file_i++;
            }
        }

        $this->validateEofOffset($PartialFiles);
        $TmpFile->rewind();
        return $column_byte_offsets;
    }

    /**
     * @param SplFileObject $PartialFile
     * @param array $file_meta
     * @param string $column
     * @throws Exception
     * @return void
     */
    protected function validateColumnOffset(SplFileObject $PartialFile, array $file_meta, string $column): void {
        $header_length = $file_meta["header_length"];
        $column_offset_after_header = $file_meta["column_meta"][$column]["offset"];
        $column_offset = $column_offset_after_header + $header_length;
        if ($PartialFile->ftell() !== $column_offset) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." '{$PartialFile->getPathname()}' file pointer was not at " .
                "expected location. Expected {$column_offset}, got {$PartialFile->ftell()}.");
        }
    }

    /**
     * @param array $PartialFiles
     * @throws Exception
     * @return void
     */
    protected function validateEofOffset(array $PartialFiles) {
        foreach ($PartialFiles as $file_path => $PartialFile) {
            if ($PartialFile->ftell() !== $PartialFile->getSize()) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." '{$file_path}' file pointer was not at last byte " .
                    "after reading. Expected {$PartialFile->getSize()}, got {$PartialFile->ftell()}.");
            }
        }
    }

    /**
     * @param array $file_metas
     * @param array $column_byte_offsets
     * @throws Exception
     * @return array
     */
    protected function generateCombinedMeta(array $file_metas, array $column_byte_offsets): array {
        return [
            "date" => $this->date,
            "metric" => $this->MetricDefinition->getName(),
            "status" => Reader::FILE_STATUS_HAS_DATA,
            "lib_version" => Reader::LIB_MAJOR_VERSION,
            "min" => min(array_column($file_metas,"min")),
            "max" => max(array_column($file_metas,"max")),
            "sum" => array_sum(array_column($file_metas,"sum")),
            "count" => array_sum(array_column($file_metas,"count")),
            "column_meta" => $this->generateColumnMeta($column_byte_offsets),
        ];
    }

    /**
     * @param string $combined_file_path
     * @param array $partial_file_paths
     * @throws Exception
     * @return void
     */
    protected function moveCombinedFileToOutputFile(string $combined_file_path, array $partial_file_paths): void {
        $preserve_original = (count($partial_file_paths) === 1 && $partial_file_paths[0] === $combined_file_path);
        $this->FileHelper->moveFile($combined_file_path,$this->output_file_path,$this->lock_output_file,$preserve_original);
    }

}