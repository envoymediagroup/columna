<?php

namespace EnvoyMediaGroup\Columna;

use JsonException;
use Throwable;
use Exception;
use SplFileObject;
use ZipArchive;

class ZipCombinedWriter extends WriterAbstract {

    /**
     * @param string $date
     * @param ColumnDefinition $MetricDefinition
     * @param ColumnDefinition[] $DimensionDefinitions
     * @param string $source_files_zip_path
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
        string           $source_files_zip_path,
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
        $CombinedFile = $this->combineFiles($source_files_zip_path);

        if (is_a($CombinedFile,EmptyFile::class)) {
            $this->writeOutputFileNoData();
        } else {
            $this->moveCombinedFileToOutputFile($CombinedFile);
        }

        return [
            "status" => $this->getStatusFromFile($CombinedFile),
            "write_time_ms" => $this->millisecondsElapsed($hr_start),
        ];
    }

    /**
     * @param string $zip_path
     * @throws Throwable
     * @return SplFileObject|EmptyFile
     */
    protected function combineFiles(string $zip_path) {
        $TmpFile = null;
        $Zip = null;
        $file_handles = [];

        try {
            clearstatcache(true,$zip_path);
            if (!file_exists($zip_path)) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." zip file '{$zip_path}' does not exist.");
            }
            $Zip = new ZipArchive();
            if (!$Zip->open($zip_path)) { throw new Exception(__CLASS__.'::'.__FUNCTION__." could not open zip '{$zip_path}'."); }
            $file_handles = $this->openPartialFilesToCombine($Zip);
            $file_metas = $this->readFileMetas($file_handles);
            $this->removeFilesWithNoData($file_handles,$file_metas);

            if (empty($file_handles)) {
                return new EmptyFile();
            }

            $TmpFile = $this->FileHelper->openNewTmpFileForReadAndWrite();
            $column_byte_offsets = $this->writeTmpFileWithoutMetaAndGenerateOffsets($TmpFile,$file_handles,$file_metas);
            $combined_meta = $this->generateCombinedMeta($file_metas,$column_byte_offsets);
            $PartialOutputFile = $this->FileHelper->openNewTmpFileForReadAndWrite();
            $this->writeCombinedFileFromTmpFileAndMeta($TmpFile,$PartialOutputFile,$combined_meta);

            return $PartialOutputFile;
        } finally {
            if (isset($Zip)) { $Zip->close(); $Zip = null; }
            foreach ($file_handles as $fh) {
                fclose($fh);
            }
            $this->FileHelper->closeAndDeleteFile($TmpFile);
        }
    }

    /**
     * @param resource[] $file_handles
     * @throws JsonException
     * @return array
     */
    protected function readFileMetas(array $file_handles): array {
        $metas = [];
        foreach ($file_handles as $file_path => $file_handle) {
            $line = fgets($file_handle);
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
     * @param resource[] $file_handles
     * @param array $file_metas
     * @return void
     */
    protected function removeFilesWithNoData(array &$file_handles, array &$file_metas): void {
        foreach ($file_metas as $file_path => $file_meta) {
            if ($file_meta["status"] === Reader::FILE_STATUS_NO_DATA) {
                unset($file_handles[$file_path]);
                unset($file_metas[$file_path]);
            }
        }
    }

    /**
     * @param ZipArchive $Zip
     * @throws Exception
     * @return resource[]
     */
    protected function openPartialFilesToCombine(ZipArchive $Zip): array {
        $file_handles = [];
        for ($i=0; $i<$Zip->numFiles;$i++) {
            $file_handles[$Zip->getNameIndex($i)] = $Zip->getStream($Zip->getNameIndex($i));
        }
        if (empty($file_handles)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." no file handles could be opened from zip '{$Zip->filename}'.");
        }
        return $file_handles;
    }

    /**
     * @param SplFileObject $TmpFile
     * @param resource[] $file_handles
     * @param array $file_metas
     * @throws Exception
     * @return array
     */
    protected function writeTmpFileWithoutMetaAndGenerateOffsets(
        SplFileObject $TmpFile,
        array         $file_handles,
        array         $file_metas
    ): array {
        $offset = 0;
        $column_byte_offsets = [];
        $file_max = count($file_handles) - 1;

        foreach ($this->indexes_to_column_names as $column) {
            $column_byte_offsets[$column] = $offset;
            $file_i = 0;
            foreach ($file_handles as $file_path => $file_handle) {
                $this->validateColumnOffset($file_handle,$file_metas[$file_path],$column);
                $line = rtrim(fgets($file_handle));
                $line .= ($file_i < $file_max) ? CsvSafeHelper::CSV_SEPARATOR : "\n";
                $offset += $TmpFile->fwrite($line);
                $file_i++;
            }
        }

//        $this->validateEofOffset($file_handles);
        $TmpFile->rewind();
        return $column_byte_offsets;
    }

    /**
     * @param resource $file_handle
     * @param array $file_meta
     * @param string $column
     * @throws Exception
     * @return void
     */
    protected function validateColumnOffset($file_handle, array $file_meta, string $column): void {
        $header_length = $file_meta["header_length"];
        $column_offset_after_header = $file_meta["column_meta"][$column]["offset"];
        $column_offset = $column_offset_after_header + $header_length;
        if (ftell($file_handle) !== $column_offset) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." 'file pointer was not at " .
                "expected location. Expected {$column_offset}, got ".ftell($file_handle).".");
        }
    }

    /**
     * @param resource[] $file_handles
     * @throws Exception
     * @return void
     */
    protected function validateEofOffset(array $file_handles) {
        foreach ($file_handles as $file_path => $file_handle) {
            $tell = ftell($file_handle);
            if ($tell !== $size) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." '{$file_path}' file pointer was not at last byte " .
                    "after reading. Expected {$size}, got {$tell}.");
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
     * @param SplFileObject $CombinedFile
     * @throws Exception
     * @return void
     */
    protected function moveCombinedFileToOutputFile(SplFileObject $CombinedFile): void {
        $this->FileHelper->moveFile($CombinedFile->getPathname(),$this->output_file_path,$this->lock_output_file);
    }

    /**
     * @param SplFileObject|EmptyFile $File
     * @return string
     */
    protected function getStatusFromFile($File): string {
        return is_a($File,EmptyFile::class) ? Reader::FILE_STATUS_NO_DATA : Reader::FILE_STATUS_HAS_DATA;
    }

}