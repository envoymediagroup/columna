<!-- based on: https://github.com/othneildrew/Best-README-Template/blob/master/README.md -->
# Columnar Analytics (in pure PHP)

On GitHub: [https://github.com/envoymediagroup/Columna](https://github.com/envoymediagroup/Columna)

## About the project
### What does it do?
This library allows you to write and read a simple columnar file format in a performant way with a lightweight, pure PHP implementation. 

### Why columnar analytics in PHP?
This library started as a scratch-our-own-itch project at [Envoy Media Group](https://www.envoymediagroup.com/). We needed fast, columnar analytics that would work well with our all-PHP stack, but found PHP's support and performance for mainstream columnar formats (Parquet, ORC, etc.) to be lacking. So we rolled our own simple columnar format with its own speedy writer and reader.

### How battle tested is it?
This library has been in production use as the backbone of Envoy's analytics and business intelligence since early 2022. It processes hundreds of thousands of reads and writes per day, serving both custom reports for business users and automated requests for monitoring and machine learning applications. Bug fixes, feature adds, and improvements are ongoing based on our experience using this library every day in production.


## Installation

Add this library to your project using [Composer](https://getcomposer.org/):
```sh
composer require envoymediagroup/Columna
```


## Usage

### Writer
Each columnar file is specific to one date and one metric, with any number of dimensions. For this example, we will assume a metric named `clicks` and three dimensions named `platform_id`, `site_id`, and `url`. Note that we provide the headers and values as separate inputs to the Writer; this makes sense when we are working with large data sets and want to preserve some memory by not duplicating associative string keys on every array item.

#### Data Types
Currently supported data types include strings, ints, floats, and bools, and a special "datetime" type. Datetimes are treated as strings except when evaluating query conditions, when they are parsed with strtotime() and compared with integer operations >, <, =, etc. Nested data is not currently supported. While it is possible to store JSON or other serializations in the string type, these values will not be unserialized by the engine and so cannot be evaluated for nested values. The column definitions include an empty value which will always be used in place of nulls in the data set, so null is never stored in the files or returned when reading a file. 

#### Usage
Let's walk through using the Writer in the comments below: 
```php
<?php
require('../vendor/autoload.php');

// Import the classes we need
use EnvoyMediaGroup\Columna\Writer;
use EnvoyMediaGroup\Columna\Reader;
use EnvoyMediaGroup\Columna\ColumnDefinition;

// Create or retrieve our data set
$array = [
    [
        'clicks' => 12,
        'platform_id' => 2,
        'site_id' => 7,
        'url' => 'https://www.foo.com',
    ],
    [
        'clicks' => 31,
        'platform_id' => 2,
        'site_id' => 9,
        'url' => 'https://www.barbaz.net',
    ],
    //... etc.
];

// Define our Metric and our Dimensions
// The names should match the keys in your data set
$MetricDefinition = new ColumnDefinition(
    ColumnDefinition::AXIS_TYPE_METRIC, // metric or dimension
    'clicks',                           // name (should match the keys in your data set)
    ColumnDefinition::DATA_TYPE_INT,    // data type (string, int, float, bool, datetime)
    null,                               // precision (for floats)
    0                                   // empty value (matching the specified data type)
);

$DimensionDefinitions = [
    new ColumnDefinition(
        ColumnDefinition::AXIS_TYPE_DIMENSION,
        'platform_id',
        ColumnDefinition::DATA_TYPE_INT,
        null,
        0
    ),
    new ColumnDefinition(
        ColumnDefinition::AXIS_TYPE_DIMENSION,
        'site_id',
        ColumnDefinition::DATA_TYPE_INT,
        null,
        0
    ),
    new ColumnDefinition(
        ColumnDefinition::AXIS_TYPE_DIMENSION,
        'url',
        ColumnDefinition::DATA_TYPE_STRING,
        null,
        ''
    ),
];

// Set our output path and the date for this file's data
$date = '2022-07-08';
$file_path = "/data_directory/{$date}/{$MetricDefinition->getName()}." . Reader::FILE_EXTENSION;

// Instantiate the Writer 
$Writer = new Writer();

// The Writer expects headers (string keys) separate from data (0-indexed).
//   If your data is associative like the above, you can separate it with
//   this helper function.
list($headers,$data) = $Writer->separateHeadersAndData($array);

// Write the columnar file
$Writer->writeFile(
    $date,
    $MetricDefinition,
    $DimensionDefinitions,
    $headers,
    $data,
    $file_path
);
```

Now we have a complete file at `$file_path`.

### Reader
Here's how to read a file. Note that this library contains both `Reader` and `BundledReader` classes. They both do the same thing and you can use them interchangeably, but you will see a slight performance win by using the `BundledReader` because it reduces the number of `include()`s PHP has to perform. It's a small win that can add up at scale.

#### Call with arguments, get array results
To call the Reader normally with arguments:
```php
<?php

// Import the needed classes
use EnvoyMediaGroup\Columna\BundledReader as Reader;
use EnvoyMediaGroup\Columna\Constraint;

// Specify our metric and date, and the corresponding file path
$metric = 'clicks';
$date = '2022-07-08';
$file_path = "/data_directory/{$date}/{$metric}." . Reader::FILE_EXTENSION;

// Set what dimensions we want to include in our results
$dimensions = [
    'platform_id',
    'site_id',
];

// Define our constraints
// Constraints are ANDed within groups, ORed between groups
// This example is equivalent to the following SQL:
//   SELECT * FROM file WHERE (platform_id = 7 AND site_id in (1,3,17)) OR (url LIKE '%sale%');
$constraints = [
    [
        (new Constraint("platform_id",Constraint::EQUALS,7))->toArray(),
        (new Constraint("site_id",Constraint::IN,[1,3,17]))->toArray(),
    ],
    [
        (new Constraint("url",Constraint::CONTAINS,"sale"))->toArray(),
    ]
];

// Group the results by the dimensions we asked for (in this case, platform_id and site_id)
$do_aggregate = true;
// Don't provide extra metadata with sum/count/min/max for each grouping, just aggregate the values
$do_aggregate_meta = false;

$Reader = new Reader();
$Reader->run(
    $date,
    $metric,
    $dimensions,
    $constraints,
    $do_aggregate,
    $do_aggregate_meta,
    $file_path
);

$metadata = $Reader->getMetadata(); // Metadata about the request and results; see sample below.
$data = $Reader->getResults(); // Results of the request; see sample below.
```

#### Call with JSON string workload, get JSON+CSV string results
The Reader is designed for easy use when running a large number of requests distributed over many worker processes using an RPC or messaging framework such as AWS SQS, RabbitMQ, or our own `envoymediagroup/lib-rpc`. For this reason, the Reader can accept a string as its input and return a string as its output. The request string is a JSON serialization of the Reader arguments. For the result string, the first line is the metadata of the response encoded as JSON, and the following lines are the result data encoded as CSV with a bit of extra escaping for more safety in encoding/decoding strings. The `Response` class will handle unserializing this string for you. **Be sure to use this Response class** to parse results, as it will handle unescaping those strings properly. 

An example caller:
```php
<?php

use EnvoyMediaGroup\Columna\Response;

// Craft your request
$workload_array = [
    "date" => "2022-07-08",
    "metric" => "clicks",
    "dimensions" => ["platform_id","site_id"],
    "constraints" => [
        [
            [
                "name" => "platform_id",
                "comparator" => ">=",
                "value" => 5,
            ],
        ],
    ],
    "do_aggregate" => true,
    "do_aggregate_meta" => false,
    "file" => "path/to/file.ccf",
];
$workload = json_encode($workload_array);

// Transmit that workload over a network with your RPC framework of choice...
$result_string = $SomeRpcClient->request($workload);

// Unserialize the result with the Response class
$Response = new Response($result_string);

$metadata = $Response->getMetadata();
$results  = $Response->getResults();
```
An example worker:
```php
<?php

use EnvoyMediaGroup\Columna\BundledReader as Reader;

$workload = $SomeRpcClient->receive();

$Reader = new Reader();
$Reader->runFromWorkload($workload);
$result_string = $Reader->getResponsePayload();

// Return that result string over your RPC framework...
$SomeRpcClient->respond($result_string);
```

#### Metadata
Metadata looks like this:
```php
Array(
  'date' => '2022-07-08', // Date of the file
  'metric' => 'clicks',   // Name of the metric in the file
  'status' => 'success',  // 'success' if records were found, 'empty' if no records were found, 'error' on failure
  'min' => 1,   // Least metric value among the records
  'max' => 64,  // Greatest metric value among the records
  'sum' => 102, // Total metric value among the records
  'matched_row_count' => 102, // Number of records in the file that matched your constraints prior to aggregation
  'result_row_count' => 17,   // Number of records in the result set after aggregation
  'column_meta' => Array( // Description of the columns in the result set
    0 => Array(
      // MD5 is automatically prepended. Records with matching dimension values will have matching md5 hashes.
      // This is helpful if you need to aggregate multiple Reader results together.
      'definition' => Array(
        'axis_type' => 'dimension',
        'name' => 'md5',
        'data_type' => 'string',
        'empty_value' => '',
      ),
      'index' => 0, // Numerical index in each result record that corresponds to this column
    ),
    1 => Array(
      'definition' => Array(
        'axis_type' => 'metric',
        'name' => 'clicks',
        'data_type' => 'int',
        'precision' => NULL,
        'empty_value' => 0,
      ),
      'index' => 1,
    ),
    2 => Array(
      'definition' => Array(
        'axis_type' => 'dimension',
        'name' => 'platform_id',
        'data_type' => 'int',
        'precision' => NULL,
        'empty_value' => 0,
      ),
      'index' => 2,
    ),
    3 => Array(
      'definition' => Array(
        'axis_type' => 'dimension',
        'name' => 'site_id',
        'data_type' => 'int',
        'precision' => NULL,
        'empty_value' => 0,
      ),
      'index' => 3,
    ),
  ),
  'is_aggregated' => true, // Read back of whether these results are aggregated on matching dimension values.
  'aggregate_includes_meta' => false, // Read back of whether the results include metadata for each aggregate grouping.
  'host' => 'worker-1', // Result of php_uname('n'), helpful for tracing/debugging
  'ms_elapsed' => 32,   // Milliseconds it took to complete your request
)
```

#### Results
Result data set looks like this. Note that you can reference the 'index' field in the 'column_meta' of the metadata to map the indexes in each record to the appropriate column names.
```php
Array(
    0 => Array (
        0 => 'a060e57689d68664f873561a78e002d9',
        1 => 3,
        2 => 58,
        3 => 1,
    ),
    1 => Array (
        0 => 'f9f0a70e4d259b63914ccc98ed438d0e',
        1 => 16,
        2 => 54,
        3 => 1,
    ),
    2 => Array (
        0 => '166dea5e7a502516e662d389239bd2fc',
        1 => 4,
        2 => 75,
        3 => 1,
    ),
    // ... etc.
)
```

## File format
What file format does this library use to store data? It's fairly simple: all the metadata about the file, its columns, and their definitions and offsets are stored on line 1 in a simple JSON header. The rest of the record is CSV data in a columnar arrangement (each column corresponding to one line in the file) using RLE compression and a Record Separator character as the delimiter between the RLE values and their repetition counts. For a sample, see `tests/fixtures/clicks--has_data_with_csort.ccf`. The file extension is for Columna Columnar Format.

## Q&A
#### Why didn't you use library X, built-in function Y, or design pattern Z?
The short answer is performance. I kept the requirements of this library as small as possible to make the autoload very lightweight and reduce time spent `include()`ing files, which adds up quickly when you are optimizing for every millisecond. Many of PHP's built-in array functions actually run slower than `foreach`ing the same array. Design patterns with more abstraction mean more classes and more weight. Keeping it simple keeps it fast.


## Issues, Feature Requests

See the [open issues](https://github.com/envoymediagroup/Columna/issues) for a full list of known issues or to submit an issue or feature request. 

Of course, if you spot any egregious bugs or security holes, **please create an issue and notify me right away** (contact info below).


## Contributing

Contributions are what make the open source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

If you have a suggestion that would make this better, please fork the repo and create a pull request. You can also simply open an issue with the tag "enhancement".
Don't forget to give the project a star! Thanks again!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request


## License

Distributed under the MIT License. See `LICENSE.txt` for more information.


## Contact

Creator: Ryan Marlow

Twitter:[@myanrarlow](https://twitter.com/MyanRarlow)

Email: [ryanmarlow.oss@gmail.com](mailto:ryanmarlow.oss@gmail.com)


## Acknowledgments

Here are some resources I've found helpful for this project. 

* [Choose an Open Source License](https://choosealicense.com)
* [Best README Template](https://github.com/othneildrew/Best-README-Template)
* [Mockaroo](https://www.mockaroo.com/)
