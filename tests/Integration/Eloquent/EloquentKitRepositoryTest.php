<?php

declare(strict_types=1);

namespace Tests\Integration\Eloquent;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Persistence\Repositories\EloquentKitRepository;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use InventoryApp\Domain\Kit\ValueObjects\KitComponent;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class EloquentKitRepositoryTest extends TestCase
{
    public function testSaveAndRetrieveKit(): void
    {
        $repo = new EloquentKitRepository();

        $kitId = uuidv4();
        $sku = 'KIT-' . strtoupper(bin2hex(random_bytes(4)));
        $name = 'Classic Starter Kit';

        $kit = new Kit($kitId, $sku, $name);

        $variant1 = uuidv4();
        $variant2 = uuidv4();

        $kit->addComponent($variant1, 2);
        $kit->addComponent($variant2, 5);

        // Save
        $repo->save($kit);

        // Find by Sku
        $found = $repo->findBySku($sku);
        $this->assertNotNull($found);
        $this->assertEquals($kitId, $found->id);
        $this->assertEquals($sku, $found->sku);
        $this->assertEquals($name, $found->name);
        $this->assertCount(2, $found->components());

        $components = $found->components();
        // Index mapping check
        $comp1 = null;
        $comp2 = null;
        foreach ($components as $c) {
            if ($c->variantId === $variant1) $comp1 = $c;
            if ($c->variantId === $variant2) $comp2 = $c;
        }

        $this->assertNotNull($comp1);
        $this->assertEquals(2, $comp1->quantity);
        $this->assertNotNull($comp2);
        $this->assertEquals(5, $comp2->quantity);

        // Find by ID
        $found2 = $repo->findOrFail($kitId);
        $this->assertEquals($sku, $found2->sku);

        // Update (add a component and increase quantity of existing)
        $found->addComponent($variant1, 3); // merges to 5
        $variant3 = uuidv4();
        $found->addComponent($variant3, 1);

        $repo->save($found);

        // Reload
        $reloaded = $repo->findOrFail($kitId);
        $this->assertCount(3, $reloaded->components());

        $reloadedComps = $reloaded->components();
        $reloadedComp1 = null;
        $reloadedComp3 = null;
        foreach ($reloadedComps as $c) {
            if ($c->variantId === $variant1) $reloadedComp1 = $c;
            if ($c->variantId === $variant3) $reloadedComp3 = $c;
        }

        $this->assertNotNull($reloadedComp1);
        $this->assertEquals(5, $reloadedComp1->quantity);
        $this->assertNotNull($reloadedComp3);
        $this->assertEquals(1, $reloadedComp3->quantity);
    }
}
