<?php

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\ConformanceHelper;

class ConformanceHelperTest extends TestCase
{
    private string $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = 'phpunit-tenant-' . bin2hex(random_bytes(6));
    }

    public function testVersionCheckAndBumpAndConflict(): void
    {
        $sku = 'SKU-VER-' . bin2hex(random_bytes(4));
        $loc = 'default';

        $this->assertSame(0, ConformanceHelper::getVersion($this->tenant, $sku, $loc));
        $this->assertTrue(ConformanceHelper::checkVersion($this->tenant, $sku, $loc, null));
        $this->assertTrue(ConformanceHelper::checkVersion($this->tenant, $sku, $loc, 0));
        $this->assertFalse(ConformanceHelper::checkVersion($this->tenant, $sku, $loc, 1));

        $v1 = ConformanceHelper::bumpVersion($this->tenant, $sku, $loc);
        $this->assertSame(1, $v1);
        $this->assertSame(1, ConformanceHelper::getVersion($this->tenant, $sku, $loc));

        $this->assertNull(ConformanceHelper::checkAndBumpVersion($this->tenant, $sku, $loc, 0));
        $this->assertSame(2, ConformanceHelper::checkAndBumpVersion($this->tenant, $sku, $loc, 1));
    }

    public function testPerTenantCacheTrackingAndInvalidation(): void
    {
        ConformanceHelper::clearCache($this->tenant);
        $initial = ConformanceHelper::getCacheStats($this->tenant);
        $this->assertSame(0, $initial['activeKeysCount']);

        // First read is a miss, second read is a hit
        ConformanceHelper::recordStockRead($this->tenant, 'SKU-C1', 'default');
        ConformanceHelper::recordStockRead($this->tenant, 'SKU-C1', 'default');

        $stats = ConformanceHelper::getCacheStats($this->tenant);
        $this->assertSame(1, $stats['misses']);
        $this->assertSame(1, $stats['hits']);
        $this->assertSame(50.0, $stats['hitRatio']);
        $this->assertSame(1, $stats['activeKeysCount']);

        // Invalidating SKU clears key and bumps invalidations
        ConformanceHelper::invalidateCache($this->tenant, 'SKU-C1');
        $afterInv = ConformanceHelper::getCacheStats($this->tenant);
        $this->assertSame(0, $afterInv['activeKeysCount']);
        $this->assertGreaterThanOrEqual(1, $afterInv['invalidations']);
    }

    public function testLotQuarantineRecallAndReleaseLifecycle(): void
    {
        $lot = 'LOT-' . bin2hex(random_bytes(4));
        $this->assertFalse(ConformanceHelper::isLotBlocked($this->tenant, null));
        $this->assertFalse(ConformanceHelper::isLotBlocked($this->tenant, ''));
        $this->assertFalse(ConformanceHelper::isLotBlocked($this->tenant, $lot));

        ConformanceHelper::setLotStatus($this->tenant, $lot, 'QUARANTINED');
        $this->assertTrue(ConformanceHelper::isLotBlocked($this->tenant, $lot));

        ConformanceHelper::setLotStatus($this->tenant, $lot, 'RECALLED');
        $this->assertTrue(ConformanceHelper::isLotBlocked($this->tenant, $lot));

        ConformanceHelper::setLotStatus($this->tenant, $lot, 'ACTIVE');
        $this->assertFalse(ConformanceHelper::isLotBlocked($this->tenant, $lot));
    }
}
