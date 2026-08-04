<?php

namespace Tests\Unit\Domain\Procurement\Services;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Procurement\Services\ReorderPolicyService;
use InventoryApp\Domain\Procurement\Repositories\ReorderPolicyRepositoryInterface;
use InventoryApp\Domain\Procurement\Repositories\PurchaseOrderRepositoryInterface;
use InventoryApp\Domain\Procurement\Services\ReorderPointForecaster;
use InventoryApp\Domain\Inventory\Repositories\ProductRepositoryInterface;
use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use InventoryApp\Domain\Procurement\Aggregates\ReorderPolicy;
use InventoryApp\Domain\Inventory\ValueObjects\SKU;
use Exception;

class ReorderPolicyServiceTest extends TestCase
{
    private $reorderPolicyRepoMock;
    private $poRepoMock;
    private $eventDispatcherMock;
    private $forecasterMock;
    private $productRepoMock;
    private $ledgerRepoMock;
    private $service;

    protected function setUp(): void
    {
        $this->reorderPolicyRepoMock = $this->createMock(ReorderPolicyRepositoryInterface::class);
        $this->poRepoMock = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->forecasterMock = $this->createMock(ReorderPointForecaster::class);
        $this->productRepoMock = $this->createMock(ProductRepositoryInterface::class);
        $this->ledgerRepoMock = $this->createMock(LedgerRepositoryInterface::class);

        $this->service = new ReorderPolicyService(
            $this->reorderPolicyRepoMock,
            $this->poRepoMock,
            $this->eventDispatcherMock
        );
    }

    public function testEvaluatePoliciesContinuesWhenForecasterThrowsException()
    {
        $tenantId = 'tenant-123';
        $sku = new SKU('SKU-123');
        $policy = new ReorderPolicy('id-1', $sku, 'LOC-1', 10, 50, 5, true);

        $this->reorderPolicyRepoMock->expects($this->once())
            ->method('findAll')
            ->willReturn([$policy]);

        $this->forecasterMock->expects($this->once())
            ->method('forecastReorderPoint')
            ->willThrowException(new Exception("Forecasting failed"));

        $this->reorderPolicyRepoMock->expects($this->never())
            ->method('save');

        $this->productRepoMock->expects($this->once())
            ->method('findBySkus')
            ->with([$sku])
            ->willReturn([]);

        // Redirect stderr to suppress the error_log output during the test
        $tmp = tmpfile();
        $prevStderr = ini_get('error_log');
        ini_set('error_log', stream_get_meta_data($tmp)['uri']);

        $results = $this->service->evaluatePolicies(
            $tenantId,
            $this->forecasterMock,
            $this->productRepoMock,
            $this->ledgerRepoMock
        );

        // Restore stderr
        ini_set('error_log', $prevStderr);
        fclose($tmp);

        $this->assertCount(1, $results);
        $this->assertEquals('SKU-123', $results[0]['sku']);
        $this->assertEquals(10, $results[0]['reorderPoint']);
    }
}
