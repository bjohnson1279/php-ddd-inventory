<?php

namespace Tests\Unit\Infrastructure\Persistence;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use InventoryApp\Infrastructure\Persistence\TenantProvisioner;
use InventoryApp\Infrastructure\Persistence\TenantRegistry;
use InventoryApp\Infrastructure\Persistence\TenantRegistryEntry;
use PHPUnit\Framework\TestCase;

class TenantProvisionerTest extends TestCase
{
    public function testDeprovisionTenantExecutesParameterizedConnectionTerminationQuery(): void
    {
        // Ensure TenantRegistry.php is loaded so TenantRegistryEntry class is defined
        class_exists(TenantRegistry::class);

        $tenantId = 'tenant-123';
        $entry = new TenantRegistryEntry(
            $tenantId,
            'localhost',
            5432,
            'tenant_db_123',
            'db_user',
            'db_pass',
            'ACTIVE',
            new \DateTimeImmutable(),
            '1'
        );

        $mockRegistry = $this->createMock(TenantRegistry::class);
        $mockRegistry->expects($this->once())
            ->method('lookupTenant')
            ->with($tenantId)
            ->willReturn($entry);

        $mockRegistry->expects($this->once())
            ->method('deprovisionTenant')
            ->with($tenantId);

        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->expects($this->exactly(2))
            ->method('statement')
            ->willReturnCallback(function ($sql, $bindings = []) use ($entry) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    $expectedSql = "
                SELECT pg_terminate_backend(pg_stat_activity.pid)
                FROM pg_stat_activity
                WHERE pg_stat_activity.datname = ?
                  AND pid <> pg_backend_pid()
            ";
                    $this->assertSame($expectedSql, $sql);
                    $this->assertSame([$entry->dbName], $bindings);
                } elseif ($callCount === 2) {
                    $this->assertSame("DROP DATABASE IF EXISTS \"tenant_db_123\"", $sql);
                }

                return true;
            });

        $mockCapsule = $this->createMock(Capsule::class);
        $mockCapsule->method('getConnection')->willReturn($mockConnection);

        $provisioner = new TenantProvisioner($mockCapsule, $mockRegistry);
        $provisioner->deprovisionTenant($tenantId);
    }
}
