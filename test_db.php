<?php
require 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

Capsule::schema()->create('rfid_tags', function ($table) {
    $table->string('epc')->primary();
    $table->string('sku');
    $table->string('serial_number');
    $table->string('status');
    $table->string('last_seen_at')->nullable();
    $table->string('last_location')->nullable();
    $table->string('created_at');
});

use InventoryApp\Infrastructure\Models\RfidTagModel;

$tag = RfidTagModel::create([
    'epc' => '0123456789abcdef01234567',
    'sku' => 'SKU-1',
    'serial_number' => 'SN-1',
    'status' => 'ACTIVE',
    'created_at' => date('Y-m-d H:i:s')
]);

print_r($tag->toArray());
