<?php

namespace InventoryApp\Domain\Rfid;

use InventoryApp\Domain\Serial\ValueObjects\SerialNumber;

class RfidTag
{
    public function __construct(
        public readonly string $epc,
        public readonly string $sku,
        public readonly SerialNumber $serialNumber,
        public string $status = 'ACTIVE',
        public ?\DateTimeImmutable $lastSeenAt = null,
        public ?string $lastLocation = null
    ) {
        $this->validateEpc($epc);
    }

    private function validateEpc(string $epc): void
    {
        if (!preg_match('/^[0-9a-fA-F]{24}$/', $epc)) {
            throw new \InvalidArgumentException("Invalid Electronic Product Code (EPC): must be a 24-character hexadecimal string.");
        }
    }
}
