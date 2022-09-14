<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\FileHelper;
use PHPUnit\Framework\TestCase;

class FileHelperTest extends TestCase {

    protected const TEST_FILE = '/tmp/file_helper_test.txt';

    public function setUp(): void {
        parent::setUp();
        @unlink(self::TEST_FILE);
    }

    public function tearDown(): void {
        @unlink(self::TEST_FILE);
        parent::tearDown();
    }

    public function testCloseAndDeleteFile() {
        file_put_contents(self::TEST_FILE,'1');
        $this->assertFileExists(self::TEST_FILE);
        $File = new \SplFileObject(self::TEST_FILE);
        (new FileHelper())->closeAndDeleteFile($File);
        $this->assertFileDoesNotExist(self::TEST_FILE);
    }

}