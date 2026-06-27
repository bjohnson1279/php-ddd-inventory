<?php

namespace Tests\Unit\Application\Returns\UseCases;

use Exception;
use InvalidArgumentException;
use InventoryApp\Application\Returns\UseCases\AuthorizeRMA;
use InventoryApp\Domain\Identity\ValueObjects\TenantId;
use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InventoryApp\Domain\Returns\Aggregates\RMA;
use InventoryApp\Domain\Returns\Enums\RMAStatus;
use InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface;
use PHPUnit\Framework\TestCase;

class AuthorizeRMATest extends TestCase
{
    public function testExecuteSuccessfullyAuthorizesRMA(): void
    {
        $rmaRepository = $this->createMock(RMARepositoryInterface::class);

        $rmaId = 'rma-123';
        $rma = new RMA(
            $rmaId,
            'RMA-001',
            new TenantId('tenant-1'),
            'cust-1',
            new LocationId('LOC-1'),
            RMAStatus::Requested,
            []
        );

        $rmaRepository->expects($this->once())
            ->method('findById')
            ->with($rmaId)
            ->willReturn($rma);

        $rmaRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (RMA $savedRma) use ($rmaId) {
                return $savedRma->getId() === $rmaId
                    && $savedRma->getStatus() === RMAStatus::Authorized;
            }));

        $useCase = new AuthorizeRMA($rmaRepository);
        $useCase->execute($rmaId);
    }

    public function testExecuteThrowsExceptionWhenRmaCannotBeAuthorized(): void
    {
        $rmaRepository = $this->createMock(RMARepositoryInterface::class);
        $rma = $this->createMock(RMA::class);
        $rmaId = 'rma-789';

        $rmaRepository->expects($this->once())
            ->method('findById')
            ->with($rmaId)
            ->willReturn($rma);

        $rma->expects($this->once())
            ->method('authorize')
            ->willThrowException(new InvalidArgumentException("RMA must be in Requested status to be authorized."));

        $rmaRepository->expects($this->never())
            ->method('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("RMA must be in Requested status to be authorized.");

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

        $rmaRepository->expects($this->never())
            ->method('save');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("RMA with ID {$rmaId} not found.");

        $useCase = new AuthorizeRMA($rmaRepository);
        $useCase->execute($rmaId);
    }
}
