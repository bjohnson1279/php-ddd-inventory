<?php

namespace InventoryApp\Domain\Inventory\Entities;

use InventoryApp\Domain\Inventory\ValueObjects\LocationId;
use InvalidArgumentException;

class WarehouseLocation
{
    private LocationId $id;
    private string $warehouseId;
    private string $zone;
    private string $aisle;
    private string $rack;
    private string $shelf;
    private string $bin;
    private int $maxWeightGrams;
    private float $maxVolumeCubicMeters;
    private int $gridX;
    private int $gridY;
    private int $width;
    private int $height;

    public function __construct(
        LocationId $id,
        string $warehouseId,
        string $zone,
        string $aisle,
        string $rack,
        string $shelf,
        string $bin,
        int $maxWeightGrams,
        float $maxVolumeCubicMeters,
        int $gridX = 0,
        int $gridY = 0,
        int $width = 1,
        int $height = 1
    ) {
        if (empty(trim($warehouseId))) {
            throw new InvalidArgumentException("Warehouse ID cannot be empty.");
        }
        if (empty(trim($zone))) {
            throw new InvalidArgumentException("Zone cannot be empty.");
        }
        if (empty(trim($aisle))) {
            throw new InvalidArgumentException("Aisle cannot be empty.");
        }
        if (empty(trim($rack))) {
            throw new InvalidArgumentException("Rack cannot be empty.");
        }
        if (empty(trim($shelf))) {
            throw new InvalidArgumentException("Shelf cannot be empty.");
        }
        if (empty(trim($bin))) {
            throw new InvalidArgumentException("Bin cannot be empty.");
        }
        if ($maxWeightGrams <= 0) {
            throw new InvalidArgumentException("Max weight must be greater than zero.");
        }
        if ($maxVolumeCubicMeters <= 0.0) {
            throw new InvalidArgumentException("Max volume must be greater than zero.");
        }

        $this->id = $id;
        $this->warehouseId = trim($warehouseId);
        $this->zone = trim($zone);
        $this->aisle = trim($aisle);
        $this->rack = trim($rack);
        $this->shelf = trim($shelf);
        $this->bin = trim($bin);
        $this->maxWeightGrams = $maxWeightGrams;
        $this->maxVolumeCubicMeters = $maxVolumeCubicMeters;
        $this->gridX = $gridX;
        $this->gridY = $gridY;
        $this->width = $width;
        $this->height = $height;
    }

    public static function parsePath(string $path, int $maxWeight = 1000000, float $maxVolume = 10.0): self
    {
        $parts = explode('-', $path);
        if (count($parts) < 6) {
            throw new InvalidArgumentException("Invalid location path format. Expected: WH-ZONE-AISLE-RACK-SHELF-BIN");
        }
        return new self(
            new LocationId($path),
            $parts[0],
            $parts[1],
            $parts[2],
            $parts[3],
            $parts[4],
            $parts[5],
            $maxWeight,
            $maxVolume
        );
    }

    public function getId(): LocationId { return $this->id; }
    public function getWarehouseId(): string { return $this->warehouseId; }
    public function getZone(): string { return $this->zone; }
    public function getAisle(): string { return $this->aisle; }
    public function getRack(): string { return $this->rack; }
    public function getShelf(): string { return $this->shelf; }
    public function getBin(): string { return $this->bin; }
    public function getMaxWeightGrams(): int { return $this->maxWeightGrams; }
    public function getMaxVolumeCubicMeters(): float { return $this->maxVolumeCubicMeters; }
    public function getPath(): string { return $this->id->getValue(); }
    public function getGridX(): int { return $this->gridX; }
    public function getGridY(): int { return $this->gridY; }
    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }
}
