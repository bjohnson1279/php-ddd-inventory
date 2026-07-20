<?php

namespace InventoryApp\Infrastructure\Http;

/**
 * Contract for HTTP request objects used in controllers.
 * 
 * Allows for easy testing and compatibility with various HTTP frameworks.
 */
interface RequestInterface
{
    /**
     * Validate the request against the given rules.
     *
     * @param array $rules Validation rules keyed by field name
     * @return array The validated data
     * @throws \Exception If validation fails
     */
    public function validate(array $rules): array;

    /**
     * Retrieve a query parameter from the request.
     *
     * @param string $key The query parameter key
     * @param mixed $default Default value if key does not exist
     * @return mixed The query parameter value or default
     */
    public function query(string $key, $default = null);
}
