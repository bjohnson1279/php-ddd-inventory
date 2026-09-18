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

DB::table('test_table')->insert(['id' => 1, 'name' => 'ref1']);
DB::table('test_table')->insert(['id' => 2, 'name' => 'ref2']);

$arr1 = DB::table('test_table')->pluck('name', 'id')->toArray();
var_dump($arr1);

$locations = DB::table('test_table')->pluck('name', 'id');
$locations2 = $locations->mapWithKeys(function ($name, $id) { return [(string)$id => $name]; })->toArray();
var_dump($locations2);
