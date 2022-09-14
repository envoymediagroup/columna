<?php

namespace EnvoyMediaGroup\Columna;

use Exception;
use JsonException;

class Response {

    /** @var bool */
    protected $throw_on_status_error;
    /** @var array */
    protected $metadata;
    /** @var array */
    protected $results;

    /**
     * @param string $response_payload
     * @param bool $throw_on_status_error
     * @throws JsonException
     */
    public function __construct(string $response_payload, bool $throw_on_status_error = true) {
        $this->throw_on_status_error = $throw_on_status_error;
        $lines = $this->parseResponsePayloadToLines($response_payload);
        $this->setMetadata($this->parseMetadata(array_shift($lines)));
        $this->setResults($this->parseResults($lines));
    }

    /**
     * @param string $response_payload
     * @throws Exception
     * @return array
     */
    protected function parseResponsePayloadToLines(string $response_payload): array {
        if (empty($response_payload)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." response payload was empty.");
        }
        return explode("\n",trim($response_payload));
    }

    /**
     * @param string $first_line
     * @throws JsonException
     * @return array
     */
    protected function parseMetadata(string $first_line): array {
        return json_decode($first_line,true,512,JSON_THROW_ON_ERROR);
    }

    /**
     * @param array $lines
     * @throws Exception
     * @return array
     */
    protected function parseResults(array $lines): array {
        if ($this->metadata["status"] !== Reader::RESULT_STATUS_SUCCESS) {
            return [];
        }

        $column_meta = $this->metadata["column_meta"];

        $data = [];
        foreach ($lines as $line) {
            $line_data = CsvSafeHelper::strGetcsvSafe($line);

            $item = [];
            foreach ($line_data as $i => $value) {
                if (
                    $column_meta[$i]["definition"]["name"] === $this->metadata["metric"] &&
                    array_key_exists("aggregate_includes_meta",$this->metadata) &&
                    $this->metadata["aggregate_includes_meta"] === true
                ) {
                    $item[$i] = json_decode($value,true,512,JSON_THROW_ON_ERROR);
                } else {
                    $item[$i] = DataTypeHelper::applyDataTypeSingle($value,$column_meta[$i]["definition"]);
                }
            }

            $data[$line_data[0]] = $item; //md5 is now the key, and one of the things in the row
        }

        return $data;
    }

    /**
     * @param array $metadata
     * @throws Exception
     * @return void
     */
    protected function setMetadata(array $metadata): void {
        if ($metadata["status"] === Reader::RESULT_STATUS_EMPTY) {
            $this->metadata = $metadata;
            return;
        }
        if ($metadata["status"] === Reader::RESULT_STATUS_ERROR) {
            if ($this->throw_on_status_error) {
                throw new Exception("Reader request failed with status '{$metadata["status"]}', " .
                    "error: " . $metadata["error"]);
            }
            $this->metadata = $metadata;
            return;
        }
        if ($metadata["status"] !== Reader::RESULT_STATUS_SUCCESS) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." status is missing or invalid.");
        }
        if (!array_key_exists("metric",$metadata) || !is_string($metadata["metric"]) || $metadata["metric"] === '') {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." metric is missing or invalid.");
        }
        if (!array_key_exists("date",$metadata) || !is_string($metadata["date"]) || $metadata["date"] === '') {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." date is missing or invalid.");
        }
        if (!array_key_exists("column_meta",$metadata) || !is_array($metadata["column_meta"]) || empty($metadata["column_meta"])) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." column_meta is missing or invalid.");
        }
        if (!array_key_exists("is_aggregated",$metadata) || !is_bool($metadata["is_aggregated"])) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." is_aggregated is missing or invalid.");
        }
        if (!array_key_exists("aggregate_includes_meta",$metadata) || !is_bool($metadata["aggregate_includes_meta"])) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." aggregate_includes_meta is missing or invalid.");
        }
        $this->metadata = $metadata;
    }

    /**
     * @param array $results
     * @return void
     */
    protected function setResults(array $results): void {
        $this->results = $results;
    }

    /**
     * @return array
     */
    public function getMetadata(): array {
        return $this->metadata;
    }

    /**
     * @return array
     */
    public function getResults(): array {
        return $this->results;
    }

}