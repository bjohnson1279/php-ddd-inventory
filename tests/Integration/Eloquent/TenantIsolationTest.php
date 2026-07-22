<?php

declare(strict_types=1);

namespace tests\Integration\Eloquent;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\TenantRegistry;
use InventoryApp\Infrastructure\Persistence\TenantConnectionPool;
use InventoryApp\Infrastructure\Persistence\TenantProvisioner;
use InventoryApp\Infrastructure\Persistence\TenantRegistryEntry;
use Illuminate\Database\Capsule\Manager as Capsule;
use InventoryApp\Infrastructure\ServiceContainer;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class TenantIsolationTest extends TestCase
{
    private $db;
    private $capsule;

    protected function setUp(): void
    {
        $this->capsule = ServiceContainer::getInstance()->make(Capsule::class);
        $this->db = $this->capsule->getConnection();
        
        // Ensure tenant_registry table exists for tests
        $this->db->statement("
            CREATE TABLE IF NOT EXISTS tenant_registry (
                tenant_id        TEXT PRIMARY KEY,
                db_host          TEXT NOT NULL DEFAULT '127.0.0.1',
                db_port          INTEGER NOT NULL DEFAULT 5432,
                db_name          TEXT NOT NULL,
                db_user          TEXT NOT NULL DEFAULT 'postgres',
                db_password      TEXT NOT NULL DEFAULT 'password',
                status           TEXT NOT NULL DEFAULT 'PROVISIONING',
                provisioned_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                migrated_version TEXT NOT NULL DEFAULT '0'
            )
        ");

        // Clean registry
        $this->db->table('tenant_registry')->truncate();
    }

    protected function tearDown(): void
    {
        $this->db->table('tenant_registry')->truncate();
    }

    public function test_registry_can_register_and_lookup_tenant(): void
    {
        $registry = new TenantRegistry($this->capsule);

        $entry = $registry->registerTenant('acme-corp', '127.0.0.1', 5432, 'inventory_tenant_acme_corp');

        $this->assertEquals('acme-corp', $entry->tenantId);
        $this->assertEquals('inventory_tenant_acme_corp', $entry->dbName);
        $this->assertEquals('PROVISIONING', $entry->status);

        $lookup = $registry->lookupTenant('acme-corp');
        $this->assertNotNull($lookup);
        $this->assertEquals('inventory_tenant_acme_corp', $lookup->dbName);
        $this->assertEquals('PROVISIONING', $lookup->status);

        // Update status and version
        $registry->updateStatus('acme-corp', 'ACTIVE');
        $registry->updateMigratedVersion('acme-corp', '1');

        $lookupAfter = $registry->lookupTenant('acme-corp');
        $this->assertEquals('ACTIVE', $lookupAfter->status);
        $this->assertEquals('1', $lookupAfter->migratedVersion);
    }

    public function test_registry_cannot_register_duplicate_active_tenant(): void
    {
        $registry = new TenantRegistry($this->capsule);
        $registry->registerTenant('globex');
        $registry->updateStatus('globex', 'ACTIVE');

        $this->expectException(\RuntimeException::class);
        $registry->registerTenant('globex');
    }

    public function test_registry_can_reregister_deprovisioned_tenant(): void
    {
        $registry = new TenantRegistry($this->capsule);
        $registry->registerTenant('old-tenant');
        $registry->deprovisionTenant('old-tenant');

        // Should allow re-registering
        $entry = $registry->registerTenant('old-tenant');
        $this->assertEquals('PROVISIONING', $entry->status);
    }

    public function test_connection_pool_lru_and_eviction(): void
    {
        // Mock registry lookup for ACTIVE tenants
        $mockRegistry = $this->createMock(TenantRegistry::class);
        $mockRegistry->method('lookupTenant')->willReturnCallback(function ($tenantId) {
            return new TenantRegistryEntry(
                $tenantId,
                '127.0.0.1',
                5432,
                'db_' . $tenantId,
                'postgres',
                'password',
                'ACTIVE',
                new \DateTimeImmutable(),
                '1'
            );
        });

        // Initialize pool with Capsule and mock registry (max capacity 3)
        $pool = new TenantConnectionPool($this->capsule, $mockRegistry, 3, 2);

        $this->assertFalse($pool->has('t1'));

        // Retrieve connections
        $pool->getConnection('t1');
        $pool->getConnection('t2');
        $pool->getConnection('t3');

        $this->assertTrue($pool->has('t1'));
        $this->assertTrue($pool->has('t2'));
        $this->assertTrue($pool->has('t3'));

        // Exceed capacity -> t1 should be evicted (LRU)
        $pool->getConnection('t4');

        $this->assertFalse($pool->has('t1'));
        $this->assertTrue($pool->has('t4'));

        // Test shutdown
        $pool->shutdown();
        $this->assertFalse($pool->has('t2'));
        $this->assertFalse($pool->has('t4'));
    }

    public function test_provisioner_runs_tenant_migrations(): void
    {
        // Mock DB connection for migrations run
        $mockConnection = $this->createMock(\Illuminate\Database\Connection::class);
        
        // Expect statement calls for DDL tables
        $mockConnection->expects($this->atLeast(5))
            ->method('statement');

        $mockRegistry = $this->createMock(TenantRegistry::class);
        $mockRegistry->method('registerTenant')->willReturn(new TenantRegistryEntry(
            'tenant-x',
            '127.0.0.1',
            5432,
            'inventory_tenant_tenant_x',
            'postgres',
            'password',
            'PROVISIONING',
            new \DateTimeImmutable(),
            '0'
        ));

        // Use a partial mock to intercept getTenantConnection and return the mock connection
        $provisioner = $this->getMockBuilder(TenantProvisioner::class)
            ->setConstructorArgs([$this->capsule, $mockRegistry])
            ->onlyMethods(['getTenantConnection'])
            ->getMock();

        $provisioner->method('getTenantConnection')->willReturn($mockConnection);

        $ref = new \ReflectionClass(TenantProvisioner::class);
        $method = $ref->getMethod('runMigrationsOnTenantDb');
        $method->setAccessible(true);

        $mockEntry = new TenantRegistryEntry(
            'tenant-x',
            '127.0.0.1',
            5432,
            'inventory_tenant_tenant_x',
            'postgres',
            'password',
            'PROVISIONING',
            new \DateTimeImmutable(),
            '0'
        );

        $method->invoke($provisioner, $mockEntry);
    }
}
