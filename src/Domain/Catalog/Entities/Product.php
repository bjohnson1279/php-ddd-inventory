<?php

namespace InventoryApp\Domain\Catalog\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\Department;
use InventoryApp\Domain\Shared\Entities\AggregateRoot;
use InventoryApp\Domain\Catalog\Events\VariantAddedToCatalog;

class Product extends AggregateRoot
{
    private string $id;
    private string $name;
    private string $description;
    private Department $department;
    
    /** @var Variant[] */
    private array $variants = [];

    public function __construct(string $id, string $name, string $description, Department $department)
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->department = $department;
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getDepartment(): Department { return $this->department; }
    public function getVariants(): array { return $this->variants; }

    public function addVariant(Variant $variant): void
    {
        $this->variants[] = $variant;
        
        $this->recordEvent(new VariantAddedToCatalog(
            $this->id,
            $this->name,
            $this->department,
            $variant->getSku()
        ));
    }
}
