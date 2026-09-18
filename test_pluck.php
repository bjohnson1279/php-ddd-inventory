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
    $table->string('reference_id');
});

DB::table('test_table')->insert(['reference_id' => 'ref1']);
DB::table('test_table')->insert(['reference_id' => 'ref2']);

$arr1 = array_flip(DB::table('test_table')->pluck('reference_id')->toArray());
var_dump($arr1);

$arr2 = DB::table('test_table')->pluck('reference_id', 'reference_id')->toArray();
var_dump($arr2);
