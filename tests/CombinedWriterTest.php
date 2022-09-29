<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\CombinedWriter;

/**
 * @covers CombinedWriter
 */
class CombinedWriterTest extends WriterAbstractTestCase {

//    public function testWritesFileWithDataFromManyFiles() {
//        $files = [
//            self::FIXTURES_DIR . "combined_writer_test_shard_1.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard_2.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard_3.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard_4.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard_5.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard_6.scf",
//        ];
//        $CombinedWriter = new CombinedWriter($this->getMockFileHelper());
//
//        $CombinedWriter->writeCombinedFile(
//            self::TEST_DATE,
//            $this->getMetricDefinitionClicks(),
//            $this->getSubsetOfDimensionDefinitions(),
//            $files,
//            self::TEST_OUTPUT_FILE
//        );
//
////        //For updating the test files
////        print "Saved output with data to file:\n" . self::TEST_OUTPUT_FILE . "\n";
////        exit;
//
//        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--has_data.scf', self::TEST_OUTPUT_FILE);
//    }

    public function testWritesFileFromRecursiveCombinationOfChunksOfFiles() {
        $files = [
            self::FIXTURES_DIR . "combined_writer_test_shard_1.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard_2.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard_3.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard_4.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard_5.scf",
            self::FIXTURES_DIR . "combined_writer_test_shard_6.scf",
        ];
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
            $files,
            self::TEST_OUTPUT_FILE
        );

        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--has_data.scf', self::TEST_OUTPUT_FILE);
        //Expect 3 invocations:
        //   - 5 files gets broken down into 3 groups -> 3 results
        //   - 3 files gets broken down into 2 groups -> 2 results
        //   - 2 files get combined into 1 result
        $this->assertEquals(3,$CombinedWriter->combine_invocations);
    }
//
//    public function testWritesFileFromRecursiveCombinationOfChunksOfFilesAllNoData() {
//        $files = [
//            self::FIXTURES_DIR . "combined_writer_test_shard_3.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard_5.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard_6.scf",
//        ];
//        $CombinedWriter = new class($this->getMockFileHelper()) extends CombinedWriter {
//            public $combine_invocations = 0;
//            protected function getChunkSize(): int {
//                return 2;
//            }
//            protected function combineFilesRecursivelyByChunks(array $partial_files, bool $recur = false) {
//                ++$this->combine_invocations;
//                return parent::combineFilesRecursivelyByChunks($partial_files,$recur);
//            }
//        };
//
//        $CombinedWriter->writeCombinedFile(
//            self::TEST_DATE,
//            $this->getMetricDefinitionClicks(),
//            $this->getSubsetOfDimensionDefinitions(),
//            $files,
//            self::TEST_OUTPUT_FILE
//        );
//
//        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--no_data.scf', self::TEST_OUTPUT_FILE);
//        //Expect 2 invocations:
//        //   - 3 files gets broken down into 2 groups -> 2 results
//        //   - 2 files get combined into 1 result
//        $this->assertEquals(2,$CombinedWriter->combine_invocations);
//    }
//
//    public function testWritesFileWithDataFromOneFile() {
//        $files = [
//            self::FIXTURES_DIR . "combined_writer_test_shard_1.scf",
//        ];
//        $CombinedWriter = new CombinedWriter($this->getMockFileHelper());
//
//        $CombinedWriter->writeCombinedFile(
//            self::TEST_DATE,
//            $this->getMetricDefinitionClicks(),
//            $this->getSubsetOfDimensionDefinitions(),
//            $files,
//            self::TEST_OUTPUT_FILE
//        );
//
//        $this->assertFileEqualsBrief(self::FIXTURES_DIR . "combined_writer_test_shard_1.scf", self::TEST_OUTPUT_FILE);
//    }
//
//    public function testWritesFileWithNoData() {
//        $files = [
//            self::FIXTURES_DIR . "combined_writer_test_shard_3.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard_5.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard_6.scf",
//        ];
//        $CombinedWriter = new CombinedWriter($this->getMockFileHelper());
//
//        $CombinedWriter->writeCombinedFile(
//            self::TEST_DATE,
//            $this->getMetricDefinitionClicks(),
//            $this->getSubsetOfDimensionDefinitions(),
//            $files,
//            self::TEST_OUTPUT_FILE
//        );
//
//        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--no_data.scf', self::TEST_OUTPUT_FILE);
//    }
//
//    public function testFailsOnMismatchedColumnMetas() {
//        $files = [
//            self::FIXTURES_DIR . "combined_writer_test_shard_1.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard_2.scf",
//            self::FIXTURES_DIR . "combined_writer_test_shard--wrong_meta.scf",
//        ];
//        $CombinedWriter = new CombinedWriter($this->getMockFileHelper());
//
//        $this->expectExceptionMessage("column 'user_id' had invalid column_meta, expected definition");
//        $CombinedWriter->writeCombinedFile(
//            self::TEST_DATE,
//            $this->getMetricDefinitionClicks(),
//            $this->getSubsetOfDimensionDefinitions(),
//            $files,
//            self::TEST_OUTPUT_FILE
//        );
//    }
//
//    //Later: test all the other error conditions

}