<?php

namespace InventoryApp\Application\AI;

class PythonSidecarClient
{
    private string $sidecarUrl;

    public function __construct()
    {
        $this->sidecarUrl = getenv('PYTHON_SIDECAR_URL') ?: 'http://localhost:5005';
    }

    public function post(string $endpoint, string $payload): ?array
    {
        $url = $this->sidecarUrl . $endpoint;
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            return null;
        }

        $decoded = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }
}
