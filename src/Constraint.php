<?php

namespace EnvoyMediaGroup\Columna;

use Exception;

class Constraint {

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

    protected const VALID_COMPARATORS = [
        self::EQUALS,self::NOT_EQUALS,self::GREATER_THAN,self::GREATER_THAN_OR_EQUALS,self::LESS_THAN,
        self::LESS_THAN_OR_EQUALS,self::IN,self::NOT_IN,self::CONTAINS,self::NOT_CONTAINS,self::CONTAINS_IN,
        self::CONTAINS_ALL,self::NOT_CONTAINS_IN,self::BEGINS_WITH,self::NOT_BEGINS_WITH,self::ENDS_WITH,
        self::NOT_ENDS_WITH,self::REGEX,self::NOT_REGEX,self::EMPTY,self::NOT_EMPTY
    ];

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
    public function __construct(string $name, string $comparator, $value) {
        $this->setName($name);
        $this->setComparator($comparator);
        $this->setValue($value);
    }

    /**
     * @param string $name
     * @throws Exception
     */
    protected function setName(string $name) {
        if ($name === '') {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." name cannot be empty.");
        }
        if (preg_match("/^[a-z][a-z0-9_]*$/",$name) !== 1) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid name '{$name}'. Only lowercase letters, numbers, and underscores are permitted; must start with a letter.");
        }
        $this->name = $name;
    }

    /**
     * @param string $comparator
     * @throws Exception
     */
    protected function setComparator(string $comparator) {
        if (!in_array($comparator,self::VALID_COMPARATORS)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid comparator '{$comparator}'.");
        }
        $this->comparator = $comparator;
    }

    /**
     * @param $value
     */
    protected function setValue($value) {
        //Value can be anything
        if ($this->comparator === self::EMPTY || $this->comparator === self::NOT_EMPTY) {
            $value = null; //Ignore value
        }
        $this->value = $value;
    }

    /**
     * @return array
     */
    public function toArray(): array {
        return [
            "name" => $this->name,
            "comparator" => $this->comparator,
            "value" => $this->value,
        ];
    }

    /**
     * @param array $array
     * @throws Exception
     * @return Constraint
     */
    public static function fromArray(array $array): Constraint {
        $expected_keys = [
            "name",
            "comparator",
            "value",
        ];
        if (array_keys($array) !== $expected_keys) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." invalid array: " . print_r($array,true));
        }
        return new self($array["name"],$array["comparator"],$array["value"]);
    }

}