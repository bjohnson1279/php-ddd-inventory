<?php
require 'vendor/autoload.php';

class Model {
    public $id;
    public $sku;
    public $name;
    public $reorder_threshold;
}

$items = [];
for ($i=0; $i<10000; $i++) {
    $m = new Model();
    $m->id = $i;
    $m->sku = "SKU$i";
    $m->name = "Name$i";
    $m->reorder_threshold = 10;
    $items[] = $m;
}

$col = collect($items);

$start = microtime(true);
for ($i=0; $i<100; $i++) {
    $r = $col->keyBy(fn($item) => (string)$item->id)->toArray();
}
$end = microtime(true);
echo "keyBy closure: " . ($end - $start) . "\n";

$start = microtime(true);
for ($i=0; $i<100; $i++) {
    $r = [];
    foreach ($col as $item) {
        $r[(string)$item->id] = (array)$item;
    }
}
$end = microtime(true);
echo "foreach: " . ($end - $start) . "\n";
