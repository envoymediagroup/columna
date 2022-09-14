<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\RleHelper;
use PHPUnit\Framework\TestCase;

/**
 * @covers RleHelper
 */
class RleHelperTest extends TestCase {

    public function testRleCompressWorksOnMultipleValues() {
        $column = "bar";
        $values = ['foo','foo','foo','baz','bar','bar','foo'];

        $result = RleHelper::rleCompress($column,$values);

        $expected = [
            'foo' . RleHelper::RLE_SEPARATOR . '3',
            'baz',
            'bar' . RleHelper::RLE_SEPARATOR . '2',
            'foo',
        ];
        $this->assertEquals($expected,$result);
    }

    public function testRleCompressWorksOnSingleValue() {
        $column = "bar";
        $values = ['foo'];

        $result = RleHelper::rleCompress($column,$values);

        $this->assertEquals($values,$result);
    }

    public function testRleCompressWorksOnEmptyValues() {
        $column = "bar";
        $values = [];

        $result = RleHelper::rleCompress($column,$values);

        $this->assertEquals($values,$result);
    }

    public function testRleCompressWorksOnMultipleEmptyStrings() {
        $column = "bar";
        $values = [
            '','','','','','','','','',''
        ];

        $result = RleHelper::rleCompress($column,$values);

        $this->assertEquals([RleHelper::RLE_SEPARATOR .'10'],$result);
    }

    public function testRleCompressWorksOnSingleEmptyString() {
        $column = "bar";
        $values = [''];

        $result = RleHelper::rleCompress($column,$values);

        $this->assertEquals($values,$result);
    }

    public function testRleCompressThrowsOnDataContainingRleSeparator() {
        $column = "foo";
        $values = [
            "one",
            "two\036three",
            "four"
        ];

        $this->expectExceptionMessage("contains RLE separator");
        RleHelper::rleCompress($column,$values);
    }

    public function testRleUncompressWorksOnMultipleValues() {
        $values = [
            'foo' . RleHelper::RLE_SEPARATOR . '3',
            'baz',
            'bar' . RleHelper::RLE_SEPARATOR . '2',
            'foo',
        ];

        $result = RleHelper::rleUncompress($values);

        $expected = ['foo','foo','foo','baz','bar','bar','foo'];
        $this->assertEquals($expected,$result);
    }

    public function testRleUncompressWorksOnMultipleValuesWithoutCompression() {
        $values = [
            'foo',
            'foo',
            'foo',
            'baz',
            'bar',
            'bar',
            'foo',
        ];

        $result = RleHelper::rleUncompress($values);

        $expected = ['foo','foo','foo','baz','bar','bar','foo'];
        $this->assertEquals($expected,$result);
    }

    public function testRleUncompressWorksOnSingleValue() {
        $values = ['foo'];

        $result = RleHelper::rleUncompress($values);

        $this->assertEquals($values,$result);
    }

    public function testRleUncompressWorksOnEmptyValues() {
        $values = [];

        $result = RleHelper::rleUncompress($values);

        $this->assertEquals($values,$result);
    }

    public function testRleUncompressWorksOnMultipleEmptyStrings() {
        $values = [RleHelper::RLE_SEPARATOR .'10'];
        $result = RleHelper::rleUncompress($values);

        $expected = [
            '','','','','','','','','',''
        ];
        $this->assertEquals($expected,$result);
    }

    public function testRleUncompressWorksOnSingleEmptyString() {
        $values = [''];

        $result = RleHelper::rleUncompress($values);

        $this->assertEquals($values,$result);
    }

    public function testCompressValuesIfThresholdMetNoChangeBelowThreshold() {
        $column = "bar";
        $values = ['foo','foo','bar','bar','baz','baz'];

        $result = RleHelper::rleCompressIfThresholdMet($column,$values,30);

        $this->assertEquals($values,$result);
    }

    public function testCompressValuesIfThresholdMetChangesAboveThreshold() {
        $column = "bar";
        $values = ['foo','foo','foo','foo','foo','baz'];

        $result = RleHelper::rleCompressIfThresholdMet($column,$values,30);

        $expected = [
            'foo' . RleHelper::RLE_SEPARATOR . '5',
            'baz',
        ];
        $this->assertEquals($expected,$result);
    }

    //rleUncompress doesn't throw anything

}