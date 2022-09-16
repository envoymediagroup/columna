<?php
ini_set('memory_limit', '1G');
chdir(__DIR__);

$data = json_decode(file_get_contents("../tests/fixtures/dimension_definitions.json"),true);
ksort($data);
file_put_contents("../tests/fixtures/dimension_definitions.json",json_encode($data,JSON_PRETTY_PRINT));

$data = json_decode(file_get_contents("../tests/fixtures/clicks--row_based_associative_data.json"),true);
foreach ($data as &$row) {
    ksort($row);
}

$headers = array_keys(current($data));
$data = array_map('array_values',$data);
file_put_contents("../tests/fixtures/clicks--row_based_headers.json",json_encode($headers,JSON_PRETTY_PRINT));
file_put_contents("../tests/fixtures/clicks--row_based_data.json",json_encode($data,JSON_PRETTY_PRINT));
print "done\n";
exit;