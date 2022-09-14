<?php

namespace EnvoyMediaGroup\Columna;

use Exception;

class ConstraintParser {

    /**
     * @param array $constraints
     * @param array $column_meta
     * @throws Exception
     * @return array
     */
    public function unserializeConstraints(array $constraints, array $column_meta): array {
        $this->validateConstraints($constraints,$column_meta);

        $temp = [];
        foreach ($constraints as $group_index => $constraint_group) {
            foreach ($constraint_group as $constraint_index => $constraint) {
                $temp[$group_index][$constraint_index] = [
                    "name" => $constraint["name"],
                    "callable" => $this->generateCallableFromConstraint(
                        $constraint,
                        $column_meta[$constraint["name"]]["definition"]
                    ),
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
    protected function validateConstraints(array $constraints, array $column_meta): void {
        $i = 0;
        foreach ($constraints as $group_index => $constraint_group) {
            if ($group_index !== $i) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." constraint groups must be 0-indexed.");
            }

            $j = 0;
            foreach ($constraint_group as $constraint_index => $constraint) {
                if ($constraint_index !== $j) {
                    throw new Exception(__CLASS__.'::'.__FUNCTION__." constraint group items must be 0-indexed.");
                }

                if (array_keys($constraint) != ["name","comparator","value"]) {
                    throw new Exception(__CLASS__.'::'.__FUNCTION__." constraint did not contain name, comparator, and value.");
                }

                if (!isset($column_meta[$constraint["name"]])) {
                    throw new Exception(__CLASS__.'::'.__FUNCTION__." column '{$constraint["name"]}' not found in metadata of file.");
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
    protected function generateCallableFromConstraint(array $constraint, array $column_definition): callable {
        if ($constraint['comparator'] === Constraint::EMPTY) {
            $constraint['comparator'] = Constraint::EQUALS;
            $constraint['value'] = $column_definition['empty_value'];
        } else if ($constraint['comparator'] === Constraint::NOT_EMPTY) {
            $constraint['comparator'] = Constraint::NOT_EQUALS;
            $constraint['value'] = $column_definition['empty_value'];
        }

        switch ($column_definition['data_type']) {
            case ColumnDefinition::DATA_TYPE_STRING:
                return $this->generateCallableFromConstraintString($constraint);
            case ColumnDefinition::DATA_TYPE_DATETIME:
                return $this->generateCallableFromConstraintDatetime($constraint);
            case ColumnDefinition::DATA_TYPE_INT:
                return $this->generateCallableFromConstraintInt($constraint);
            case ColumnDefinition::DATA_TYPE_FLOAT:
                return $this->generateCallableFromConstraintFloat($constraint, $column_definition['precision']);
            case ColumnDefinition::DATA_TYPE_BOOL:
                return $this->generateCallableFromConstraintBool($constraint);
            default:
                throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid data type '{$constraint['data_type']}'.");
        }
    }

    /**
     * @param array $constraint
     * @throws Exception
     * @return callable
     */
    protected function generateCallableFromConstraintString(array $constraint): callable {
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
            case Constraint::EQUALS:
                return function($value) use ($target_value): bool {
                    return mb_strtolower($value) === $target_value;
                };
            case Constraint::NOT_EQUALS:
                return function($value) use ($target_value): bool {
                    return mb_strtolower($value) !== $target_value;
                };
            case Constraint::GREATER_THAN:
                return function($value) use ($target_value): bool {
                    return mb_strtolower($value) > $target_value;
                };
            case Constraint::GREATER_THAN_OR_EQUALS:
                return function($value) use ($target_value): bool {
                    return mb_strtolower($value) >= $target_value;
                };
            case Constraint::LESS_THAN:
                return function($value) use ($target_value): bool {
                    return mb_strtolower($value) < $target_value;
                };
            case Constraint::LESS_THAN_OR_EQUALS:
                return function($value) use ($target_value): bool {
                    return mb_strtolower($value) <= $target_value;
                };
            case Constraint::IN:
                $target_value = array_flip($target_value);
                return function($value) use ($target_value): bool {
                    return isset($target_value[mb_strtolower($value)]);
                };
            case Constraint::NOT_IN:
                $target_value = array_flip($target_value);
                return function($value) use ($target_value): bool {
                    return !isset($target_value[mb_strtolower($value)]);
                };
            case Constraint::CONTAINS:
                return function($value) use ($target_value): bool {
                    if ($target_value === '') {
                        return true;
                    }
                    return mb_stripos($value,$target_value) !== false;
                };
            case Constraint::NOT_CONTAINS:
                return function($value) use ($target_value): bool {
                    if ($target_value === '') {
                        return false;
                    }
                    return mb_stripos($value,$target_value) === false;
                };
            case Constraint::CONTAINS_IN:
                return function($value) use ($target_value): bool {
                    foreach ($target_value as $item) {
                        if (
                            $item === '' ||
                            mb_stripos($value,$item) !== false
                        ) {
                            return true;
                        }
                    }
                    return false;
                };
            case Constraint::NOT_CONTAINS_IN:
                return function($value) use ($target_value): bool {
                    foreach ($target_value as $item) {
                        if (
                            $item === '' ||
                            mb_stripos($value,$item) !== false
                        ) {
                            return false;
                        }
                    }
                    return true;
                };
            case Constraint::BEGINS_WITH:
                return function($value) use ($target_value): bool {
                    if ($target_value === '') {
                        return true;
                    }
                    return mb_strtolower(mb_substr($value,0,mb_strlen($target_value))) === $target_value;
                };
            case Constraint::NOT_BEGINS_WITH:
                return function($value) use ($target_value): bool {
                    if ($target_value === '') {
                        return false;
                    }
                    return mb_strtolower(mb_substr($value,0,mb_strlen($target_value))) !== $target_value;
                };
            case Constraint::ENDS_WITH:
                return function($value) use ($target_value): bool {
                    if ($target_value === '') {
                        return true;
                    }
                    return mb_strtolower(mb_substr($value,-mb_strlen($target_value))) === $target_value;
                };
            case Constraint::NOT_ENDS_WITH:
                return function($value) use ($target_value): bool {
                    if ($target_value === '') {
                        return false;
                    }
                    return mb_strtolower(mb_substr($value,-mb_strlen($target_value))) !== $target_value;
                };
            case Constraint::REGEX:
                $target_value = $constraint['value']; //Don't do case-insensitive unless the regex calls for it
                return function($value) use ($target_value): bool {
                    return preg_match($target_value, $value) === 1;
                };
            case Constraint::NOT_REGEX:
                $target_value = $constraint['value']; //Don't do case-insensitive unless the regex calls for it
                return function($value) use ($target_value): bool {
                    return preg_match($target_value, $value) !== 1;
                };
            default:
                throw new Exception(__CLASS__.'::'.__FUNCTION__." got invalid comparator '{$constraint['comparator']}'.");
        }
    }

    /**
     * @param array $constraint
     * @throws Exception
     * @return callable
     */
    protected function generateCallableFromConstraintDatetime(array $constraint): callable {
        if (in_array($constraint['comparator'],[
            Constraint::IN,
            Constraint::NOT_IN,
            Constraint::CONTAINS_IN,
            Constraint::NOT_CONTAINS_IN,
        ])) {
            return $this->generateCallableFromConstraintString($constraint);
        }

        $target_value = strtotime($constraint['value']);

        switch ($constraint['comparator']) {
            case Constraint::EQUALS:
                return function($value) use ($target_value): bool {
                    return strtotime($value) === $target_value;
                };
            case Constraint::NOT_EQUALS:
                return function($value) use ($target_value): bool {
                    return strtotime($value) !== $target_value;
                };
            case Constraint::GREATER_THAN:
                return function($value) use ($target_value): bool {
                    return strtotime($value) > $target_value;
                };
            case Constraint::GREATER_THAN_OR_EQUALS:
                return function($value) use ($target_value): bool {
                    return strtotime($value) >= $target_value;
                };
            case Constraint::LESS_THAN:
                return function($value) use ($target_value): bool {
                    return strtotime($value) < $target_value;
                };
            case Constraint::LESS_THAN_OR_EQUALS:
                return function($value) use ($target_value): bool {
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
    protected function generateCallableFromConstraintInt(array $constraint): callable {
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
            case Constraint::EQUALS:
                return function($value) use ($target_value): bool {
                    return $value === $target_value;
                };
            case Constraint::NOT_EQUALS:
                return function($value) use ($target_value): bool {
                    return $value !== $target_value;
                };
            case Constraint::GREATER_THAN:
                return function($value) use ($target_value): bool {
                    return $value > $target_value;
                };
            case Constraint::GREATER_THAN_OR_EQUALS:
                return function($value) use ($target_value): bool {
                    return $value >= $target_value;
                };
            case Constraint::LESS_THAN:
                return function($value) use ($target_value): bool {
                    return $value < $target_value;
                };
            case Constraint::LESS_THAN_OR_EQUALS:
                return function($value) use ($target_value): bool {
                    return $value <= $target_value;
                };
            case Constraint::IN:
                $target_value = array_flip($target_value);
                return function($value) use ($target_value): bool {
                    return isset($target_value[$value]);
                };
            case Constraint::NOT_IN:
                $target_value = array_flip($target_value);
                return function($value) use ($target_value): bool {
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
    protected function generateCallableFromConstraintFloat(array $constraint, ?int $precision): callable {
        $target_value = $constraint['value'];

        if (isset($precision)) {
            if (is_array($target_value)) {
                foreach ($target_value as $i => $item) {
                    $target_value[$i] = round($item,$precision);
                }
            } else {
                $target_value = round($target_value,$precision);
            }
        }

        $constraint['value'] = $target_value; //Preserve the rounding if we pass this on to the int case

        switch ($constraint['comparator']) {
            case Constraint::EQUALS:
                return function($value) use ($target_value,$precision): bool {
                    return round($value,$precision) === $target_value;
                };
            case Constraint::NOT_EQUALS:
                return function($value) use ($target_value,$precision): bool {
                    return round($value,$precision) !== $target_value;
                };
            case Constraint::GREATER_THAN:
                return function($value) use ($target_value,$precision): bool {
                    return round($value,$precision) > $target_value;
                };
            case Constraint::GREATER_THAN_OR_EQUALS:
                return function($value) use ($target_value,$precision): bool {
                    return round($value,$precision) >= $target_value;
                };
            case Constraint::LESS_THAN:
                return function($value) use ($target_value,$precision): bool {
                    return round($value,$precision) < $target_value;
                };
            case Constraint::LESS_THAN_OR_EQUALS:
                return function($value) use ($target_value,$precision): bool {
                    return round($value,$precision) <= $target_value;
                };
            case Constraint::IN:
                return function($value) use ($target_value,$precision): bool {
                    $value = round($value,$precision);
                    foreach ($target_value as $item) {
                        if ($value === $item) {
                            return true;
                        }
                    }
                    return false;
                };
            case Constraint::NOT_IN:
                return function($value) use ($target_value,$precision): bool {
                    $value = round($value,$precision);
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
    protected function generateCallableFromConstraintBool(array $constraint): callable {
        $target_value = boolval($constraint['value']);

        switch ($constraint['comparator']) {
            case Constraint::EQUALS:
                return function($value) use ($target_value) {
                    return boolval($value) === $target_value;
                };
            case Constraint::NOT_EQUALS:
                return function($value) use ($target_value) {
                    return boolval($value) !== $target_value;
                };
            default:
                throw new Exception(__CLASS__.'::'.__FUNCTION__." only '".Constraint::EQUALS."' and '" .
                    Constraint::NOT_EQUALS."' comparators are supported for data_type 'bool'.");
        }
    }

}
