<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Custom autoloader fallback for Domain classes defined in multi-class files
spl_autoload_register(function ($class) {
    if (!str_starts_with($class, 'App\\Domain\\')) {
        return;
    }
    
    $domainMap = [
        'Notification' => 'NotificationEntities.php',
        'NotificationDispatcherService' => 'NotificationServices.php',
        'NotificationInboxService' => 'NotificationServices.php',
        'NotificationPreference' => 'NotificationEntities.php',
        'NotificationRule' => 'NotificationEntities.php',
        'NotificationCategory' => 'NotificationEntities.php',
        'NotificationSeverity' => 'NotificationEntities.php',
        'NotificationStatus' => 'NotificationEntities.php',
        'NotificationChannel' => 'NotificationEntities.php',
        'ComputerVisionService' => 'VisionServices.php',
        'YieldCalculationService' => 'YieldServices.php',
        'LaborAllocationService' => 'LaborServices.php',
        'AgingAnalysisService' => 'AgingServices.php',
        'BillingExecutionService' => 'BillingServices.php',
        'IntercompanyTransferService' => 'IntercompanyServices.php',
    ];
    
    $parts = explode('\\', $class);
    $className = end($parts);
    $subdomain = $parts[2] ?? '';
    
    if (isset($domainMap[$className])) {
        $file = __DIR__ . '/../src/Domain/' . $subdomain . '/' . $domainMap[$className];
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
