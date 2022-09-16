<?php

namespace EnvoyMediaGroup\Columna\Tests;

use EnvoyMediaGroup\Columna\Response;
use PHPUnit\Framework\TestCase;

/**
 * @covers Response
 */
class ResponseTest extends TestCase {

    protected const FIXTURES_DIR = '/app/tests/fixtures/';

    public function testResponseHasDataAndResultHasDataWithAggregationNoMeta() {
        $response_payload_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_aggregated_no_meta_with_csort.txt";
        $response_payload = file_get_contents($response_payload_file);
        $Response = new Response($response_payload);

        $expected_metadata = array (
            'date' => '2022-07-08',
            'metric' => 'clicks',
            'status' => 'success',
            'min' => 1,
            'max' => 10,
            'sum' => 616,
            'matched_row_count' => 122,
            'column_meta' => $this->getExpectedColumnMeta(),
            'is_aggregated' => true,
            'aggregate_includes_meta' => false,
            'host' => 'columna',
            'result_row_count' => 68,
            'ms_elapsed' => 44,
        );
        $expected_results = $this->getExpectedResults1();

        $this->assertEquals($expected_metadata,$Response->getMetadata());
        $this->assertEquals($expected_results,$Response->getResults());
    }

    public function testResponseHasDataAndResultHasDataWithAggregationAndMeta() {
        $response_payload_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_aggregated_with_meta.txt";
        $response_payload = file_get_contents($response_payload_file);
        $Response = new Response($response_payload);

        $expected_metadata = array (
            'date' => '2022-07-08',
            'metric' => 'clicks',
            'status' => 'success',
            'min' => 1,
            'max' => 10,
            'sum' => 616,
            'matched_row_count' => 122,
            'column_meta' => $this->getExpectedColumnMeta(),
            'is_aggregated' => true,
            'aggregate_includes_meta' => true,
            'host' => 'columna',
            'result_row_count' => 68,
            'ms_elapsed' => 23,
        );
        $expected_results = $this->getExpectedResults2();

        $this->assertEquals($expected_metadata,$Response->getMetadata());
        $this->assertEquals($expected_results,$Response->getResults());
    }

    public function testFileHasDataAndResultHasDataWithoutAggregation() {
        $response_payload_file = self::FIXTURES_DIR . "clicks--reader_response_with_data_not_aggregated.txt";
        $response_payload = file_get_contents($response_payload_file);
        $Response = new Response($response_payload);
        $expected_metadata = array (
            'date' => '2022-07-08',
            'metric' => 'clicks',
            'status' => 'success',
            'min' => 1,
            'max' => 10,
            'sum' => 616,
            'matched_row_count' => 122,
            'column_meta' => $this->getExpectedColumnMeta(),
            'is_aggregated' => false,
            'aggregate_includes_meta' => false,
            'host' => 'columna',
            'result_row_count' => 122,
            'ms_elapsed' => 23,
        );
        $expected_results = $this->getExpectedResults3();

        $this->assertEquals($expected_metadata,$Response->getMetadata());
        $this->assertEquals($expected_results,$Response->getResults());
    }

    public function testResponseStatusEmpty() {
        $response_payload_file = self::FIXTURES_DIR . "clicks--reader_response_empty_result.txt";
        $response_payload = file_get_contents($response_payload_file);
        $Response = new Response($response_payload);

        $expected_metadata = array (
            'date' => '2022-07-08',
            'metric' => 'clicks',
            'status' => 'empty',
            'host' => 'columna',
            'ms_elapsed' => 19,
        );
        $expected_results = [];

        $this->assertEquals($expected_metadata,$Response->getMetadata());
        $this->assertEquals($expected_results,$Response->getResults());
    }

    public function testResponseStatusErrorThrow() {
        $response_payload_file = self::FIXTURES_DIR . "clicks--reader_response_error_result.txt";
        $response_payload = file_get_contents($response_payload_file);
        $this->expectExceptionMessage("Reader request failed with status 'error'");
        new Response($response_payload);
    }

    public function testResponseStatusErrorDoNotThrow() {
        $response_payload_file = self::FIXTURES_DIR . "clicks--reader_response_error_result.txt";
        $response_payload = file_get_contents($response_payload_file);
        $Response = new Response($response_payload,false);

        $expected_metadata = array (
            'date' => '2022-07-08',
            'metric' => 'clicks',
            'status' => 'error',
            'error' => 'EnvoyMediaGroup\Columna\Reader::validateFileMeta file meta does not include required key \'date\'.',
            'host' => 'columna',
            'ms_elapsed' => 12,
        );
        $expected_results = [];

        $this->assertEquals($expected_metadata,$Response->getMetadata());
        $this->assertEquals($expected_results,$Response->getResults());
    }

    protected function getExpectedColumnMeta(): array {
        return array (
            0 =>
                array (
                    'definition' =>
                        array (
                            'axis_type' => 'dimension',
                            'name' => 'md5',
                            'data_type' => 'string',
                            'empty_value' => '',
                        ),
                    'index' => 0,
                ),
            1 =>
                array (
                    'definition' =>
                        array (
                            'axis_type' => 'dimension',
                            'name' => 'car_make',
                            'data_type' => 'string',
                            'precision' => NULL,
                            'empty_value' => '',
                        ),
                    'index' => 1,
                ),
            2 =>
                array (
                    'definition' =>
                        array (
                            'axis_type' => 'metric',
                            'name' => 'clicks',
                            'data_type' => 'int',
                            'precision' => NULL,
                            'empty_value' => 0,
                        ),
                    'index' => 2,
                ),
            3 =>
                array (
                    'definition' =>
                        array (
                            'axis_type' => 'dimension',
                            'name' => 'platform_id',
                            'data_type' => 'int',
                            'precision' => NULL,
                            'empty_value' => 0,
                        ),
                    'index' => 3,
                ),
        );
    }

    protected function getExpectedResults1(): array {
        return array (
            'caa72bd10e0134a66debc5507d5ba755' =>
                array (
                    0 => 'caa72bd10e0134a66debc5507d5ba755',
                    1 => 'Lexus',
                    2 => 3,
                    3 => 3,
                ),
            '7d46aa45223f859ac38089b793faf700' =>
                array (
                    0 => '7d46aa45223f859ac38089b793faf700',
                    1 => 'Porsche',
                    2 => 10,
                    3 => 3,
                ),
            '01104475e13e824550c23c553e4080a3' =>
                array (
                    0 => '01104475e13e824550c23c553e4080a3',
                    1 => 'Mercedes-Benz',
                    2 => 6,
                    3 => 3,
                ),
            '8543e94b62e2f51eb5a9ab727bc9814e' =>
                array (
                    0 => '8543e94b62e2f51eb5a9ab727bc9814e',
                    1 => 'Chevrolet',
                    2 => 32,
                    3 => 3,
                ),
            '887fb0407cabea6ee228a30fd0e41421' =>
                array (
                    0 => '887fb0407cabea6ee228a30fd0e41421',
                    1 => 'Nissan',
                    2 => 12,
                    3 => 3,
                ),
            '6e415fbf548698688e8caa552b641cb0' =>
                array (
                    0 => '6e415fbf548698688e8caa552b641cb0',
                    1 => 'Studebaker',
                    2 => 1,
                    3 => 3,
                ),
            '28e53a0c4dacec8b85707fd8dae33d33' =>
                array (
                    0 => '28e53a0c4dacec8b85707fd8dae33d33',
                    1 => 'Ford',
                    2 => 10,
                    3 => 3,
                ),
            'f155329d2d1a1c5ba75be0223f55c51d' =>
                array (
                    0 => 'f155329d2d1a1c5ba75be0223f55c51d',
                    1 => 'Dodge',
                    2 => 3,
                    3 => 3,
                ),
            'b9540914288caf85017c5ef84f0475f3' =>
                array (
                    0 => 'b9540914288caf85017c5ef84f0475f3',
                    1 => 'BMW',
                    2 => 19,
                    3 => 3,
                ),
            '9c1109a1edbfa3f032b3a90b91f81509' =>
                array (
                    0 => '9c1109a1edbfa3f032b3a90b91f81509',
                    1 => 'Saturn',
                    2 => 8,
                    3 => 3,
                ),
            'a4ceddab5184e95c71147d4d6f5ec5f6' =>
                array (
                    0 => 'a4ceddab5184e95c71147d4d6f5ec5f6',
                    1 => 'Oldsmobile',
                    2 => 5,
                    3 => 3,
                ),
            'f86cc6501388912a5731aefaf18967f8' =>
                array (
                    0 => 'f86cc6501388912a5731aefaf18967f8',
                    1 => 'Pontiac',
                    2 => 5,
                    3 => 3,
                ),
            '1f5fb789d741bd31a22b94449da811da' =>
                array (
                    0 => '1f5fb789d741bd31a22b94449da811da',
                    1 => 'Subaru',
                    2 => 5,
                    3 => 3,
                ),
            '3ab4e6b4907d88c68b4e593a82105082' =>
                array (
                    0 => '3ab4e6b4907d88c68b4e593a82105082',
                    1 => 'GMC',
                    2 => 9,
                    3 => 3,
                ),
            '62ce70a0e4a93afe338eaf3318865e24' =>
                array (
                    0 => '62ce70a0e4a93afe338eaf3318865e24',
                    1 => 'Ford',
                    2 => 19,
                    3 => 4,
                ),
            'ed45832d8630db7699f0d2017bbf94f7' =>
                array (
                    0 => 'ed45832d8630db7699f0d2017bbf94f7',
                    1 => 'Pontiac',
                    2 => 20,
                    3 => 4,
                ),
            '7aa23956c77f7679de5bd9eedb79a92f' =>
                array (
                    0 => '7aa23956c77f7679de5bd9eedb79a92f',
                    1 => 'BMW',
                    2 => 3,
                    3 => 4,
                ),
            'ed9f49135d1f3f30e3910aeefd39d713' =>
                array (
                    0 => 'ed9f49135d1f3f30e3910aeefd39d713',
                    1 => 'Honda',
                    2 => 21,
                    3 => 4,
                ),
            'f157b649993d71954ffc31c1b4fc2e76' =>
                array (
                    0 => 'f157b649993d71954ffc31c1b4fc2e76',
                    1 => 'Buick',
                    2 => 9,
                    3 => 4,
                ),
            'ff73cce19a5d02c8fc456e5295371b32' =>
                array (
                    0 => 'ff73cce19a5d02c8fc456e5295371b32',
                    1 => 'Mercedes-Benz',
                    2 => 7,
                    3 => 4,
                ),
            '5741f262b5d6909afbe1eb1e8251d0a3' =>
                array (
                    0 => '5741f262b5d6909afbe1eb1e8251d0a3',
                    1 => 'Lincoln',
                    2 => 5,
                    3 => 4,
                ),
            '63d07ef545f38ec9828589e279f1023e' =>
                array (
                    0 => '63d07ef545f38ec9828589e279f1023e',
                    1 => 'Toyota',
                    2 => 14,
                    3 => 4,
                ),
            'ecbea69fcc5e78a767d1c921fd6f2c2c' =>
                array (
                    0 => 'ecbea69fcc5e78a767d1c921fd6f2c2c',
                    1 => 'Dodge',
                    2 => 12,
                    3 => 4,
                ),
            '9f4bfe3f0480f53bae18ab08ec53225c' =>
                array (
                    0 => '9f4bfe3f0480f53bae18ab08ec53225c',
                    1 => 'Oldsmobile',
                    2 => 5,
                    3 => 4,
                ),
            'dd58ef095f3c241adf6ef8175f48b623' =>
                array (
                    0 => 'dd58ef095f3c241adf6ef8175f48b623',
                    1 => 'GMC',
                    2 => 22,
                    3 => 4,
                ),
            'd4de225737b78d21e301f5fb19ec6ce5' =>
                array (
                    0 => 'd4de225737b78d21e301f5fb19ec6ce5',
                    1 => 'Mazda',
                    2 => 3,
                    3 => 4,
                ),
            '5599b866662221ae1563ba654b2ddb53' =>
                array (
                    0 => '5599b866662221ae1563ba654b2ddb53',
                    1 => 'Maybach',
                    2 => 1,
                    3 => 4,
                ),
            'be922633e3fab4e764b48aebd894ffee' =>
                array (
                    0 => 'be922633e3fab4e764b48aebd894ffee',
                    1 => 'Smart',
                    2 => 2,
                    3 => 4,
                ),
            'd2dd74c3ca37c85d263d93b611eb9f5f' =>
                array (
                    0 => 'd2dd74c3ca37c85d263d93b611eb9f5f',
                    1 => 'Volvo',
                    2 => 5,
                    3 => 4,
                ),
            'e5891b0efc99db248f68d0d6f1afa158' =>
                array (
                    0 => 'e5891b0efc99db248f68d0d6f1afa158',
                    1 => 'Chrysler',
                    2 => 3,
                    3 => 4,
                ),
            '7407ec39078219baa5e7ad0cc5d6f301' =>
                array (
                    0 => '7407ec39078219baa5e7ad0cc5d6f301',
                    1 => 'Lexus',
                    2 => 7,
                    3 => 4,
                ),
            'e45ea4ce2780d4995dbe76f5b5506c79' =>
                array (
                    0 => 'e45ea4ce2780d4995dbe76f5b5506c79',
                    1 => 'Mitsubishi',
                    2 => 13,
                    3 => 4,
                ),
            '5ba287ff341b664b229b4e17d98b7fca' =>
                array (
                    0 => '5ba287ff341b664b229b4e17d98b7fca',
                    1 => 'Mitsubishi',
                    2 => 15,
                    3 => 5,
                ),
            '9b396cafdfa0ea0e3f65d2f1a18d0d1a' =>
                array (
                    0 => '9b396cafdfa0ea0e3f65d2f1a18d0d1a',
                    1 => 'Pontiac',
                    2 => 5,
                    3 => 5,
                ),
            '11c1794818d6c45b5292462a8dc0f90a' =>
                array (
                    0 => '11c1794818d6c45b5292462a8dc0f90a',
                    1 => 'Chevrolet',
                    2 => 13,
                    3 => 5,
                ),
            '0a410e7f4d09d5bf154b6118854eafa6' =>
                array (
                    0 => '0a410e7f4d09d5bf154b6118854eafa6',
                    1 => 'Ford',
                    2 => 22,
                    3 => 5,
                ),
            '4f04cb2896255d327129d3eaa7e75118' =>
                array (
                    0 => '4f04cb2896255d327129d3eaa7e75118',
                    1 => 'Austin',
                    2 => 6,
                    3 => 5,
                ),
            '0d6dddc9db175d56cad45959e893d6f2' =>
                array (
                    0 => '0d6dddc9db175d56cad45959e893d6f2',
                    1 => 'Audi',
                    2 => 13,
                    3 => 5,
                ),
            'b11c7540d4652920fa63eb25acbc42ef' =>
                array (
                    0 => 'b11c7540d4652920fa63eb25acbc42ef',
                    1 => 'Maybach',
                    2 => 8,
                    3 => 5,
                ),
            'b0b666bc25245d58bf5bec5b12e77375' =>
                array (
                    0 => 'b0b666bc25245d58bf5bec5b12e77375',
                    1 => 'Chrysler',
                    2 => 10,
                    3 => 5,
                ),
            'f2181ba7e144c5117fb8eb4af2426d06' =>
                array (
                    0 => 'f2181ba7e144c5117fb8eb4af2426d06',
                    1 => 'Oldsmobile',
                    2 => 10,
                    3 => 5,
                ),
            '3a29d90beca7a06c4d7f5a1aaab42a12' =>
                array (
                    0 => '3a29d90beca7a06c4d7f5a1aaab42a12',
                    1 => 'Kia',
                    2 => 3,
                    3 => 5,
                ),
            '4721ea59680e3faed129f5c4e1ce8774' =>
                array (
                    0 => '4721ea59680e3faed129f5c4e1ce8774',
                    1 => 'Volvo',
                    2 => 2,
                    3 => 5,
                ),
            'c103c93f7d8881589bdb0149efcfc9a4' =>
                array (
                    0 => 'c103c93f7d8881589bdb0149efcfc9a4',
                    1 => 'GMC',
                    2 => 11,
                    3 => 5,
                ),
            'a350add7b005bf397c0112426b59326b' =>
                array (
                    0 => 'a350add7b005bf397c0112426b59326b',
                    1 => 'Hyundai',
                    2 => 9,
                    3 => 5,
                ),
            '8860598314f1326f3d88ab66711b69bf' =>
                array (
                    0 => '8860598314f1326f3d88ab66711b69bf',
                    1 => 'Volkswagen',
                    2 => 18,
                    3 => 5,
                ),
            'e3a3d6c159837b8afd3334bb5610dda7' =>
                array (
                    0 => 'e3a3d6c159837b8afd3334bb5610dda7',
                    1 => 'Land Rover',
                    2 => 9,
                    3 => 5,
                ),
            '987613ded073ff63fa8ab858ac7ded1b' =>
                array (
                    0 => '987613ded073ff63fa8ab858ac7ded1b',
                    1 => 'Ferrari',
                    2 => 1,
                    3 => 3,
                ),
            '6a7427457bf99ecdf1138089733c0993' =>
                array (
                    0 => '6a7427457bf99ecdf1138089733c0993',
                    1 => 'Mazda',
                    2 => 8,
                    3 => 3,
                ),
            'f15b6fe411709435f285cf40621cd204' =>
                array (
                    0 => 'f15b6fe411709435f285cf40621cd204',
                    1 => 'Lamborghini',
                    2 => 4,
                    3 => 3,
                ),
            'c9f00e09cf93555fe6abd6c2c18ade4a' =>
                array (
                    0 => 'c9f00e09cf93555fe6abd6c2c18ade4a',
                    1 => 'Maserati',
                    2 => 5,
                    3 => 3,
                ),
            '58e85fa65a826d1837bea08e4344fabf' =>
                array (
                    0 => '58e85fa65a826d1837bea08e4344fabf',
                    1 => 'Buick',
                    2 => 16,
                    3 => 3,
                ),
            '669e7a3298714fbc874fd6674462c0ef' =>
                array (
                    0 => '669e7a3298714fbc874fd6674462c0ef',
                    1 => 'Mitsubishi',
                    2 => 1,
                    3 => 3,
                ),
            'c6dfbc56c9df68987c5000bc468ee33b' =>
                array (
                    0 => 'c6dfbc56c9df68987c5000bc468ee33b',
                    1 => 'Audi',
                    2 => 7,
                    3 => 3,
                ),
            '3b155ff3a9e45dae702eac0a467b2e5e' =>
                array (
                    0 => '3b155ff3a9e45dae702eac0a467b2e5e',
                    1 => 'Jaguar',
                    2 => 9,
                    3 => 3,
                ),
            'd1c0b06a6ddb471a3febb410b51d0c61' =>
                array (
                    0 => 'd1c0b06a6ddb471a3febb410b51d0c61',
                    1 => 'Cadillac',
                    2 => 2,
                    3 => 3,
                ),
            '44c82cd45336ffe4448268573a9141c9' =>
                array (
                    0 => '44c82cd45336ffe4448268573a9141c9',
                    1 => 'Nissan',
                    2 => 19,
                    3 => 4,
                ),
            '48d1bc8cdd3a04495d73e9b42d6b3293' =>
                array (
                    0 => '48d1bc8cdd3a04495d73e9b42d6b3293',
                    1 => 'Chevrolet',
                    2 => 23,
                    3 => 4,
                ),
            'e60f9025d043aa1b2495b51daf9f945a' =>
                array (
                    0 => 'e60f9025d043aa1b2495b51daf9f945a',
                    1 => 'Volkswagen',
                    2 => 15,
                    3 => 4,
                ),
            '330eb1cc86351fa7237057cf0819e9aa' =>
                array (
                    0 => '330eb1cc86351fa7237057cf0819e9aa',
                    1 => 'Saturn',
                    2 => 14,
                    3 => 4,
                ),
            '0af232cbf2ba4e6a871e494583e996ea' =>
                array (
                    0 => '0af232cbf2ba4e6a871e494583e996ea',
                    1 => 'Lotus',
                    2 => 5,
                    3 => 4,
                ),
            '41b1a52173ecb844472064b3c8c213da' =>
                array (
                    0 => '41b1a52173ecb844472064b3c8c213da',
                    1 => 'Mercury',
                    2 => 9,
                    3 => 4,
                ),
            'c77c770182ac9bb97d0b9bfad5f6891c' =>
                array (
                    0 => 'c77c770182ac9bb97d0b9bfad5f6891c',
                    1 => 'Isuzu',
                    2 => 2,
                    3 => 4,
                ),
            '771bff6f30fcecb75431ef9cab4f8e28' =>
                array (
                    0 => '771bff6f30fcecb75431ef9cab4f8e28',
                    1 => 'Mercury',
                    2 => 3,
                    3 => 5,
                ),
            '1d8367db9032659f1791dbdf1a77d7b1' =>
                array (
                    0 => '1d8367db9032659f1791dbdf1a77d7b1',
                    1 => 'Dodge',
                    2 => 5,
                    3 => 5,
                ),
            '79b945479014c079306ca6275cdc2a59' =>
                array (
                    0 => '79b945479014c079306ca6275cdc2a59',
                    1 => 'Porsche',
                    2 => 2,
                    3 => 5,
                ),
            'fbccb3b8d2a1f7859bcb2181ff57239b' =>
                array (
                    0 => 'fbccb3b8d2a1f7859bcb2181ff57239b',
                    1 => 'Mercedes-Benz',
                    2 => 12,
                    3 => 5,
                ),
            'd6369fd9fd81dba6c3055156533f4eba' =>
                array (
                    0 => 'd6369fd9fd81dba6c3055156533f4eba',
                    1 => 'Toyota',
                    2 => 1,
                    3 => 5,
                ),
        );
    }

    protected function getExpectedResults2(): array {
        return array (
            'caa72bd10e0134a66debc5507d5ba755' =>
                array (
                    0 => 'caa72bd10e0134a66debc5507d5ba755',
                    1 => 'Lexus',
                    2 =>
                        array (
                            'sum' => 3,
                            'min' => 3,
                            'max' => 3,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            '7d46aa45223f859ac38089b793faf700' =>
                array (
                    0 => '7d46aa45223f859ac38089b793faf700',
                    1 => 'Porsche',
                    2 =>
                        array (
                            'sum' => 10,
                            'min' => 10,
                            'max' => 10,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            '01104475e13e824550c23c553e4080a3' =>
                array (
                    0 => '01104475e13e824550c23c553e4080a3',
                    1 => 'Mercedes-Benz',
                    2 =>
                        array (
                            'sum' => 6,
                            'min' => 2,
                            'max' => 4,
                            'cnt' => 2,
                        ),
                    3 => 3,
                ),
            '8543e94b62e2f51eb5a9ab727bc9814e' =>
                array (
                    0 => '8543e94b62e2f51eb5a9ab727bc9814e',
                    1 => 'Chevrolet',
                    2 =>
                        array (
                            'sum' => 32,
                            'min' => 5,
                            'max' => 8,
                            'cnt' => 5,
                        ),
                    3 => 3,
                ),
            '887fb0407cabea6ee228a30fd0e41421' =>
                array (
                    0 => '887fb0407cabea6ee228a30fd0e41421',
                    1 => 'Nissan',
                    2 =>
                        array (
                            'sum' => 12,
                            'min' => 5,
                            'max' => 7,
                            'cnt' => 2,
                        ),
                    3 => 3,
                ),
            '6e415fbf548698688e8caa552b641cb0' =>
                array (
                    0 => '6e415fbf548698688e8caa552b641cb0',
                    1 => 'Studebaker',
                    2 =>
                        array (
                            'sum' => 1,
                            'min' => 1,
                            'max' => 1,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            '28e53a0c4dacec8b85707fd8dae33d33' =>
                array (
                    0 => '28e53a0c4dacec8b85707fd8dae33d33',
                    1 => 'Ford',
                    2 =>
                        array (
                            'sum' => 10,
                            'min' => 2,
                            'max' => 8,
                            'cnt' => 2,
                        ),
                    3 => 3,
                ),
            'f155329d2d1a1c5ba75be0223f55c51d' =>
                array (
                    0 => 'f155329d2d1a1c5ba75be0223f55c51d',
                    1 => 'Dodge',
                    2 =>
                        array (
                            'sum' => 3,
                            'min' => 3,
                            'max' => 3,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            'b9540914288caf85017c5ef84f0475f3' =>
                array (
                    0 => 'b9540914288caf85017c5ef84f0475f3',
                    1 => 'BMW',
                    2 =>
                        array (
                            'sum' => 19,
                            'min' => 5,
                            'max' => 7,
                            'cnt' => 3,
                        ),
                    3 => 3,
                ),
            '9c1109a1edbfa3f032b3a90b91f81509' =>
                array (
                    0 => '9c1109a1edbfa3f032b3a90b91f81509',
                    1 => 'Saturn',
                    2 =>
                        array (
                            'sum' => 8,
                            'min' => 8,
                            'max' => 8,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            'a4ceddab5184e95c71147d4d6f5ec5f6' =>
                array (
                    0 => 'a4ceddab5184e95c71147d4d6f5ec5f6',
                    1 => 'Oldsmobile',
                    2 =>
                        array (
                            'sum' => 5,
                            'min' => 5,
                            'max' => 5,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            'f86cc6501388912a5731aefaf18967f8' =>
                array (
                    0 => 'f86cc6501388912a5731aefaf18967f8',
                    1 => 'Pontiac',
                    2 =>
                        array (
                            'sum' => 5,
                            'min' => 5,
                            'max' => 5,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            '1f5fb789d741bd31a22b94449da811da' =>
                array (
                    0 => '1f5fb789d741bd31a22b94449da811da',
                    1 => 'Subaru',
                    2 =>
                        array (
                            'sum' => 5,
                            'min' => 5,
                            'max' => 5,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            '3ab4e6b4907d88c68b4e593a82105082' =>
                array (
                    0 => '3ab4e6b4907d88c68b4e593a82105082',
                    1 => 'GMC',
                    2 =>
                        array (
                            'sum' => 9,
                            'min' => 9,
                            'max' => 9,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            '62ce70a0e4a93afe338eaf3318865e24' =>
                array (
                    0 => '62ce70a0e4a93afe338eaf3318865e24',
                    1 => 'Ford',
                    2 =>
                        array (
                            'sum' => 19,
                            'min' => 1,
                            'max' => 5,
                            'cnt' => 7,
                        ),
                    3 => 4,
                ),
            'ed45832d8630db7699f0d2017bbf94f7' =>
                array (
                    0 => 'ed45832d8630db7699f0d2017bbf94f7',
                    1 => 'Pontiac',
                    2 =>
                        array (
                            'sum' => 20,
                            'min' => 2,
                            'max' => 10,
                            'cnt' => 3,
                        ),
                    3 => 4,
                ),
            '7aa23956c77f7679de5bd9eedb79a92f' =>
                array (
                    0 => '7aa23956c77f7679de5bd9eedb79a92f',
                    1 => 'BMW',
                    2 =>
                        array (
                            'sum' => 3,
                            'min' => 3,
                            'max' => 3,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            'ed9f49135d1f3f30e3910aeefd39d713' =>
                array (
                    0 => 'ed9f49135d1f3f30e3910aeefd39d713',
                    1 => 'Honda',
                    2 =>
                        array (
                            'sum' => 21,
                            'min' => 4,
                            'max' => 7,
                            'cnt' => 4,
                        ),
                    3 => 4,
                ),
            'f157b649993d71954ffc31c1b4fc2e76' =>
                array (
                    0 => 'f157b649993d71954ffc31c1b4fc2e76',
                    1 => 'Buick',
                    2 =>
                        array (
                            'sum' => 9,
                            'min' => 4,
                            'max' => 5,
                            'cnt' => 2,
                        ),
                    3 => 4,
                ),
            'ff73cce19a5d02c8fc456e5295371b32' =>
                array (
                    0 => 'ff73cce19a5d02c8fc456e5295371b32',
                    1 => 'Mercedes-Benz',
                    2 =>
                        array (
                            'sum' => 7,
                            'min' => 2,
                            'max' => 5,
                            'cnt' => 2,
                        ),
                    3 => 4,
                ),
            '5741f262b5d6909afbe1eb1e8251d0a3' =>
                array (
                    0 => '5741f262b5d6909afbe1eb1e8251d0a3',
                    1 => 'Lincoln',
                    2 =>
                        array (
                            'sum' => 5,
                            'min' => 5,
                            'max' => 5,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            '63d07ef545f38ec9828589e279f1023e' =>
                array (
                    0 => '63d07ef545f38ec9828589e279f1023e',
                    1 => 'Toyota',
                    2 =>
                        array (
                            'sum' => 14,
                            'min' => 1,
                            'max' => 7,
                            'cnt' => 4,
                        ),
                    3 => 4,
                ),
            'ecbea69fcc5e78a767d1c921fd6f2c2c' =>
                array (
                    0 => 'ecbea69fcc5e78a767d1c921fd6f2c2c',
                    1 => 'Dodge',
                    2 =>
                        array (
                            'sum' => 12,
                            'min' => 5,
                            'max' => 7,
                            'cnt' => 2,
                        ),
                    3 => 4,
                ),
            '9f4bfe3f0480f53bae18ab08ec53225c' =>
                array (
                    0 => '9f4bfe3f0480f53bae18ab08ec53225c',
                    1 => 'Oldsmobile',
                    2 =>
                        array (
                            'sum' => 5,
                            'min' => 5,
                            'max' => 5,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            'dd58ef095f3c241adf6ef8175f48b623' =>
                array (
                    0 => 'dd58ef095f3c241adf6ef8175f48b623',
                    1 => 'GMC',
                    2 =>
                        array (
                            'sum' => 22,
                            'min' => 4,
                            'max' => 9,
                            'cnt' => 3,
                        ),
                    3 => 4,
                ),
            'd4de225737b78d21e301f5fb19ec6ce5' =>
                array (
                    0 => 'd4de225737b78d21e301f5fb19ec6ce5',
                    1 => 'Mazda',
                    2 =>
                        array (
                            'sum' => 3,
                            'min' => 3,
                            'max' => 3,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            '5599b866662221ae1563ba654b2ddb53' =>
                array (
                    0 => '5599b866662221ae1563ba654b2ddb53',
                    1 => 'Maybach',
                    2 =>
                        array (
                            'sum' => 1,
                            'min' => 1,
                            'max' => 1,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            'be922633e3fab4e764b48aebd894ffee' =>
                array (
                    0 => 'be922633e3fab4e764b48aebd894ffee',
                    1 => 'Smart',
                    2 =>
                        array (
                            'sum' => 2,
                            'min' => 2,
                            'max' => 2,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            'd2dd74c3ca37c85d263d93b611eb9f5f' =>
                array (
                    0 => 'd2dd74c3ca37c85d263d93b611eb9f5f',
                    1 => 'Volvo',
                    2 =>
                        array (
                            'sum' => 5,
                            'min' => 2,
                            'max' => 3,
                            'cnt' => 2,
                        ),
                    3 => 4,
                ),
            'e5891b0efc99db248f68d0d6f1afa158' =>
                array (
                    0 => 'e5891b0efc99db248f68d0d6f1afa158',
                    1 => 'Chrysler',
                    2 =>
                        array (
                            'sum' => 3,
                            'min' => 3,
                            'max' => 3,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            '7407ec39078219baa5e7ad0cc5d6f301' =>
                array (
                    0 => '7407ec39078219baa5e7ad0cc5d6f301',
                    1 => 'Lexus',
                    2 =>
                        array (
                            'sum' => 7,
                            'min' => 7,
                            'max' => 7,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            'e45ea4ce2780d4995dbe76f5b5506c79' =>
                array (
                    0 => 'e45ea4ce2780d4995dbe76f5b5506c79',
                    1 => 'Mitsubishi',
                    2 =>
                        array (
                            'sum' => 13,
                            'min' => 5,
                            'max' => 8,
                            'cnt' => 2,
                        ),
                    3 => 4,
                ),
            '5ba287ff341b664b229b4e17d98b7fca' =>
                array (
                    0 => '5ba287ff341b664b229b4e17d98b7fca',
                    1 => 'Mitsubishi',
                    2 =>
                        array (
                            'sum' => 15,
                            'min' => 1,
                            'max' => 8,
                            'cnt' => 4,
                        ),
                    3 => 5,
                ),
            '9b396cafdfa0ea0e3f65d2f1a18d0d1a' =>
                array (
                    0 => '9b396cafdfa0ea0e3f65d2f1a18d0d1a',
                    1 => 'Pontiac',
                    2 =>
                        array (
                            'sum' => 5,
                            'min' => 5,
                            'max' => 5,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
            '11c1794818d6c45b5292462a8dc0f90a' =>
                array (
                    0 => '11c1794818d6c45b5292462a8dc0f90a',
                    1 => 'Chevrolet',
                    2 =>
                        array (
                            'sum' => 13,
                            'min' => 3,
                            'max' => 6,
                            'cnt' => 3,
                        ),
                    3 => 5,
                ),
            '0a410e7f4d09d5bf154b6118854eafa6' =>
                array (
                    0 => '0a410e7f4d09d5bf154b6118854eafa6',
                    1 => 'Ford',
                    2 =>
                        array (
                            'sum' => 22,
                            'min' => 4,
                            'max' => 9,
                            'cnt' => 4,
                        ),
                    3 => 5,
                ),
            '4f04cb2896255d327129d3eaa7e75118' =>
                array (
                    0 => '4f04cb2896255d327129d3eaa7e75118',
                    1 => 'Austin',
                    2 =>
                        array (
                            'sum' => 6,
                            'min' => 6,
                            'max' => 6,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
            '0d6dddc9db175d56cad45959e893d6f2' =>
                array (
                    0 => '0d6dddc9db175d56cad45959e893d6f2',
                    1 => 'Audi',
                    2 =>
                        array (
                            'sum' => 13,
                            'min' => 6,
                            'max' => 7,
                            'cnt' => 2,
                        ),
                    3 => 5,
                ),
            'b11c7540d4652920fa63eb25acbc42ef' =>
                array (
                    0 => 'b11c7540d4652920fa63eb25acbc42ef',
                    1 => 'Maybach',
                    2 =>
                        array (
                            'sum' => 8,
                            'min' => 8,
                            'max' => 8,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
            'b0b666bc25245d58bf5bec5b12e77375' =>
                array (
                    0 => 'b0b666bc25245d58bf5bec5b12e77375',
                    1 => 'Chrysler',
                    2 =>
                        array (
                            'sum' => 10,
                            'min' => 10,
                            'max' => 10,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
            'f2181ba7e144c5117fb8eb4af2426d06' =>
                array (
                    0 => 'f2181ba7e144c5117fb8eb4af2426d06',
                    1 => 'Oldsmobile',
                    2 =>
                        array (
                            'sum' => 10,
                            'min' => 10,
                            'max' => 10,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
            '3a29d90beca7a06c4d7f5a1aaab42a12' =>
                array (
                    0 => '3a29d90beca7a06c4d7f5a1aaab42a12',
                    1 => 'Kia',
                    2 =>
                        array (
                            'sum' => 3,
                            'min' => 1,
                            'max' => 2,
                            'cnt' => 2,
                        ),
                    3 => 5,
                ),
            '4721ea59680e3faed129f5c4e1ce8774' =>
                array (
                    0 => '4721ea59680e3faed129f5c4e1ce8774',
                    1 => 'Volvo',
                    2 =>
                        array (
                            'sum' => 2,
                            'min' => 2,
                            'max' => 2,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
            'c103c93f7d8881589bdb0149efcfc9a4' =>
                array (
                    0 => 'c103c93f7d8881589bdb0149efcfc9a4',
                    1 => 'GMC',
                    2 =>
                        array (
                            'sum' => 11,
                            'min' => 1,
                            'max' => 5,
                            'cnt' => 3,
                        ),
                    3 => 5,
                ),
            'a350add7b005bf397c0112426b59326b' =>
                array (
                    0 => 'a350add7b005bf397c0112426b59326b',
                    1 => 'Hyundai',
                    2 =>
                        array (
                            'sum' => 9,
                            'min' => 9,
                            'max' => 9,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
            '8860598314f1326f3d88ab66711b69bf' =>
                array (
                    0 => '8860598314f1326f3d88ab66711b69bf',
                    1 => 'Volkswagen',
                    2 =>
                        array (
                            'sum' => 18,
                            'min' => 8,
                            'max' => 10,
                            'cnt' => 2,
                        ),
                    3 => 5,
                ),
            'e3a3d6c159837b8afd3334bb5610dda7' =>
                array (
                    0 => 'e3a3d6c159837b8afd3334bb5610dda7',
                    1 => 'Land Rover',
                    2 =>
                        array (
                            'sum' => 9,
                            'min' => 9,
                            'max' => 9,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
            '987613ded073ff63fa8ab858ac7ded1b' =>
                array (
                    0 => '987613ded073ff63fa8ab858ac7ded1b',
                    1 => 'Ferrari',
                    2 =>
                        array (
                            'sum' => 1,
                            'min' => 1,
                            'max' => 1,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            '6a7427457bf99ecdf1138089733c0993' =>
                array (
                    0 => '6a7427457bf99ecdf1138089733c0993',
                    1 => 'Mazda',
                    2 =>
                        array (
                            'sum' => 8,
                            'min' => 8,
                            'max' => 8,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            'f15b6fe411709435f285cf40621cd204' =>
                array (
                    0 => 'f15b6fe411709435f285cf40621cd204',
                    1 => 'Lamborghini',
                    2 =>
                        array (
                            'sum' => 4,
                            'min' => 4,
                            'max' => 4,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            'c9f00e09cf93555fe6abd6c2c18ade4a' =>
                array (
                    0 => 'c9f00e09cf93555fe6abd6c2c18ade4a',
                    1 => 'Maserati',
                    2 =>
                        array (
                            'sum' => 5,
                            'min' => 5,
                            'max' => 5,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            '58e85fa65a826d1837bea08e4344fabf' =>
                array (
                    0 => '58e85fa65a826d1837bea08e4344fabf',
                    1 => 'Buick',
                    2 =>
                        array (
                            'sum' => 16,
                            'min' => 6,
                            'max' => 10,
                            'cnt' => 2,
                        ),
                    3 => 3,
                ),
            '669e7a3298714fbc874fd6674462c0ef' =>
                array (
                    0 => '669e7a3298714fbc874fd6674462c0ef',
                    1 => 'Mitsubishi',
                    2 =>
                        array (
                            'sum' => 1,
                            'min' => 1,
                            'max' => 1,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            'c6dfbc56c9df68987c5000bc468ee33b' =>
                array (
                    0 => 'c6dfbc56c9df68987c5000bc468ee33b',
                    1 => 'Audi',
                    2 =>
                        array (
                            'sum' => 7,
                            'min' => 7,
                            'max' => 7,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            '3b155ff3a9e45dae702eac0a467b2e5e' =>
                array (
                    0 => '3b155ff3a9e45dae702eac0a467b2e5e',
                    1 => 'Jaguar',
                    2 =>
                        array (
                            'sum' => 9,
                            'min' => 9,
                            'max' => 9,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            'd1c0b06a6ddb471a3febb410b51d0c61' =>
                array (
                    0 => 'd1c0b06a6ddb471a3febb410b51d0c61',
                    1 => 'Cadillac',
                    2 =>
                        array (
                            'sum' => 2,
                            'min' => 2,
                            'max' => 2,
                            'cnt' => 1,
                        ),
                    3 => 3,
                ),
            '44c82cd45336ffe4448268573a9141c9' =>
                array (
                    0 => '44c82cd45336ffe4448268573a9141c9',
                    1 => 'Nissan',
                    2 =>
                        array (
                            'sum' => 19,
                            'min' => 3,
                            'max' => 8,
                            'cnt' => 4,
                        ),
                    3 => 4,
                ),
            '48d1bc8cdd3a04495d73e9b42d6b3293' =>
                array (
                    0 => '48d1bc8cdd3a04495d73e9b42d6b3293',
                    1 => 'Chevrolet',
                    2 =>
                        array (
                            'sum' => 23,
                            'min' => 4,
                            'max' => 10,
                            'cnt' => 3,
                        ),
                    3 => 4,
                ),
            'e60f9025d043aa1b2495b51daf9f945a' =>
                array (
                    0 => 'e60f9025d043aa1b2495b51daf9f945a',
                    1 => 'Volkswagen',
                    2 =>
                        array (
                            'sum' => 15,
                            'min' => 3,
                            'max' => 7,
                            'cnt' => 3,
                        ),
                    3 => 4,
                ),
            '330eb1cc86351fa7237057cf0819e9aa' =>
                array (
                    0 => '330eb1cc86351fa7237057cf0819e9aa',
                    1 => 'Saturn',
                    2 =>
                        array (
                            'sum' => 14,
                            'min' => 6,
                            'max' => 8,
                            'cnt' => 2,
                        ),
                    3 => 4,
                ),
            '0af232cbf2ba4e6a871e494583e996ea' =>
                array (
                    0 => '0af232cbf2ba4e6a871e494583e996ea',
                    1 => 'Lotus',
                    2 =>
                        array (
                            'sum' => 5,
                            'min' => 5,
                            'max' => 5,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            '41b1a52173ecb844472064b3c8c213da' =>
                array (
                    0 => '41b1a52173ecb844472064b3c8c213da',
                    1 => 'Mercury',
                    2 =>
                        array (
                            'sum' => 9,
                            'min' => 9,
                            'max' => 9,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            'c77c770182ac9bb97d0b9bfad5f6891c' =>
                array (
                    0 => 'c77c770182ac9bb97d0b9bfad5f6891c',
                    1 => 'Isuzu',
                    2 =>
                        array (
                            'sum' => 2,
                            'min' => 2,
                            'max' => 2,
                            'cnt' => 1,
                        ),
                    3 => 4,
                ),
            '771bff6f30fcecb75431ef9cab4f8e28' =>
                array (
                    0 => '771bff6f30fcecb75431ef9cab4f8e28',
                    1 => 'Mercury',
                    2 =>
                        array (
                            'sum' => 3,
                            'min' => 3,
                            'max' => 3,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
            '1d8367db9032659f1791dbdf1a77d7b1' =>
                array (
                    0 => '1d8367db9032659f1791dbdf1a77d7b1',
                    1 => 'Dodge',
                    2 =>
                        array (
                            'sum' => 5,
                            'min' => 1,
                            'max' => 4,
                            'cnt' => 2,
                        ),
                    3 => 5,
                ),
            '79b945479014c079306ca6275cdc2a59' =>
                array (
                    0 => '79b945479014c079306ca6275cdc2a59',
                    1 => 'Porsche',
                    2 =>
                        array (
                            'sum' => 2,
                            'min' => 2,
                            'max' => 2,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
            'fbccb3b8d2a1f7859bcb2181ff57239b' =>
                array (
                    0 => 'fbccb3b8d2a1f7859bcb2181ff57239b',
                    1 => 'Mercedes-Benz',
                    2 =>
                        array (
                            'sum' => 12,
                            'min' => 3,
                            'max' => 9,
                            'cnt' => 2,
                        ),
                    3 => 5,
                ),
            'd6369fd9fd81dba6c3055156533f4eba' =>
                array (
                    0 => 'd6369fd9fd81dba6c3055156533f4eba',
                    1 => 'Toyota',
                    2 =>
                        array (
                            'sum' => 1,
                            'min' => 1,
                            'max' => 1,
                            'cnt' => 1,
                        ),
                    3 => 5,
                ),
        );
    }

    protected function getExpectedResults3(): array {
        return array (
            'caa72bd10e0134a66debc5507d5ba755' =>
                array (
                    0 => 'caa72bd10e0134a66debc5507d5ba755',
                    1 => 'Lexus',
                    2 => 3,
                    3 => 3,
                ),
            '7d46aa45223f859ac38089b793faf700' =>
                array (
                    0 => '7d46aa45223f859ac38089b793faf700',
                    1 => 'Porsche',
                    2 => 10,
                    3 => 3,
                ),
            '01104475e13e824550c23c553e4080a3' =>
                array (
                    0 => '01104475e13e824550c23c553e4080a3',
                    1 => 'Mercedes-Benz',
                    2 => 2,
                    3 => 3,
                ),
            '8543e94b62e2f51eb5a9ab727bc9814e' =>
                array (
                    0 => '8543e94b62e2f51eb5a9ab727bc9814e',
                    1 => 'Chevrolet',
                    2 => 6,
                    3 => 3,
                ),
            '887fb0407cabea6ee228a30fd0e41421' =>
                array (
                    0 => '887fb0407cabea6ee228a30fd0e41421',
                    1 => 'Nissan',
                    2 => 5,
                    3 => 3,
                ),
            '6e415fbf548698688e8caa552b641cb0' =>
                array (
                    0 => '6e415fbf548698688e8caa552b641cb0',
                    1 => 'Studebaker',
                    2 => 1,
                    3 => 3,
                ),
            '28e53a0c4dacec8b85707fd8dae33d33' =>
                array (
                    0 => '28e53a0c4dacec8b85707fd8dae33d33',
                    1 => 'Ford',
                    2 => 8,
                    3 => 3,
                ),
            'f155329d2d1a1c5ba75be0223f55c51d' =>
                array (
                    0 => 'f155329d2d1a1c5ba75be0223f55c51d',
                    1 => 'Dodge',
                    2 => 3,
                    3 => 3,
                ),
            'b9540914288caf85017c5ef84f0475f3' =>
                array (
                    0 => 'b9540914288caf85017c5ef84f0475f3',
                    1 => 'BMW',
                    2 => 7,
                    3 => 3,
                ),
            '9c1109a1edbfa3f032b3a90b91f81509' =>
                array (
                    0 => '9c1109a1edbfa3f032b3a90b91f81509',
                    1 => 'Saturn',
                    2 => 8,
                    3 => 3,
                ),
            'a4ceddab5184e95c71147d4d6f5ec5f6' =>
                array (
                    0 => 'a4ceddab5184e95c71147d4d6f5ec5f6',
                    1 => 'Oldsmobile',
                    2 => 5,
                    3 => 3,
                ),
            'f86cc6501388912a5731aefaf18967f8' =>
                array (
                    0 => 'f86cc6501388912a5731aefaf18967f8',
                    1 => 'Pontiac',
                    2 => 5,
                    3 => 3,
                ),
            '1f5fb789d741bd31a22b94449da811da' =>
                array (
                    0 => '1f5fb789d741bd31a22b94449da811da',
                    1 => 'Subaru',
                    2 => 5,
                    3 => 3,
                ),
            '3ab4e6b4907d88c68b4e593a82105082' =>
                array (
                    0 => '3ab4e6b4907d88c68b4e593a82105082',
                    1 => 'GMC',
                    2 => 9,
                    3 => 3,
                ),
            '62ce70a0e4a93afe338eaf3318865e24' =>
                array (
                    0 => '62ce70a0e4a93afe338eaf3318865e24',
                    1 => 'Ford',
                    2 => 4,
                    3 => 4,
                ),
            'ed45832d8630db7699f0d2017bbf94f7' =>
                array (
                    0 => 'ed45832d8630db7699f0d2017bbf94f7',
                    1 => 'Pontiac',
                    2 => 8,
                    3 => 4,
                ),
            '7aa23956c77f7679de5bd9eedb79a92f' =>
                array (
                    0 => '7aa23956c77f7679de5bd9eedb79a92f',
                    1 => 'BMW',
                    2 => 3,
                    3 => 4,
                ),
            'ed9f49135d1f3f30e3910aeefd39d713' =>
                array (
                    0 => 'ed9f49135d1f3f30e3910aeefd39d713',
                    1 => 'Honda',
                    2 => 7,
                    3 => 4,
                ),
            'f157b649993d71954ffc31c1b4fc2e76' =>
                array (
                    0 => 'f157b649993d71954ffc31c1b4fc2e76',
                    1 => 'Buick',
                    2 => 4,
                    3 => 4,
                ),
            'ff73cce19a5d02c8fc456e5295371b32' =>
                array (
                    0 => 'ff73cce19a5d02c8fc456e5295371b32',
                    1 => 'Mercedes-Benz',
                    2 => 2,
                    3 => 4,
                ),
            '5741f262b5d6909afbe1eb1e8251d0a3' =>
                array (
                    0 => '5741f262b5d6909afbe1eb1e8251d0a3',
                    1 => 'Lincoln',
                    2 => 5,
                    3 => 4,
                ),
            '63d07ef545f38ec9828589e279f1023e' =>
                array (
                    0 => '63d07ef545f38ec9828589e279f1023e',
                    1 => 'Toyota',
                    2 => 1,
                    3 => 4,
                ),
            'ecbea69fcc5e78a767d1c921fd6f2c2c' =>
                array (
                    0 => 'ecbea69fcc5e78a767d1c921fd6f2c2c',
                    1 => 'Dodge',
                    2 => 7,
                    3 => 4,
                ),
            '9f4bfe3f0480f53bae18ab08ec53225c' =>
                array (
                    0 => '9f4bfe3f0480f53bae18ab08ec53225c',
                    1 => 'Oldsmobile',
                    2 => 5,
                    3 => 4,
                ),
            'dd58ef095f3c241adf6ef8175f48b623' =>
                array (
                    0 => 'dd58ef095f3c241adf6ef8175f48b623',
                    1 => 'GMC',
                    2 => 9,
                    3 => 4,
                ),
            'd4de225737b78d21e301f5fb19ec6ce5' =>
                array (
                    0 => 'd4de225737b78d21e301f5fb19ec6ce5',
                    1 => 'Mazda',
                    2 => 3,
                    3 => 4,
                ),
            '5599b866662221ae1563ba654b2ddb53' =>
                array (
                    0 => '5599b866662221ae1563ba654b2ddb53',
                    1 => 'Maybach',
                    2 => 1,
                    3 => 4,
                ),
            'be922633e3fab4e764b48aebd894ffee' =>
                array (
                    0 => 'be922633e3fab4e764b48aebd894ffee',
                    1 => 'Smart',
                    2 => 2,
                    3 => 4,
                ),
            'd2dd74c3ca37c85d263d93b611eb9f5f' =>
                array (
                    0 => 'd2dd74c3ca37c85d263d93b611eb9f5f',
                    1 => 'Volvo',
                    2 => 3,
                    3 => 4,
                ),
            'e5891b0efc99db248f68d0d6f1afa158' =>
                array (
                    0 => 'e5891b0efc99db248f68d0d6f1afa158',
                    1 => 'Chrysler',
                    2 => 3,
                    3 => 4,
                ),
            '7407ec39078219baa5e7ad0cc5d6f301' =>
                array (
                    0 => '7407ec39078219baa5e7ad0cc5d6f301',
                    1 => 'Lexus',
                    2 => 7,
                    3 => 4,
                ),
            'e45ea4ce2780d4995dbe76f5b5506c79' =>
                array (
                    0 => 'e45ea4ce2780d4995dbe76f5b5506c79',
                    1 => 'Mitsubishi',
                    2 => 5,
                    3 => 4,
                ),
            '5ba287ff341b664b229b4e17d98b7fca' =>
                array (
                    0 => '5ba287ff341b664b229b4e17d98b7fca',
                    1 => 'Mitsubishi',
                    2 => 3,
                    3 => 5,
                ),
            '9b396cafdfa0ea0e3f65d2f1a18d0d1a' =>
                array (
                    0 => '9b396cafdfa0ea0e3f65d2f1a18d0d1a',
                    1 => 'Pontiac',
                    2 => 5,
                    3 => 5,
                ),
            '11c1794818d6c45b5292462a8dc0f90a' =>
                array (
                    0 => '11c1794818d6c45b5292462a8dc0f90a',
                    1 => 'Chevrolet',
                    2 => 6,
                    3 => 5,
                ),
            '0a410e7f4d09d5bf154b6118854eafa6' =>
                array (
                    0 => '0a410e7f4d09d5bf154b6118854eafa6',
                    1 => 'Ford',
                    2 => 4,
                    3 => 5,
                ),
            '4f04cb2896255d327129d3eaa7e75118' =>
                array (
                    0 => '4f04cb2896255d327129d3eaa7e75118',
                    1 => 'Austin',
                    2 => 6,
                    3 => 5,
                ),
            '0d6dddc9db175d56cad45959e893d6f2' =>
                array (
                    0 => '0d6dddc9db175d56cad45959e893d6f2',
                    1 => 'Audi',
                    2 => 7,
                    3 => 5,
                ),
            'b11c7540d4652920fa63eb25acbc42ef' =>
                array (
                    0 => 'b11c7540d4652920fa63eb25acbc42ef',
                    1 => 'Maybach',
                    2 => 8,
                    3 => 5,
                ),
            'b0b666bc25245d58bf5bec5b12e77375' =>
                array (
                    0 => 'b0b666bc25245d58bf5bec5b12e77375',
                    1 => 'Chrysler',
                    2 => 10,
                    3 => 5,
                ),
            'f2181ba7e144c5117fb8eb4af2426d06' =>
                array (
                    0 => 'f2181ba7e144c5117fb8eb4af2426d06',
                    1 => 'Oldsmobile',
                    2 => 10,
                    3 => 5,
                ),
            '3a29d90beca7a06c4d7f5a1aaab42a12' =>
                array (
                    0 => '3a29d90beca7a06c4d7f5a1aaab42a12',
                    1 => 'Kia',
                    2 => 2,
                    3 => 5,
                ),
            '4721ea59680e3faed129f5c4e1ce8774' =>
                array (
                    0 => '4721ea59680e3faed129f5c4e1ce8774',
                    1 => 'Volvo',
                    2 => 2,
                    3 => 5,
                ),
            'c103c93f7d8881589bdb0149efcfc9a4' =>
                array (
                    0 => 'c103c93f7d8881589bdb0149efcfc9a4',
                    1 => 'GMC',
                    2 => 1,
                    3 => 5,
                ),
            'a350add7b005bf397c0112426b59326b' =>
                array (
                    0 => 'a350add7b005bf397c0112426b59326b',
                    1 => 'Hyundai',
                    2 => 9,
                    3 => 5,
                ),
            '8860598314f1326f3d88ab66711b69bf' =>
                array (
                    0 => '8860598314f1326f3d88ab66711b69bf',
                    1 => 'Volkswagen',
                    2 => 10,
                    3 => 5,
                ),
            'e3a3d6c159837b8afd3334bb5610dda7' =>
                array (
                    0 => 'e3a3d6c159837b8afd3334bb5610dda7',
                    1 => 'Land Rover',
                    2 => 9,
                    3 => 5,
                ),
            '987613ded073ff63fa8ab858ac7ded1b' =>
                array (
                    0 => '987613ded073ff63fa8ab858ac7ded1b',
                    1 => 'Ferrari',
                    2 => 1,
                    3 => 3,
                ),
            '6a7427457bf99ecdf1138089733c0993' =>
                array (
                    0 => '6a7427457bf99ecdf1138089733c0993',
                    1 => 'Mazda',
                    2 => 8,
                    3 => 3,
                ),
            'f15b6fe411709435f285cf40621cd204' =>
                array (
                    0 => 'f15b6fe411709435f285cf40621cd204',
                    1 => 'Lamborghini',
                    2 => 4,
                    3 => 3,
                ),
            'c9f00e09cf93555fe6abd6c2c18ade4a' =>
                array (
                    0 => 'c9f00e09cf93555fe6abd6c2c18ade4a',
                    1 => 'Maserati',
                    2 => 5,
                    3 => 3,
                ),
            '58e85fa65a826d1837bea08e4344fabf' =>
                array (
                    0 => '58e85fa65a826d1837bea08e4344fabf',
                    1 => 'Buick',
                    2 => 6,
                    3 => 3,
                ),
            '669e7a3298714fbc874fd6674462c0ef' =>
                array (
                    0 => '669e7a3298714fbc874fd6674462c0ef',
                    1 => 'Mitsubishi',
                    2 => 1,
                    3 => 3,
                ),
            'c6dfbc56c9df68987c5000bc468ee33b' =>
                array (
                    0 => 'c6dfbc56c9df68987c5000bc468ee33b',
                    1 => 'Audi',
                    2 => 7,
                    3 => 3,
                ),
            '3b155ff3a9e45dae702eac0a467b2e5e' =>
                array (
                    0 => '3b155ff3a9e45dae702eac0a467b2e5e',
                    1 => 'Jaguar',
                    2 => 9,
                    3 => 3,
                ),
            'd1c0b06a6ddb471a3febb410b51d0c61' =>
                array (
                    0 => 'd1c0b06a6ddb471a3febb410b51d0c61',
                    1 => 'Cadillac',
                    2 => 2,
                    3 => 3,
                ),
            '44c82cd45336ffe4448268573a9141c9' =>
                array (
                    0 => '44c82cd45336ffe4448268573a9141c9',
                    1 => 'Nissan',
                    2 => 8,
                    3 => 4,
                ),
            '48d1bc8cdd3a04495d73e9b42d6b3293' =>
                array (
                    0 => '48d1bc8cdd3a04495d73e9b42d6b3293',
                    1 => 'Chevrolet',
                    2 => 10,
                    3 => 4,
                ),
            'e60f9025d043aa1b2495b51daf9f945a' =>
                array (
                    0 => 'e60f9025d043aa1b2495b51daf9f945a',
                    1 => 'Volkswagen',
                    2 => 5,
                    3 => 4,
                ),
            '330eb1cc86351fa7237057cf0819e9aa' =>
                array (
                    0 => '330eb1cc86351fa7237057cf0819e9aa',
                    1 => 'Saturn',
                    2 => 6,
                    3 => 4,
                ),
            '0af232cbf2ba4e6a871e494583e996ea' =>
                array (
                    0 => '0af232cbf2ba4e6a871e494583e996ea',
                    1 => 'Lotus',
                    2 => 5,
                    3 => 4,
                ),
            '41b1a52173ecb844472064b3c8c213da' =>
                array (
                    0 => '41b1a52173ecb844472064b3c8c213da',
                    1 => 'Mercury',
                    2 => 9,
                    3 => 4,
                ),
            'c77c770182ac9bb97d0b9bfad5f6891c' =>
                array (
                    0 => 'c77c770182ac9bb97d0b9bfad5f6891c',
                    1 => 'Isuzu',
                    2 => 2,
                    3 => 4,
                ),
            '771bff6f30fcecb75431ef9cab4f8e28' =>
                array (
                    0 => '771bff6f30fcecb75431ef9cab4f8e28',
                    1 => 'Mercury',
                    2 => 3,
                    3 => 5,
                ),
            '1d8367db9032659f1791dbdf1a77d7b1' =>
                array (
                    0 => '1d8367db9032659f1791dbdf1a77d7b1',
                    1 => 'Dodge',
                    2 => 4,
                    3 => 5,
                ),
            '79b945479014c079306ca6275cdc2a59' =>
                array (
                    0 => '79b945479014c079306ca6275cdc2a59',
                    1 => 'Porsche',
                    2 => 2,
                    3 => 5,
                ),
            'fbccb3b8d2a1f7859bcb2181ff57239b' =>
                array (
                    0 => 'fbccb3b8d2a1f7859bcb2181ff57239b',
                    1 => 'Mercedes-Benz',
                    2 => 9,
                    3 => 5,
                ),
            'd6369fd9fd81dba6c3055156533f4eba' =>
                array (
                    0 => 'd6369fd9fd81dba6c3055156533f4eba',
                    1 => 'Toyota',
                    2 => 1,
                    3 => 5,
                ),
        );
    }

}