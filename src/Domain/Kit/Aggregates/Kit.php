<?php

namespace InventoryApp\Domain\Kit\Aggregates;

use InventoryApp\Domain\Kit\ValueObjects\KitComponent;

class Kit
{
    /** @var KitComponent[] */
    private array $components = [];

    public function __construct(public readonly string $id, public readonly string $sku, public readonly string $name) {}

    public function addComponent(string $variantId, int $quantity): void
    {
        foreach ($this->components as $i => $component) {
            if ($component->variantId === $variantId) {
                $this->components[$i] = new KitComponent($variantId, $component->quantity + $quantity);
                return;
            }
        }

        $this->components[] = new KitComponent($variantId, $quantity);
    }

    /** @return KitComponent[] */
    public function components(): array
    {
        return $this->components;
    }

    public function isEmpty(): bool
    {
        return empty($this->components);
    }
}
