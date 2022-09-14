<?php

namespace EnvoyMediaGroup\Columna;

use Exception;

class RleHelper {

    public const RLE_SEPARATOR = "\036"; //Record Separator, chr(30), hex 036

    /**
     * @param string $column
     * @param array $values
     * @param int $threshold_percent
     * @throws Exception
     * @return array
     */
    public static function rleCompressIfThresholdMet(string $column, array $values, int $threshold_percent): array {
        if ($threshold_percent <= 0) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." threshold percent must be a positive integer.");
        }
        if (empty($values)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." values array was empty.");
        }

        if (count($values) === 1) {
            return $values;
        }

        $len_check_separator = ',';
        $len = mb_strlen(join($len_check_separator,$values));
        $compressed = RleHelper::rleCompress($column,$values);
        $compressed_len = mb_strlen(join($len_check_separator,$compressed));
        $compression_savings_pct = 1 - ($compressed_len / $len);
        $min_compression_pct = ($threshold_percent / 100);

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
    public static function rleCompress(string $column, array $values): array {
        if (empty($values)) {
            return [];
        }

        $last_value = null;
        $compressed_values = [];
        $value_count = 0;

        foreach ($values as $value) {
            if (mb_strpos($value, self::RLE_SEPARATOR) !== false) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." could not compress column '{$column}' value " .
                    "because it contains RLE separator '" . self::RLE_SEPARATOR . "'. Value: {$value}");
            }
            if (isset($last_value) && $value !== $last_value) {
                $compressed_values[] = $last_value . ($value_count > 1 ? (self::RLE_SEPARATOR . $value_count) : "");
                $value_count = 0;
            }
            $last_value = $value;
            $value_count++;
        }

        $compressed_values[] = $last_value . ($value_count > 1 ? (self::RLE_SEPARATOR . $value_count) : "");

        if ($values !== self::rleUncompress($compressed_values)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." on column '{$column}' values before and " .
                "after compression do not match.");
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
    public static function rleUncompress(array $column_values): array {
        $temp = [];
        if (mb_strpos(join('',$column_values),self::RLE_SEPARATOR) === false) {
            return $column_values;
        }
        foreach ($column_values as $compressed) {
            if (mb_strpos($compressed,self::RLE_SEPARATOR) === false) {
                $temp[] = $compressed;
            } else {
                list ($value,$repetitions) = explode(self::RLE_SEPARATOR,$compressed);
                $repetitions = $repetitions ?? 1;
                for ($i=0; $i<$repetitions; $i++) {
                    $temp[] = $value;
                }
            }
        }
        return $temp;
    }

}