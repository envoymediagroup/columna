<?php
ini_set('memory_limit', '1G');
chdir(__DIR__);
$autoload_path = file_exists('../vendor/autoload.php') ? '../vendor/autoload.php' : '../../../autoload.php';
require($autoload_path);

use EnvoyMediaGroup\Columna\Reader;
use EnvoyMediaGroup\Columna\BundledReader;

$opts = getopt("w:pb",["workload:","profile","bundled_reader"]);
$workload = ($opts["w"] ?? null);
$profile = ($opts["p"] ?? null) === false;
$bundled_reader = ($opts["b"] ?? null) === false;

if (empty($workload)) {
    print "ERROR: No workload given.\n";
    print "Pass a json workload with ['date','metric','dimensions','constraints','do_aggregate','do_aggregate_meta','file'] keys as '-w'/'--workload'.\n";
    print "Optionally, add '-p'/'--profile' to show profiling, or '-b'/'--bundled_reader' to use BundledReader instead of Reader.\n";
    print "Example:\n";
    print 'php reader_cli.php -w \'{"date":"2022-07-08","metric":"clicks","dimensions":["site_id","platform_id","sequence_id"],"constraints":[[{"name":"platform_id","comparator":"=","value":1},{"name":"site_id","comparator":"in","value":[1,49,53]},{"name":"sequence_id","comparator":"=","value":1}]],"do_aggregate":true,"do_aggregate_meta":false,"file":"/path/clicks.scf"}\''."\n";
    exit(1);
}

if ($profile) {
    $mem_usage_start = memory_get_usage();
    $mem_peak_usage_start = memory_get_peak_usage(true);
    $hr_start = hrtime(true);
    print "includes before Reader:\n";
    print_r(get_included_files());
}

if ($bundled_reader) {
    $Reader = new BundledReader();
} else {
    $Reader = new Reader();
}

$Reader->runFromWorkload($workload);

if ($profile) {
    $hr_end = hrtime(true);
    $mem_usage_end = memory_get_usage();
    $mem_peak_usage_end = memory_get_peak_usage(true);
}

print $Reader->getResponsePayload();

if ($profile) {
    print "\n";
    print "time:                " . number_format(($hr_end - $hr_start) / 1000000,2,'.','')  . " ms\n";
    print "mem_usage diff:      " . number_format($mem_usage_end - $mem_usage_start) . " B\n";
    print "mem_peak_usage diff: " . number_format($mem_peak_usage_end - $mem_peak_usage_start) . " B\n";
    print "includes after Reader:\n";
    print_r(get_included_files());
}

exit;