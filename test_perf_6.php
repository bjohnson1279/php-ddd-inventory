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

DB::schema()->create('products', function ($table) {
    $table->integer('id');
    $table->string('sku');
    $table->string('name');
    $table->integer('reorder_threshold');
});

for ($i = 0; $i < 10000; $i++) {
    DB::table('products')->insert([
        'id' => $i,
        'sku' => "SKU_$i",
        'name' => "Name_$i",
        'reorder_threshold' => 10,
    ]);
}

$start = microtime(true);
for ($i=0; $i<100; $i++) {
    $products = DB::table('products')->get(['id', 'sku', 'name', 'reorder_threshold']);
    $productsMap = $products->keyBy(fn($item) => (string)$item->id);
}
$end = microtime(true);
echo "get + keyBy: " . ($end - $start) . "\n";


$start = microtime(true);
for ($i=0; $i<100; $i++) {
    $products = DB::table('products')->get(['id', 'sku', 'name', 'reorder_threshold']);
    $productsMap = [];
    foreach ($products as $p) {
        $productsMap[(string)$p->id] = $p;
    }
}
$end = microtime(true);
echo "get + foreach: " . ($end - $start) . "\n";


$start = microtime(true);
for ($i=0; $i<100; $i++) {
    // using query builder get gives us an array of stdClass if we aren't using models.
    // Wait, query builder get() returns a Collection.
    $products = DB::table('products')->get(['id', 'sku', 'name', 'reorder_threshold'])->keyBy('id');
    // We want string keys, but let's test this
}
$end = microtime(true);
echo "get keyBy string: " . ($end - $start) . "\n";
