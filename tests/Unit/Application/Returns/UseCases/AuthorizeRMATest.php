<?php

namespace Tests\Unit\Application\Returns\UseCases;

use Exception;
use InventoryApp\Application\Returns\UseCases\AuthorizeRMA;
use InventoryApp\Domain\Returns\Aggregates\RMA;
use InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface;
use PHPUnit\Framework\TestCase;

class AuthorizeRMATest extends TestCase
{
    public function testExecuteSuccessfullyAuthorizesRMA(): void
    {
        $rmaRepository = $this->createMock(RMARepositoryInterface::class);
        $rma = $this->createMock(RMA::class);

        $rmaId = 'rma-123';

        $rmaRepository->expects($this->once())
            ->method('findById')
            ->with($rmaId)
            ->willReturn($rma);

        $rma->expects($this->once())
            ->method('authorize');

        $rmaRepository->expects($this->once())
            ->method('save')
            ->with($rma);

        $useCase = new AuthorizeRMA($rmaRepository);
        $useCase->execute($rmaId);
    }

    public function testExecuteThrowsExceptionWhenRMANotFound(): void
    {
        $rmaRepository = $this->createMock(RMARepositoryInterface::class);
        $rmaId = 'rma-456';

        $rmaRepository->expects($this->once())
            ->method('findById')
            ->with($rmaId)
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("RMA with ID {$rmaId} not found.");

        $useCase = new AuthorizeRMA($rmaRepository);
        $useCase->execute($rmaId);
    }
}
