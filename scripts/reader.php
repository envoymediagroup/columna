<?php
ini_set('memory_limit', '1G');
chdir(__DIR__);
$autoload_path = file_exists('../vendor/autoload.php') ? '../vendor/autoload.php' : '../../../autoload.php';
require($autoload_path);

use EnvoyMediaGroup\Columna\BundledReader;

$workload = $argv[1];

if (empty($workload)) {
    print "ERROR: No workload given.\n";
    print "Pass a json workload like so:.\n";
    print 'php reader.php \'{"date":"2022-07-08","metric":"clicks","dimensions":["site_id","sequence_id"],"constraints":[[{"name":"platform_id","comparator":"=","value":1},{"name":"site_id","comparator":"in","value":[1,49,53]}]],"do_aggregate":true,"do_aggregate_meta":false,"file":"/path/clicks.scf"}\''."\n";
    exit(1);
}

$Reader = new BundledReader();
$Reader->runFromWorkload($workload);

print $Reader->getResponsePayload();

exit;