<?php

namespace InventoryApp\Domain\Inventory\Exceptions;

use Exception;

class CapacityExceededException extends \DomainException
{
    private string $locationId;
    private string $limitType;
    private float $limit;
    private float $prospective;

    public function __construct(string $locationId, string $limitType, float $limit, float $prospective)
    {
        $this->locationId = $locationId;
        $this->limitType = $limitType;
        $this->limit = $limit;
        $this->prospective = $prospective;

        parent::__construct("Capacity exceeded at location {$locationId}: {$limitType} limit is {$limit}, but prospective {$limitType} is {$prospective}.");
    }

    public function getLocationId(): string { return $this->locationId; }
    public function getLimitType(): string { return $this->limitType; }
    public function getLimit(): float { return $this->limit; }
    public function getProspective(): float { return $this->prospective; }
}
