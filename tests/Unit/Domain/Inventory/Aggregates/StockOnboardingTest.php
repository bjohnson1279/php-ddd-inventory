<?php

namespace Tests\Unit\Domain\Inventory\Aggregates;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Inventory\Aggregates\StockOnboarding;
use InventoryApp\Domain\Inventory\Aggregates\StockOnboardingStatus;
use InventoryApp\Domain\Inventory\Events\StockOnboardingSubmitted;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

class StockOnboardingTest extends TestCase
{
    private string $id = 'onboarding-1';
    private string $tenantId = 'tenant-1';
    private string $locationId = 'loc-1';
    private DateTimeImmutable $asOfDate;

    protected function setUp(): void
    {
        $this->asOfDate = new DateTimeImmutable('2023-01-01');
    }

    public function testInitializesInDraftState(): void
    {
        $onboarding = new StockOnboarding($this->id, $this->tenantId, $this->locationId, $this->asOfDate);

        $this->assertFalse($onboarding->isSubmitted());
        $this->assertEmpty($onboarding->items());
        $this->assertEmpty($onboarding->releaseEvents());
    }

    public function testCanAddItem(): void
    {
        $onboarding = new StockOnboarding($this->id, $this->tenantId, $this->locationId, $this->asOfDate);
        $onboarding->setItem('var-1', 10, 500);

        $this->assertCount(1, $onboarding->items());
        $this->assertEquals('var-1', $onboarding->items()[0]->variantId);
        $this->assertEquals(10, $onboarding->items()[0]->quantity);
        $this->assertEquals(500, $onboarding->items()[0]->unitCostCents);
    }

    public function testCannotAddNegativeQuantityItem(): void
    {
        $onboarding = new StockOnboarding($this->id, $this->tenantId, $this->locationId, $this->asOfDate);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Opening balance quantity cannot be negative.');
        $onboarding->setItem('var-1', -5, 500);
    }

    public function testCanRemoveItem(): void
    {
        $onboarding = new StockOnboarding($this->id, $this->tenantId, $this->locationId, $this->asOfDate);
        $onboarding->setItem('var-1', 10, 500);
        $onboarding->setItem('var-2', 5, 200);

        $onboarding->removeItem('var-1');

        $this->assertCount(1, $onboarding->items());
        $this->assertEquals('var-2', $onboarding->items()[0]->variantId);
    }

    public function testCanSubmitWhenItemsPresent(): void
    {
        $onboarding = new StockOnboarding($this->id, $this->tenantId, $this->locationId, $this->asOfDate);
        $onboarding->setItem('var-1', 10, 500);

        $onboarding->submit();

        $this->assertTrue($onboarding->isSubmitted());

        $events = $onboarding->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StockOnboardingSubmitted::class, $events[0]);
        $this->assertEquals($this->id, $events[0]->onboardingId);
        $this->assertEquals($this->tenantId, $events[0]->tenantId);
        $this->assertEquals($this->locationId, $events[0]->locationId);
        $this->assertEquals($this->asOfDate, $events[0]->asOfDate);

        // Assert events array is cleared after release
        $this->assertEmpty($onboarding->releaseEvents());
    }

    public function testCannotSubmitEmptyOnboarding(): void
    {
        $onboarding = new StockOnboarding($this->id, $this->tenantId, $this->locationId, $this->asOfDate);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot submit empty onboarding');

        $onboarding->submit();
    }

    public function testCannotModifyAfterSubmission(): void
    {
        $onboarding = new StockOnboarding($this->id, $this->tenantId, $this->locationId, $this->asOfDate);
        $onboarding->setItem('var-1', 10, 500);
        $onboarding->submit();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Onboarding already submitted.');

        $onboarding->setItem('var-2', 5, 200);
    }

    public function testCannotRemoveItemAfterSubmission(): void
    {
        $onboarding = new StockOnboarding($this->id, $this->tenantId, $this->locationId, $this->asOfDate);
        $onboarding->setItem('var-1', 10, 500);
        $onboarding->submit();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Onboarding already submitted.');

        $onboarding->removeItem('var-1');
    }

    public function testCannotSubmitAlreadySubmittedOnboarding(): void
    {
        $onboarding = new StockOnboarding($this->id, $this->tenantId, $this->locationId, $this->asOfDate);
        $onboarding->setItem('var-1', 10, 500);
        $onboarding->submit();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Onboarding already submitted.');

        $onboarding->submit();
    }
}
