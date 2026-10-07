<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Ensure all Domain classes and interfaces in multi-class files are loaded for unit test suites
$domainDir = __DIR__ . '/../src/Domain';
if (is_dir($domainDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($domainDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            require_once $file->getPathname();
        }
    }
}
