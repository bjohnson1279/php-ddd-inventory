<?php
require 'vendor/autoload.php';

class TestObj {
    public $id;
    public $val;
    public function __construct($id, $val) {
        $this->id = $id;
        $this->val = $val;
    }
}

$col = collect();
for ($i=0; $i<10000; $i++) {
    $col->push(new TestObj($i, "Val $i"));
}

$start = microtime(true);
for ($i=0; $i<100; $i++) {
    $arr = $col->keyBy(fn($item) => (string)$item->id)->toArray();
}
$end = microtime(true);
echo "keyBy string: " . ($end - $start) . "\n";


$start = microtime(true);
for ($i=0; $i<100; $i++) {
    $arr = $col->mapWithKeys(function ($item) { return [(string)$item->id => $item]; })->toArray();
}
$end = microtime(true);
echo "mapWithKeys string: " . ($end - $start) . "\n";
