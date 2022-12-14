<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\CombinedWriter;
use EnvoyMediaGroup\Columna\FileHelper;
use Exception;

/**
 * @covers CombinedWriter
 */
class CombinedWriterTest extends WriterAbstractTestCase {

    protected const TEST_SHARD_FILES = [
        self::FIXTURES_DIR . "combined_writer_test_shard_1.scf",
        self::FIXTURES_DIR . "combined_writer_test_shard_2.scf",
        self::FIXTURES_DIR . "combined_writer_test_shard_3.scf",
        self::FIXTURES_DIR . "combined_writer_test_shard_4.scf",
        self::FIXTURES_DIR . "combined_writer_test_shard_5.scf",
        self::FIXTURES_DIR . "combined_writer_test_shard_6.scf",
    ];

    public function tearDown(): void {
        foreach (self::TEST_SHARD_FILES as $file) {
            if (!file_exists($file)) {
                throw new Exception("Test fixture file '{$file}' was deleted during a test.");
            }
        }

        $tmp_ext = FileHelper::TMP_FILE_EXTENSION;
        $tmp_ext_len = strlen($tmp_ext);
        $tmp_files = scandir('/tmp/');
        foreach ($tmp_files as $file) {
            if (mb_substr($file,-$tmp_ext_len,$tmp_ext_len) === $tmp_ext) {
                throw new Exception("Test left a tmp file behind at: /tmp/{$file}");
            }
        }

        parent::tearDown();
    }

    public function testWritesFileWithDataFromManyFiles() {
        $CombinedWriter = new CombinedWriter($this->getMockFileHelper());

        $CombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            self::TEST_SHARD_FILES,
            self::TEST_OUTPUT_FILE
        );

//        //For updating the test files
//        print "Saved output with data to file:\n" . self::TEST_OUTPUT_FILE . "\n";
//        exit;

        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--has_data.scf', self::TEST_OUTPUT_FILE);
    }

    public function testWritesFileFromRecursiveCombinationOfChunksOfFiles() {
        $CombinedWriter = new class($this->getMockFileHelper()) extends CombinedWriter {
            public $combine_invocations = 0;
            protected function getChunkSize(): int {
                return 2;
            }
            protected function combineFilesRecursivelyByChunks(array $partial_files,bool $recur = false) {
                ++$this->combine_invocations;
                return parent::combineFilesRecursivelyByChunks($partial_files, $recur);
            }
        };

        $CombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            self::TEST_SHARD_FILES,
            self::TEST_OUTPUT_FILE
        );

        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--has_data.scf', self::TEST_OUTPUT_FILE);
        //Expect 3 invocations:
        //   - 6 files gets broken down into 3 groups -> 3 results
        //   - 3 files gets broken down into 2 groups -> 2 results
        //   - 2 files get combined into 1 result
        $this->assertEquals(3,$CombinedWriter->combine_invocations);
    }

    public function testWritesFileFromRecursiveCombinationOfChunksOfFilesAllNoData() {
        $files = [
            self::FIXTURES_DIR . "combined_writer_test_shard_3.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard_5.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard_6.scf",
        ];
        $CombinedWriter = new class($this->getMockFileHelper()) extends CombinedWriter {
            public $combine_invocations = 0;
            protected function getChunkSize(): int {
                return 2;
            }
            protected function combineFilesRecursivelyByChunks(array $partial_files, bool $recur = false) {
                ++$this->combine_invocations;
                return parent::combineFilesRecursivelyByChunks($partial_files,$recur);
            }
        };

        $CombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            $files,
            self::TEST_OUTPUT_FILE
        );

        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--no_data.scf', self::TEST_OUTPUT_FILE);
        //Expect 2 invocations:
        //   - 3 files gets broken down into 2 groups -> 2 results
        //   - 2 files get combined into 1 result
        $this->assertEquals(2,$CombinedWriter->combine_invocations);
    }

    public function testWritesFileWithDataFromOneFile() {
        $files = [
            self::FIXTURES_DIR . "combined_writer_test_shard_1.scf",
        ];
        $CombinedWriter = new CombinedWriter($this->getMockFileHelper());

        $CombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            $files,
            self::TEST_OUTPUT_FILE
        );

        $this->assertFileEqualsBrief(self::FIXTURES_DIR . "combined_writer_test_shard_1.scf", self::TEST_OUTPUT_FILE);
    }

    public function testWritesFileWithNoData() {
        $files = [
            self::FIXTURES_DIR . "combined_writer_test_shard_3.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard_5.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard_6.scf",
        ];
        $CombinedWriter = new CombinedWriter($this->getMockFileHelper());

        $CombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            $files,
            self::TEST_OUTPUT_FILE
        );

        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--no_data.scf', self::TEST_OUTPUT_FILE);
    }

    public function testFailsOnMismatchedColumnMetas() {
        $files = [
            self::FIXTURES_DIR . "combined_writer_test_shard_1.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard_2.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard--wrong_meta.scf",
        ];
        $CombinedWriter = new CombinedWriter($this->getMockFileHelper());

        $this->expectExceptionMessage("column 'user_id' had invalid column_meta, expected definition");
        $CombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            $files,
            self::TEST_OUTPUT_FILE
        );
    }

}