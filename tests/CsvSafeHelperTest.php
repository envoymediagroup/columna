<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\CsvSafeHelper;
use PHPUnit\Framework\TestCase;
use SplFileObject;

/**
 * @covers CsvSafeHelper
 */
class CsvSafeHelperTest extends TestCase {

    protected const TEST_OUTPUT_FILE = '/tmp/test_output_file.scf';

    public function testHandlesAVarietyOfStrings() {
        $values = [
            'chapter 7 bankruptcy on credit for how long',
            'chapter 7 "bankruptcy on credit for how long',
            'chapter 7 "bankruptcy" on credit for how long',
            'chapter 7 bankruptcy on credit for how long',
            '"chapter 7 bankruptcy on credit for how long"',
            '"chapter 7 bankruptcy on credit for how long',
            'chapter 7 bankruptcy on credit for how long"',
            'chapter 7 \'bankruptcy on credit for how long',
            'chapter 7 \'bankruptcy\' on credit for how long',
            '\'chapter 7 bankruptcy on credit for how long\'',
            '\'chapter 7 bankruptcy on credit for how long',
            'chapter 7 bankruptcy on credit for how long\'',
            'chapter 7 "bankruptcy" on credit for how long\\',
            'chapter 7 \\"bankruptcy" on credit for how long\\',
            'chapter 7 \\bankruptcy" on \'credit for how long\\',
            'chapter 7 \\bankruptcy" on credit\' for how long\\\\',
            'chapter 7 \\bankruptcy" on \'credit for how long\\\\\\',
            'chapter 7 \"bankruptcy" /on, \/credit \for how long\\',
            ',chapter 7 \"bankruptcy" /on\', \/credit \for how long\\,',
            '\,chapter 7 \"bankruptcy" /on, \/credit \for how long\\\,',
            '\,chapter 7 \"bankruptcy" /on\', \/credit \for how long\\\,',
            'chapter 7 \\bankruptcy" on, \'credit for how long\\\\\\',
            '\'chapter 7 bankruptcy on, credit for how long\'',
            'chapter 7 "bankruptcy" on, credit for how long',
            'chapter 7 ""bankruptcy" on,, credit\'\' for how long""',
            json_encode(['\,chapter 7 \"bankruptcy" /on, \/credit\' \for how long\\']),
            json_encode(["foo" => "b\ar" , "baz" => "boff\\"]),
            '0',
            '1',
            '-123',
            '123',
            '123.02',
            '123,02',
            '',
            "\t",
            '\\',
            '"',
            '\'',
            ',',
            'lingüística',
            '¡Dígaselo a mi mamá!',
            '¿Dónde?',
            'cañon',
            '👨🏻‍🚀',
        ];

        $this->assertValuesMatchAfterWriteAndRead($values);
    }

    public function testHandlesSingleEmptyString() {
        //Regular fputcsv/fgetcsv fails on an array with a single value of empty string (it would produce null instead)
        $values = [
            '',
        ];

        $this->assertValuesMatchAfterWriteAndRead($values);
    }

    public function testHandlesMultipleEmptyStrings() {
        $values = [
            '','','',
        ];

        $this->assertValuesMatchAfterWriteAndRead($values);
    }

    public function testThrowsOnInt() {
        $values = [
            1,
        ];

        $this->expectExceptionMessage("received non-string value");

        $TmpFile = $this->openTmpFile();
        CsvSafeHelper::fputcsvSafe($TmpFile, $values);
    }

    public function testThrowsOnFloat() {
        $values = [
            1.23,
        ];

        $this->expectExceptionMessage("received non-string value");

        $TmpFile = $this->openTmpFile();
        CsvSafeHelper::fputcsvSafe($TmpFile, $values);
    }

    public function testThrowsOnBool() {
        $values = [
            true,
        ];

        $this->expectExceptionMessage("received non-string value");

        $TmpFile = $this->openTmpFile();
        CsvSafeHelper::fputcsvSafe($TmpFile, $values);
    }

    public function testThrowsOnArray() {
        $values = [
            [1.23],
        ];

        $this->expectExceptionMessage("received non-string value");

        $TmpFile = $this->openTmpFile();
        CsvSafeHelper::fputcsvSafe($TmpFile, $values);
    }

    public function testThrowsOnObject() {
        $values = [
            new \stdClass(),
        ];

        $this->expectExceptionMessage("received non-string value");

        $TmpFile = $this->openTmpFile();
        CsvSafeHelper::fputcsvSafe($TmpFile, $values);
    }

    public function testThrowsOnNull() {
        $values = [
            null,
        ];

        $this->expectExceptionMessage("received non-string value");

        $TmpFile = $this->openTmpFile();
        CsvSafeHelper::fputcsvSafe($TmpFile, $values);
    }

    public function testThrowsOnLineBreak() {
        $values = [
            "foo\nbar",
            "\n",
        ];

        $this->expectExceptionMessage("contained EOL");

        $TmpFile = $this->openTmpFile();
        CsvSafeHelper::fputcsvSafe($TmpFile, $values);
    }

    public function setUp(): void {
        parent::setUp();
        if (file_exists(self::TEST_OUTPUT_FILE)) {
            unlink(self::TEST_OUTPUT_FILE);
        }
    }

    public function tearDown(): void {
        if (file_exists(self::TEST_OUTPUT_FILE)) {
            unlink(self::TEST_OUTPUT_FILE);
        }
        parent::tearDown();
    }

    public function testConvertValuesToStrings() {
        $values = [
            'foo',
            123,
            45.67,
            false,
            ['bar' => 'baz'],
        ];

        $result = CsvSafeHelper::convertValuesToStrings($values);

        $expected = [
            'foo',
            '123',
            '45.67',
            '0',
            '{"bar":"baz"}',
        ];
        $this->assertSame($expected,$result);
    }

    public function testConvertValuesToStringsThrowsOnInvalidDataType() {
        $values = [
            'foo',
            new \stdClass(),
        ];

        $this->expectExceptionMessage("got value with invalid type");
        CsvSafeHelper::convertValuesToStrings($values);
    }

    public function testFputcsvSafeAndFgetcsvSafe() {
        $row = [
            0 => '123',
            1 => 'chapter 7 bankruptcy on credit for how long',
            2 => 'chapter 7 "bankruptcy" on credit for how long\\',
            3 => 'chapter 7 \\"bankruptcy" on credit for how long\\',
            4 => 'chapter 7 \"bankruptcy" /on, \/credit \for how long\\',
            5 => ',chapter 7 \"bankruptcy" /on, \/credit \for how long\\,',
            6 => '\,chapter 7 \"bankruptcy" /on, \/credit \for how long\\\,',
            7 => '\,chapter 7 \"bankruptcy" /on, \/credit \for how long\\\,',
            8 => json_encode(['\,chapter 7 \"bankruptcy" /on, \/credit \for how long\\']),
            9 => '1',
            10 => '0',
        ];

        touch(self::TEST_OUTPUT_FILE);
        $File = new SplFileObject(self::TEST_OUTPUT_FILE,'r+');
        CsvSafeHelper::fputcsvSafe($File,$row);
        $File->rewind();
        $result = CsvSafeHelper::fgetcsvSafe($File);
        unset($File);

        $this->assertSame($row,$result);
    }

    /**
     * @param array $values
     * @throws \Exception
     * @return void
     */
    protected function assertValuesMatchAfterWriteAndRead(array $values): void {
        $TmpFile = $this->openTmpFile();
        CsvSafeHelper::fputcsvSafe($TmpFile, $values);
        $TmpFile->rewind();
        $result = CsvSafeHelper::fgetcsvSafe($TmpFile);
        $this->closeTmpFile($TmpFile);
        $this->assertEquals($values,$result);
    }

    /**
     * @return SplFileObject
     */
    protected function openTmpFile(): SplFileObject {
        if (file_exists(self::TEST_OUTPUT_FILE)) {
            unlink(self::TEST_OUTPUT_FILE);
        }
        touch(self::TEST_OUTPUT_FILE);
        return new SplFileObject(self::TEST_OUTPUT_FILE,'r+');
    }

    /**
     * @param SplFileObject $TmpFile
     * @return void
     */
    protected function closeTmpFile(SplFileObject &$TmpFile): void {
        $TmpFile = null;
        if (file_exists(self::TEST_OUTPUT_FILE)) {
            unlink(self::TEST_OUTPUT_FILE);
        }
    }

}