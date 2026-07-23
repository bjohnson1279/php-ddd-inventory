<?php

namespace InventoryApp\Infrastructure\IoT;

use PhpMqtt\Client\MqttClient;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class MqttIngestService
{
    private ?MqttClient $mqttClient = null;
    private ?AMQPStreamConnection $amqpConnection = null;
    private $amqpChannel = null;

    public function __construct(
        private readonly string $mqttUrl,
        private readonly string $amqpUrl
    ) {}

    public function start(): void
    {
        try {
            // 1. Connect to RabbitMQ (AMQP)
            $amqpParts = parse_url($this->amqpUrl);
            $host = $amqpParts['host'] ?? 'localhost';
            $port = $amqpParts['port'] ?? 5672;
            $user = $amqpParts['user'] ?? 'guest';
            $pass = $amqpParts['pass'] ?? 'guest';
            $vhost = isset($amqpParts['path']) && $amqpParts['path'] !== '/' ? substr($amqpParts['path'], 1) : '/';

            $this->amqpConnection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
            $this->amqpChannel = $this->amqpConnection->channel();
            $this->amqpChannel->queue_declare('rfid_bulk_scans', false, true, false, false);

            echo json_encode([
                "context" => "MqttIngestService",
                "message" => "[MqttIngestService] Connected to RabbitMQ at {$this->amqpUrl} and asserted queue \"rfid_bulk_scans\""
            ]) . "\n";

            // 2. Connect to MQTT Broker
            $mqttParts = parse_url($this->mqttUrl);
            $mqttHost = $mqttParts['host'] ?? 'localhost';
            $mqttPort = $mqttParts['port'] ?? 1883;

            $this->mqttClient = new MqttClient($mqttHost, $mqttPort, 'php-mqtt-ingest-service');
            $this->mqttClient->connect();

            echo json_encode([
                "context" => "MqttIngestService",
                "message" => "[MqttIngestService] Connected to MQTT Broker at {$this->mqttUrl}"
            ]) . "\n";

            // Subscribe to wildcard tenant topic
            $this->mqttClient->subscribe('tenants/+/rfid/scans', function (string $topic, string $message) {
                $this->handleMqttMessage($topic, $message);
            }, 0);

            echo json_encode([
                "context" => "MqttIngestService",
                "message" => "Subscribed to MQTT topic 'tenants/+/rfid/scans'"
            ]) . "\n";

            // Loop MQTT client to listen for messages
            $this->mqttClient->loop(true);

        } catch (\Throwable $e) {
            echo json_encode([
                "context" => "MqttIngestService",
                "message" => "Failed to start MqttIngestService: " . $e->getMessage()
            ]) . "\n";
            throw $e;
        }
    }

    private function handleMqttMessage(string $topic, string $rawPayload): void
    {
        if (preg_match('/^tenants\/([^\/]+)\/rfid\/scans$/', $topic, $matches)) {
            $tenantId = $matches[1];

            try {
                $data = json_decode($rawPayload, true);
                if (!$data || !isset($data['locationId']) || !is_array($data['tags'] ?? null)) {
                    throw new \InvalidArgumentException("Invalid scan payload: locationId and tags array are required.");
                }

                $tags = [];
                foreach ($data['tags'] as $t) {
                    $tags[] = [
                        'epc' => $t['epc'],
                        'rssi' => $t['rssi'] ?? null
                    ];
                }

                $amqpPayload = [
                    'tenantId' => $tenantId,
                    'locationId' => $data['locationId'],
                    'tags' => $tags,
                    'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
                ];

                $msg = new AMQPMessage(json_encode($amqpPayload), [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
                ]);

                $this->amqpChannel->basic_publish($msg, '', 'rfid_bulk_scans');

                echo json_encode([
                    "context" => "MqttIngestService",
                    "message" => "[MqttIngestService] Queued scan batch of " . count($tags) . " tags to AMQP for tenant \"{$tenantId}\" at location \"{$data['locationId']}\""
                ]) . "\n";

            } catch (\Throwable $e) {
                echo json_encode([
                    "context" => "MqttIngestService",
                    "message" => "Failed to process MQTT message on topic {$topic}: " . $e->getMessage()
                ]) . "\n";
            }
        } else {
            echo json_encode([
                "context" => "MqttIngestService",
                "message" => "Ignored message on unsupported topic: {$topic}"
            ]) . "\n";
        }
    }

    public function stop(): void
    {
        if ($this->mqttClient) {
            $this->mqttClient->disconnect();
            $this->mqttClient = null;
        }
        if ($this->amqpChannel) {
            $this->amqpChannel->close();
            $this->amqpChannel = null;
        }
        if ($this->amqpConnection) {
            $this->amqpConnection->close();
            $this->amqpConnection = null;
        }
        echo json_encode([
            "context" => "MqttIngestService",
            "message" => "Stopped MqttIngestService"
        ]) . "\n";
    }
}
