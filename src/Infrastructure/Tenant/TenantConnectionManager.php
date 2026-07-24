<?php

namespace App\Infrastructure\Tenant;

use Illuminate\Database\Capsule\Manager as DB;

class TenantConnectionManager
{
    private static ?TenantConnectionManager $instance = null;
    private array $tenantConnections = [];

    private function __construct() {}

    public static function getInstance(): TenantConnectionManager
    {
        if (self::$instance === null) {
            self::$instance = new TenantConnectionManager();
        }
        return self::$instance;
    }

    public function registerTenantConnection(string $tenantId, array $dbConfig): void
    {
        $connectionName = "tenant_" . $tenantId;
        
        $config = array_merge([
            'driver'    => 'pgsql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '5432'),
            'database'  => 'inventory_' . $tenantId,
            'username'  => env('DB_USERNAME', 'postgres'),
            'password'  => env('DB_PASSWORD', 'postgres'),
            'charset'   => 'utf8',
            'prefix'    => '',
            'schema'    => 'public',
        ], $dbConfig);

        DB::addConnection($config, $connectionName);
        $this->tenantConnections[$tenantId] = $connectionName;
    }

    public function switchToTenant(string $tenantId): void
    {
        if (isset($this->tenantConnections[$tenantId])) {
            DB::setDefaultConnection($this->tenantConnections[$tenantId]);
        } else {
            DB::setDefaultConnection('default');
        }
    }
}
