<?php

namespace EnvoyMediaGroup\Columna;

use Exception;
use JsonException;
use SplFileObject;

class CsvSafeHelper {

    public const CSV_ENCLOSURE = '"';
    public const CSV_SEPARATOR = ',';
    public const CSV_ESCAPE = "\\";
    public const CSV_EOL = "\n";

    /**
     * @param array $row
     * @throws JsonException
     * @return array
     */
    public static function convertValuesToStrings(array $row): array {
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
                throw new Exception(__CLASS__.'::'.__FUNCTION__." got value with invalid type " .
                    ErrorHelper::varDumpToString($value));
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
    public static function fputcsvSafe(SplFileObject $File, array $values): int {
        $values_before_safe = $values;

        foreach ($values as $i => $value) {
            if (!is_string($value)) {
                throw new CsvException(__CLASS__ . '::' . __FUNCTION__ . " received non-string value " .
                    ErrorHelper::varDumpToString($value) . ". Convert values first.");
            }
            if (strpos($value, self::CSV_EOL) !== false) {
                throw new CsvException(__CLASS__ . '::' . __FUNCTION__ . " value " .
                    ErrorHelper::varDumpToString($value) . " contained EOL. Sanitize values first.");
            }
            if (strpos($value, '\\') !== false) {
                $values[$i] = addslashes($value);
            }
        }

        $offset_before_write = $File->ftell();
        $number_of_bytes_written = $File->fputcsv($values, self::CSV_SEPARATOR, self::CSV_ENCLOSURE, self::CSV_ESCAPE);

        if ($number_of_bytes_written === false) {
            throw new CsvException(__CLASS__ . '::' . __FUNCTION__ . " fputcsv failed on '{$File->getPathname()}' line " .
                "{$File->key()}. Error: " . print_r(error_get_last(),true));
        }

        $File->fseek($offset_before_write);
        $read_back_values = self::fgetcsvSafe($File); //moves file pointer back to offset after write
        self::validateCsvReadBack($values_before_safe, $read_back_values);

        return $number_of_bytes_written;
    }

    /**
     * @param SplFileObject $File
     * @throws Exception
     * @return string[]
     */
    public static function fgetcsvSafe(SplFileObject $File): array {
        $values = $File->fgetcsv(self::CSV_SEPARATOR, self::CSV_ENCLOSURE, self::CSV_ESCAPE);

        if ($values === false) {
            throw new CsvException(__CLASS__ . '::' . __FUNCTION__ . " fgetcsv failed on '{$File->getPathname()}' line " .
                "{$File->key()}. Error: " . print_r(error_get_last(),true));
        }

        return self::valuesGetcsvSafe($values);
    }

    /**
     * @param string $string
     * @return string[]
     */
    public static function strGetcsvSafe(string $string): array {
        $values = str_getcsv($string,self::CSV_SEPARATOR,self::CSV_ENCLOSURE,self::CSV_ESCAPE);
        return self::valuesGetcsvSafe($values);
    }

    /**
     * @param array $values
     * @return string[]
     */
    protected static function valuesGetcsvSafe(array $values): array {
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
    protected static function validateCsvReadBack(array $values, array $read_back_values): void {
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
            throw new CsvException(__CLASS__ . '::' . __FUNCTION__ . " failed, could not determine which value caused " .
                "the error.");
        }

        $mismatch_original_value = ErrorHelper::varDumpToString($values[$first_mismatch_index]);
        $mismatch_read_back_value = ErrorHelper::varDumpToString($read_back_values[$first_mismatch_index]);

        throw new CsvException(__CLASS__ . '::' . __FUNCTION__ . " failed. First instance at " .
            "index {$first_mismatch_index}: original value {$mismatch_original_value}, read back value " .
            "{$mismatch_read_back_value}.");
    }

}