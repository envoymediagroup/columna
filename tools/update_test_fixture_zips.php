<?php
chdir(__DIR__);
require ('../vendor/autoload.php');

$dir = "../tests/fixtures/";

$map = [
    "zip_combined_writer_all_files_compressed_CM_DEFLATE.zip" => [
        "algo" => ZipArchive::CM_DEFLATE,
        "files" => [
            'combined_writer_test_shard_1.scf',
            'combined_writer_test_shard_2.scf',
            'combined_writer_test_shard_3.scf',
            'combined_writer_test_shard_4.scf',
            'combined_writer_test_shard_5.scf',
            'combined_writer_test_shard_6.scf',
        ],
    ],
    "zip_combined_writer_all_files_uncompressed_CM_STORE.zip" => [
        "algo" => ZipArchive::CM_STORE,
        "files" => [
            'combined_writer_test_shard_1.scf',
            'combined_writer_test_shard_2.scf',
            'combined_writer_test_shard_3.scf',
            'combined_writer_test_shard_4.scf',
            'combined_writer_test_shard_5.scf',
            'combined_writer_test_shard_6.scf',
        ],
    ],
    "zip_combined_writer_mismatched_meta.zip" => [
        "algo" => ZipArchive::CM_DEFLATE,
        "files" => [
            'combined_writer_test_shard_1.scf',
            'combined_writer_test_shard--wrong_meta.scf',
        ],
    ],
    "zip_combined_writer_no_data.zip" => [
        "algo" => ZipArchive::CM_DEFLATE,
        "files" => [
            'combined_writer_test_shard_3.scf',
            'combined_writer_test_shard_5.scf',
            'combined_writer_test_shard_6.scf',
        ],
    ],
    "zip_combined_writer_one_file.zip" => [
        "algo" => ZipArchive::CM_DEFLATE,
        "files" => [
            'combined_writer_test_shard_1.scf',
        ],
    ],
];

foreach ($map as $zip => $spec) {
    $zip = $dir . $zip;
    $ZipArchive = new ZipArchive();
    $ZipArchive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($spec["files"] as $i => $file) {
        $ZipArchive->addFile($dir . $file);
        $ZipArchive->setCompressionIndex($i,$spec["algo"]);
    }
    $ZipArchive->close();
    print "Saved: $zip\n";
}

print "DONE\n";
exit;