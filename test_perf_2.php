<?php
require 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

$locations = new Collection();
for ($i=0; $i<10000; $i++) {
    $locations->put($i, "Loc $i");
}

$start = microtime(true);
for ($i=0; $i<100; $i++) {
    $arr = $locations->mapWithKeys(function ($name, $id) { return [(string)$id => $name]; })->toArray();
}
$end = microtime(true);
echo "mapWithKeys: " . ($end - $start) . "\n";


$start = microtime(true);
for ($i=0; $i<100; $i++) {
    $arr = [];
    foreach ($locations as $id => $name) {
        $arr[(string)$id] = $name;
    }
}
$end = microtime(true);
echo "foreach: " . ($end - $start) . "\n";
