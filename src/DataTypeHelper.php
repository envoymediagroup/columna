<?php

namespace EnvoyMediaGroup\Columna;

use Exception;

class DataTypeHelper {

    /**
     * @param $value
     * @param array $column_definition
     * @throws Exception
     * @return mixed
     */
    public static function applyDataTypeSingle($value, array $column_definition) {
        switch ($column_definition['data_type']) {
            case ColumnDefinition::DATA_TYPE_STRING:
            case ColumnDefinition::DATA_TYPE_DATETIME:
                return $value; //already a string
            case ColumnDefinition::DATA_TYPE_INT:
                return intval($value);
            case ColumnDefinition::DATA_TYPE_FLOAT:
                if (isset($column_definition['precision'])) {
                    return round($value, $column_definition['precision']);
                } else {
                    return floatval($value);
                }
            case ColumnDefinition::DATA_TYPE_BOOL:
                return boolval($value);
            default:
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " got invalid data type " .
                    "'{$column_definition['data_type']}'.");
        }
    }

    /**
     * @param array $values
     * @param array $column_definition
     * @throws Exception
     * @return array
     */
    public static function applyDataTypeMultiple(array $values, array $column_definition): array {
        switch ($column_definition['data_type']) {
            case ColumnDefinition::DATA_TYPE_STRING:
            case ColumnDefinition::DATA_TYPE_DATETIME:
                return $values; //already strings
            case ColumnDefinition::DATA_TYPE_INT:
                $temp = [];
                foreach ($values as $value) {
                    $temp[] = intval($value);
                }
                return $temp;
            case ColumnDefinition::DATA_TYPE_FLOAT:
                $temp = [];
                if (isset($column_definition['precision'])) {
                    foreach ($values as $value) {
                        $temp[] = round($value,$column_definition['precision']);
                    }
                } else {
                    foreach ($values as $value) {
                        $temp[] = floatval($value);
                    }
                }
                return $temp;
            case ColumnDefinition::DATA_TYPE_BOOL:
                $temp = [];
                foreach ($values as $value) {
                    $temp[] = boolval($value);
                }
                return $temp;
            default:
                throw new Exception(__CLASS__ . '::' . __FUNCTION__ . " got invalid data type " .
                    "'{$column_definition['data_type']}'.");
        }
    }

}