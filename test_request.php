<?php
require 'vendor/autoload.php';
$ref = new ReflectionClass('InventoryApp\Infrastructure\Http\RequestInterface');
print_r($ref->getMethods());
