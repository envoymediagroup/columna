<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\ZipCombinedWriter;

/**
 * @covers ZipCombinedWriter
 */
class ZipCombinedWriterTest extends WriterAbstractTestCase {

    public function testWritesFileWithDataFromManyFilesUncompressed() {
        $zip_file = self::FIXTURES_DIR . 'zip_combined_writer_all_files_uncompressed_CM_STORE.zip';
        $ZipCombinedWriter = new ZipCombinedWriter($this->getMockFileHelper());

        $ZipCombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            $zip_file,
            self::TEST_OUTPUT_FILE
        );

        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--has_data.scf', self::TEST_OUTPUT_FILE);
    }

    public function testWritesFileWithDataFromManyFilesCompressed() {
        $zip_file = self::FIXTURES_DIR . 'zip_combined_writer_all_files_compressed_CM_DEFLATE.zip';
        $ZipCombinedWriter = new ZipCombinedWriter($this->getMockFileHelper());

        $ZipCombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            $zip_file,
            self::TEST_OUTPUT_FILE
        );

        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--has_data.scf', self::TEST_OUTPUT_FILE);
    }

    public function testWritesFileWithDataFromOneFile() {
        $zip_file = self::FIXTURES_DIR . 'zip_combined_writer_one_file.zip';
        $ZipCombinedWriter = new ZipCombinedWriter($this->getMockFileHelper());

        $ZipCombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            $zip_file,
            self::TEST_OUTPUT_FILE
        );

        $this->assertFileEqualsBrief(self::FIXTURES_DIR . "combined_writer_test_shard_1.scf", self::TEST_OUTPUT_FILE);
    }

    public function testWritesFileWithNoData() {
        $zip_file = self::FIXTURES_DIR . 'zip_combined_writer_no_data.zip';
        $ZipCombinedWriter = new ZipCombinedWriter($this->getMockFileHelper());

        $ZipCombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            $zip_file,
            self::TEST_OUTPUT_FILE
        );

        $this->assertFileEqualsBrief(self::FIXTURES_DIR . 'combined_file--no_data.scf', self::TEST_OUTPUT_FILE);
    }

    public function testFailsOnMismatchedColumnMetas() {
        $zip_file = self::FIXTURES_DIR . 'zip_combined_writer_mismatched_meta.zip';
        $ZipCombinedWriter = new ZipCombinedWriter($this->getMockFileHelper());

        $this->expectExceptionMessage("column 'user_id' had invalid column_meta, expected definition");
        $ZipCombinedWriter->writeCombinedFile(
            self::TEST_DATE,
            $this->getMetricDefinitionClicks(),
            $this->getSubsetOfDimensionDefinitions(),
            $zip_file,
            self::TEST_OUTPUT_FILE
        );
    }

    //Later: test all the other error conditions

}