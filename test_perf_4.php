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
    DB::table('test_table')->insert(['id' => $i, 'name' => 'Loc ' . $i]);
}

$start = microtime(true);
for ($i=0; $i<100; $i++) {
    $locations = DB::table('test_table')->pluck('name', 'id');
    if ($locations instanceof \Illuminate\Support\Collection) {
        $locations = $locations->mapWithKeys(function ($name, $id) { return [(string)$id => $name]; })->toArray();
    }
}
$end = microtime(true);
echo "mapWithKeys: " . ($end - $start) . "\n";


$start = microtime(true);
for ($i=0; $i<100; $i++) {
    $locations = DB::table('test_table')->pluck('name', 'id');
    if ($locations instanceof \Illuminate\Support\Collection) {
        // use keyBy string casting
        // wait, we can't use keyBy on values directly if we want the key
        // we can just use toArray() but keys are integers. Does it matter for this specific code?
        $arr = [];
        foreach ($locations as $id => $name) {
            $arr[(string)$id] = $name;
        }
        $locations = $arr;
    }
}
$end = microtime(true);
echo "foreach loop: " . ($end - $start) . "\n";
