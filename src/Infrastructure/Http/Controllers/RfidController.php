<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Models\RfidTagModel;
use PhpMqtt\Client\MqttClient;
use Exception;

class RfidController
{
    public function list(RequestInterface $request, string $tenantId)
    {
        try {
            $tags = RfidTagModel::orderBy('created_at', 'desc')->get()->toArray();
            return new Response(['tags' => $tags], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 500);
        }
    }

    public function assign(RequestInterface $request, string $tenantId)
    {
        try {
            $body = json_decode($request->getBody(), true) ?? [];
            $epc = $body['epc'] ?? '';
            $sku = $body['sku'] ?? '';
            $serialNumber = $body['serialNumber'] ?? $body['serial_number'] ?? '';

            if (!$epc || !$sku || !$serialNumber) {
                return new Response(['error' => 'Missing required fields: epc, sku, serialNumber'], 400);
            }

            if (!preg_match('/^[0-9A-Fa-f]{24}$/', $epc)) {
                return new Response(['error' => 'RFID EPC must be a 24-character hexadecimal string.'], 400);
            }

            $tag = RfidTagModel::create([
                'epc' => $epc,
                'sku' => $sku,
                'serial_number' => $serialNumber,
                'status' => 'ACTIVE',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return new Response(['message' => 'Tag assigned successfully', 'tag' => $tag->toArray()], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 500);
        }
    }

    public function simulateScan(RequestInterface $request, string $tenantId)
    {
        try {
            $body = json_decode($request->getBody(), true) ?? [];
            $locationId = $body['locationId'] ?? $body['location_id'] ?? '';
            $tags = $body['tags'] ?? [];

            if (!$locationId || !$tags || !is_array($tags)) {
                return new Response(['error' => 'Missing required fields: locationId, tags (array)'], 400);
            }

            $mqttUrl = env('MQTT_URL', 'mqtt://localhost:1883');
            $parts = parse_url($mqttUrl);
            $host = $parts['host'] ?? 'localhost';
            $port = $parts['port'] ?? 1883;

            $mqtt = new MqttClient($host, $port, 'php-mqtt-sim-' . uniqid());
            $mqtt->connect();

            $payload = [
                'locationId' => $locationId,
                'tags' => array_map(function($epc) {
                    return ['epc' => $epc];
                }, $tags)
            ];

            $mqtt->publish("tenants/{$tenantId}/rfid/scans", json_encode($payload), 0);
            $mqtt->disconnect();

            return new Response(['message' => 'RFID scan simulation published.'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 500);
        }
    }
}
