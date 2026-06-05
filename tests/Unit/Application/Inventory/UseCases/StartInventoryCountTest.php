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
            'simple id' => ['c-1'],
            'UUID v4' => ['00000000-0000-0000-0000-000000000000'],
            'empty string' => [''],
            'alphanumeric id' => ['count_id_12345'],
            'multibyte string' => ['マルチバイト'],
            'special characters' => ['!@#$%^&*()_+'],
            '255 characters string' => [str_repeat('a', 255)],
            'whitespace only string' => ['   '],
            'string with null byte' => ["id\0with\0null\0bytes"],
            'sql injection attempt' => ["1'; DROP TABLE inventory_counts;--"],
            'xss attempt' => ["<script>alert('xss')</script>"],
            'html entities' => ["&lt;b&gt;id&lt;/b&gt;"],
            'new lines and tabs' => ["id\nwith\twhitespace\r\n"],
        ];
    }

    public function testStartInventoryCountDoesNotQueryExistingCount(): void
    {
        // StartInventoryCount should simply create a new entity and pass it to save.
        // It does not need to verify existence before hand (optimistic creation).
        $this->countRepo->expects($this->never())->method('findById');

        $this->countRepo->expects($this->once())->method('save')
            ->with($this->callback(function (InventoryCount $c) {
                return $c->getId() === 'c-1'
                    && $c->getStatus()->equals(CountStatus::started())
                    && empty($c->getItems());
            }));

        $useCase = new StartInventoryCount($this->countRepo);
        $useCase->execute('c-1');
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

    public function testStartInventoryCountThrowsExceptionWhenRepositoryThrowsDuplicateKeyException(): void
    {
        $this->countRepo->expects($this->once())->method('save')
            ->willThrowException(new \RuntimeException("Duplicate key error: c-1 already exists"));

        $useCase = new StartInventoryCount($this->countRepo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Duplicate key error: c-1 already exists");

        $useCase->execute('c-1');
    }

    public function testExecuteCanBeCalledMultipleTimesToStartMultipleCounts(): void
    {
        $this->countRepo->expects($this->exactly(2))->method('save')
            ->withConsecutive(
                [$this->callback(function (InventoryCount $c) {
                    return $c->getId() === 'c-1'
                        && $c->getStatus()->equals(CountStatus::started())
                        && count($c->getItems()) === 0;
                })],
                [$this->callback(function (InventoryCount $c) {
                    return $c->getId() === 'c-2'
                        && $c->getStatus()->equals(CountStatus::started())
                        && count($c->getItems()) === 0;
                })]
            );

        $useCase = new StartInventoryCount($this->countRepo);

        $useCase->execute('c-1');
        $useCase->execute('c-2');
    }
}
