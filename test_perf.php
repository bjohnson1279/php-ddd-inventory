<?php
require 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

$db = new DB;
$db->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);
$db->setAsGlobal();
$db->bootEloquent();

DB::schema()->create('test_table', function ($table) {
    $table->integer('id');
    $table->string('name');
});

for ($i = 0; $i < 10000; $i++) {
    DB::table('test_table')->insert(['id' => $i, 'name' => 'name' . $i]);
}

$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $locations = DB::table('test_table')->pluck('name', 'id');
    $locations->mapWithKeys(function ($name, $id) { return [(string)$id => $name]; })->toArray();
}
$end = microtime(true);
echo "mapWithKeys: " . ($end - $start) . "\n";


$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $locations = DB::table('test_table')->pluck('name', 'id')->toArray();
    // Since PDO already returns associative array with integer string keys as integers, we don't necessarily need mapWithKeys if we just want a map
}
$end = microtime(true);
echo "pluck toArray: " . ($end - $start) . "\n";


$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $locations = DB::table('test_table')->get()->keyBy(fn($item) => (string)$item->id)->toArray();
}
$end = microtime(true);
echo "get keyBy: " . ($end - $start) . "\n";
