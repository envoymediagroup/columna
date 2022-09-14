<?php

namespace EnvoyMediaGroup\Columna;

use Exception;

class ColumnDefinition {

    public const AXIS_TYPE_METRIC = 'metric';
    public const AXIS_TYPE_DIMENSION = 'dimension';

    protected const VALID_AXIS_TYPES = [self::AXIS_TYPE_METRIC, self::AXIS_TYPE_DIMENSION];

    public const DATA_TYPE_STRING = 'string';
    public const DATA_TYPE_INT = 'int';
    public const DATA_TYPE_FLOAT = 'float';
    public const DATA_TYPE_BOOL = 'bool';
    public const DATA_TYPE_DATETIME = 'datetime';

    protected const DATA_TYPE_VALIDATION_MAP = [
        self::DATA_TYPE_STRING => 'string',
        self::DATA_TYPE_INT => 'integer',
        self::DATA_TYPE_FLOAT => 'double',
        self::DATA_TYPE_BOOL => 'boolean',
        self::DATA_TYPE_DATETIME => 'string',
    ];

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
    public function __construct(
        string $axis_type,
        string $name,
        string $data_type,
        ?int $precision,
        $empty_value
    ) {
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
    protected function setAxisType(string $axis_type): void {
        if (!in_array($axis_type,self::VALID_AXIS_TYPES)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid axis_type '{$axis_type}'. Must be one of : '" . join("','",self::VALID_AXIS_TYPES) . "'.");
        }
        $this->axis_type = $axis_type;
    }

    /**
     * @param string $name
     * @throws Exception
     */
    protected function setName(string $name): void {
        if ($name === '') {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." name cannot be empty.");
        }
        if (preg_match("/^[a-z][a-z0-9_]*$/",$name) !== 1) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid name '{$name}'. Only lowercase letters, numbers, and underscores are permitted; must start with a letter.");
        }
        $this->name = $name;
    }

    /**
     * @param string $data_type
     * @throws Exception
     */
    protected function setDataType(string $data_type): void {
        if (!array_key_exists($data_type,self::DATA_TYPE_VALIDATION_MAP)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid data_type '{$data_type}'. Must be one of : '" . join("','",array_keys(self::DATA_TYPE_VALIDATION_MAP)) . "'.");
        }
        $this->data_type = $data_type;
    }

    /**
     * @param int|null $precision
     * @throws Exception
     */
    protected function setPrecision(?int $precision): void {
        if (isset($precision)) {
            if ($this->data_type !== self::DATA_TYPE_FLOAT) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." precision may only be provided when data_type is 'float'; data_type is '{$this->data_type}'.");
            }
            if ($precision <= 0) {
                throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid precision {$precision}. Precision, if provided, must be greater than zero.");
            }
        }
        $this->precision = $precision;
    }

    /**
     * @param mixed $empty_value
     * @throws Exception
     */
    protected function setEmptyValue($empty_value): void {
        if (
            $this->data_type === self::DATA_TYPE_FLOAT && is_int($empty_value)
        ) {
            $empty_value = floatval($empty_value);
        }
        if (gettype($empty_value) !== self::DATA_TYPE_VALIDATION_MAP[$this->data_type]) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." value '{$empty_value}' does not match data_type '{$this->data_type}'.");
        }
        $this->empty_value = $empty_value;
    }

    /**
     * @return string
     */
    public function getAxisType(): string {
        return $this->axis_type;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDataType(): string {
        return $this->data_type;
    }

    /**
     * @return int|null
     */
    public function getPrecision(): ?int {
        return $this->precision;
    }

    /**
     * @return mixed
     */
    public function getEmptyValue() {
        return $this->empty_value;
    }

    /**
     * @return array
     */
    public function toArray(): array {
        return [
            "axis_type" => $this->axis_type,
            "name" => $this->name,
            "data_type" => $this->data_type,
            "precision" => $this->precision,
            "empty_value" => $this->empty_value,
        ];
    }

    /**
     * @param array $array
     * @throws Exception
     * @return ColumnDefinition
     */
    public static function fromArray(array $array): ColumnDefinition {
        $expected_keys = [
            "axis_type",
            "name",
            "data_type",
            "precision",
            "empty_value",
        ];
        if (array_keys($array) !== $expected_keys) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid array: " . print_r($array,true));
        }
        return new ColumnDefinition(
            $array["axis_type"],
            $array["name"],
            $array["data_type"],
            $array["precision"],
            $array["empty_value"]
        );
    }

}