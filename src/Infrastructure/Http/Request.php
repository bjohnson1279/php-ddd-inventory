<?php

namespace InventoryApp\Infrastructure\Http;

interface Request
{
    /**
     * Validate the request data against the given rules.
     *
     * @param array<string, string> $rules Validation rules in format 'field' => 'rule1|rule2'
     * @return array<string, mixed> Validated request data
     * @throws \Exception When validation fails
     */
    public function validate(array $rules): array;

    /**
     * Get a query parameter from the request.
     *
     * @param string $key The query parameter key
     * @return string|null The query parameter value or null if not present
     */
    public function query(string $key): ?string;
}
