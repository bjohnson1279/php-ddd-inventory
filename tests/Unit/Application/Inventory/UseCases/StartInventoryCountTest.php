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

    /**
     * @dataProvider countIdProvider
     */
    public function testStartInventoryCountSavesNewAggregate(string $countId): void
    {
        $this->countRepo->expects($this->once())->method('save')
            ->with($this->callback(function (InventoryCount $c) use ($countId) {
                return $c->getId() === $countId
                    && $c->getStatus()->equals(CountStatus::started())
                    && empty($c->getItems());
            }));

        $useCase = new StartInventoryCount($this->countRepo);
        $useCase->execute($countId);
    }

    public function countIdProvider(): array
    {
        return [
            ['c-1'],
            ['00000000-0000-0000-0000-000000000000'],
            [''],
            ['count_id_12345'],
        ];
    }

    public function testStartInventoryCountThrowsExceptionWhenRepositoryFails(): void
    {
        $this->countRepo->expects($this->once())->method('save')
            ->willThrowException(new \RuntimeException("Database error"));

        $useCase = new StartInventoryCount($this->countRepo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Database error");

        $useCase->execute('c-1');
    }
}
