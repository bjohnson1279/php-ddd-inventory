<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\LocationId;

class Location
{
    private LocationId $id;
    private string $name;
    private string $type; // e.g., 'STOREFRONT', 'BACKROOM', 'WAREHOUSE'

    public function __construct(LocationId $id, string $name, string $type)
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
    }

    public function getId(): LocationId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
