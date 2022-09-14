<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\CardinalityItem;
use PHPUnit\Framework\TestCase;

/**
 * @covers CardinalityItem
 */
class CardinalityItemTest extends TestCase {

    public function testAllowsValidItem() {
        $Item = new CardinalityItem(4,513);
        $this->assertEquals(4,$Item->getIndex());
        $this->assertEquals(513,$Item->getCount());
    }

    public function testThrowsOnNegativeIndex() {
        $this->expectExceptionMessage("index cannot be negative");
        new CardinalityItem(-1,12);
    }

    public function testThrowsOnNegativeCount() {
        $this->expectExceptionMessage("count cannot be less than zero");
        new CardinalityItem(0,-3);
    }

    public function testSortCorrectlySortsOnCount() {
        $Item0 = new CardinalityItem(0,1);      //account_id
        $Item1 = new CardinalityItem(1,6);      //engine_id
        $Item2 = new CardinalityItem(2,100);    //referrer_url

        $CardinalityItems = [
            2 => $Item2,
            0 => $Item0,
            1 => $Item1,
        ];

        uasort($CardinalityItems,function($ItemA,$ItemB) { return CardinalityItem::sort($ItemA,$ItemB); });

        $Expected = [
            0 => $Item0,
            1 => $Item1,
            2 => $Item2,
        ];
        $this->assertSame($Expected,$CardinalityItems);
    }

    public function testSortCorrectlySortsOnIndex() {
        $Item1 = new CardinalityItem(0,1); //account_id
        $Item2 = new CardinalityItem(2,6); //engine_id
        $Item3 = new CardinalityItem(3,100); //referrer_url
        $Item4 = new CardinalityItem(1,6); //campaign_id

        $CardinalityItems = [
            3 => $Item3,
            0 => $Item1,
            2 => $Item2,
            1 => $Item4,
        ];

        uasort($CardinalityItems,function($ItemA,$ItemB) { return CardinalityItem::sort($ItemA,$ItemB); });

        $Expected = [
            0 => $Item1,
            1 => $Item4,
            2 => $Item2,
            3 => $Item3,
        ];
        $this->assertSame($Expected,$CardinalityItems);
    }

    public function testThrowsOnSameIndex() {
        $Item1 = new CardinalityItem(0,1);
        $Item2 = new CardinalityItem(0,6);
        $this->expectExceptionMessage("cannot sort two CardinalityItems with the same index");
        CardinalityItem::sort($Item1,$Item2);
    }

    //Exception 'two CardinalityItems had identical sort results' should be unreachable

}