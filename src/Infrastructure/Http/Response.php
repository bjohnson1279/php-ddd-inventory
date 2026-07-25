<?php

namespace InventoryApp\Infrastructure\Http;

class Response
{
    private int $status;
    private $body;

    public function __construct($body, int $status = 200)
    {
        $this->body = $body;
        $this->status = $status;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getContent(): string
    {
        $encoded = json_encode($this->body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($encoded === false) {
            return json_encode([
                'error' => 'JSON encoding failed',
                'message' => is_string($this->body) ? $this->body : 'Invalid payload'
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        return $encoded;
    }
}
