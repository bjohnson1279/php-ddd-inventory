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
        return json_encode($this->body);
    }
}
