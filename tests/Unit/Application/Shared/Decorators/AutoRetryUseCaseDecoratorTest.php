<?php

namespace Tests\Unit\Application\Shared\Decorators;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Shared\Decorators\AutoRetryUseCaseDecorator;
use InventoryApp\Domain\Inventory\Exceptions\ConcurrencyException;
use Exception;
use InvalidArgumentException;

class AutoRetryUseCaseDecoratorTest extends TestCase
{
    public function testExecuteSucceedsWithoutRetry(): void
    {
        $mockUseCase = new class {
            public int $callCount = 0;
            public function execute(string $arg): string {
                $this->callCount++;
                return "result-{$arg}";
            }
        };

        $decorator = new AutoRetryUseCaseDecorator($mockUseCase, 3, 1);
        $result = $decorator->execute("test");

        $this->assertEquals("result-test", $result);
        $this->assertEquals(1, $mockUseCase->callCount);
    }

    public function testRetriesOnConcurrencyExceptionAndSucceeds(): void
    {
        $mockUseCase = new class {
            public int $callCount = 0;
            public function execute(): string {
                $this->callCount++;
                if ($this->callCount < 3) {
                    throw new ConcurrencyException("SKU-1", "LOC-1");
                }
                return "success-on-third";
            }
        };

        // Set low delay (5ms) for fast unit test
        $decorator = new AutoRetryUseCaseDecorator($mockUseCase, 3, 5);
        $result = $decorator->execute();

        $this->assertEquals("success-on-third", $result);
        $this->assertEquals(3, $mockUseCase->callCount);
    }

    public function testPropagatesExceptionWhenMaxRetriesExceeded(): void
    {
        $mockUseCase = new class {
            public int $callCount = 0;
            public function execute(): void {
                $this->callCount++;
                throw new ConcurrencyException("SKU-1", "LOC-1");
            }
        };

        $decorator = new AutoRetryUseCaseDecorator($mockUseCase, 2, 5);

        $this->expectException(ConcurrencyException::class);
        try {
            $decorator->execute();
        } finally {
            $this->assertEquals(3, $mockUseCase->callCount); // 1 initial + 2 retries
        }
    }

    public function testDoesNotRetryOnOtherExceptions(): void
    {
        $mockUseCase = new class {
            public int $callCount = 0;
            public function execute(): void {
                $this->callCount++;
                throw new InvalidArgumentException("Invalid data");
            }
        };

        $decorator = new AutoRetryUseCaseDecorator($mockUseCase, 3, 5);

        $this->expectException(InvalidArgumentException::class);
        try {
            $decorator->execute();
        } finally {
            $this->assertEquals(1, $mockUseCase->callCount);
        }
    }
}
