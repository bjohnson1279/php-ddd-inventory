<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use InventoryApp\Infrastructure\Persistence\TenantProvisioner;
use InventoryApp\Infrastructure\Persistence\TenantRegistry;
use InventoryApp\Infrastructure\Persistence\TenantRegistryEntry;

// Ensure TenantRegistry is loaded so TenantRegistryEntry defined in the same file is registered
class_exists(TenantRegistry::class);

final class TenantProvisionerTest extends TestCase
{
    public function test_provision_tenant_sanitizes_database_name_in_sql_statements(): void
    {
        $mockConnection = $this->createMock(Connection::class);
        $executedStatements = [];

        $mockConnection->expects($this->atLeastOnce())
            ->method('statement')
            ->willReturnCallback(function (string $query) use (&$executedStatements) {
                $executedStatements[] = $query;
                if (str_contains($query, 'inventory_items')) {
                    throw new \RuntimeException('Trigger failure for cleanup test');
                }
                return true;
            });

        $mockCapsule = $this->createMock(Capsule::class);
        $mockCapsule->method('getConnection')->willReturn($mockConnection);

        $maliciousDbName = 'tenant_db"; DROP TABLE users; --';
        $entry = new TenantRegistryEntry(
            'test-tenant',
            '127.0.0.1',
            5432,
            $maliciousDbName,
            'postgres',
            'password',
            'PROVISIONING',
            new \DateTimeImmutable(),
            '0'
        );

        $mockRegistry = $this->createMock(TenantRegistry::class);
        $mockRegistry->method('registerTenant')->willReturn($entry);

        $provisioner = $this->getMockBuilder(TenantProvisioner::class)
            ->setConstructorArgs([$mockCapsule, $mockRegistry])
            ->onlyMethods(['getTenantConnection'])
            ->getMock();

        $provisioner->method('getTenantConnection')->willReturn($mockConnection);

        try {
            $provisioner->provisionTenant('test-tenant');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Trigger failure for cleanup test', $e->getMessage());
        }

        $expectedSanitizedDbName = 'tenant_dbDROPTABLEusers';

        $this->assertCount(3, $executedStatements);
        $this->assertEquals("CREATE DATABASE \"{$expectedSanitizedDbName}\"", $executedStatements[0]);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS inventory_items', $executedStatements[1]);
        $this->assertEquals("DROP DATABASE IF EXISTS \"{$expectedSanitizedDbName}\"", $executedStatements[2]);
    }

    public function test_deprovision_tenant_sanitizes_database_name_in_sql_statements(): void
    {
        $mockConnection = $this->createMock(Connection::class);
        $executedStatements = [];

        $mockConnection->expects($this->exactly(2))
            ->method('statement')
            ->willReturnCallback(function (string $query) use (&$executedStatements) {
                $executedStatements[] = $query;
                return true;
            });

        $mockCapsule = $this->createMock(Capsule::class);
        $mockCapsule->method('getConnection')->willReturn($mockConnection);

        $maliciousDbName = 'tenant_db\'; DROP TABLE users; --';
        $entry = new TenantRegistryEntry(
            'test-tenant',
            '127.0.0.1',
            5432,
            $maliciousDbName,
            'postgres',
            'password',
            'ACTIVE',
            new \DateTimeImmutable(),
            '1'
        );

        $mockRegistry = $this->createMock(TenantRegistry::class);
        $mockRegistry->method('lookupTenant')->willReturn($entry);

        $provisioner = new TenantProvisioner($mockCapsule, $mockRegistry);
        $provisioner->deprovisionTenant('test-tenant');

        $expectedSanitizedDbName = 'tenant_dbDROPTABLEusers';

        $this->assertCount(2, $executedStatements);
        $this->assertStringContainsString("datname = '{$expectedSanitizedDbName}'", $executedStatements[0]);
        $this->assertEquals("DROP DATABASE IF EXISTS \"{$expectedSanitizedDbName}\"", $executedStatements[1]);
    }
}
