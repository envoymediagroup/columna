<?php

namespace EnvoyMediaGroup\Columna;

use Exception;

class CardinalitySortHelper {

    /**
     * @param array $indexes_to_column_names
     * @param array $row_based_data
     * @throws Exception
     * @return void
     */
    public static function sortByCardinality(array $indexes_to_column_names, array &$row_based_data): void {
        $sort_order = self::generateCardinalitySortOrder($indexes_to_column_names, $row_based_data);
        self::multiSort($row_based_data,$sort_order);
    }

    /**
     * @param array $indexes_to_column_names
     * @param array $row_based_data
     * @throws Exception
     * @return array
     */
    protected static function generateCardinalitySortOrder(array $indexes_to_column_names, array $row_based_data): array {
        $CardinalityItems = [];
        foreach ($indexes_to_column_names as $column_index => $column_name) {
            $count = self::generateCardinalityForColumn($row_based_data,$column_index);
            $CardinalityItems[$column_index] = new CardinalityItem($column_index,$count);
        }
        uasort($CardinalityItems,function($ItemA,$ItemB) { return CardinalityItem::sort($ItemA,$ItemB); });
        return array_keys($CardinalityItems);
    }

    /**
     * @param array $row_based_data
     * @param int $column_index
     * @return int
     */
    protected static function generateCardinalityForColumn(array $row_based_data, int $column_index): int {
        $uniques = [];
        foreach ($row_based_data as $row) {
            $value = $row[$column_index];
            if (strlen($value) > 32) {
                $value = md5($value);
            }
            if (!array_key_exists($value,$uniques)) {
                $uniques[$value] = true;
            }
        }
        return count($uniques);
    }

    /**
     * @param array $array
     * @param array $sort_order
     */
    protected static function multiSort(array &$array, array $sort_order) {
        //Note that here we usort instead of uasort because we don't need to preserve keys.
        usort($array, function ($a, $b) use ($sort_order) {
            foreach ($sort_order as $index) {
                $sort_value = $a[$index] <=> $b[$index];
                if ($sort_value !== 0) {
                    return $sort_value;
                }
            }
            return 0;
        });
    }

}