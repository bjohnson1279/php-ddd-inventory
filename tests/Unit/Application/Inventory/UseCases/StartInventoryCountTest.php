<?php

namespace Tests\Unit\Application\Inventory\UseCases;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Inventory\UseCases\StartInventoryCount;
use InventoryApp\Domain\Inventory\Repositories\InventoryCountRepositoryInterface;
use InventoryApp\Domain\Inventory\Entities\InventoryCount;
use InventoryApp\Domain\Inventory\ValueObjects\CountStatus;

class StartInventoryCountTest extends TestCase
{
    private $countRepo;

    protected function setUp(): void
    {
        $this->countRepo = $this->createMock(InventoryCountRepositoryInterface::class);
    }

    public function testStartInventoryCountSavesNewAggregate(): void
    {
        $this->countRepo->expects($this->once())->method('save')
            ->with($this->callback(function (InventoryCount $c) {
                return $c->getId() === 'c-1'
                    && $c->getStatus()->equals(CountStatus::started())
                    && empty($c->getItems());
            }));

        $useCase = new StartInventoryCount($this->countRepo);
        $useCase->execute('c-1');
    }
}
