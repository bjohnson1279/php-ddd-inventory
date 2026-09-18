<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap database capsule
$capsule = require_once __DIR__ . '/../src/Infrastructure/Persistence/bootstrap_database.php';

use InventoryApp\Application\Webhooks\Workers\WebhookDeliveryWorker;

$once = in_array('--once', $argv);

$worker = new WebhookDeliveryWorker();
$worker->run($once);
