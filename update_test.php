<?php

$file = 'tests/Unit/Http/Middleware/RequirePermissionTest.php';
$contents = file_get_contents($file);

// Replace the generic stdClass mock which causes issues in older PHP versions
$contents = str_replace(
    "\$requestMock = \$this->getMockBuilder(\stdClass::class)->addMethods(['input'])->getMock();",
    "\$requestMock = \$this->createMock(\InventoryApp\Infrastructure\Http\RequestInterface::class);",
    $contents
);

file_put_contents($file, $contents);
echo "Updated file.";
