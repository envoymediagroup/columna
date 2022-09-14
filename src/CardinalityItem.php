<?php

namespace EnvoyMediaGroup\Columna;

use Exception;

class CardinalityItem {

    /** @var int */
    protected $index;
    /** @var int */
    protected $count;

    /**
     * @param int $index
     * @param int $count
     * @throws Exception
     */
    public function __construct(int $index, int $count) {
        if ($index < 0) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." index cannot be negative.");
        }
        $this->index = $index;

        if ($count < 0) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." count cannot be less than zero.");
        }
        $this->count = $count;
    }

    /**
     * @return int
     */
    public function getIndex(): int {
        return $this->index;
    }

    /**
     * @return int
     */
    public function getCount(): int {
        return $this->count;
    }

    /**
     * @param CardinalityItem $ItemA
     * @param CardinalityItem $ItemB
     * @throws Exception
     * @return int
     */
    public static function sort(CardinalityItem $ItemA, CardinalityItem $ItemB): int {
        if ($ItemA->getIndex() === $ItemB->getIndex()) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." cannot sort two CardinalityItems with the same index.");
        }

        $result = $ItemA->getCount() <=> $ItemB->getCount();

        if ($result === 0) {
            $result = $ItemA->getIndex() <=> $ItemB->getIndex();
        }

        if ($result === 0) {
            throw new Exception(
                __CLASS__.'::'.__FUNCTION__." two CardinalityItems had identical sort results: " .
                "ItemA({$ItemA->getIndex()},{$ItemA->getCount()}), ItemB({$ItemB->getIndex()},{$ItemB->getCount()})."
            );
        }

        return $result;
    }

}